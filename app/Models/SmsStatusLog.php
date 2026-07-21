<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsStatusLog extends Model
{
    public $timestamps = false; // seulement created_at, pas de updated_at

    protected $fillable = ['sms_message_id', 'status', 'details'];

    protected $casts = ['created_at' => 'datetime'];

    public function smsMessage()
    {
        return $this->belongsTo(SmsMessage::class);
    }
}
