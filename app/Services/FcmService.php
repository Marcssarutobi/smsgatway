<?php

namespace App\Services;

use App\Models\Device;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmService
{
    public function sendWakeUp(Device $device): void
    {
        if (!$device->fcm_token) {
            Log::info("Device #{$device->id} sans fcm_token, wake-up ignoré (mode dev ?).");
            return;
        }

        $serverKey = config('services.fcm.server_key');

        if (!$serverKey) {
            // Utile en développement/soutenance : pas bloquant si FCM n'est pas encore configuré
            Log::warning('FCM_SERVER_KEY non configurée : notification non envoyée (device devra faire du polling).');
            return;
        }

        try {
            Http::withToken($serverKey)
                ->timeout(5)
                ->post('https://fcm.googleapis.com/fcm/send', [
                    'to' => $device->fcm_token,
                    'data' => ['type' => 'new_sms_job'],
                    'priority' => 'high',
                ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
