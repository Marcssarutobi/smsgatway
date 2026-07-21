<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Device;
use App\Models\DeviceSim;
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

        $device = Device::create([
            'user_id' => $userId,
            'name' => $request->device_name,
            'device_token' => Str::random(60),
            'android_device_id' => $request->android_device_id,
            'fcm_token' => $request->fcm_token,
            'status' => 'online',
            'last_seen_at' => now(),
        ]);

        foreach ($request->sims as $sim) {
            DeviceSim::create([
                'device_id' => $device->id,
                'slot_index' => $sim['slot_index'],
                'phone_number' => $sim['phone_number'] ?? null,
                'operator' => $sim['operator'] ?? null,
            ]);
        }

        cache()->forget("pairing:{$request->pairing_token}");

        return response()->json([
            'device_token' => $device->device_token, // l'app le stocke pour s'authentifier ensuite
            'device' => $device->load('sims'),
        ], 200);
    }
}
