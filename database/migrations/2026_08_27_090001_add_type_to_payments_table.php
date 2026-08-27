<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // 'subscription' = souscription/changement de plan (comportement existant)
            // 'topup'        = achat de crédit SMS supplémentaire sur un abonnement actif
            $table->string('type')->default('subscription')->after('subscription_id');

            // Uniquement renseigné si type = 'topup' : la quantité de SMS que
            // CE paiement précis doit ajouter une fois approuvé. Figée à l'achat
            // plutôt que recalculée depuis plans.topup_price au moment du
            // webhook, au cas où l'admin changerait la config entre-temps.
            $table->unsignedInteger('sms_credit_purchased')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['type', 'sms_credit_purchased']);
        });
    }
};
