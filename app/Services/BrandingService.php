<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class BrandingService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function publicBrand(?string $companyCode = null): array
    {
        $company = $companyCode
            ? $this->db->fetch('SELECT * FROM companies WHERE code=:code AND is_active=1 AND deleted_at IS NULL', ['code' => $companyCode])
            : null;
        if (!$company) $company = $this->db->fetch('SELECT * FROM companies WHERE is_active=1 AND deleted_at IS NULL ORDER BY id LIMIT 1');
        if (!$company) return ['company' => null, 'settings' => [], 'sso_enabled' => false];
        $settings = $this->settings((int) $company['id']);
        return ['company' => $company, 'settings' => $settings, 'sso_enabled' => ($settings['sso_enabled'] ?? '0') === '1' && !empty($settings['sso_authorize_url']) && !empty($settings['sso_client_id'])];
    }

    public function settings(int $companyId): array
    {
        $rows = $this->db->fetchAll('SELECT setting_key,setting_value FROM system_settings WHERE company_id=:cid', ['cid' => $companyId]);
        $settings = [];
        foreach ($rows as $row) $settings[$row['setting_key']] = $row['setting_value'];
        if (!empty($settings['sso_client_secret'])) {
            $settings['sso_client_secret'] = SecretService::decrypt((string) $settings['sso_client_secret']);
        }
        return $settings;
    }
}
