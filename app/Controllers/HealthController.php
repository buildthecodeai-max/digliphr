<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Application;

final class HealthController extends Controller
{
    public function check(): array
    {
        $database = 'ok';
        try {
            Database::getInstance()->fetchColumn('SELECT 1');
        } catch (\Throwable) {
            $database = 'failed';
        }

        $storage = is_writable((string) Application::getInstance()->config('app.paths.storage')) ? 'ok' : 'failed';
        $healthy = $database === 'ok' && $storage === 'ok';

        $payload = [
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => ['database' => $database, 'storage' => $storage],
            'request_id' => \App\Services\ObservabilityService::requestId(),
        ];
        $this->response->json($payload, $healthy ? 200 : 503);
    }
}
