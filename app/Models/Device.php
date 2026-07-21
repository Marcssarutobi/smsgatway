<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    protected $fillable = [
        'user_id', 'name', 'device_token', 'android_device_id',
        'status', 'fcm_token', 'battery_level', 'last_seen_at',
    ];

    protected $hidden = ['device_token', 'fcm_token'];

    protected $casts = ['last_seen_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sims()
    {
        return $this->hasMany(DeviceSim::class);
    }

    public function activeSims()
    {
        return $this->hasMany(DeviceSim::class)->where('is_active', true);
    }
}
