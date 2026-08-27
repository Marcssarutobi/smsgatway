<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    // Exposés automatiquement dans le JSON (voir GET /subscription) pour que
    // le front affiche le crédit restant sans calcul côté client.
    protected $appends = ['sms_quota_total', 'sms_credit_remaining'];

    protected $fillable = [
        'user_id', 'plan_id', 'status', 'sms_used', 'extra_sms_credit',
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

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    // Crédit total disponible pour la période en cours : quota inclus dans le
    // plan (x durée souscrite) + tout crédit supplémentaire acheté en cours
    // de route (voir SubscriptionTopupController).
    public function smsQuotaTotal(): int
    {
        return ($this->plan->sms_quota_monthly * max(1, $this->duration_months ?? 1)) + $this->extra_sms_credit;
    }

    public function smsCreditRemaining(): int
    {
        return max(0, $this->smsQuotaTotal() - $this->sms_used);
    }

    public function hasQuotaLeft(): bool
    {
        return $this->sms_used < $this->smsQuotaTotal();
    }

    // Utilisé pour l'envoi groupé : vérifie qu'il reste assez de crédit pour
    // TOUT le lot de destinataires, pas juste pour un seul message.
    public function hasQuotaLeftFor(int $count): bool
    {
        return ($this->sms_used + $count) <= $this->smsQuotaTotal();
    }

    public function getSmsQuotaTotalAttribute(): int
    {
        return $this->smsQuotaTotal();
    }

    public function getSmsCreditRemainingAttribute(): int
    {
        return $this->smsCreditRemaining();
    }
}
