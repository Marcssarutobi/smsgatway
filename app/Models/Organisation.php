<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Organisation extends Model
{
    protected $fillable = ['user_id', 'name', 'signature', 'logo', 'website', 'phone', 'address'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
