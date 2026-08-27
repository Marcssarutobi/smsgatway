<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Plan;

class PlanController extends Controller
{
    // Public, pas besoin d'auth — utilisé sur la page tarifs du site
    public function index()
    {
        return response()->json(Plan::where('active', true)->get());
    }

    // ---------- Gestion des tarifs, réservée au staff (middleware 'admin') ----------

    // GET /api/admin/plans — tous les plans, actifs ou non
    public function adminIndex(): JsonResponse
    {
        return response()->json(Plan::withCount('subscriptions')->orderBy('price')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'price' => 'required|numeric|min:0',
            'currency' => 'required|string|max:10',
            'sms_quota_monthly' => 'required|integer|min:0',
            'max_devices' => 'required|integer|min:1',
            'features' => 'nullable|array',
            'active' => 'sometimes|boolean',
            // Prix du pack de recharge (50% du quota, voir Plan::topupSmsCount).
            // null/absent = achat de crédit désactivé pour ce plan.
            'topup_price' => 'nullable|numeric|min:0',
        ]);

        $plan = Plan::create($validated);

        return response()->json($plan, 201);
    }

    public function update(Request $request, Plan $plan): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'price' => 'sometimes|numeric|min:0',
            'currency' => 'sometimes|string|max:10',
            'sms_quota_monthly' => 'sometimes|integer|min:0',
            'max_devices' => 'sometimes|integer|min:1',
            'features' => 'nullable|array',
            'active' => 'sometimes|boolean',
            'topup_price' => 'nullable|numeric|min:0',
        ]);

        $plan->update($validated);

        return response()->json($plan);
    }

    public function destroy(Plan $plan): JsonResponse
    {
        if ($plan->subscriptions()->exists()) {
            // On ne supprime jamais un plan déjà souscrit par un client, même
            // ancien : ça casserait l'historique. On le désactive à la place.
            $plan->update(['active' => false]);

            return response()->json(['message' => 'Ce plan a des abonnés (passés ou présents) : il a été désactivé plutôt que supprimé, pour préserver l\'historique.']);
        }

        $plan->delete();

        return response()->json(['message' => 'Plan supprimé']);
    }
}
