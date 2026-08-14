<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Plan;
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
     * Démarre le paiement d'un plan payant, ou active immédiatement un plan gratuit (ex: Trial).
     * Appelé par le bouton "Confirmer le changement de plan" du front.
     */
    public function checkout(Request $request)
    {
        $request->validate(['plan_id' => 'required|exists:plans,id']);

        $plan = Plan::findOrFail($request->plan_id);
        $user = $request->user();

        // Plan gratuit : pas besoin de passer par FedaPay, activation immédiate.
        if ((float) $plan->price <= 0) {
            $subscription = $this->activateSubscription($user, $plan);

            return response()->json([
                'free' => true,
                'subscription' => $subscription->load('plan'),
            ]);
        }

        $payment = Payment::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'amount' => $plan->price,
            'currency' => $plan->currency,
            'status' => 'pending',
        ]);

        try {
            [$firstname, $lastname] = $this->splitName($user->name);

            $transaction = Transaction::create([
                'description' => "Abonnement plan {$plan->name} - SMS Gateway",
                // FedaPay attend un montant entier (pas de centimes) pour le XOF
                'amount' => (int) round((float) $plan->price),
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
                    $subscription = $this->activateSubscription($payment->user, $payment->plan);
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
     * et démarre une nouvelle période de facturation d'un mois avec un quota remis à zéro.
     */
    private function activateSubscription(User $user, Plan $plan)
    {
        $user->subscriptions()->where('status', 'active')->update(['status' => 'cancelled']);

        return $user->subscriptions()->create([
            'plan_id' => $plan->id,
            'status' => 'active',
            'sms_used' => 0,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);
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
