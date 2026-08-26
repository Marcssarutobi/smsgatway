<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Organisation extends Model
{
    protected $fillable = [
        'user_id', 'name', 'signature', 'logo', 'website', 'phone', 'address',
        'mtn_sender_address', 'mtn_country_code', 'preferred_sms_channel',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function usesDeviceChannel(): bool
    {
        return $this->preferred_sms_channel !== 'network';
    }
}
