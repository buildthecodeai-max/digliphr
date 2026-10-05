<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use Throwable;

final class ObservabilityService
{
    private static ?string $requestId = null;
    private static float $startedAt = 0.0;

    public static function startRequest(): string
    {
        self::$startedAt = microtime(true);
        self::$requestId = bin2hex(random_bytes(16));
        header('X-Request-ID: ' . self::$requestId);
        return self::$requestId;
    }

    public static function requestId(): ?string
    {
        return self::$requestId;
    }

    public static function event(string $name, array $context = []): void
    {
        (new Logger(dirname(__DIR__, 2) . '/storage/logs/observability-' . date('Y-m-d') . '.log'))
            ->info($name, self::context($context));
    }

    public static function exception(Throwable $exception, array $context = []): void
    {
        (new Logger(dirname(__DIR__, 2) . '/storage/logs/observability-' . date('Y-m-d') . '.log'))
            ->error($exception->getMessage(), self::context(array_merge($context, [
                'exception' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ])));
    }

    public static function finishRequest(): void
    {
        self::event('request.completed', [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
            'duration_ms' => self::$startedAt > 0 ? round((microtime(true) - self::$startedAt) * 1000, 2) : null,
            'memory_bytes' => memory_get_peak_usage(true),
        ]);
    }

    private static function context(array $context): array
    {
        return array_merge([
            'request_id' => self::$requestId,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ], $context);
    }
}
