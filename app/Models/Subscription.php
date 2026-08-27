<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = [
        'user_id', 'plan_id', 'status', 'sms_used',
        'current_period_start', 'current_period_end',
        'channel', 'duration_months', 'sms_rate_applied', 'amount_paid',
    ];

    protected $casts = [
        'current_period_start' => 'date',
        'current_period_end' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    // Quota total pour TOUTE la période souscrite (ex: plan 1000 SMS/mois
    // souscrit pour 3 mois => 3000 SMS), et non juste le quota mensuel du plan.
    public function smsQuotaTotal(): int
    {
        return $this->plan->sms_quota_monthly * max(1, $this->duration_months ?? 1);
    }

    public function hasQuotaLeft(): bool
    {
        return $this->sms_used < $this->smsQuotaTotal();
    }

    // Utilisé pour l'envoi groupé : vérifie qu'il reste assez de quota pour
    // TOUT le lot de destinataires, pas juste pour un seul message.
    public function hasQuotaLeftFor(int $count): bool
    {
        return ($this->sms_used + $count) <= $this->smsQuotaTotal();
    }
}
