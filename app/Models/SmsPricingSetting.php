<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsPricingSetting extends Model
{
    protected $fillable = ['price_per_sms', 'currency'];

    protected $casts = ['price_per_sms' => 'decimal:4'];

    // Singleton : une seule ligne existe jamais (id=1), créée par la migration.
    // firstOrCreate en filet de sécurité si jamais la table est vide (ex: après
    // un refresh de la base sans re-jouer le seed initial de la migration).
    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1], ['price_per_sms' => 2, 'currency' => 'XOF']);
    }
}
