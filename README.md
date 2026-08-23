# SMS Gateway — Backend (API)

API Laravel du SaaS SMS Gateway : transforme des téléphones Android en passerelles d'envoi de SMS, pilotables via une API REST, avec facturation par abonnement (FedaPay), 2FA, et un panneau d'administration plateforme.

## Stack

- **Laravel 13** / PHP 8.3
- **Laravel Sanctum** pour l'authentification API (tokens, abilities)
- **FedaPay** pour la facturation des abonnements (XOF)
- **Google2FA** pour la double authentification
- **Firebase Cloud Messaging (FCM)** pour réveiller les téléphones-passerelles et notifier les utilisateurs
- **Google Analytics Data API (GA4)** pour les statistiques de trafic dans le panneau admin

## Installation locale

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

Lance le serveur :
```bash
php artisan serve
```

Les tâches planifiées (reset quota SMS quotidien, détection des devices hors ligne) nécessitent que le scheduler tourne :
```bash
php artisan schedule:work   # à laisser tourner en local pendant le développement
```
En production, une seule tâche cron suffit : `* * * * * php artisan schedule:run`.

## Variables d'environnement importantes

Toutes documentées avec leur usage dans `.env.example`. Points d'attention :

| Variable | Sert à |
|---|---|
| `FEDAPAY_*` | Paiement des abonnements |
| `GOOGLE_WEB_CLIENT_ID` / `GOOGLE_ANDROID_CLIENT_ID` | Connexion Google (web + mobile) |
| `FCM_SERVER_KEY` | Réveil des devices + notifications push aux utilisateurs |
| `GOOGLE_ANALYTICS_PROPERTY_ID` / `GOOGLE_ANALYTICS_CREDENTIALS_PATH` | Stats de trafic dans le panneau admin (`/staff`) — voir `app/Services/GoogleAnalyticsService.php` pour la procédure de configuration complète |

## Rôles

Deux rôles seulement : `Client` (utilisateur normal du SaaS) et `Admin` (staff de la plateforme, accès à `/api/admin/*`). Pas de notion d'équipe/membres au sein d'un compte — chaque `User` a sa propre `Organisation` individuelle.

## Modules principaux

- **Devices** : pairing d'un téléphone Android comme passerelle SMS, heartbeat, gestion des SIM
- **SMS** : envoi via l'API (`/api/v1/sms`), dispatch vers le device, suivi de statut (delivered/failed)
- **Abonnements** : plans (`/api/plans`), paiement FedaPay, quota mensuel de SMS
- **Contact** : formulaire public (`/api/contact`), notifie le staff
- **Notifications** : système générique (in-app + push FCM) pour clients et staff — voir `app/Notifications/`
- **Panneau admin** (`/api/admin/*`) : stats globales, gestion des utilisateurs, des tarifs, des messages de contact, trafic Google Analytics

## Tests

⚠️ Seuls les stubs par défaut de Laravel sont présents actuellement (`tests/Feature/ExampleTest.php`). Aucune fonctionnalité métier n'est encore couverte par des tests automatisés — à prioriser avant une montée en charge importante.
