<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');                     // "Téléphone Bureau"
            $table->string('device_token')->unique();
            $table->string('android_device_id')->nullable(); // identifiant matériel du téléphone
            $table->enum('status', ['online', 'offline'])->default('offline');
            $table->text('fcm_token')->nullable();
            $table->unsignedTinyInteger('battery_level')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        // device_sims : une ligne par carte SIM détectée sur le téléphone
        Schema::create('device_sims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('slot_index');   // 0 ou 1 (SIM 1 / SIM 2)
            $table->string('phone_number')->nullable();
            $table->string('operator')->nullable();
            $table->boolean('is_active')->default(true); // le client peut désactiver une SIM
            $table->unsignedInteger('daily_quota')->default(200);
            $table->unsignedInteger('sent_today')->default(0);
            $table->unsignedTinyInteger('signal_strength')->nullable();
            $table->timestamps();

            $table->unique(['device_id', 'slot_index']); // pas 2 fois le même slot sur un device
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
