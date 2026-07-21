<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = [
        'name', 'price', 'currency', 'sms_quota_monthly', 'max_devices', 'active',
    ];

    protected $casts = ['active' => 'boolean'];

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }
}
