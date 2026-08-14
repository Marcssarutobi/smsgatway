<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiKey extends Model
{
    protected $fillable = ['user_id', 'name', 'key', 'environment', 'secret', 'status', 'last_used_at'];

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

    // Génère la paire unique de clés (test + live) d'un utilisateur. Centralisé
    // ici pour être appelé partout où un compte est créé (inscription classique,
    // Google web, Google mobile) ainsi que pour la régénération manuelle.
    public static function generatePairFor(User $user, ?string $label = null): \Illuminate\Support\Collection
    {
        return collect(['test', 'live'])->map(function ($environment) use ($user, $label) {
            return $user->apiKeys()->create([
                'name' => ($label ?? 'Clé API') . ' (' . ucfirst($environment) . ')',
                'environment' => $environment,
                'key' => $environment === 'test'
                    ? 'sk_test_' . \Illuminate\Support\Str::random(32)
                    : 'sk_live_' . \Illuminate\Support\Str::random(32),
                'secret' => $environment === 'test'
                    ? 'ss_test_' . \Illuminate\Support\Str::random(48)
                    : 'ss_live_' . \Illuminate\Support\Str::random(48),
                'status' => 'active',
            ]);
        });
    }
}
