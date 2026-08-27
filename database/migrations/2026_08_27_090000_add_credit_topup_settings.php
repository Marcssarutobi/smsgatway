<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            // Prix d'un pack de recharge pour ce plan — configurable par
            // l'admin dans /staff. nullable : tant que non renseigné, l'achat
            // de crédit supplémentaire n'est pas proposé pour ce plan (voir
            // SubscriptionTopupController::checkout). La QUANTITÉ ajoutée par
            // pack n'est volontairement pas stockée ici : elle se calcule
            // dynamiquement (50% du quota mensuel, voir Plan::topupSmsCount())
            // pour rester automatiquement cohérente si le quota du plan change.
            $table->decimal('topup_price', 10, 2)->nullable()->after('sms_quota_monthly');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            // Crédit acheté en plus du quota inclus dans le plan, pour la
            // période en cours. Remis à zéro à chaque nouvelle souscription
            // (non reporté d'une période à l'autre, comme le quota de base).
            $table->unsignedInteger('extra_sms_credit')->default(0)->after('sms_used');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('topup_price');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('extra_sms_credit');
        });
    }
};
