<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = [
        'name', 'price', 'currency', 'sms_quota_monthly', 'max_devices', 'features', 'active',
    ];

    protected $casts = ['active' => 'boolean','features' => 'array',];

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }
}
