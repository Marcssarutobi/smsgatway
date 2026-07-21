<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiKey extends Model
{
    protected $fillable = ['user_id', 'name', 'key', 'secret', 'status', 'last_used_at'];

    protected $hidden = ['secret'];

    protected $casts = ['last_used_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function smsMessages()
    {
        return $this->hasMany(SmsMessage::class);
    }
}
