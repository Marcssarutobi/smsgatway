<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Intégration Google Analytics Data API (GA4) via un compte de service.
 *
 * Volontairement fait en appels HTTP bruts + JWT signé (au lieu du SDK
 * officiel google/analytics-data, qui embarque une arborescence de
 * dépendances énorme) — cohérent avec le reste du projet, qui appelle déjà
 * les API Google directement (voir GoogleAuthController::mobileLogin).
 *
 * Prérequis pour que ce service fonctionne :
 * 1. Créer un compte de service dans Google Cloud Console, télécharger sa
 *    clé JSON, la déposer sur le serveur (JAMAIS commitée dans git) au
 *    chemin défini par GOOGLE_ANALYTICS_CREDENTIALS_PATH.
 * 2. Dans Google Analytics (GA4) > Paramètres du compte > Gestion des accès,
 *    ajouter l'email de ce compte de service en tant que lecteur ("Viewer").
 * 3. Renseigner GOOGLE_ANALYTICS_PROPERTY_ID (visible dans GA4 > Paramètres
 *    de la propriété, format numérique, ex: 123456789).
 */
class GoogleAnalyticsService
{
    private const TOKEN_CACHE_KEY = 'google_analytics_access_token';
    private const SCOPE = 'https://www.googleapis.com/auth/analytics.readonly';

    private ?string $propertyId;
    private ?string $credentialsPath;

    public function __construct()
    {
        $this->propertyId = config('services.google_analytics.property_id');
        $this->credentialsPath = config('services.google_analytics.credentials_path');
    }

    public function isConfigured(): bool
    {
        return $this->propertyId && $this->credentialsPath && file_exists($this->credentialsPath);
    }

    /**
     * Retourne les stats combinées pour le dashboard : totaux sur la période,
     * répartition par pays, et série temporelle jour par jour.
     *
     * Mise en cache 15 minutes : l'API GA4 a un quota de requêtes limité, et
     * ces chiffres n'ont de toute façon pas besoin d'être à la seconde près.
     */
    public function getOverview(int $days = 30): array
    {
        return Cache::remember("ga_overview_{$days}", now()->addMinutes(15), function () use ($days) {
            return [
                'totals' => $this->fetchTotals($days),
                'by_country' => $this->fetchByCountry($days),
                'by_date' => $this->fetchByDate($days),
            ];
        });
    }

    private function fetchTotals(int $days): array
    {
        $report = $this->runReport([
            'dateRanges' => [['startDate' => "{$days}daysAgo", 'endDate' => 'today']],
            'metrics' => [
                ['name' => 'activeUsers'],
                ['name' => 'sessions'],
                ['name' => 'screenPageViews'],
                ['name' => 'eventCount'],
            ],
        ]);

        $row = $report['rows'][0]['metricValues'] ?? null;

        return [
            'active_users' => (int) ($row[0]['value'] ?? 0),
            'sessions' => (int) ($row[1]['value'] ?? 0),
            'page_views' => (int) ($row[2]['value'] ?? 0),
            'clicks' => (int) ($row[3]['value'] ?? 0), // eventCount englobe les clics trackés (click, etc.)
        ];
    }

    private function fetchByCountry(int $days): array
    {
        $report = $this->runReport([
            'dateRanges' => [['startDate' => "{$days}daysAgo", 'endDate' => 'today']],
            'dimensions' => [['name' => 'country']],
            'metrics' => [['name' => 'activeUsers']],
            'orderBys' => [['metric' => ['metricName' => 'activeUsers'], 'desc' => true]],
            'limit' => 15,
        ]);

        return collect($report['rows'] ?? [])->map(fn ($row) => [
            'country' => $row['dimensionValues'][0]['value'] ?? 'Inconnu',
            'active_users' => (int) ($row['metricValues'][0]['value'] ?? 0),
        ])->all();
    }

    private function fetchByDate(int $days): array
    {
        $report = $this->runReport([
            'dateRanges' => [['startDate' => "{$days}daysAgo", 'endDate' => 'today']],
            'dimensions' => [['name' => 'date']],
            'metrics' => [['name' => 'sessions'], ['name' => 'activeUsers']],
            'orderBys' => [['dimension' => ['dimensionName' => 'date']]],
        ]);

        return collect($report['rows'] ?? [])->map(fn ($row) => [
            'date' => $row['dimensionValues'][0]['value'] ?? '', // format YYYYMMDD
            'sessions' => (int) ($row['metricValues'][0]['value'] ?? 0),
            'active_users' => (int) ($row['metricValues'][1]['value'] ?? 0),
        ])->all();
    }

    private function runReport(array $requestBody): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException("Google Analytics n'est pas configuré (GOOGLE_ANALYTICS_PROPERTY_ID / GOOGLE_ANALYTICS_CREDENTIALS_PATH manquants).");
        }

        $response = Http::withToken($this->getAccessToken())
            ->post("https://analyticsdata.googleapis.com/v1beta/properties/{$this->propertyId}:runReport", $requestBody);

        if (!$response->successful()) {
            Log::warning('Échec appel Google Analytics Data API', ['response' => $response->json()]);
            throw new \RuntimeException('Impossible de récupérer les statistiques Google Analytics.');
        }

        return $response->json();
    }

    // Récupère un access_token OAuth2 (durée de vie 1h), mis en cache pour
    // éviter de re-signer un JWT et de re-contacter Google à chaque requête.
    private function getAccessToken(): string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, now()->addMinutes(55), function () {
            $credentials = json_decode(file_get_contents($this->credentialsPath), true);

            $now = time();
            $assertion = JWT::encode([
                'iss' => $credentials['client_email'],
                'scope' => self::SCOPE,
                'aud' => 'https://oauth2.googleapis.com/token',
                'exp' => $now + 3600,
                'iat' => $now,
            ], $credentials['private_key'], 'RS256');

            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]);

            if (!$response->successful()) {
                Log::error('Échec authentification compte de service Google Analytics', ['response' => $response->json()]);
                throw new \RuntimeException("Authentification Google Analytics échouée. Vérifiez le fichier de clé du compte de service.");
            }

            return $response->json('access_token');
        });
    }
}
