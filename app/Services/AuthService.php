<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Session;
use App\Models\User;

class AuthService
{
    private Database $db;
    private User $users;
    private static ?array $userCache = null;
    private static ?array $employeeCache = null;
    private static ?array $permissionsCache = null;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->users = new User();
    }

    public function attempt(string $email, string $password, bool $remember = false): array
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if ($this->isLockedOut($email, $ip)) {
            return ['success' => false, 'message' => 'Too many failed login attempts. Please try again later.'];
        }

        $user = $this->users->findByEmail($email);

        if (!$user || !password_verify($password, $user['password'])) {
            $this->recordLoginAttempt($email, $ip, false);
            return ['success' => false, 'message' => 'Invalid email or password.'];
        }

        if (!(int) ($user['is_active'] ?? 0)) {
            return ['success' => false, 'message' => 'Your account is inactive. Contact HR.'];
        }

        $this->recordLoginAttempt($email, $ip, true);
        $this->login($user, $remember);

        return ['success' => true, 'message' => 'Login successful.', 'user' => $this->user()];
    }

    public function login(array $user, bool $remember = false): void
    {
        Session::regenerate();
        Session::set('user_id', (int) $user['id']);
        Session::set('login_at', time());
        Session::set('_password_changed_at', $user['password_changed_at'] ?? null);

        $this->db->update('users', [
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_login_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ], 'id = :id', ['id' => $user['id']]);

        if ($remember) {
            // The schema requires a unique selector for each persistent-login
            // record. Keep the existing token hash lookup behavior while also
            // populating the selector column required by MySQL strict mode.
            $selector = bin2hex(random_bytes(16));
            $token = bin2hex(random_bytes(32));
            $hash = hash('sha256', $token);
            $days = (int) config('app.remember_me_days', 30);
            $expires = date('Y-m-d H:i:s', time() + ($days * 86400));

            $this->db->insert('remember_tokens', [
                'user_id' => $user['id'],
                'selector' => $selector,
                'token_hash' => $hash,
                'expires_at' => $expires,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            setcookie('remember_token', $token, [
                'expires' => time() + ($days * 86400),
                'path' => '/',
                'secure' => (bool) config('app.session_secure', false),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        (new AuditService())->log('login', 'auth', (int) $user['id'], null, ['email' => $user['email']]);
        self::$userCache = null;
        self::$employeeCache = null;
        self::$permissionsCache = null;
    }

    public function logout(): void
    {
        $userId = Session::get('user_id');
        if ($userId) {
            (new AuditService())->log('logout', 'auth', (int) $userId);
            $this->db->delete('remember_tokens', 'user_id = :id', ['id' => $userId]);
        }

        setcookie('remember_token', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => (bool) config('app.session_secure', false),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        Session::destroy();
        self::$userCache = null;
        self::$employeeCache = null;
        self::$permissionsCache = null;
    }

    public function check(): bool
    {
        if (Session::has('user_id')) {
            return $this->user() !== null;
        }

        return $this->attemptRemember();
    }

    public function user(): ?array
    {
        if (self::$userCache !== null) {
            return self::$userCache;
        }

        $userId = Session::get('user_id');
        if (!$userId) {
            return null;
        }

        $user = $this->users->findWithRoles((int) $userId);
        if (!$user || !(int) ($user['is_active'] ?? 0)) {
            return null;
        }

        if ($this->passwordChangedSinceSessionStarted($user)) {
            $this->clearAuthenticatedSession();
            return null;
        }

        self::$userCache = $user;
        return $user;
    }

    public function id(): ?int
    {
        $user = $this->user();
        return $user ? (int) $user['id'] : null;
    }

    public function employee(): ?array
    {
        if (self::$employeeCache !== null) {
            return self::$employeeCache;
        }

        $user = $this->user();
        if (!$user) {
            return null;
        }

        $employee = $this->db->fetch(
            'SELECT e.*, d.name AS department_name, b.name AS branch_name, des.name AS designation_name,
                    s.name AS shift_name, s.start_time AS shift_start, s.end_time AS shift_end
             FROM employees e
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN branches b ON b.id = e.branch_id
             LEFT JOIN designations des ON des.id = e.designation_id
             LEFT JOIN shifts s ON s.id = e.shift_id
             WHERE e.user_id = :user_id AND e.deleted_at IS NULL
             LIMIT 1',
            ['user_id' => $user['id']]
        );

        self::$employeeCache = $employee;
        return $employee;
    }

    public function can(string $permission): bool
    {
        $permissions = $this->permissions();
        if (in_array('*', $permissions, true)) {
            return true;
        }

        if (in_array($permission, $permissions, true)) {
            return true;
        }

        // Wildcard: employees.* matches employees.view
        $parts = explode('.', $permission);
        if (count($parts) === 2) {
            return in_array($parts[0] . '.*', $permissions, true);
        }

        return false;
    }

    public function permissions(): array
    {
        if (self::$permissionsCache !== null) {
            return self::$permissionsCache;
        }

        $user = $this->user();
        if (!$user) {
            return [];
        }

        $roleSlugs = $user['roles'] ?? [];
        if (in_array('super_admin', $roleSlugs, true)) {
            self::$permissionsCache = ['*'];
            return self::$permissionsCache;
        }

        $rows = $this->db->fetchAll(
            'SELECT DISTINCT p.slug
             FROM permissions p
             INNER JOIN role_permissions rp ON rp.permission_id = p.id
             INNER JOIN roles r ON r.id = rp.role_id
             INNER JOIN user_roles ur ON ur.role_id = r.id
             WHERE ur.user_id = :user_id',
            ['user_id' => $user['id']]
        );

        self::$permissionsCache = array_column($rows, 'slug');
        return self::$permissionsCache;
    }

    public function hasRole(string $role): bool
    {
        $user = $this->user();
        return $user && in_array($role, $user['roles'] ?? [], true);
    }

    public function isAdmin(): bool
    {
        $role = $this->user()['primary_role'] ?? 'employee';
        return in_array($role, ['super_admin', 'company_admin', 'hr_manager', 'department_manager', 'accountant'], true);
    }

    private function attemptRemember(): bool
    {
        $token = $_COOKIE['remember_token'] ?? null;
        if (!$token) {
            return false;
        }

        $hash = hash('sha256', $token);
        $row = $this->db->fetch(
            'SELECT rt.*, u.id AS uid, u.password_changed_at
             FROM remember_tokens rt
             INNER JOIN users u ON u.id = rt.user_id
             WHERE rt.token_hash = :hash AND rt.expires_at > NOW() AND u.is_active = 1
             LIMIT 1',
            ['hash' => $hash]
        );

        if (!$row) {
            return false;
        }

        Session::set('user_id', (int) $row['user_id']);
        Session::set('login_at', time());
        Session::set('_password_changed_at', $row['password_changed_at'] ?? null);
        return true;
    }

    private function isLockedOut(string $email, string $ip): bool
    {
        $max = (int) config('app.login_max_attempts', 5);
        $minutes = (int) config('app.login_lockout_minutes', 15);

        $count = (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM login_attempts
             WHERE email = :email AND ip_address = :ip AND was_successful = 0
             AND attempted_at > DATE_SUB(NOW(), INTERVAL :minutes MINUTE)',
            ['email' => $email, 'ip' => $ip, 'minutes' => $minutes]
        );

        return $count >= $max;
    }

    private function recordLoginAttempt(string $email, string $ip, bool $success): void
    {
        $this->db->insert('login_attempts', [
            'email' => $email,
            'ip_address' => $ip,
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            'was_successful' => $success ? 1 : 0,
            'attempted_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function createPasswordReset(string $email): ?string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $recentRequests = (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM password_reset_attempts
             WHERE (email = :email OR ip_address = :ip)
               AND requested_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)',
            ['email' => $email, 'ip' => $ip]
        );
        $maxRequests = (int) config('app.password_reset_max_requests_per_hour', 5);
        if ($maxRequests > 0 && $recentRequests >= $maxRequests) {
            return null;
        }

        $this->db->insert('password_reset_attempts', [
            'email' => $email,
            'ip_address' => $ip,
            'requested_at' => date('Y-m-d H:i:s'),
        ]);

        $user = $this->users->findByEmail($email);
        if (!$user) {
            return null;
        }

        $token = bin2hex(random_bytes(32));
        $now = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', time() + 3600);

        $this->db->beginTransaction();
        try {
            // A newly requested link supersedes every earlier unused link.
            $this->db->update('password_resets', [
                'used_at' => $now,
            ], 'email = :email AND used_at IS NULL', ['email' => $email]);

            $this->db->insert('password_resets', [
                'email' => $email,
                'token' => hash('sha256', $token),
                'expires_at' => $expiresAt,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $token;
    }

    public function resetPassword(string $email, string $token, string $password): bool
    {
        $this->db->beginTransaction();
        try {
            $row = $this->db->fetch(
                'SELECT * FROM password_resets
                 WHERE email = :email AND token = :token
                   AND used_at IS NULL AND expires_at > NOW()
                 LIMIT 1 FOR UPDATE',
                ['email' => $email, 'token' => hash('sha256', $token)]
            );

            if (!$row) {
                $this->db->rollBack();
                return false;
            }

            $user = $this->users->findByEmail($email);
            if (!$user) {
                $this->db->rollBack();
                return false;
            }

            $changedAt = date('Y-m-d H:i:s');
            $this->users->update((int) $user['id'], [
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'password_changed_at' => $changedAt,
                'force_password_reset' => 0,
            ]);
            $this->db->update('password_resets', [
                'used_at' => $changedAt,
            ], 'id = :id AND used_at IS NULL', ['id' => $row['id']]);
            $this->db->delete('remember_tokens', 'user_id = :id', ['id' => $user['id']]);
            $this->db->commit();

            $this->clearAuthenticatedSession();
            (new AuditService())->log('password_reset', 'auth', (int) $user['id'], null, [
                'sessions_invalidated' => true,
            ], (int) $user['id']);

            return true;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function changePassword(int $userId, string $current, string $new): array
    {
        $user = $this->db->fetch('SELECT * FROM users WHERE id = :id', ['id' => $userId]);
        if (!$user || !password_verify($current, $user['password'])) {
            return ['success' => false, 'message' => 'Current password is incorrect.'];
        }

        $changedAt = date('Y-m-d H:i:s');
        $this->db->beginTransaction();
        try {
            $this->users->update($userId, [
                'password' => password_hash($new, PASSWORD_DEFAULT),
                'password_changed_at' => $changedAt,
                'force_password_reset' => 0,
            ]);
            $this->db->delete('remember_tokens', 'user_id = :id', ['id' => $userId]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        $this->clearAuthenticatedSession();
        (new AuditService())->log('password_change', 'auth', $userId, null, [
            'sessions_invalidated' => true,
        ], $userId);

        return ['success' => true, 'message' => 'Password changed successfully. Please sign in again.'];
    }

    private function passwordChangedSinceSessionStarted(array $user): bool
    {
        $changedAt = $user['password_changed_at'] ?? null;
        if (!$changedAt) {
            return false;
        }

        $sessionVersion = Session::get('_password_changed_at');
        if (is_string($sessionVersion)) {
            return !hash_equals($sessionVersion, (string) $changedAt);
        }

        // Compatibility for sessions created before password version tracking.
        $loginAt = (int) Session::get('login_at', 0);
        return $loginAt > 0 && strtotime((string) $changedAt) > $loginAt;
    }

    private function clearAuthenticatedSession(): void
    {
        setcookie('remember_token', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => (bool) config('app.session_secure', false),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        Session::remove('user_id');
        Session::remove('login_at');
        Session::remove('_password_changed_at');
        Session::regenerate();
        self::$userCache = null;
        self::$employeeCache = null;
        self::$permissionsCache = null;
    }
}
