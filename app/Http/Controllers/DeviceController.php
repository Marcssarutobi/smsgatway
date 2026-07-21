<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Device;
use Illuminate\Support\Str;

class DeviceController extends Controller
{

    public function index(Request $request)
    {
        return response()->json(
            $request->user()->devices()->with('sims')->latest()->get()
        );
    }

    public function show(Request $request, Device $device)
    {
        if ($device->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        return response()->json($device->load('sims'));
    }

    // Génère un code de pairing temporaire + QR code que l'app mobile va scanner
    public function generatePairingCode(Request $request)
    {
        $plan = $request->user()->activeSubscription?->plan;

        if ($plan && $request->user()->devices()->count() >= $plan->max_devices) {
            return response()->json(['message' => 'Limite de devices atteinte pour votre plan'], 403);
        }

        $pairingToken = Str::random(40);

        // Stocké temporairement en cache (10 minutes), pas encore un vrai device
        cache()->put("pairing:{$pairingToken}", $request->user()->id, now()->addMinutes(10));

        return response()->json([
            'pairing_token' => $pairingToken,
            'qr_payload' => json_encode([
                'token' => $pairingToken,
                'api_url' => config('app.url') . '/api/device/pair',
            ]),
            'expires_in' => 600,
        ]);
    }

    public function rename(Request $request, Device $device)
    {
        if ($device->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        $request->validate(['name' => 'required|string|max:255']);
        $device->update(['name' => $request->name]);

        return response()->json($device);
    }

    public function destroy(Request $request, Device $device)
    {
        if ($device->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        $device->delete(); // cascade supprime aussi les device_sims

        return response()->json(['message' => 'Device supprimé']);
    }

}
