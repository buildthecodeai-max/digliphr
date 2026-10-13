<?php

declare(strict_types=1);

namespace App\Controllers\Employee;

use App\Core\Controller;
use App\Core\Database;

class TimeclockController extends Controller
{
    private Database $db;
    private static bool $sessionsReady = false;

    public function __construct(\App\Core\Request $request, \App\Core\Response $response)
    {
        parent::__construct($request, $response);
        $this->db = Database::getInstance();
    }

    /** GET /employee/timeclock/status */
    public function status(): void
    {
        $employee = $this->employee();
        if (!$employee) {
            $this->jsonError('No employee profile.', null, 403);
            return;
        }

        $this->ensureSessionsTable();
        $this->jsonSuccess('ok', $this->buildStatus((int) $employee['id']));
    }

    /** POST /employee/timeclock/action  (action = check_in|check_out|break_start|break_end) */
    public function action(): void
    {
        $employee = $this->employee();
        if (!$employee) {
            $this->jsonError('No employee profile.', null, 403);
            return;
        }

        $employeeId = (int) $employee['id'];
        $companyId  = (int) $employee['company_id'];
        $action     = trim((string) $this->request->input('action', ''));
        $now        = date('Y-m-d H:i:s');
        $today      = date('Y-m-d');

        $this->ensureSessionsTable();

        switch ($action) {
            case 'check_in':
                // Block if there is an unclosed check-in from a previous day
                $prevOpen = $this->db->fetch(
                    'SELECT attendance_date FROM attendance
                     WHERE employee_id=:e AND attendance_date < :d
                       AND check_in_at IS NOT NULL AND check_out_at IS NULL AND deleted_at IS NULL
                     ORDER BY attendance_date DESC LIMIT 1',
                    ['e' => $employeeId, 'd' => $today]
                );
                if ($prevOpen) {
                    $prevLabel = date('M j', strtotime((string) $prevOpen['attendance_date']));
                    $this->jsonError('Please check out of your ' . $prevLabel . ' session before checking in again.', null, 409);
                    return;
                }
                $att = $this->db->fetch(
                    'SELECT * FROM attendance WHERE employee_id=:e AND attendance_date=:d AND deleted_at IS NULL LIMIT 1',
                    ['e' => $employeeId, 'd' => $today]
                );
                if ($att) {
                    if (!$att['check_out_at']) {
                        $this->jsonError('Already checked in.', null, 409);
                        return;
                    }
                    // Re-check-in after checkout: reopen row, add new session
                    $this->db->query(
                        'UPDATE attendance SET check_out_at=NULL, updated_at=NOW() WHERE id=:id',
                        ['id' => $att['id']]
                    );
                    $this->db->insert('attendance_sessions', [
                        'attendance_id'    => $att['id'],
                        'session_start_at' => $now,
                        'created_at'       => $now,
                        'updated_at'       => $now,
                    ]);
                } else {
                    // First check-in of the day
                    $uuid = $this->uuid();
                    $this->db->insert('attendance', [
                        'uuid'                => $uuid,
                        'company_id'          => $companyId,
                        'employee_id'         => $employeeId,
                        'attendance_date'     => $today,
                        'check_in_at'         => $now,
                        'original_check_in_at'=> $now,
                        'source'              => 'web',
                        'status'              => 'present',
                        'verification_status' => 'auto_verified',
                        'created_at'          => $now,
                        'updated_at'          => $now,
                    ]);
                    $newAtt = $this->db->fetch(
                        'SELECT id FROM attendance WHERE uuid=:u LIMIT 1',
                        ['u' => $uuid]
                    );
                    if ($newAtt) {
                        $this->db->insert('attendance_sessions', [
                            'attendance_id'    => $newAtt['id'],
                            'session_start_at' => $now,
                            'created_at'       => $now,
                            'updated_at'       => $now,
                        ]);
                    }
                }
                break;

            case 'check_out':
                $att = $this->db->fetch(
                    'SELECT * FROM attendance WHERE employee_id=:e AND attendance_date <= :d AND check_out_at IS NULL AND deleted_at IS NULL ORDER BY attendance_date DESC LIMIT 1',
                    ['e' => $employeeId, 'd' => $today]
                );
                if (!$att) {
                    $this->jsonError('No active check-in found.', null, 409);
                    return;
                }
                $attId = (int) $att['id'];
                // End any open break first
                $this->db->query(
                    'UPDATE attendance_breaks SET break_end_at=:n, duration_minutes=GREATEST(0,TIMESTAMPDIFF(MINUTE,break_start_at,:n2)), updated_at=:n3
                     WHERE attendance_id=:id AND break_end_at IS NULL',
                    ['n' => $now, 'n2' => $now, 'n3' => $now, 'id' => $attId]
                );
                // End current open session
                $openSess = $this->db->fetch(
                    'SELECT id, session_start_at FROM attendance_sessions WHERE attendance_id=:id AND session_end_at IS NULL ORDER BY session_start_at DESC LIMIT 1',
                    ['id' => $attId]
                );
                if ($openSess) {
                    $sessDur = max(0, (int) floor((strtotime($now) - strtotime((string) $openSess['session_start_at'])) / 60));
                    $this->db->query(
                        'UPDATE attendance_sessions SET session_end_at=:n, duration_minutes=:dm, updated_at=:n2 WHERE id=:id',
                        ['n' => $now, 'dm' => $sessDur, 'n2' => $now, 'id' => $openSess['id']]
                    );
                }
                // Totals from all sessions minus breaks
                $totalSessMins = (int) $this->db->fetchColumn(
                    'SELECT COALESCE(SUM(duration_minutes),0) FROM attendance_sessions WHERE attendance_id=:id',
                    ['id' => $attId]
                );
                $breakMins = $this->sumBreaks($attId);
                $workMins  = max(0, $totalSessMins - $breakMins);

                // Cap work_minutes when checkout is on a later date than attendance_date
                // (forgotten checkout being fixed the next day) — prevents fabricated overtime.
                $attDate = (string) ($att['attendance_date'] ?? $today);
                if ($today > $attDate) {
                    $expectedMins = (int) ($att['expected_work_minutes'] ?? 420);
                    $workMins = min($workMins, $expectedMins > 0 ? $expectedMins : 420);
                }

                // Calculate overtime_minutes so the attendance record is fully consistent.
                $shiftRow = $att['shift_id']
                    ? ($this->db->fetch('SELECT * FROM shifts WHERE id=:id LIMIT 1', ['id' => (int) $att['shift_id']]) ?? [])
                    : [];
                $expectedForOt = (int) ($att['expected_work_minutes'] ?? (int) ($shiftRow['expected_work_minutes'] ?? 420));
                $otThreshold   = (int) ($shiftRow['overtime_after_minutes'] ?? 0);
                $baseline      = $expectedForOt + $otThreshold;
                $overtimeMins  = $workMins > $baseline ? $workMins - $baseline : 0;

                $this->db->query(
                    'UPDATE attendance SET check_out_at=:co, work_minutes=:wm, overtime_minutes=:ot, break_minutes=:bm, updated_at=NOW() WHERE id=:id',
                    ['co' => $now, 'wm' => $workMins, 'ot' => $overtimeMins, 'bm' => $breakMins, 'id' => $attId]
                );
                break;

            case 'break_start':
                $att = $this->db->fetch(
                    'SELECT id FROM attendance WHERE employee_id=:e AND attendance_date <= :d AND check_out_at IS NULL AND deleted_at IS NULL ORDER BY attendance_date DESC LIMIT 1',
                    ['e' => $employeeId, 'd' => $today]
                );
                if (!$att) {
                    $this->jsonError('Not currently checked in.', null, 409);
                    return;
                }
                $openBreak = $this->db->fetch(
                    'SELECT id FROM attendance_breaks WHERE attendance_id=:id AND break_end_at IS NULL LIMIT 1',
                    ['id' => $att['id']]
                );
                if ($openBreak) {
                    $this->jsonError('Break already in progress.', null, 409);
                    return;
                }
                $this->db->insert('attendance_breaks', [
                    'attendance_id'  => $att['id'],
                    'break_start_at' => $now,
                    'break_type'     => 'other',
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]);
                break;

            case 'break_end':
                $att = $this->db->fetch(
                    'SELECT id FROM attendance WHERE employee_id=:e AND attendance_date <= :d AND check_out_at IS NULL AND deleted_at IS NULL ORDER BY attendance_date DESC LIMIT 1',
                    ['e' => $employeeId, 'd' => $today]
                );
                if (!$att) {
                    $this->jsonError('No active check-in.', null, 409);
                    return;
                }
                $br = $this->db->fetch(
                    'SELECT id, break_start_at FROM attendance_breaks WHERE attendance_id=:id AND break_end_at IS NULL LIMIT 1',
                    ['id' => $att['id']]
                );
                if (!$br) {
                    $this->jsonError('No active break.', null, 409);
                    return;
                }
                $durMins = max(0, (int) floor((strtotime($now) - strtotime((string) $br['break_start_at'])) / 60));
                $this->db->query(
                    'UPDATE attendance_breaks SET break_end_at=:n, duration_minutes=:dm, updated_at=:n2 WHERE id=:id',
                    ['n' => $now, 'dm' => $durMins, 'n2' => $now, 'id' => $br['id']]
                );
                break;

            case 'undo_checkout':
                // Find the most recent checkout within the 30-minute grace period
                $att = $this->db->fetch(
                    'SELECT * FROM attendance WHERE employee_id=:e AND check_out_at IS NOT NULL
                     AND check_out_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE) AND deleted_at IS NULL
                     ORDER BY check_out_at DESC LIMIT 1',
                    ['e' => $employeeId]
                );
                if (!$att) {
                    $this->jsonError('Undo period has expired (30-minute grace period).', null, 409);
                    return;
                }
                $attId = (int) $att['id'];
                // Reopen the attendance record
                $this->db->query(
                    'UPDATE attendance SET check_out_at=NULL, work_minutes=0, updated_at=NOW() WHERE id=:id',
                    ['id' => $attId]
                );
                // Reopen the last ended session (the one that was closed at checkout) starting from NOW
                // so the gap between accidental checkout and undo is NOT counted as worked time
                $lastSess = $this->db->fetch(
                    'SELECT id FROM attendance_sessions WHERE attendance_id=:id ORDER BY session_start_at DESC LIMIT 1',
                    ['id' => $attId]
                );
                if ($lastSess) {
                    $this->db->query(
                        'UPDATE attendance_sessions SET session_start_at=:n, session_end_at=NULL, duration_minutes=0, updated_at=NOW() WHERE id=:id',
                        ['n' => $now, 'id' => (int) $lastSess['id']]
                    );
                } else {
                    // Fallback: add a fresh session starting now
                    $this->db->insert('attendance_sessions', [
                        'attendance_id'    => $attId,
                        'session_start_at' => $now,
                        'created_at'       => $now,
                        'updated_at'       => $now,
                    ]);
                }
                break;

            default:
                $this->jsonError('Unknown action.', null, 400);
                return;
        }

