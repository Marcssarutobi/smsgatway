<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Device;
use App\Models\DeviceSim;
use App\Models\User;
use Illuminate\Support\Str;

class DevicePairingController extends Controller
{
    // Appelé par l'app mobile juste après avoir scanné le QR code
    public function store(Request $request)
    {
        $request->validate([
            'pairing_token' => 'required|string',
            'device_name' => 'required|string|max:255',
            'android_device_id' => 'nullable|string',
            'fcm_token' => 'nullable|string',
            'sims' => 'required|array|min:1',
            'sims.*.slot_index' => 'required|integer|min:0|max:1',
            'sims.*.phone_number' => 'nullable|string',
            'sims.*.operator' => 'nullable|string',
        ]);

        $userId = cache()->get("pairing:{$request->pairing_token}");

        if (!$userId) {
            return response()->json(['message' => 'Code de pairing invalide ou expiré'], 422);
        }

        $user = User::with('activeSubscription.plan')->find($userId);

        $device = Device::create([
            'user_id' => $user->id,
            'name' => $request->device_name,
            'device_token' => Str::random(60),
            'android_device_id' => $request->android_device_id,
            'fcm_token' => $request->fcm_token,
            'status' => 'online',
            'last_seen_at' => now(),
        ]);

        $subscription = $user->activeSubscription;
        $simsCount = count($request->sims);

        // Répartit le quota du plan entre les SIM du téléphone (ex: plan à 50 SMS/mois
        // + 2 SIM -> 25 chacune). max(1, ...) garantit qu'une SIM n'est jamais bloquée
        // à 0 même avec un tout petit plan. Le vrai plafond mensuel reste de toute façon
        // contrôlé au niveau de l'abonnement (Subscription::hasQuotaLeft) : ce quota par
        // SIM ne fait que répartir la charge entre les téléphones/numéros disponibles,
        // il ne peut jamais, à lui seul, laisser dépasser le quota du plan.
        $dailyQuotaPerSim = $subscription
            ? max(1, (int) ceil($subscription->plan->sms_quota_monthly / max(1, $simsCount)))
            : 10;

        foreach ($request->sims as $sim) {
            DeviceSim::create([
                'device_id' => $device->id,
                'slot_index' => $sim['slot_index'],
                'phone_number' => $sim['phone_number'] ?? null,
                'operator' => $sim['operator'] ?? null,
                'daily_quota' => $dailyQuotaPerSim,
            ]);
        }

        cache()->forget("pairing:{$request->pairing_token}");

        return response()->json([
            'device_token' => $device->device_token,
            'device' => $device->load('sims'),
        ], 200);
    }
}
