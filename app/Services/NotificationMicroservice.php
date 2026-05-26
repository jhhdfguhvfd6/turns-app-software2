<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotificationMicroservice
{
    private string $baseUrl;
    private string $secret;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.microservices.notification_url', 'http://localhost:3001'), '/');
        $this->secret  = config('services.microservices.secret', '');
    }

    private function headers(): array
    {
        return [
            'x-service-secret' => $this->secret,
            'Accept'           => 'application/json',
        ];
    }

    /**
     * Envía un PIN de acceso al email del usuario.
     */
    public function sendPin(string $email, string $pin, string $userName): bool
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->timeout(10)
                ->post("{$this->baseUrl}/api/notifications/send-pin", [
                    'email'     => $email,
                    'pin'       => $pin,
                    'user_name' => $userName,
                ]);

            if ($response->successful()) {
                Log::info("[NotificationMicroservice] PIN enviado a {$email}");
                return true;
            }

            Log::warning("[NotificationMicroservice] Respuesta no exitosa: " . $response->body());
            return false;
        } catch (\Exception $e) {
            Log::error("[NotificationMicroservice] Error al enviar PIN: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Envía una confirmación de turno creado.
     */
    public function sendTurnConfirmation(string $email, string $userName, string $turnCode, string $serviceName): bool
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->timeout(10)
                ->post("{$this->baseUrl}/api/notifications/send-turn-confirmation", [
                    'email'        => $email,
                    'user_name'    => $userName,
                    'turn_code'    => $turnCode,
                    'service_name' => $serviceName,
                ]);

            if ($response->successful()) {
                Log::info("[NotificationMicroservice] Confirmación de turno {$turnCode} enviada a {$email}");
                return true;
            }

            Log::warning("[NotificationMicroservice] Respuesta no exitosa: " . $response->body());
            return false;
        } catch (\Exception $e) {
            Log::error("[NotificationMicroservice] Error al enviar confirmación de turno: " . $e->getMessage());
            return false;
        }
    }
}
