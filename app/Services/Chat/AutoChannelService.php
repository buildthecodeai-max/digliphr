<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Core\Database;

/**
 * Auto-provision system channels and sync membership.
 *
 * Canonical participant identity is always users.id.
 * Org-wide defaults include every active company user (admins without employee rows too).
 *
 * IMPORTANT: never call bootstrapCompany() from syncEmployee()/syncUser() — that caused
 * infinite recursion (syncEmployee → bootstrap → syncAll → syncEmployee…).
 */
final class AutoChannelService
{
    private const ORG_WIDE_SLUGS = [
        'general',
        'company-announcements',
        'hr-announcements',
        'payroll-announcements',
    ];

    private const ANNOUNCEMENT_SLUGS = [
        'company-announcements',
        'hr-announcements',
        'payroll-announcements',
    ];

    private Database $db;
    private ChannelService $channels;

    /** @var array<int,bool> Channels ensured for company this request */
    private static array $channelsReady = [];

    /** @var array<int,bool> Users already synced this request */
    private static array $syncedUsers = [];

    /** Prevent re-entrant syncAll / ensure loops */
    private static bool $busy = false;

    /** @var array<string,int> */
    private array $roleIds = [];

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->channels = new ChannelService();
    }

    /**
     * Ensure system channels exist. Optionally rebuild memberships for all company users.
     * Safe to call repeatedly; guarded against re-entrancy.
     */
    public function bootstrapCompany(int $companyId, ?int $actorUserId = null, bool $syncMembers = false): void
    {
        if ($companyId <= 0) {
            return;
        }

        if (!$syncMembers && isset(self::$channelsReady[$companyId])) {
            return;
        }

        if (self::$busy) {
            return;
        }

        self::$busy = true;
        try {
            $actorUserId = $actorUserId ?: $this->firstAdminUserId($companyId);
            $this->ensureSystemChannels($companyId, $actorUserId);
            self::$channelsReady[$companyId] = true;

            if ($syncMembers) {
                $this->syncAllCompanyUsersUnlocked($companyId);
            }
        } finally {
            self::$busy = false;
        }
    }

    public function ensureSystemChannels(int $companyId, int $actorUserId): void
    {
        if ($companyId <= 0 || $actorUserId <= 0) {
            return;
        }

        $defs = [
            ['name' => 'general', 'slug' => 'general', 'type' => 'public', 'scope' => 'general', 'desc' => 'Company-wide conversation'],
            ['name' => 'company-announcements', 'slug' => 'company-announcements', 'type' => 'announcement', 'scope' => 'company', 'desc' => 'Official company announcements', 'ack' => true],
            ['name' => 'hr-announcements', 'slug' => 'hr-announcements', 'type' => 'announcement', 'scope' => 'hr', 'desc' => 'HR announcements', 'ack' => true],
            ['name' => 'payroll-announcements', 'slug' => 'payroll-announcements', 'type' => 'announcement', 'scope' => 'payroll', 'desc' => 'Payroll notices', 'ack' => false],
            ['name' => 'onboarding', 'slug' => 'onboarding', 'type' => 'private', 'scope' => 'onboarding', 'desc' => 'New hire onboarding'],
        ];

        foreach ($defs as $def) {
            if ($this->channels->findBySlug($companyId, $def['slug'])) {
                continue;
            }
            try {
                $this->channels->create(
                    $companyId,
                    $actorUserId,
                    $def['name'],
                    $def['type'],
                    $def['desc'],
                    $def['scope'],
                    null,
                    null,
                    true,
                    !empty($def['ack'])
                );
            } catch (\Throwable) {
                // Channel may have been created concurrently (unique company+slug)
            }
        }

        $branches = $this->db->fetchAll(
            'SELECT id, name, code FROM branches WHERE company_id = :cid AND deleted_at IS NULL',
            ['cid' => $companyId]
        );
        foreach ($branches as $branch) {
            $slug = 'branch-' . ChatSupport::slugify((string) ($branch['code'] ?: $branch['name']));
            if ($this->channels->findBySlug($companyId, $slug)) {
                continue;
            }
            try {
                $this->channels->create(
                    $companyId,
                    $actorUserId,
                    'branch-' . ($branch['code'] ?: $branch['name']),
                    'private',
                    'Branch channel: ' . $branch['name'],
                    'branch',
                    (int) $branch['id'],
                    null,
                    true,
                    false
                );
            } catch (\Throwable) {
            }
        }

        $departments = $this->db->fetchAll(
            'SELECT id, name, code FROM departments WHERE company_id = :cid AND deleted_at IS NULL',
            ['cid' => $companyId]
        );
        foreach ($departments as $dept) {
            $slug = 'dept-' . ChatSupport::slugify((string) ($dept['code'] ?: $dept['name']));
            if ($this->channels->findBySlug($companyId, $slug)) {
                continue;
            }
            try {
                $this->channels->create(
                    $companyId,
                    $actorUserId,
                    'dept-' . ($dept['code'] ?: $dept['name']),
                    'private',
                    'Department channel: ' . $dept['name'],
                    'department',
                    null,
                    (int) $dept['id'],
                    true,
                    false
                );
            } catch (\Throwable) {
            }
        }

        self::$channelsReady[$companyId] = true;
    }

    /**
     * Sync channel memberships for the current authenticated user (admin or employee).
     * Safe on every chat bootstrap / page load.
     */
    public function syncUser(int $companyId, int $userId, ?array $employee = null): void
    {
        if ($companyId <= 0 || $userId <= 0) {
            return;
        }

        if (isset(self::$syncedUsers[$userId])) {
            return;
        }
        self::$syncedUsers[$userId] = true;

        if (!isset(self::$channelsReady[$companyId]) && !self::$busy) {
            $this->ensureSystemChannels($companyId, $userId);
        }

        if ($employee && (int) ($employee['user_id'] ?? 0) === $userId) {
            $this->applyMemberships($employee);
            return;
        }

        $row = $this->db->fetch(
            'SELECT * FROM employees
             WHERE user_id = :uid AND company_id = :cid AND deleted_at IS NULL
             LIMIT 1',
            ['uid' => $userId, 'cid' => $companyId]
        );
        if ($row) {
            $this->applyMemberships($row);
            return;
        }

        $this->applyAdminOnlyMemberships($companyId, $userId);
    }

    /**
     * Sync one employee's channel memberships.
     * Does NOT call bootstrapCompany / syncAll (re-entrancy safe).
     */
    public function syncEmployee(array $employee): void
    {
        $userId = (int) ($employee['user_id'] ?? 0);
        $companyId = (int) ($employee['company_id'] ?? 0);
        if ($userId <= 0 || $companyId <= 0) {
            return;
        }
        $this->syncUser($companyId, $userId, $employee);
    }

    public function syncAllEmployees(int $companyId): void
    {
        $this->syncAllCompanyUsers($companyId);
    }

    public function syncAllCompanyUsers(int $companyId): void
    {
        if ($companyId <= 0 || self::$busy) {
            return;
        }
        self::$busy = true;
        try {
            if (!isset(self::$channelsReady[$companyId])) {
                $this->ensureSystemChannels($companyId, $this->firstAdminUserId($companyId));
            }
            $this->syncAllCompanyUsersUnlocked($companyId);
        } finally {
            self::$busy = false;
        }
    }

    private function syncAllCompanyUsersUnlocked(int $companyId): void
    {
        $employees = $this->db->fetchAll(
            'SELECT * FROM employees
             WHERE company_id = :cid AND deleted_at IS NULL AND user_id IS NOT NULL
               AND employment_status IN (\'active\', \'probation\', \'notice_period\')',
            ['cid' => $companyId]
        );
        foreach ($employees as $employee) {
            $userId = (int) ($employee['user_id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }
            self::$syncedUsers[$userId] = true;
            $this->applyMemberships($employee);
        }

        // Admins / role-only users without an employee row — still users.id members.
        $admins = $this->db->fetchAll(
            'SELECT DISTINCT u.id
             FROM users u
             INNER JOIN user_roles ur ON ur.user_id = u.id AND (ur.company_id = :cid OR ur.company_id IS NULL)
             WHERE u.deleted_at IS NULL AND u.is_active = 1
               AND NOT EXISTS (
                 SELECT 1 FROM employees e
                 WHERE e.user_id = u.id AND e.company_id = :cid2 AND e.deleted_at IS NULL
               )',
            ['cid' => $companyId, 'cid2' => $companyId]
        );
        foreach ($admins as $admin) {
            $userId = (int) $admin['id'];
            if ($userId <= 0 || isset(self::$syncedUsers[$userId])) {
                continue;
            }
            self::$syncedUsers[$userId] = true;
            $this->applyAdminOnlyMemberships($companyId, $userId);
        }
    }

    private function applyMemberships(array $employee): void
    {
        $userId = (int) ($employee['user_id'] ?? 0);
        $companyId = (int) ($employee['company_id'] ?? 0);
        if ($userId <= 0 || $companyId <= 0) {
            return;
        }

        $isCompanyAdmin = $this->isCompanyScopeAdmin($companyId, $userId);
        $this->addOrgWideMemberships($companyId, $userId, $isCompanyAdmin);

        $joining = $employee['joining_date'] ?? null;
        if ($joining && strtotime((string) $joining) >= strtotime('-90 days')) {
            $onboarding = $this->channels->findBySlug($companyId, 'onboarding');
            if ($onboarding) {
                $this->channels->addMember((int) $onboarding['id'], $userId, $this->roleId('member'));
            }
        }

        $branchId = !empty($employee['branch_id']) ? (int) $employee['branch_id'] : null;
        $deptId = !empty($employee['department_id']) ? (int) $employee['department_id'] : null;

        if ($isCompanyAdmin) {
            $this->addAllScopedChannels($companyId, $userId, $this->roleId('admin'));
            return;
        }

        $this->pruneScopedMemberships($companyId, $userId, $branchId, $deptId);

        if ($branchId) {
            $branchCh = $this->db->fetch(
                'SELECT id FROM chat_channels
                 WHERE company_id = :cid AND scope = \'branch\' AND branch_id = :bid AND deleted_at IS NULL
                 LIMIT 1',
                ['cid' => $companyId, 'bid' => $branchId]
            );
            if ($branchCh) {
                $this->channels->addMember((int) $branchCh['id'], $userId, $this->roleId('member'));
            }
        }

        if ($deptId) {
            $deptCh = $this->db->fetch(
                'SELECT id FROM chat_channels
                 WHERE company_id = :cid AND scope = \'department\' AND department_id = :did AND deleted_at IS NULL
                 LIMIT 1',
                ['cid' => $companyId, 'did' => $deptId]
            );
            if ($deptCh) {
                $this->channels->addMember((int) $deptCh['id'], $userId, $this->roleId('member'));
            }
        }
    }

    private function applyAdminOnlyMemberships(int $companyId, int $userId): void
    {
        $isCompanyAdmin = $this->isCompanyScopeAdmin($companyId, $userId);
        // Admins without employee rows still join org-wide defaults (users.id).
        $this->addOrgWideMemberships($companyId, $userId, $isCompanyAdmin);

        // Company-scope admins see every branch/dept channel.
        if ($isCompanyAdmin) {
            $this->addAllScopedChannels($companyId, $userId, $this->roleId('admin'));
        }
    }

    private function addOrgWideMemberships(int $companyId, int $userId, bool $canModerate): void
    {
        foreach (self::ORG_WIDE_SLUGS as $slug) {
            $ch = $this->channels->findBySlug($companyId, $slug);
            if (!$ch) {
                continue;
            }
            $isAnnouncement = in_array($slug, self::ANNOUNCEMENT_SLUGS, true)
                || ($ch['channel_type'] ?? '') === 'announcement';
            if ($canModerate) {
                $roleId = $this->roleId('admin');
            } elseif ($isAnnouncement) {
                $roleId = $this->roleId('readonly');
            } else {
                $roleId = $this->roleId('member');
            }
            $this->channels->addMember((int) $ch['id'], $userId, $roleId);
        }
    }

    private function addAllScopedChannels(int $companyId, int $userId, int $roleId): void
    {
        $rows = $this->db->fetchAll(
            'SELECT id FROM chat_channels
             WHERE company_id = :cid AND scope IN (\'branch\', \'department\') AND deleted_at IS NULL',
            ['cid' => $companyId]
        );
        foreach ($rows as $ch) {
            $this->channels->addMember((int) $ch['id'], $userId, $roleId);
        }
    }

    private function pruneScopedMemberships(int $companyId, int $userId, ?int $branchId, ?int $deptId): void
    {
        $scoped = $this->db->fetchAll(
            'SELECT c.id, c.branch_id, c.department_id, c.scope
             FROM chat_channels c
             INNER JOIN chat_channel_members m ON m.channel_id = c.id AND m.user_id = :uid AND m.left_at IS NULL
             WHERE c.company_id = :cid AND c.scope IN (\'branch\', \'department\') AND c.deleted_at IS NULL',
            ['uid' => $userId, 'cid' => $companyId]
        );

        foreach ($scoped as $ch) {
            $keep = false;
            if ($ch['scope'] === 'branch' && $branchId && (int) $ch['branch_id'] === $branchId) {
                $keep = true;
            }
            if ($ch['scope'] === 'department' && $deptId && (int) $ch['department_id'] === $deptId) {
                $keep = true;
            }
            if (!$keep) {
                $this->channels->removeMember((int) $ch['id'], $userId);
            }
        }
    }

    private function isCompanyScopeAdmin(int $companyId, int $userId): bool
    {
        $row = $this->db->fetch(
            'SELECT r.id FROM user_roles ur
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE ur.user_id = :uid AND (ur.company_id = :cid OR ur.company_id IS NULL)
               AND r.slug IN (\'super_admin\', \'company_admin\', \'hr_manager\')
             LIMIT 1',
            ['uid' => $userId, 'cid' => $companyId]
        );
        return (bool) $row;
    }

    private function roleId(string $slug): int
    {
        if (isset($this->roleIds[$slug])) {
            return $this->roleIds[$slug];
        }

        $row = $this->db->fetch('SELECT id FROM chat_roles WHERE slug = :slug LIMIT 1', ['slug' => $slug]);
        if ($row) {
            return $this->roleIds[$slug] = (int) $row['id'];
        }

        // Fallbacks if a deploy has guest instead of readonly, etc.
        $fallbacks = [
            'readonly' => ['guest', 'member'],
            'admin' => ['owner', 'member'],
            'owner' => ['admin', 'member'],
            'member' => ['guest'],
        ];
        foreach ($fallbacks[$slug] ?? [] as $alt) {
            $altRow = $this->db->fetch('SELECT id FROM chat_roles WHERE slug = :slug LIMIT 1', ['slug' => $alt]);
            if ($altRow) {
                return $this->roleIds[$slug] = (int) $altRow['id'];
            }
        }

        throw new \RuntimeException('Required chat role is missing: ' . $slug);
    }

    private function firstAdminUserId(int $companyId): int
    {
        $row = $this->db->fetch(
            'SELECT u.id FROM users u
             INNER JOIN user_roles ur ON ur.user_id = u.id
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE u.deleted_at IS NULL AND u.is_active = 1
               AND r.slug IN (\'super_admin\', \'company_admin\', \'hr_manager\')
               AND (ur.company_id = :cid OR ur.company_id IS NULL)
             ORDER BY FIELD(r.slug, \'super_admin\', \'company_admin\', \'hr_manager\'), u.id
             LIMIT 1',
            ['cid' => $companyId]
        );
        if ($row) {
            return (int) $row['id'];
        }
        $companyUser = $this->db->fetch(
            'SELECT u.id FROM users u
             INNER JOIN employees e ON e.user_id = u.id
             WHERE e.company_id = :cid AND e.deleted_at IS NULL
               AND u.deleted_at IS NULL AND u.is_active = 1
             ORDER BY u.id LIMIT 1',
            ['cid' => $companyId]
        );
        if ($companyUser) {
            return (int) $companyUser['id'];
        }

        throw new \RuntimeException('No active user is available for this company.');
    }
}
