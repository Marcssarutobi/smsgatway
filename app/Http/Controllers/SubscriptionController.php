<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Plan;

class SubscriptionController extends Controller
{
    public function current(Request $request)
    {
        return response()->json($request->user()->activeSubscription()->with('plan')->first());
    }

    // Ne gère plus que les plans gratuits (ex: Trial) : activation immédiate sans paiement.
    // Pour un plan payant, le front doit utiliser POST /subscription/checkout (paiement FedaPay),
    // qui active l'abonnement une fois le webhook "transaction.approved" reçu.
    public function subscribe(Request $request)
    {
        $request->validate(['plan_id' => 'required|exists:plans,id']);

        $plan = Plan::findOrFail($request->plan_id);

        if ((float) $plan->price > 0) {
            return response()->json([
                'message' => 'Ce plan est payant : utilisez /subscription/checkout pour démarrer le paiement.',
            ], 422);
        }

        $request->user()->subscriptions()->where('status', 'active')->update(['status' => 'cancelled']);

        $subscription = $request->user()->subscriptions()->create([
            'plan_id' => $plan->id,
            'status' => 'active',
            'sms_used' => 0,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        return response()->json($subscription->load('plan'), 201);
    }
}
