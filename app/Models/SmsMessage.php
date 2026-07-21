<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsMessage extends Model
{
    protected $fillable = [
        'user_id', 'api_key_id', 'device_sim_id', 'recipient', 'content',
        'status', 'priority', 'cost', 'error_message', 'sent_at', 'delivered_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function apiKey()
    {
        return $this->belongsTo(ApiKey::class);
    }

    public function deviceSim()
    {
        return $this->belongsTo(DeviceSim::class);
    }

    public function statusLogs()
    {
        return $this->hasMany(SmsStatusLog::class);
    }

    // change le statut ET enregistre l'historique en une seule méthode
    public function updateStatus(string $status, ?string $details = null): void
    {
        $this->update(['status' => $status]);
        $this->statusLogs()->create(['status' => $status, 'details' => $details]);
    }
}
