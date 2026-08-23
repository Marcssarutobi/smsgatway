<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envoie une vraie notification push visible (titre + corps) à l'appareil
 * d'un utilisateur (client ou staff), via son fcm_token personnel.
 *
 * À ne pas confondre avec FcmService, qui envoie un ping silencieux au
 * Device (le téléphone-passerelle SMS) pour le réveiller — celui-ci
 * s'adresse à la personne elle-même, sur l'app où elle est connectée.
 */
class PushNotificationService
{
    public function sendToUser(User $user, string $title, string $body, array $data = []): void
    {
        if (!$user->fcm_token) {
            return; // utilisateur n'a jamais ouvert l'app mobile / autorisé les notifs
        }

        $serverKey = config('services.fcm.server_key');

        if (!$serverKey) {
            Log::warning('FCM_SERVER_KEY non configurée : push utilisateur non envoyé.');
            return;
        }

        try {
            Http::withToken($serverKey)
                ->timeout(5)
                ->post('https://fcm.googleapis.com/fcm/send', [
                    'to' => $user->fcm_token,
                    'notification' => ['title' => $title, 'body' => $body],
                    'data' => $data,
                    'priority' => 'high',
                ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
