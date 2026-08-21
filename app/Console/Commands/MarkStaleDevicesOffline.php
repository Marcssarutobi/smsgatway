<?php

namespace App\Console\Commands;

use App\Models\Device;
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
        $updated = Device::where('status', 'online')
            ->where(function ($query) {
                $query->whereNull('last_seen_at')
                    ->orWhere('last_seen_at', '<', now()->subMinutes(self::STALE_AFTER_MINUTES));
            })
            ->update(['status' => 'offline']);

        $this->info("{$updated} device(s) passé(s) hors ligne (pas de heartbeat depuis " . self::STALE_AFTER_MINUTES . " min).");

        return self::SUCCESS;
    }
}
