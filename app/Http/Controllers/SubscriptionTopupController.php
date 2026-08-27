<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use FedaPay\FedaPay;
use FedaPay\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SubscriptionTopupController extends Controller
{
    public function __construct()
    {
        FedaPay::setApiKey(config('services.fedapay.secret_key'));
        FedaPay::setEnvironment(config('services.fedapay.environment', 'sandbox'));
    }

    // Infos affichées côté client avant achat : quantité + prix du pack de
    // recharge pour SON plan actuel (dépend du quota du plan, voir
    // Plan::topupSmsCount()).
    public function show(Request $request)
    {
        $subscription = $request->user()->activeSubscription()->with('plan')->first();

        if (!$subscription) {
            return response()->json(['message' => 'Aucun abonnement actif.'], 404);
        }

        $plan = $subscription->plan;

        return response()->json([
            'available' => $plan->hasTopupAvailable(),
            'sms_count' => $plan->topupSmsCount(),
            'price' => $plan->topup_price,
            'currency' => $plan->currency,
            'credit_remaining' => $subscription->smsCreditRemaining(),
        ]);
    }

    // Démarre le paiement d'un pack de recharge via FedaPay. La quantité et
    // le prix sont figés MAINTENANT (voir payments.sms_credit_purchased) :
    // si l'admin change topup_price après coup, ça n'affecte jamais un achat
    // déjà en cours de paiement.
    public function checkout(Request $request)
    {
        $user = $request->user();
        $subscription = $user->activeSubscription()->with('plan')->first();

        if (!$subscription) {
            return response()->json(['message' => 'Aucun abonnement actif.'], 404);
        }

        $plan = $subscription->plan;

        if (!$plan->hasTopupAvailable()) {
            return response()->json([
                'message' => "L'achat de crédit supplémentaire n'est pas disponible pour votre plan actuel.",
            ], 422);
        }

        $smsCount = $plan->topupSmsCount();
        $price = (float) $plan->topup_price;

        $payment = Payment::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'subscription_id' => $subscription->id,
            'type' => 'topup',
            'sms_credit_purchased' => $smsCount,
            'amount' => $price,
            'currency' => $plan->currency,
            'status' => 'pending',
        ]);

        try {
            [$firstname, $lastname] = $this->splitName($user->name);

            $transaction = Transaction::create([
                'description' => "Recharge de {$smsCount} SMS - SMS Gateway",
                'amount' => (int) round($price),
                'currency' => ['iso' => $plan->currency],
                'callback_url' => rtrim(config('app.frontend_url'), '/')
                    . '/admin/subscription/callback?payment_id=' . $payment->id,
                'customer' => [
                    'firstname' => $firstname,
                    'lastname' => $lastname,
                    'email' => $user->email,
                ],
                'custom_metadata' => [
                    'type' => 'topup',
                    'payment_id' => $payment->id,
                    'subscription_id' => $subscription->id,
                    'sms_credit_purchased' => $smsCount,
                ],
            ]);

            $token = $transaction->generateToken();

            $payment->update([
                'fedapay_transaction_id' => $transaction->id,
                'checkout_url' => $token->url,
            ]);

            return response()->json([
                'payment_id' => $payment->id,
                'checkout_url' => $token->url,
            ], 201);
        } catch (\Throwable $e) {
            Log::error('FedaPay: échec de la création de la transaction de recharge', [
                'message' => $e->getMessage(),
                'payment_id' => $payment->id,
            ]);

            $payment->update(['status' => 'failed']);

            return response()->json([
                'message' => "Impossible de démarrer le paiement pour le moment. Réessayez dans un instant.",
            ], 502);
        }
    }

    private function splitName(?string $name): array
    {
        $parts = preg_split('/\s+/', trim((string) $name), 2);

        return [
            $parts[0] !== '' ? $parts[0] : 'Client',
            $parts[1] ?? '-',
        ];
    }
}
