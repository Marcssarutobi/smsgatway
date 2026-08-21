<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Remet à zéro le quota journalier de chaque SIM à minuit. Nécessite que le
// scheduler Laravel tourne réellement :
// - En prod : une tâche cron unique -> */1 * * * * php artisan schedule:run
// - En local (Windows y compris) : laisser tourner `php artisan schedule:work`
//   dans un terminal pendant vos tests.
Schedule::command('sms:reset-daily-quota')->dailyAt('00:00');

// Détecte les téléphones qui ne répondent plus (app fermée, batterie morte,
// perte réseau) et les repasse "offline" dans le dashboard admin.
Schedule::command('devices:mark-offline')->everyMinute();
