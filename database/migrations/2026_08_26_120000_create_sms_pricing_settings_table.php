<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Une seule ligne (singleton), plutôt qu'une valeur figée dans .env :
        // l'admin doit pouvoir la modifier depuis /staff sans redéploiement,
        // et on veut pouvoir suivre qui/quand ça a changé (updated_at suffit ici).
        Schema::create('sms_pricing_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('price_per_sms', 10, 4)->default(2);
            $table->string('currency', 3)->default('XOF');
            $table->timestamps();
        });

        // Ligne unique initiale — le modèle SmsPricingSetting s'appuie dessus
        // (voir SmsPricingSetting::current(), firstOrCreate sur id=1).
        \DB::table('sms_pricing_settings')->insert([
            'price_per_sms' => 2,
            'currency' => 'XOF',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_pricing_settings');
    }
};
