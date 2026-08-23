<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Distinct de Device::fcm_token (qui sert à réveiller le
            // téléphone-passerelle pour l'envoi de SMS). Celui-ci sert à
            // notifier l'UTILISATEUR lui-même (côté app mobile ou web push),
            // que ce soit un client ou un membre du staff (role Admin).
            $table->string('fcm_token')->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('fcm_token');
        });
    }
};
