<?php

use App\Http\Controllers\ApiKeyController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\DeviceJobController;
use App\Http\Controllers\DevicePairingController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\OrganisationController;
use App\Http\Controllers\SmsMessageController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::prefix('auth')->group(function () {
    Route::post('/register', [UserController::class, 'register']);
    Route::post('/login', [UserController::class, 'login']);
    Route::get('/google/redirect', [GoogleAuthController::class, 'redirect']);
    Route::get('/google/callback', [GoogleAuthController::class, 'callback']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [UserController::class, 'logout']);
        Route::get('/me', [UserController::class, 'me']);
        Route::put('/profile', [UserController::class, 'updateProfile']);
        Route::put('/profile/password', [UserController::class, 'changePassword']);
        Route::post('/2fa/setup', [TwoFactorController::class, 'setup']);
        Route::post('/2fa/confirm', [TwoFactorController::class, 'confirm']);
    });

    // Auth spéciale : uniquement le temp_token avec ability '2fa-pending'
    Route::middleware(['auth:sanctum', 'ability:2fa-pending'])->group(function () {
        Route::post('/2fa/verify', [TwoFactorController::class, 'verify']);
    });
});

Route::middleware('auth:sanctum')->group(function(){

    //Utilisateur
    Route::get('/users', [UserController::class, 'index']);        // check rôle fait dans le contrôleur
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);

    Route::apiResource('api-keys', ApiKeyController::class)->only(['index', 'store', 'destroy']);
    Route::patch('/api-keys/{apiKey}/revoke', [ApiKeyController::class, 'revoke']);

    Route::get('/devices', [DeviceController::class, 'index']);
    Route::get('/devices/{device}', [DeviceController::class, 'show']);
    Route::post('/devices/pairing-code', [DeviceController::class, 'generatePairingCode']);
    Route::patch('/devices/{device}', [DeviceController::class, 'rename']);
    Route::delete('/devices/{device}', [DeviceController::class, 'destroy']);

    Route::get('/webhooks', [WebhookController::class, 'index']);
    Route::post('/webhooks', [WebhookController::class, 'store']);
    Route::patch('/webhooks/{webhook}/toggle', [WebhookController::class, 'toggle']);
    Route::delete('/webhooks/{webhook}', [WebhookController::class, 'destroy']);

    Route::get('/organisation', [OrganisationController::class, 'show']);
    Route::put('/organisation', [OrganisationController::class, 'update']);

    Route::get('/subscription', [SubscriptionController::class, 'current']);
    Route::post('/subscription', [SubscriptionController::class, 'subscribe']);

});

// ---------- API SMS (authentification par clé API, pas Sanctum) ----------
Route::prefix('v1')->middleware('api.key')->group(function () {
    Route::post('/sms/send', [SmsMessageController::class, 'store']);
    Route::get('/sms/{sms}', [SmsMessageController::class, 'show']);
    Route::get('/sms', [SmsMessageController::class, 'index']);
});

// ---------- App mobile (authentification par device_token) ----------
Route::prefix('device')->group(function () {
    Route::post('/pair', [DevicePairingController::class, 'store']);

    Route::middleware('device.auth')->group(function () {
        Route::get('/jobs/pending', [DeviceJobController::class, 'pending']);
        Route::post('/jobs/{sms}/report', [DeviceJobController::class, 'report']);
        Route::post('/heartbeat', [DeviceJobController::class, 'heartbeat']);
    });
});
