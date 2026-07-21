<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceSim extends Model
{
    protected $fillable = [
        'device_id', 'slot_index', 'phone_number', 'operator',
        'is_active', 'daily_quota', 'sent_today', 'signal_strength',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    public function smsMessages()
    {
        return $this->hasMany(SmsMessage::class);
    }

    // pratique pour le dispatcher : cette SIM peut-elle encore envoyer aujourd'hui ?
    public function hasQuotaLeft(): bool
    {
        return $this->is_active && $this->sent_today < $this->daily_quota;
    }
}
