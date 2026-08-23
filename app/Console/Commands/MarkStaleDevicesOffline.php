<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Notifications\DeviceWentOfflineNotification;
use Illuminate\Console\Command;

class MarkStaleDevicesOffline extends Command
{
    protected $signature = 'devices:mark-offline';

    protected $description = "Passe un device en 'offline' s'il n'a pas envoyé de heartbeat depuis 2 minutes (voir routes/console.php)";

    // Le mobile envoie un heartbeat toutes les 30s (voir useHeartbeat.ts côté app) :
    // 2 minutes de silence = 4 heartbeats manqués d'affilée, largement suffisant
    // pour ne pas basculer offline sur une simple coupure réseau passagère.
    private const STALE_AFTER_MINUTES = 2;

    public function handle(): int
    {
        $staleDevices = Device::where('status', 'online')
            ->where(function ($query) {
                $query->whereNull('last_seen_at')
                    ->orWhere('last_seen_at', '<', now()->subMinutes(self::STALE_AFTER_MINUTES));
            })
            ->with('user')
            ->get();

        foreach ($staleDevices as $device) {
            $device->update(['status' => 'offline']);

            // Notifie le client que sa passerelle SMS ne répond plus — utile
            // pour qu'il aille vérifier son téléphone avant que ses SMS en
            // attente ne s'accumulent sans jamais partir.
            $device->user?->notify(new DeviceWentOfflineNotification($device));
        }

        $this->info(count($staleDevices) . " device(s) passé(s) hors ligne (pas de heartbeat depuis " . self::STALE_AFTER_MINUTES . " min).");

        return self::SUCCESS;
    }
}
