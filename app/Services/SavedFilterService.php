<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Exceptions\HttpException;

final class SavedFilterService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function forUser(int $companyId, int $userId, string $module): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM saved_filters
             WHERE company_id = :cid AND user_id = :uid AND module = :module
             ORDER BY is_default DESC, name',
            ['cid' => $companyId, 'uid' => $userId, 'module' => $module]
        );
    }

    public function save(int $companyId, int $userId, string $module, string $name, array $filters, bool $default): int
    {
        $allowedModules = ['employees', 'attendance', 'leave', 'payroll', 'reports', 'approvals'];
        if (!in_array($module, $allowedModules, true)) {
            throw new HttpException('Unsupported filter module.', 422);
        }
        $filters = array_filter($filters, static fn (mixed $value): bool => $value !== '' && $value !== null && !is_array($value));
        unset($filters['_csrf'], $filters['page'], $filters['saved_filter_id']);
        if ($default) {
            $this->db->update('saved_filters', ['is_default' => 0], 'company_id = :cid AND user_id = :uid AND module = :module', ['cid' => $companyId, 'uid' => $userId, 'module' => $module]);
        }
        $existing = $this->db->fetch(
            'SELECT id FROM saved_filters WHERE user_id = :uid AND module = :module AND name = :name',
            ['uid' => $userId, 'module' => $module, 'name' => $name]
        );
        $payload = ['company_id' => $companyId, 'filters' => json_encode($filters, JSON_UNESCAPED_UNICODE), 'is_default' => $default ? 1 : 0];
        if ($existing) {
            $this->db->update('saved_filters', $payload, 'id = :id', ['id' => $existing['id']]);
            return (int) $existing['id'];
        }
        return $this->db->insert('saved_filters', $payload + ['user_id' => $userId, 'module' => $module, 'name' => $name]);
    }

    public function delete(int $id, int $companyId, int $userId): void
    {
        $this->db->delete('saved_filters', 'id = :id AND company_id = :cid AND user_id = :uid', ['id' => $id, 'cid' => $companyId, 'uid' => $userId]);
    }
}
