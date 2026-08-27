<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = [
        'name', 'price', 'currency', 'sms_quota_monthly', 'max_devices', 'features', 'active',
        'topup_price',
    ];

    protected $casts = ['active' => 'boolean','features' => 'array',];

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    // Coût SMS du quota complet de ce plan, en mode Réseau — jamais stocké en
    // dur sur le plan lui-même car le tarif SMS (voir SmsPricingSetting) peut
    // changer indépendamment du prix du plan (voir PaymentController::checkout,
    // qui appelle ceci avec le tarif figé au moment précis de l'achat).
    public function networkModeSmsCost(float $pricePerSms): float
    {
        return round($this->sms_quota_monthly * $pricePerSms, 2);
    }

    // Quantité de crédit SMS ajoutée par un pack de recharge : toujours 50%
    // du quota mensuel de CE plan, recalculée à la volée (jamais stockée) pour
    // rester automatiquement cohérente si l'admin change le quota du plan.
    public function topupSmsCount(): int
    {
        return (int) round($this->sms_quota_monthly / 2);
    }

    public function hasTopupAvailable(): bool
    {
        return $this->topup_price !== null && (float) $this->topup_price > 0;
    }
}
