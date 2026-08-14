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

];
