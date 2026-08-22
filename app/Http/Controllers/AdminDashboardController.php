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
            ],
            'subscriptions_by_plan' => $activeSubscriptionsByPlan,
            'revenue_this_month' => (float) $revenueThisMonth,
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
}
