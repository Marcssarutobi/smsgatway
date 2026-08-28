<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\SmsMessage;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    // GET /api/admin/dashboard — vue globale de la plateforme (réservé role Admin)
    public function overview(Request $request): JsonResponse
    {
        $now = now();
        $startOfMonth = $now->copy()->startOfMonth();
        $startOfToday = $now->copy()->startOfDay();

        $totalUsers = User::where('role', 'Client')->count();
        $newUsersThisMonth = User::where('role', 'Client')->where('created_at', '>=', $startOfMonth)->count();

        $devices = Device::selectRaw("status, count(*) as total")->groupBy('status')->pluck('total', 'status');

        $smsToday = SmsMessage::where('created_at', '>=', $startOfToday)->count();
        $smsThisMonth = SmsMessage::where('created_at', '>=', $startOfMonth)->count();
        $smsFailedThisMonth = SmsMessage::where('created_at', '>=', $startOfMonth)->where('status', 'failed')->count();

        // Seuls les SMS réellement partis comptent pour la facturation MTN —
        // un SMS 'pending'/'queued'/'failed' n'a jamais atteint l'opérateur,
        // donc ne nous sera pas facturé.
        $smsSentViaMtnThisMonth = SmsMessage::where('created_at', '>=', $startOfMonth)
            ->where('channel', 'mtn')
            ->whereIn('status', ['sent', 'delivered'])
            ->count();

        $smsSentViaDeviceThisMonth = SmsMessage::where('created_at', '>=', $startOfMonth)
            ->where('channel', 'device')
            ->whereIn('status', ['sent', 'delivered'])
            ->count();

        // Coût réellement dû à MTN ce mois-ci, au tarif unitaire actuellement
        // configuré (Réglages > Tarif SMS). Si MTN facture un tarif de gros
        // différent du tarif public affiché aux clients, ce montant n'est
        // qu'une ESTIMATION à ce même tarif — ajuste si besoin le jour où un
        // tarif de gros distinct est négocié avec MTN.
        $smsUnitPrice = (float) \App\Models\SmsPricingSetting::current()->price_per_sms;
        $mtnCostThisMonth = $smsSentViaMtnThisMonth * $smsUnitPrice;

        $activeSubscriptionsByPlan = Subscription::where('status', 'active')
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->selectRaw('plans.name as plan_name, count(*) as total')
            ->groupBy('plans.name')
            ->pluck('total', 'plan_name');

        $revenueThisMonth = Payment::where('status', 'approved')
            ->where('created_at', '>=', $startOfMonth)
            ->sum('amount');

        $latestSignups = User::where('role', 'Client')
            ->latest()
            ->take(8)
            ->get(['id', 'name', 'email', 'created_at', 'status']);

        return response()->json([
            'users' => [
                'total' => $totalUsers,
                'new_this_month' => $newUsersThisMonth,
            ],
            'devices' => [
                'online' => (int) ($devices['online'] ?? 0),
                'offline' => (int) ($devices['offline'] ?? 0),
                'total' => (int) $devices->sum(),
            ],
            'sms' => [
                'today' => $smsToday,
                'this_month' => $smsThisMonth,
                'failed_this_month' => $smsFailedThisMonth,
                'sent_via_device_this_month' => $smsSentViaDeviceThisMonth,
                'sent_via_mtn_this_month' => $smsSentViaMtnThisMonth,
            ],
            'subscriptions_by_plan' => $activeSubscriptionsByPlan,
            'revenue_this_month' => (float) $revenueThisMonth,
            // Ce que la plateforme doit reverser à MTN ce mois-ci pour les
            // SMS réellement envoyés via l'opérateur (mode Réseau), et ce
            // qu'il reste une fois ce coût déduit du revenu encaissé — la
            // marge disponible pour l'hébergement et les autres charges.
            'mtn_cost_this_month' => round($mtnCostThisMonth, 2),
            'sms_unit_price' => $smsUnitPrice,
            'net_profit_this_month' => round((float) $revenueThisMonth - $mtnCostThisMonth, 2),
            'latest_signups' => $latestSignups,
        ]);
    }

    // GET /api/admin/users — liste paginée de tous les utilisateurs de la plateforme
    public function users(Request $request): JsonResponse
    {
        $query = User::query()->where('role', 'Client')
            ->withCount(['devices', 'smsMessages'])
            ->with('activeSubscription.plan');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
        }

        return response()->json($query->latest()->paginate(20));
    }

    // POST /api/admin/users/{user}/suspend — bloque l'accès (ex: abus, impayé confirmé manuellement)
    public function suspendUser(User $user): JsonResponse
    {
        abort_if($user->role === 'Admin', 403, "On ne suspend pas un compte staff depuis cet écran.");

        $user->update(['status' => 'suspendu']);

        return response()->json(['message' => "{$user->name} a été suspendu."]);
    }

    // POST /api/admin/users/{user}/activate — lève une suspension
    public function activateUser(User $user): JsonResponse
    {
        $user->update(['status' => 'actif']);

        return response()->json(['message' => "{$user->name} a été réactivé."]);
    }
}
