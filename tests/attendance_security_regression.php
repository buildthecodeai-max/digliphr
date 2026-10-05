#!/usr/bin/env php
<?php

declare(strict_types=1);

if (getenv('TEST_ATTENDANCE_SECURITY') !== '1') {
    echo "Attendance security integration tests skipped (set TEST_ATTENDANCE_SECURITY=1 to enable).\n";
    exit(0);
}

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Application;
use App\Core\Database;
use App\Services\AttendanceSecurityService;
use App\Services\AttendanceService;
use App\Services\ClientIpService;

new Application();
$db = Database::getInstance();
$security = new AttendanceSecurityService();
$attendance = new AttendanceService();
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};
$uuid = static function (): string {
    $hex = bin2hex(random_bytes(16));
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-4' . substr($hex, 13, 3) . '-a' . substr($hex, 17, 3) . '-' . substr($hex, 20, 12);
};
$companyId = (int) $db->fetchColumn('SELECT id FROM companies ORDER BY id LIMIT 1');
$adminId = (int) $db->fetchColumn("SELECT u.id FROM users u INNER JOIN user_roles ur ON ur.user_id=u.id INNER JOIN roles r ON r.id=ur.role_id WHERE r.slug IN ('super_admin','company_admin') ORDER BY (r.slug='super_admin') DESC LIMIT 1");
if (!$companyId || !$adminId) throw new RuntimeException('A company and administrator fixture are required.');
$settingRows = $db->fetchAll("SELECT setting_key, setting_value FROM system_settings WHERE company_id=:cid AND group_name='attendance_security'", ['cid' => $companyId]);
$originalSettings = array_column($settingRows, 'setting_value', 'setting_key');
$auditStart = (int) $db->fetchColumn('SELECT COALESCE(MAX(id),0) FROM audit_logs');
$stamp = bin2hex(random_bytes(5));
$userId = 0;
$employeeId = 0;

$set = static function (string $key, string $value) use ($db, $companyId): void {
    $db->update('system_settings', ['setting_value' => $value], 'company_id=:cid AND setting_key=:key', ['cid' => $companyId, 'key' => $key]);
};
$payload = ['latitude' => 0, 'longitude' => 0, 'accuracy' => 10, 'source' => 'web'];
$clearAttendance = static function () use ($db, &$employeeId): void {
    if ($employeeId) $db->delete('attendance', 'employee_id=:eid', ['eid' => $employeeId]);
};

