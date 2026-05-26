<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AuditMicroservice
{
    private string $baseUrl;
    private string $secret;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.microservices.audit_url', 'http://localhost:3002'), '/');
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
     * Registra una entrada de auditoría en el microservicio.
     */
    public function log(
        string  $action,
        string  $description = '',
        ?string $userId      = null,
        ?string $userName    = null,
        ?string $userEmail   = null,
        ?string $ipAddress   = null,
        ?string $userAgent   = null
    ): bool {
        try {
            $response = Http::withHeaders($this->headers())
                ->timeout(5)
                ->post("{$this->baseUrl}/api/audit/logs", [
                    'action'      => $action,
                    'description' => $description,
                    'user_id'     => $userId,
                    'user_name'   => $userName ?? 'Sistema',
                    'user_email'  => $userEmail,
                    'ip_address'  => $ipAddress,
                    'user_agent'  => $userAgent,
                ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error("[AuditMicroservice] Error al registrar log: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene logs de auditoría con filtros y paginación.
     */
    public function getLogs(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->timeout(10)
                ->get("{$this->baseUrl}/api/audit/logs", array_merge($filters, [
                    'page'     => $page,
                    'per_page' => $perPage,
                ]));

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning("[AuditMicroservice] Error al obtener logs: " . $response->body());
            return [];
        } catch (\Exception $e) {
            Log::error("[AuditMicroservice] Error al obtener logs: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene estadísticas para las gráficas del panel admin.
     */
    public function getStats(): array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->timeout(10)
                ->get("{$this->baseUrl}/api/audit/stats");

            if ($response->successful()) {
                return $response->json('data', []);
            }

            Log::warning("[AuditMicroservice] Error al obtener stats: " . $response->body());
            return [];
        } catch (\Exception $e) {
            Log::error("[AuditMicroservice] Error al obtener stats: " . $e->getMessage());
            return [];
        }
    }
}
