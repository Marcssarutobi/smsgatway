<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    // GET /api/notifications — liste (paginée) des notifications de l'utilisateur connecté
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'unread_count' => $request->user()->unreadNotifications()->count(),
            'notifications' => $request->user()->notifications()->paginate(20),
        ]);
    }

    // POST /api/notifications/{id}/read
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json(['message' => 'Marqué comme lu']);
    }

    // POST /api/notifications/read-all
    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['message' => 'Toutes les notifications marquées comme lues']);
    }

    // POST /api/notifications/fcm-token — enregistre le token de l'appareil
    // pour recevoir de vraies notifications push (client OU staff)
    public function registerFcmToken(Request $request): JsonResponse
    {
        $request->validate(['fcm_token' => 'required|string']);

        $request->user()->update(['fcm_token' => $request->fcm_token]);

        return response()->json(['message' => 'Token enregistré']);
    }
}
