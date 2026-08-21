<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, Notifiable, MustVerifyEmailTrait;

    protected $fillable = ['name', 'email', 'password', 'avatar', 'role', 'status'];

    protected $hidden = [
        'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'two_factor_confirmed_at' => 'datetime',
        'two_factor_secret' => 'encrypted',
        'two_factor_recovery_codes' => 'encrypted:array',
    ];

    public function oauthAccounts()
    {
        return $this->hasMany(OauthAccount::class);
    }

    // Par défaut, Laravel envoie une notification pointant vers une vue Blade
    // (/password/reset/{token}) qui n'existe pas dans une API pure. On la
    // redirige vers la page React du frontend à la place.
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\ResetPasswordNotification($token));
    }

    public function twoFactorRecoveryAttempts()
    {
        return $this->hasMany(TwoFactorRecoveryAttempt::class);
    }

    public function apiKeys()
    {
        return $this->hasMany(ApiKey::class);
    }

    public function devices()
    {
        return $this->hasMany(Device::class);
    }

    public function smsMessages()
    {
        return $this->hasMany(SmsMessage::class);
    }

    public function webhooks()
    {
        return $this->hasMany(Webhook::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    // Un plan gratuit (essai) ne doit pouvoir être activé qu'une seule fois par
    // utilisateur, sinon il suffirait de le resélectionner pour reset son quota.
    // On regarde tout l'historique (pas seulement l'abonnement actif).
    public function hasAlreadyUsedPlan(Plan $plan): bool
    {
        return $this->subscriptions()->where('plan_id', $plan->id)->exists();
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function activeSubscription()
    {
        return $this->hasOne(Subscription::class)->where('status', 'active')->latestOfMany();
    }

    public function organisation()
    {
        return $this->hasOne(Organisation::class);
    }

    public function startTrialSubscription(): Subscription
    {
        $trialPlan = Plan::where('name', 'Trial')->firstOrFail();

        return $this->subscriptions()->create([
            'plan_id' => $trialPlan->id,
            'status' => 'active',
            'sms_used' => 0,
            'current_period_start' => now(),
            'current_period_end' => now()->addDays(14),
        ]);
    }
}
