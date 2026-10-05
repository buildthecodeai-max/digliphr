<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class SecretService
{
    public static function encrypt(string $value): string
    {
        if ($value === '') return '';
        $key = self::key(); $iv = random_bytes(12); $tag = '';
        $cipher = openssl_encrypt($value, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) throw new RuntimeException('Could not protect the secret setting.');
        return 'enc:v1:' . base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(string $value): string
    {
        if (!str_starts_with($value, 'enc:v1:')) return $value;
        $raw = base64_decode(substr($value, 7), true);
        if ($raw === false || strlen($raw) < 29) return '';
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        return $plain === false ? '' : $plain;
    }

    private static function key(): string
    {
        $key = (string) config('app.key', '');
        if ($key === '') throw new RuntimeException('APP_KEY must be configured before saving SSO credentials.');
        return hash('sha256', $key, true);
    }
}
