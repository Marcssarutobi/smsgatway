<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_pricing_settings', function (Blueprint $table) {
            // Interrupteur activable/désactivable par le staff depuis /staff,
            // sans avoir besoin d'un accès SSH au serveur (contrairement à
            // MTN_SMS_ENABLED dans .env, qui reste un second garde-fou : les
            // deux doivent être à "true"/actif pour que le mode Réseau
            // fonctionne réellement, voir SmsMessageController::canSendNow).
            // Défaut à false : tant que le short code MTN n'est pas obtenu,
            // le mode Réseau reste désactivé par défaut même si un client
            // l'a choisi à la souscription.
            $table->boolean('network_enabled')->default(false)->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('sms_pricing_settings', function (Blueprint $table) {
            $table->dropColumn('network_enabled');
        });
    }
};
