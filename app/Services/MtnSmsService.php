<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Intégration de l'API MTN SMS v3 (https://developers.mtn.com/products/sms-v3-api).
 *
 * Flux : on récupère un token OAuth2 (client_credentials), mis en cache, puis
 * on appelle POST /v3/sms/messages/sms/outbound avec le numéro du client
 * normalisé au format attendu par MTN (E.164 sans "+").
 */
class MtnSmsService
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $tokenUrl,
        private readonly ?string $clientId,
        private readonly ?string $clientSecret,
        private readonly ?string $serviceCode,
        private readonly ?string $senderAddress,
        private readonly string $countryCode,
    ) {
    }

    /**
     * Identifiants de la plateforme (client_id/secret/service_code) — les
     * mêmes pour tous les clients, un seul compte MTN Developer approuvé.
     * senderAddress/countryCode restent nuls ici : ce sont des réglages
     * propres à chaque client, voir forOrganisation() ci-dessous.
     */
    public static function fromConfig(): self
    {
        return new self(
            baseUrl: rtrim((string) config('services.mtn.base_url'), '/'),
            tokenUrl: (string) config('services.mtn.token_url'),
            clientId: config('services.mtn.client_id'),
            clientSecret: config('services.mtn.client_secret'),
            serviceCode: config('services.mtn.service_code'),
            senderAddress: null,
            countryCode: (string) config('services.mtn.country_code', '229'),
        );
    }

    /**
     * Même identifiants plateforme que fromConfig(), mais avec le
     * senderAddress et le countryCode propres à l'organisation du client
     * (configurés depuis son dashboard — voir OrganisationController) plutôt
     * que des valeurs globales dans .env. C'est la méthode à utiliser pour
     * envoyer un SMS au nom d'un client précis (voir DispatchSmsJob).
     */
    public static function forOrganisation(?\App\Models\Organisation $organisation): self
    {
        return new self(
            baseUrl: rtrim((string) config('services.mtn.base_url'), '/'),
            tokenUrl: (string) config('services.mtn.token_url'),
            clientId: config('services.mtn.client_id'),
            clientSecret: config('services.mtn.client_secret'),
            serviceCode: config('services.mtn.service_code'),
            senderAddress: $organisation?->mtn_sender_address,
            countryCode: $organisation?->mtn_country_code ?? (string) config('services.mtn.country_code', '229'),
        );
    }

    /**
     * Normalise un numéro de téléphone saisi par le client (formats variés :
     * "0197xxxxxx", "+229 97 xx xx xx", "22997xxxxxx", "00229 97xxxxxx",
     * avec espaces/tirets/points...) vers le format attendu par MTN pour
     * `receiverAddress` dans le swagger officiel : uniquement des chiffres,
     * préfixés par l'indicatif pays, sans "+", "00", espace ni séparateur
     * (ex. officiel du swagger : "23423456789").
     *
     * IMPORTANT (Bénin) : depuis la réforme du 30/11/2024, tous les numéros
     * béninois commencent par "01" (ex: 0197xxxxxx). Ce "01" fait partie du
     * numéro lui-même (contrairement à un trunk prefix européen) : on ne le
     * retire JAMAIS, on ajoute seulement l'indicatif pays devant.
     */
    public function normalizePhoneNumber(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if ($digits === '') {
            throw new InvalidArgumentException("Numéro de téléphone vide ou invalide : \"{$raw}\"");
        }

        // "00229971234567" (préfixe international composé "00") -> "229971234567"
        if (str_starts_with($digits, '00' . $this->countryCode)) {
            $digits = substr($digits, 2);
        }

        // Déjà préfixé par l'indicatif pays (ex: client a saisi "+229 97...") -> inchangé
        if (str_starts_with($digits, $this->countryCode)) {
            return $digits;
        }

        // Numéro local (ex: "0197123456" ou, avant la réforme, "97123456") -> on préfixe l'indicatif pays
        return $this->countryCode . $digits;
    }

    /**
     * Construit le corps de requête attendu par
     * POST /v3/sms/messages/sms/outbound (voir swagger officiel :
     * receiverAddress, message, serviceCode, clientCorrelatorId sont requis ;
     * senderAddress est optionnel mais prend le pas sur serviceCode pour
     * l'affichage de l'expéditeur si les deux sont fournis).
     */
    public function buildOutboundPayload(string $recipient, string $message, ?string $clientCorrelatorId = null): array
    {
        $payload = [
            'receiverAddress' => [$this->normalizePhoneNumber($recipient)],
            'message' => $message,
            'serviceCode' => $this->serviceCode,
            'clientCorrelatorId' => $clientCorrelatorId ?? (string) Str::uuid(),
            'requestDeliveryReceipt' => false,
        ];

        if ($this->senderAddress) {
            $payload['senderAddress'] = $this->senderAddress;
        }

        return $payload;
    }

    private function fetchAccessToken(): string
    {
        // Format exact documenté par MTN (developers.mtn.com/getting-started/
        // understanding-oauth-20) : grant_type est un paramètre de l'URL
        // (query string), PAS un champ du corps du formulaire — contrairement
        // à ce qu'on pourrait attendre d'un flux OAuth2 client_credentials
        // "standard". Mélanger les deux (comme avant) provoque un 400 côté MTN.
        $response = Http::asForm()->post($this->tokenUrl . '?grant_type=client_credentials', [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        ]);

        if (!$response->ok() || !$response->json('access_token')) {
            throw new RuntimeException('Impossible d\'obtenir un token MTN : [' . $response->status() . '] ' . $response->body());
        }

        return $response->json('access_token');
    }

    private function getAccessToken(bool $forceRefresh = false): string
    {
        if ($forceRefresh) {
            Cache::forget('mtn_sms_access_token');
        }

        // 3300s (55 min) : marge de sécurité sous la durée de vie habituelle
        // d'1h d'un token OAuth2 client_credentials, pour ne jamais présenter
        // un token expiré à MTN.
        return Cache::remember('mtn_sms_access_token', 3300, fn () => $this->fetchAccessToken());
    }

    /**
     * Envoie un SMS via l'API MTN SMS v3. Retourne le tableau décodé de la
     * réponse MTN (statusCode, statusMessage, transactionId, data.status).
     *
     * @throws RuntimeException en cas d'échec (token, réseau, ou refus MTN)
     * @throws InvalidArgumentException si le numéro de téléphone est invalide
     */
    public function send(string $recipient, string $message, ?string $clientCorrelatorId = null): array
    {
        $payload = $this->buildOutboundPayload($recipient, $message, $clientCorrelatorId);
        $endpoint = "{$this->baseUrl}/v3/sms/messages/sms/outbound";

        $response = Http::withToken($this->getAccessToken())->post($endpoint, $payload);

        if ($response->status() === 401) {
            // Le cache du token peut être désynchronisé de la durée de vie
            // réelle côté MTN : on force un renouvellement et on retente une
            // seule fois avant d'abandonner.
            $response = Http::withToken($this->getAccessToken(forceRefresh: true))->post($endpoint, $payload);
        }

        if (!$response->successful()) {
            throw new RuntimeException(
                "Échec de l'envoi MTN [{$response->status()}] : " . $response->body()
            );
        }

        return $response->json();
    }
}
