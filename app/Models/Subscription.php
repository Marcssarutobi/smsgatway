<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = [
        'user_id', 'plan_id', 'status', 'sms_used',
        'current_period_start', 'current_period_end',
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

    public function hasQuotaLeft(): bool
    {
        return $this->sms_used < $this->plan->sms_quota_monthly;
    }

    // Utilisé pour l'envoi groupé : vérifie qu'il reste assez de quota pour
    // TOUT le lot de destinataires, pas juste pour un seul message.
    public function hasQuotaLeftFor(int $count): bool
    {
        return ($this->sms_used + $count) <= $this->plan->sms_quota_monthly;
    }
}
