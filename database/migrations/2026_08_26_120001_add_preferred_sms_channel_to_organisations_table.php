<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            // Reflète le choix fait à la souscription (voir subscriptions.channel) —
            // c'est ce que DispatchSmsJob consulte au moment d'envoyer un SMS.
            // 'device' = via un téléphone Android appairé (défaut)
            // 'network' = via l'opérateur réseau partenaire unique de la plateforme
            $table->string('preferred_sms_channel')->default('device')->after('mtn_country_code');
        });
    }

    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropColumn('preferred_sms_channel');
        });
    }
};
