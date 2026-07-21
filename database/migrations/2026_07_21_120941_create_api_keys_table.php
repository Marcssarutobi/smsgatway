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
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();           // ex: "Clé production", "Clé test"
            $table->enum('environment', ['test', 'live'])->default('test'); 
            $table->string('key')->unique();               // clé publique envoyée au client
            $table->string('secret')->nullable();           // secret hashé si tu signes les requêtes
            $table->enum('status', ['active', 'revoked'])->default('active');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
