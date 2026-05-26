<?php

namespace App\Helpers;

use App\Models\AuditLog;
use App\Services\AuditMicroservice;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditHelper
{
    public static function log(string $action, string $description = '')
    {
        $user = Auth::user();

        $payload = [
            'user_id'    => $user ? (string) $user->id : null,
            'user_name'  => $user ? $user->name : 'Sistema',
            'user_email' => $user ? $user->email : null,
            'action'     => $action,
            'description'=> $description,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'created_at' => now(),
        ];

        // Persiste localmente (monolito)
        AuditLog::create($payload);

        // Replica al microservicio de auditoría de forma asíncrona (sin bloquear)
        try {
            app(AuditMicroservice::class)->log(
                action:      $action,
                description: $description,
                userId:      $payload['user_id'],
                userName:    $payload['user_name'],
                userEmail:   $payload['user_email'],
                ipAddress:   $payload['ip_address'],
                userAgent:   $payload['user_agent'],
            );
        } catch (\Throwable) {
            // El microservicio de auditoría es secundario; si falla no interrumpe el flujo
        }
    }
}
