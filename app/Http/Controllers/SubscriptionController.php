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

    // Simplifié : pas de paiement réel ici, juste le changement de plan
    public function subscribe(Request $request)
    {
        $request->validate(['plan_id' => 'required|exists:plans,id']);

        $plan = Plan::findOrFail($request->plan_id);

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
