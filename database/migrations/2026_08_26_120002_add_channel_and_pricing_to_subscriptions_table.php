<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // Choisi par le client au moment de la souscription (modal
            // Device/Opérateur) — PAS juste un réglage libre modifiable à part,
            // puisqu'il détermine directement le prix payé (voir
            // PaymentController::checkout).
            $table->string('channel')->default('device')->after('plan_id');

            // 1, 3, 6 ou 12 mois — détermine current_period_end ET le montant total.
            $table->unsignedTinyInteger('duration_months')->default(1)->after('channel');

            // Tarif SMS figé au moment de l'achat (uniquement si channel =
            // 'network'). Indispensable : le tarif admin (sms_pricing_settings)
            // peut changer après coup, mais cette souscription-ci a été payée
            // sur la base du tarif en vigueur CE jour-là — on ne doit jamais
            // recalculer rétroactivement ce qu'un client a déjà payé.
            $table->decimal('sms_rate_applied', 10, 4)->nullable()->after('duration_months');

            // Montant total réellement payé (abonnement + coût SMS le cas
            // échéant, multiplié par duration_months) — indépendant du prix
            // courant du plan, qui peut lui aussi changer après coup.
            $table->decimal('amount_paid', 12, 2)->nullable()->after('sms_rate_applied');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['channel', 'duration_months', 'sms_rate_applied', 'amount_paid']);
        });
    }
};
