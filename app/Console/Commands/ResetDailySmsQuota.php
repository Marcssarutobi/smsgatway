<?php

namespace App\Console\Commands;

use App\Models\DeviceSim;
use Illuminate\Console\Command;

class ResetDailySmsQuota extends Command
{
    protected $signature = 'sms:reset-daily-quota';

    protected $description = "Remet à zéro le compteur sent_today de chaque SIM (à lancer une fois par jour, voir routes/console.php)";

    public function handle(): int
    {
        $updated = DeviceSim::where('sent_today', '>', 0)->update(['sent_today' => 0]);

        $this->info("Quota journalier réinitialisé pour {$updated} SIM.");

        return self::SUCCESS;
    }
}
