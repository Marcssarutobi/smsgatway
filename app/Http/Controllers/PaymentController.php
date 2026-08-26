<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\SmsPricingSetting;
use App\Models\User;
use FedaPay\Error\SignatureVerification;
use FedaPay\FedaPay;
use FedaPay\Transaction;
use FedaPay\Webhook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function __construct()
    {
        FedaPay::setApiKey(config('services.fedapay.secret_key'));
        FedaPay::setEnvironment(config('services.fedapay.environment'));
    }

    /**
     * Démarre le paiement d'un plan (Device ou Réseau, 1/3/6/12 mois), ou active
     * immédiatement si le montant total calculé est nul. Le prix est TOUJOURS
     * recalculé côté serveur à partir du plan + du tarif SMS en vigueur — on ne
     * fait jamais confiance à un montant envoyé par le client.
     */
    public function checkout(Request $request)
    {
        $validated = $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'channel' => 'required|in:device,network',
            'duration_months' => 'required|integer|in:1,3,6,12',
        ]);

        $plan = Plan::findOrFail($validated['plan_id']);
        $user = $request->user();
        $channel = $validated['channel'];
        $durationMonths = (int) $validated['duration_months'];

        // Tarif SMS figé MAINTENANT : si l'admin le change demain, ça n'affecte
        // jamais cette souscription déjà payée (voir migration subscriptions).
        $smsRateApplied = null;
        $smsCostPerMonth = 0;
        if ($channel === 'network') {
            $smsRateApplied = (float) SmsPricingSetting::current()->price_per_sms;
            $smsCostPerMonth = $plan->networkModeSmsCost($smsRateApplied);
        }

        $totalAmount = ((float) $plan->price + $smsCostPerMonth) * $durationMonths;

        // Gratuit (Trial en mode Device typiquement) : pas besoin de FedaPay.
        if ($totalAmount <= 0) {
            if ($user->hasAlreadyUsedPlan($plan)) {
                return response()->json([
                    'message' => "Vous avez déjà utilisé le plan {$plan->name}. Ce plan gratuit n'est utilisable qu'une seule fois par compte.",
                ], 422);
            }

            $subscription = $this->activateSubscription(
                $user, $plan, $channel, $durationMonths, $smsRateApplied, $totalAmount
            );

            return response()->json([
                'free' => true,
                'subscription' => $subscription->load('plan'),
            ]);
        }

        $payment = Payment::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'amount' => $totalAmount,
            'currency' => $plan->currency,
            'status' => 'pending',
        ]);

        try {
            [$firstname, $lastname] = $this->splitName($user->name);

            $description = "Abonnement plan {$plan->name} ({$durationMonths} mois"
                . ($channel === 'network' ? ', mode Réseau' : '') . ") - SMS Gateway";

            $transaction = Transaction::create([
                'description' => $description,
                // FedaPay attend un montant entier (pas de centimes) pour le XOF
                'amount' => (int) round($totalAmount),
                'currency' => ['iso' => $plan->currency],
                'callback_url' => rtrim(config('app.frontend_url'), '/')
                    . '/admin/subscription/callback?payment_id=' . $payment->id,
                'customer' => [
                    'firstname' => $firstname,
                    'lastname' => $lastname,
                    'email' => $user->email,
                ],
                'custom_metadata' => [
                    'payment_id' => $payment->id,
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                    'channel' => $channel,
                    'duration_months' => $durationMonths,
                    'sms_rate_applied' => $smsRateApplied,
                ],
            ]);

            $token = $transaction->generateToken();

            $payment->update([
                'fedapay_transaction_id' => $transaction->id,
                'checkout_url' => $token->url,
            ]);

            return response()->json([
                'free' => false,
                'payment_id' => $payment->id,
                'checkout_url' => $token->url,
            ], 201);
        } catch (\Throwable $e) {
            Log::error('FedaPay: échec de la création de la transaction', [
                'message' => $e->getMessage(),
                'payment_id' => $payment->id,
            ]);

            $payment->update(['status' => 'failed']);

            return response()->json([
                'message' => "Impossible de démarrer le paiement pour le moment. Réessayez dans un instant.",
            ], 502);
        }
    }

    /**
     * Consulté par le front (polling) après retour de FedaPay sur la page callback,
     * en attendant que le webhook confirme définitivement le paiement.
     */
    public function status(Request $request, Payment $payment)
    {
        if ($payment->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        return response()->json([
            'id' => $payment->id,
            'status' => $payment->status,
            'plan_id' => $payment->plan_id,
            'subscription' => $payment->subscription()->with('plan')->first(),
        ]);
    }

    /**
     * Webhook FedaPay : source de vérité pour l'activation d'un abonnement payant.
     * Pas d'authentification Sanctum ici : la requête vient des serveurs FedaPay,
     * elle est authentifiée par la signature HMAC (en-tête X-FEDAPAY-SIGNATURE).
     */
    public function webhook(Request $request)
    {
        $secret = config('services.fedapay.webhook_secret');
        $payload = $request->getContent();
        $signature = $request->header('X-FEDAPAY-SIGNATURE') ?? $request->header('x-fedapay-signature');

        try {
            $event = Webhook::constructEvent($payload, $signature, $secret);
        } catch (SignatureVerification $e) {
            Log::warning('FedaPay webhook: signature invalide');
            return response()->json(['message' => 'Signature invalide'], 400);
        } catch (\Throwable $e) {
            Log::warning('FedaPay webhook: payload invalide', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Payload invalide'], 400);
        }

        $data = json_decode($payload, true) ?? [];
        $transactionId = $data['entity']['id'] ?? null;

        $payment = $transactionId
            ? Payment::where('fedapay_transaction_id', $transactionId)->first()
            : null;

        if (!$payment) {
            // Transaction qui ne concerne pas un abonnement suivi ici : on accuse
            // simplement réception pour que FedaPay ne retente pas indéfiniment.
            return response()->json(['message' => 'ok'], 200);
        }

        $payment->update(['raw_payload' => $data]);

        switch ($event->name) {
            case 'transaction.approved':
                if (!$payment->isApproved()) {
                    $metadata = $data['entity']['custom_metadata'] ?? [];
                    $channel = $metadata['channel'] ?? 'device';
                    $durationMonths = (int) ($metadata['duration_months'] ?? 1);
                    $smsRateApplied = isset($metadata['sms_rate_applied']) ? (float) $metadata['sms_rate_applied'] : null;

                    $subscription = $this->activateSubscription(
                        $payment->user, $payment->plan, $channel, $durationMonths, $smsRateApplied, (float) $payment->amount
                    );
                    $payment->update(['status' => 'approved', 'subscription_id' => $subscription->id]);
                }
                break;

            case 'transaction.declined':
                $payment->update(['status' => 'declined']);
                break;

            case 'transaction.canceled':
                $payment->update(['status' => 'canceled']);
                break;

            default:
                // transaction.created, transaction.transferred, etc. : rien à faire ici.
                break;
        }

        return response()->json(['message' => 'ok'], 200);
    }

    /**
     * Active un nouveau plan pour l'utilisateur : annule l'abonnement actif précédent
     * et démarre une nouvelle période de facturation de la durée choisie.
     * Synchronise aussi organisation.preferred_sms_channel, qui est ce que
     * DispatchSmsJob consulte réellement au moment d'envoyer un SMS.
     */
    private function activateSubscription(
        User $user,
        Plan $plan,
        string $channel = 'device',
        int $durationMonths = 1,
        ?float $smsRateApplied = null,
        float $amountPaid = 0,
    ) {
        $user->subscriptions()->where('status', 'active')->update(['status' => 'cancelled']);

        $subscription = $user->subscriptions()->create([
            'plan_id' => $plan->id,
            'status' => 'active',
            'sms_used' => 0,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonths($durationMonths),
            'channel' => $channel,
            'duration_months' => $durationMonths,
            'sms_rate_applied' => $smsRateApplied,
            'amount_paid' => $amountPaid,
        ]);

        $user->organisation()->updateOrCreate(
            ['user_id' => $user->id],
            ['preferred_sms_channel' => $channel]
        );

        $user->notify(new \App\Notifications\SubscriptionActivatedNotification($plan));

        return $subscription;
    }

    /**
     * FedaPay attend un prénom et un nom séparés ; on répartit du mieux possible
     * le champ "name" unique de notre modèle User.
     */
    private function splitName(?string $name): array
    {
        $parts = preg_split('/\s+/', trim((string) $name), 2);

        return [
            $parts[0] !== '' ? $parts[0] : 'Client',
            $parts[1] ?? '-',
        ];
    }
}