try {
    $userId = $db->insert('users', [
        'uuid' => $uuid(), 'name' => 'Attendance Security QA', 'email' => "attendance-security-{$stamp}@example.test",
        'password' => password_hash('Test-only-password-42!', PASSWORD_DEFAULT), 'is_active' => 1,
    ]);
    $employeeId = $db->insert('employees', [
        'uuid' => $uuid(), 'user_id' => $userId, 'company_id' => $companyId,
        'employee_code' => 'SEC-' . strtoupper($stamp), 'first_name' => 'Security', 'last_name' => 'QA',
        'joining_date' => date('Y-m-d'), 'employment_status' => 'active',
    ]);
    $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/120.0 Safari/537.36';
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    unset($_COOKIE[AttendanceSecurityService::COOKIE]);
    $set('attendance_security_mode', 'device_only');
    $set('attendance_device_registration_policy', 'auto_first');
    $set('attendance_device_change_requires_approval', '1');

    $first = $attendance->checkIn($employeeId, $payload, $userId);
    $assert($first['success'] === true, 'Test 1: first approved device must check in.');
    $tokenOne = (string) ($first['device_cookie_token'] ?? '');
    $assert(strlen($tokenOne) === 64 && !str_contains(json_encode($security->devicesForEmployee($employeeId)), $tokenOne), 'Device token must be random and stored only as a hash.');
    $_COOKIE[AttendanceSecurityService::COOKIE] = $tokenOne;
    $secondActive = $attendance->checkIn($employeeId, $payload, $userId);
    $assert(!$secondActive['success'] && ($secondActive['code'] ?? '') === 'ATTENDANCE_ALREADY_ACTIVE', 'Test 4/10: a second active check-in must be blocked.');
    $out = $attendance->checkOut($employeeId, $payload, $userId);
    $assert($out['success'] === true, 'Test 13: checkout must remain usable.');
    $clearAttendance();
    $again = $attendance->checkIn($employeeId, $payload, $userId);
    $assert($again['success'] === true, 'Test 2: approved device must work after checkout.');
    $attendance->checkOut($employeeId, $payload, $userId);
    $clearAttendance();

    unset($_COOKIE[AttendanceSecurityService::COOKIE]);
    $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1';
    $blocked = $attendance->checkIn($employeeId, $payload, $userId);
    $assert(!$blocked['success'] && ($blocked['code'] ?? '') === 'ATTENDANCE_DEVICE_NOT_AUTHORIZED', 'Test 3: different device must be blocked.');
    $tokenTwo = (string) ($blocked['device_cookie_token'] ?? '');
    $pending = $db->fetch("SELECT id FROM employee_attendance_devices WHERE employee_id=:eid AND status='pending' ORDER BY id DESC LIMIT 1", ['eid' => $employeeId]);
    $assert((bool) $pending, 'Different device must create a pending change request.');
    $security->approve((int) $pending['id'], $employeeId, $adminId);
    $assert((int) $db->fetchColumn("SELECT COUNT(*) FROM employee_attendance_devices WHERE employee_id={$employeeId} AND status='approved'") === 1, 'Test 8: approval must leave exactly one approved device.');

    $_COOKIE[AttendanceSecurityService::COOKIE] = $tokenOne;
    $oldBlocked = $attendance->checkIn($employeeId, $payload, $userId);
    $assert(!$oldBlocked['success'], 'Test 9: old revoked device must remain blocked.');
    $_COOKIE[AttendanceSecurityService::COOKIE] = $tokenTwo;
    $newWorks = $attendance->checkIn($employeeId, $payload, $userId);
    $assert($newWorks['success'] === true, 'Approved replacement device must check in.');
    $attendance->checkOut($employeeId, $payload, $userId);
    $clearAttendance();

    $set('attendance_security_mode', 'device_and_ip');
    $set('attendance_ip_source', 'office');
    $set('attendance_office_ip_addresses', '203.0.113.10');
    $_SERVER['REMOTE_ADDR'] = '198.51.100.10';
    $ipBlocked = $attendance->checkIn($employeeId, $payload, $userId);
    $assert(!$ipBlocked['success'] && ($ipBlocked['code'] ?? '') === 'ATTENDANCE_IP_NOT_ALLOWED', 'Test 5: unauthorized IP must be blocked.');
    $_SERVER['REMOTE_ADDR'] = '203.0.113.10';
    $ipAllowed = $attendance->checkIn($employeeId, $payload, $userId);
    $assert($ipAllowed['success'] === true, 'Test 6: allowed office IP must succeed.');
    $attendance->checkOut($employeeId, $payload, $userId);
    $clearAttendance();

    $security->reset($employeeId, $adminId, 'Automated regression test');
    $assert((int) $db->fetchColumn("SELECT COUNT(*) FROM employee_attendance_devices WHERE employee_id={$employeeId} AND status='approved'") === 0, 'Test 7: reset must revoke the approved device.');
    $employeePermissions = require dirname(__DIR__) . '/config/permissions.php';
    $forbidden = array_intersect($employeePermissions['role_defaults']['employee'], ['attendance.device.manage', 'attendance.device.approve', 'attendance.security.settings']);
    $assert($forbidden === [], 'Test 12: employee role must not receive device administration permissions.');
    $controllerSource = file_get_contents(dirname(__DIR__) . '/app/Controllers/Api/AttendanceApiController.php');
    $serviceSource = file_get_contents(dirname(__DIR__) . '/app/Services/AttendanceService.php');
    $assert(!str_contains($controllerSource, "input('employee_id") && str_contains($controllerSource, "employee['id']"), 'Test 11: attendance API must derive employee identity from authentication.');
    $assert(str_contains($serviceSource, 'FOR UPDATE'), 'Test 10: check-in must serialize on the employee row.');
    $assert((new ClientIpService())->matchesAny('10.20.30.40', ['10.20.0.0/16']), 'CIDR allowlist matching must work.');
    $assert((int) $db->fetchColumn("SELECT COUNT(*) FROM attendance_security_events WHERE employee_id={$employeeId} AND event_type LIKE 'CHECK_IN_%'") >= 4, 'Security-sensitive check-in outcomes must be recorded.');
    $assert(method_exists($attendance, 'todayStatus'), 'Test 14: existing attendance status/history API remains available.');

    echo "Attendance security regression tests passed ({$assertions} assertions).\n";
} finally {
    foreach ($originalSettings as $key => $value) $set((string) $key, (string) $value);
    if ($employeeId) {
        $clearAttendance();
        $db->delete('attendance_security_events', 'employee_id=:eid', ['eid' => $employeeId]);
        $db->delete('employee_attendance_devices', 'employee_id=:eid', ['eid' => $employeeId]);
        $db->delete('employment_agreements', 'employee_id=:eid', ['eid' => $employeeId]);
        $db->delete('employees', 'id=:id', ['id' => $employeeId]);
    }
    if ($employeeId) {
        $db->query(
            "DELETE FROM audit_logs WHERE id > :start AND
             (user_id = :uid OR JSON_UNQUOTE(JSON_EXTRACT(new_values, '$.employee_id')) = :eid)",
            ['start' => $auditStart, 'uid' => $userId ?: 0, 'eid' => (string) $employeeId]
        );
    }
    if ($userId) $db->delete('users', 'id=:id', ['id' => $userId]);
}
