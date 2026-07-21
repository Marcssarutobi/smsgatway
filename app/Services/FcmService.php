<?php

namespace App\Services;

use App\Models\Device;
use Illuminate\Support\Facades\Http;

class FcmService
{
    public function sendWakeUp(Device $device): void
    {
        if (!$device->fcm_token) {
            return;
        }

        Http::withToken(config('services.fcm.server_key'))
            ->post('https://fcm.googleapis.com/fcm/send', [
                'to' => $device->fcm_token,
                'data' => ['type' => 'new_sms_job'],
                'priority' => 'high',
            ]);
    }
}
