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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained();
            // Renseigné une fois le paiement approuvé et l'abonnement activé
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();

            // ID de la transaction côté FedaPay (utilisé pour retrouver le paiement depuis le webhook)
            $table->string('fedapay_transaction_id')->nullable()->unique();
            $table->string('checkout_url')->nullable();

            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('XOF');

            // pending: transaction créée, en attente de règlement par le client
            // approved: paiement confirmé par FedaPay, abonnement activé
            // declined/canceled: paiement refusé ou annulé par le client
            // failed: erreur technique lors de la création de la transaction FedaPay
            $table->enum('status', ['pending', 'approved', 'declined', 'canceled', 'failed'])->default('pending');

            // Dernier payload webhook reçu de FedaPay, conservé pour audit/debug
            $table->json('raw_payload')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
