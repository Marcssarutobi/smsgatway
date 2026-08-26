<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_messages', function (Blueprint $table) {
            // 'device' = envoyé via un téléphone appairé (device_sim_id renseigné)
            // 'mtn'    = envoyé directement via l'API MTN SMS v3 (device_sim_id reste null)
            // Défaut 'device' : tous les SMS existants ont été envoyés ainsi.
            $table->enum('channel', ['device', 'mtn'])->default('device')->after('device_sim_id');
        });
    }

    public function down(): void
    {
        Schema::table('sms_messages', function (Blueprint $table) {
            $table->dropColumn('channel');
        });
    }
};
