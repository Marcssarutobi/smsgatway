<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ---------- Rate limiting ----------
        // Chaque limiteur est nommé pour être réutilisé via ->middleware('throttle:nom')
        // sur les routes concernées (voir routes/api.php).

        // Brute force mot de passe : on limite par email ciblé + IP combinés,
        // pas seulement par IP, pour empêcher un attaquant de contourner la
        // limite en distribuant ses tentatives sur plusieurs IP contre le
        // même compte.
        RateLimiter::for('login', function ($request) {
            $key = strtolower((string) $request->input('email')) . '|' . $request->ip();
            return Limit::perMinute(5)->by($key);
        });

        // Création de compte : freine le spam de comptes (et donc le spam
        // d'emails de vérification envoyés à des adresses arbitraires).
        RateLimiter::for('register', function ($request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Mot de passe oublié : limite par email ciblé, pour empêcher qu'on
        // bombarde la boîte mail d'un utilisateur avec des liens de reset,
        // et par IP en complément contre l'énumération de comptes.
        RateLimiter::for('forgot-password', function ($request) {
            $key = strtolower((string) $request->input('email')) . '|' . $request->ip();
            return Limit::perMinute(3)->by($key);
        });

        RateLimiter::for('reset-password', function ($request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Code 2FA à 6 chiffres (1 000 000 de combinaisons) : limité par
        // utilisateur pour rendre le brute force totalement impraticable,
        // sans gêner un utilisateur légitime qui se trompe une ou deux fois.
        RateLimiter::for('2fa', function ($request) {
            $identifier = $request->user()?->id ?? $request->ip();
            return Limit::perMinute(5)->by($identifier);
        });

        RateLimiter::for('google-auth', function ($request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        // Pairing device : le pairing_token du QR est à courte durée de vie,
        // mais on protège quand même contre le brute force de sa valeur.
        RateLimiter::for('device-pair', function ($request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        // Endpoints appelés en continu par le téléphone pairé (heartbeat
        // toutes les 30s, polling des jobs toutes les 15s) : plafond large
        // pour ne jamais gêner un usage normal, mais qui protège quand même
        // si un device_token venait à fuiter et être utilisé pour du spam.
        RateLimiter::for('device-polling', function ($request) {
            $key = $request->bearerToken() ?? $request->ip();
            return Limit::perMinute(120)->by($key);
        });

        // API d'envoi de SMS (clé API client, transmise en Authorization: Bearer
        // <clé> — voir ApiKeyAuth middleware) : c'est la fonctionnalité produit
        // elle-même, donc généreux — limité par clé API plutôt que par IP pour
        // ne pas pénaliser plusieurs clients derrière une même IP (NAT
        // d'entreprise) ni permettre à un client de saturer le quota d'un autre.
        RateLimiter::for('sms-api', function ($request) {
            $key = $request->bearerToken() ?? $request->ip();
            return Limit::perMinute(60)->by($key);
        });
    }
}
