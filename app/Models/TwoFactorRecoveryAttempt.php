<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TwoFactorRecoveryAttempt extends Model
{
    protected $fillable = ['user_id', 'ip_address', 'success'];

    protected $casts = ['success' => 'boolean'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
