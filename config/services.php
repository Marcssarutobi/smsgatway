<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'), // ex: http://localhost:8000/api/auth/google/callback
        // Audiences valides pour la connexion mobile (GoogleAuthController::mobileLogin).
        // Lues ici via config() plutôt que env() directement dans le contrôleur :
        // env() en dehors de config/*.php renvoie null dès que php artisan
        // config:cache a été lancé (le .env n'est alors plus relu du tout) —
        // c'est ce qui causait "Token non destiné à cette application" pour
        // TOUT token, même parfaitement valide, une fois le cache généré en prod.
        'web_client_id' => env('GOOGLE_WEB_CLIENT_ID'),
        'android_client_id' => env('GOOGLE_ANDROID_CLIENT_ID'),
        'ios_client_id' => env('GOOGLE_IOS_CLIENT_ID'),
    ],

    'fcm' => [
        'server_key' => env('FCM_SERVER_KEY'),
    ],

    'fedapay' => [
        // Clé publique : utilisée côté front si besoin (Feda Checkout JS), jamais pour signer des requêtes serveur
        'public_key' => env('FEDAPAY_PUBLIC_KEY'),
        // Clé secrète : utilisée uniquement côté serveur pour créer les transactions
        'secret_key' => env('FEDAPAY_SECRET_KEY'),
        // 'sandbox' pour les tests, 'live' en production
        'environment' => env('FEDAPAY_ENVIRONMENT', 'sandbox'),
        // Clé secrète du endpoint webhook (visible dans le dashboard FedaPay > Développeurs > Webhooks)
        'webhook_secret' => env('FEDAPAY_WEBHOOK_SECRET'),
    ],

    'google_analytics' => [
        // Identifiant numérique de la propriété GA4 (GA4 > Paramètres de la propriété)
        'property_id' => env('GOOGLE_ANALYTICS_PROPERTY_ID'),
        // Chemin serveur vers le fichier JSON du compte de service — JAMAIS
        // commité dans git (voir GoogleAnalyticsService pour la procédure complète)
        'credentials_path' => env('GOOGLE_ANALYTICS_CREDENTIALS_PATH')
            ? base_path(env('GOOGLE_ANALYTICS_CREDENTIALS_PATH'))
            : null,
    ],

    'mtn' => [
        // Interrupteur général : tant que false, DispatchSmsJob ne bascule
        // jamais vers MTN et se comporte exactement comme avant (utile pour
        // activer progressivement, ou couper immédiatement en cas de souci
        // sans avoir à toucher au code).
        'enabled' => (bool) env('MTN_SMS_ENABLED', false),
        'base_url' => env('MTN_SMS_BASE_URL', 'https://api.mtn.com'),
        'token_url' => env('MTN_SMS_TOKEN_URL', 'https://api.mtn.com/v1/oauth/access_token/accesstoken'),
        // Consumer key/secret de l'app créée sur developers.mtn.com (SMS v3 API)
        'client_id' => env('MTN_SMS_CLIENT_ID'),
        'client_secret' => env('MTN_SMS_CLIENT_SECRET'),
        // Short code approuvé par MTN pour ton app (obligatoire côté MTN,
        // même si senderAddress est aussi renseigné)
        'service_code' => env('MTN_SMS_SERVICE_CODE'),
        // Optionnel : nom alphanumérique affiché comme expéditeur (ex: "SMSGATEWAY").
        // Si vide, MTN utilise le service_code comme identifiant d'envoi.
        'sender_address' => env('MTN_SMS_SENDER_ADDRESS'),
        // Indicatif pays utilisé pour normaliser les numéros locaux du client
        // (229 = Bénin). À ajuster si tu sers un autre pays supporté par MTN.
        'country_code' => env('MTN_SMS_COUNTRY_CODE', '229'),
    ],

];
