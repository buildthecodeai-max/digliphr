<?php

declare(strict_types=1);

namespace App\Core;

class Logger
{
    private string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? (dirname(__DIR__, 2) . '/storage/logs/app-' . date('Y-m-d') . '.log');
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->write('WARNING', $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->write('DEBUG', $message, $context);
    }

    private function write(string $level, string $message, array $context): void
    {
        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $line = sprintf(
            "[%s] %s: %s %s\n",
            date('Y-m-d H:i:s'),
            $level,
            $message,
            $context ? json_encode($context, JSON_UNESCAPED_UNICODE) : ''
        );

        file_put_contents($this->path, $line, FILE_APPEND | LOCK_EX);
    }
}
