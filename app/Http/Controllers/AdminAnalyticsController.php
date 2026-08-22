<?php

namespace App\Http\Controllers;

use App\Services\GoogleAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAnalyticsController extends Controller
{
    public function __construct(private GoogleAnalyticsService $analytics) {}

    // GET /api/admin/analytics?days=30
    public function overview(Request $request): JsonResponse
    {
        if (!$this->analytics->isConfigured()) {
            return response()->json([
                'configured' => false,
                'message' => "Google Analytics n'est pas encore configuré côté serveur (voir GOOGLE_ANALYTICS_PROPERTY_ID et GOOGLE_ANALYTICS_CREDENTIALS_PATH dans .env).",
            ], 200);
        }

        $days = (int) $request->query('days', 30);
        $days = max(1, min(90, $days)); // borne raisonnable, évite d'interroger l'API sur une période absurde

        try {
            $data = $this->analytics->getOverview($days);

            return response()->json(['configured' => true, ...$data]);
        } catch (\Throwable $e) {
            return response()->json([
                'configured' => true,
                'error' => true,
                'message' => $e->getMessage(),
            ], 502);
        }
    }
}