        $this->jsonSuccess('ok', $this->buildStatus($employeeId));
    }

    private function buildStatus(int $employeeId): array
    {
        $today     = date('Y-m-d');
        $now       = time();
        $staleDate = null;

        $att = $this->db->fetch(
            'SELECT * FROM attendance WHERE employee_id=:e AND attendance_date=:d AND deleted_at IS NULL LIMIT 1',
            ['e' => $employeeId, 'd' => $today]
        );

        if (!$att) {
            // Check for an unclosed check-in from a previous day
            $att = $this->db->fetch(
                'SELECT * FROM attendance
                 WHERE employee_id=:e AND attendance_date < :d
                   AND check_in_at IS NOT NULL AND check_out_at IS NULL AND deleted_at IS NULL
                 ORDER BY attendance_date DESC LIMIT 1',
                ['e' => $employeeId, 'd' => $today]
            );
            if (!$att) {
                // Check for a recent checkout within the 30-minute undo grace period
                $recentCo = $this->db->fetch(
                    'SELECT id, check_out_at, attendance_date FROM attendance
                     WHERE employee_id=:e AND check_out_at IS NOT NULL
                       AND check_out_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
                       AND deleted_at IS NULL
                     ORDER BY check_out_at DESC LIMIT 1',
                    ['e' => $employeeId]
                );
                return [
                    'state'                 => 'checked_out',
                    'worked_secs'           => 0,
                    'break_secs'            => 0,
                    'sessions'              => 0,
                    'last_activity'         => null,
                    'timeline'              => [],
                    'session_start_display' => null,
                    'stale_date'            => null,
                    'can_undo_checkout'     => $recentCo !== null,
                    'undo_at'               => $recentCo ? date('g:i A', strtotime((string) $recentCo['check_out_at'])) : null,
                    'undo_date'             => $recentCo ? (string) $recentCo['attendance_date'] : null,
                ];
            }
            $staleDate = (string) $att['attendance_date'];
        }

        $attId = (int) $att['id'];

        $sessions = $this->db->fetchAll(
            'SELECT * FROM attendance_sessions WHERE attendance_id=:id ORDER BY session_start_at ASC',
            ['id' => $attId]
        );

        $breaks = $this->db->fetchAll(
            'SELECT * FROM attendance_breaks WHERE attendance_id=:id ORDER BY break_start_at ASC',
            ['id' => $attId]
        );

        // Compute break totals
        $openBreak = null;
        $breakSecs = 0;
        foreach ($breaks as $br) {
            if ($br['break_end_at'] === null) {
                $openBreak = $br;
                $breakSecs += $now - strtotime((string) $br['break_start_at']);
            } else {
                $breakSecs += (int) $br['duration_minutes'] * 60;
            }
        }

        // Build timeline entries (breaks added at end, then sorted)
        $timeline = [];

        if (!empty($sessions)) {
            // Multi-session path
            $openSession      = null;
            $totalSessionSecs = 0;
            foreach ($sessions as $sess) {
                if ($sess['session_end_at'] === null) {
                    $openSession       = $sess;
                    $totalSessionSecs += $now - strtotime((string) $sess['session_start_at']);
                } else {
                    $totalSessionSecs += (int) $sess['duration_minutes'] * 60;
                }
                $timeline[] = ['type' => 'check_in', 'at' => $sess['session_start_at']];
                if ($sess['session_end_at']) {
                    $dur        = (int) $sess['duration_minutes'];
                    $timeline[] = ['type' => 'check_out', 'at' => $sess['session_end_at'], 'label' => $dur > 0 ? $dur . 'm' : '<1m'];
                }
            }

            $workedSecs   = max(0, $totalSessionSecs - $breakSecs);
            $sessionCount = count($sessions);
            $state        = ($att['check_out_at'] && $openSession === null)
                            ? 'checked_out'
                            : ($openBreak ? 'on_break' : 'checked_in');
            $sessionStartDisplay = $openSession
                ? date('g:i A', strtotime((string) $openSession['session_start_at']))
                : null;
        } else {
            // Legacy path: attendance row with no session records yet
            $checkInTs    = $att['check_in_at']  ? strtotime((string) $att['check_in_at'])  : null;
            $checkOutTs   = $att['check_out_at'] ? strtotime((string) $att['check_out_at']) : null;
            $totalElapsed = $checkInTs ? ($checkOutTs ?? $now) - $checkInTs : 0;
            $workedSecs   = max(0, $totalElapsed - $breakSecs);
            $sessionCount = 1;
            $state        = $att['check_out_at'] ? 'checked_out' : ($openBreak ? 'on_break' : 'checked_in');
            $sessionStartDisplay = ($att['check_in_at'] && !$att['check_out_at'])
                ? date('g:i A', strtotime((string) $att['check_in_at']))
                : null;
            if ($att['check_in_at'])  $timeline[] = ['type' => 'check_in',  'at' => $att['check_in_at']];
            if ($att['check_out_at']) $timeline[] = ['type' => 'check_out', 'at' => $att['check_out_at']];
        }

        // Merge breaks into timeline
        foreach ($breaks as $br) {
            $timeline[] = ['type' => 'break_start', 'at' => $br['break_start_at']];
            if ($br['break_end_at']) {
                $dur        = (int) $br['duration_minutes'];
                $timeline[] = ['type' => 'break_end', 'at' => $br['break_end_at'], 'label' => $dur > 0 ? $dur . 'm' : '<1m'];
            }
        }
        usort($timeline, fn($a, $b) => strtotime((string) $a['at']) <=> strtotime((string) $b['at']));

        $lastActivity = null;
        if (!empty($timeline)) {
            $last         = end($timeline);
            $lastActivity = [
                'type' => $last['type'],
                'at'   => date('g:i A', strtotime((string) $last['at'])),
            ];
        }

        // Undo checkout grace period: only relevant when state is checked_out (and not stale)
        $canUndo  = false;
        $undoAt   = null;
        $undoDate = null;
        if ($state === 'checked_out' && $staleDate === null) {
            $coTs = $att['check_out_at'] ? strtotime((string) $att['check_out_at']) : 0;
            if ($coTs && (time() - $coTs) <= 1800) { // 30-minute grace
                $canUndo  = true;
                $undoAt   = date('g:i A', $coTs);
                $undoDate = (string) $att['attendance_date'];
            }
        }

        return [
            'state'                 => $state,
            'worked_secs'           => $workedSecs,
            'break_secs'            => $breakSecs,
            'sessions'              => $sessionCount,
            'last_activity'         => $lastActivity,
            'timeline'              => $timeline,
            'session_start_display' => $sessionStartDisplay,
            'stale_date'            => $staleDate,
            'can_undo_checkout'     => $canUndo,
            'undo_at'               => $undoAt,
            'undo_date'             => $undoDate,
        ];
    }

    private function ensureSessionsTable(): void
    {
        if (self::$sessionsReady) return;

        $this->db->query(
            'CREATE TABLE IF NOT EXISTS `attendance_sessions` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `attendance_id` BIGINT UNSIGNED NOT NULL,
                `session_start_at` DATETIME NOT NULL,
                `session_end_at` DATETIME DEFAULT NULL,
                `duration_minutes` INT UNSIGNED NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_att_sessions_att` (`attendance_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            []
        );

        // Backfill one session per attendance row that has no session yet
        $this->db->query(
            'INSERT INTO attendance_sessions (attendance_id, session_start_at, session_end_at, duration_minutes, created_at, updated_at)
             SELECT a.id, a.check_in_at, a.check_out_at, COALESCE(a.work_minutes, 0), NOW(), NOW()
             FROM attendance a
             LEFT JOIN attendance_sessions s ON s.attendance_id = a.id
             WHERE a.check_in_at IS NOT NULL
               AND a.deleted_at IS NULL
               AND s.id IS NULL',
            []
        );

        self::$sessionsReady = true;
    }

    private function sumBreaks(int $attId): int
    {
        return (int) $this->db->fetchColumn(
            'SELECT COALESCE(SUM(duration_minutes),0) FROM attendance_breaks WHERE attendance_id=:id AND break_end_at IS NOT NULL',
            ['id' => $attId]
        );
    }

    private function uuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
