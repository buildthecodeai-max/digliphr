<?php

declare(strict_types=1);

namespace App\Services;

final class ClientIpService
{
    public function resolve(array $server): string
    {
        $remote = $this->validIp((string) ($server['REMOTE_ADDR'] ?? '')) ?? '0.0.0.0';
        $trusted = (array) config('app.trusted_proxies', []);
        if (!$this->matchesAny($remote, $trusted)) {
            return $remote;
        }

        $cloudflare = $this->validIp((string) ($server['HTTP_CF_CONNECTING_IP'] ?? ''));
        if ($cloudflare !== null) {
            return $cloudflare;
        }

        $forwarded = array_filter(array_map('trim', explode(',', (string) ($server['HTTP_X_FORWARDED_FOR'] ?? ''))));
        $chain = array_values(array_filter(array_map(fn (string $ip): ?string => $this->validIp($ip), $forwarded)));
        $chain[] = $remote;
        for ($i = count($chain) - 1; $i >= 0; $i--) {
            if (!$this->matchesAny($chain[$i], $trusted)) {
                return $chain[$i];
            }
        }

        return $remote;
    }

    public function matchesAny(string $ip, array $rules): bool
    {
        if ($this->validIp($ip) === null) {
            return false;
        }
        foreach ($rules as $rule) {
            $rule = trim((string) $rule);
            if ($rule !== '' && $this->matches($ip, $rule)) {
                return true;
            }
        }
        return false;
    }

    public function parseRules(string $value): array
    {
        return array_values(array_unique(array_filter(array_map('trim', preg_split('/[\s,;]+/', $value) ?: []))));
    }

    public function isValidRule(string $rule): bool
    {
        $rule = trim($rule);
        if ($rule === '') return false;
        if (!str_contains($rule, '/')) return $this->validIp($rule) !== null;
        [$network, $prefix] = array_pad(explode('/', $rule, 2), 2, '');
        $binary = @inet_pton($network);
        return $binary !== false && ctype_digit($prefix) && (int) $prefix >= 0 && (int) $prefix <= strlen($binary) * 8;
    }

    private function matches(string $ip, string $rule): bool
    {
        if (!str_contains($rule, '/')) {
            return hash_equals(strtolower($rule), strtolower($ip));
        }
        [$network, $prefix] = array_pad(explode('/', $rule, 2), 2, '');
        $ipBinary = @inet_pton($ip);
        $networkBinary = @inet_pton($network);
        if ($ipBinary === false || $networkBinary === false || strlen($ipBinary) !== strlen($networkBinary) || !ctype_digit($prefix)) {
            return false;
        }
        $bits = (int) $prefix;
        $max = strlen($ipBinary) * 8;
        if ($bits < 0 || $bits > $max) {
            return false;
        }
        $bytes = intdiv($bits, 8);
        $remaining = $bits % 8;
        if ($bytes > 0 && substr($ipBinary, 0, $bytes) !== substr($networkBinary, 0, $bytes)) {
            return false;
        }
        if ($remaining === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $remaining)) & 0xFF;
        return (ord($ipBinary[$bytes]) & $mask) === (ord($networkBinary[$bytes]) & $mask);
    }

    private function validIp(string $ip): ?string
    {
        $ip = trim($ip);
        return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : null;
    }
}
