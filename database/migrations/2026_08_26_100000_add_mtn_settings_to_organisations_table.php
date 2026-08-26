<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            // Contrairement à MTN_SMS_CLIENT_ID / MTN_SMS_CLIENT_SECRET /
            // MTN_SMS_SERVICE_CODE (identifiants MTN de la plateforme, restés
            // globaux dans .env — un seul compte MTN Developer pour tous les
            // clients), ces deux champs sont propres à CHAQUE client : ils ne
            // s'appliquent que s'il choisit d'envoyer via l'API MTN plutôt
            // que via un téléphone Android appairé (voir MtnSmsService).
            $table->string('mtn_sender_address')->nullable()->after('address');
            $table->string('mtn_country_code', 3)->default('229')->after('mtn_sender_address');
        });
    }

    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropColumn(['mtn_sender_address', 'mtn_country_code']);
        });
    }
};
