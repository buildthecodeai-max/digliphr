<?php

declare(strict_types=1);

/**
 * Master cron runner — schedule every 15 minutes:
 *   * /15 * * * * php /path/to/cron/run.php
 *
 * Or run a specific job:
 *   php cron/run.php mark_absences
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Application;
use App\Core\Database;
use App\Core\Logger;

new Application();
$db = Database::getInstance();
$logger = new Logger();
$job = $argv[1] ?? 'all';

$jobs = [
    'mark_absences' => static function () use ($db, $logger): void {
        // Supports backfill: php cron/run.php mark_absences 2026-09-01 2026-09-15
        global $argv;
        $dateFrom = isset($argv[2]) ? $argv[2] : date('Y-m-d');
        $dateTo   = isset($argv[3]) ? $argv[3] : $dateFrom;
        $dates = [];
        for ($d = strtotime($dateFrom); $d <= strtotime($dateTo); $d += 86400) {
            $dates[] = date('Y-m-d', $d);
        }
        $employees = $db->fetchAll(
            'SELECT e.id, e.company_id, e.branch_id, e.shift_id, e.joining_date
             FROM employees e
             WHERE e.employment_status IN ("active","probation") AND e.deleted_at IS NULL'
        );

        $created = 0;
        foreach ($dates as $date) {
            foreach ($employees as $employee) {
                if (!empty($employee['joining_date']) && $employee['joining_date'] > $date) {
                    continue;
                }

                $exists = $db->fetch(
                    'SELECT id FROM attendance WHERE employee_id = :eid AND attendance_date = :d AND deleted_at IS NULL LIMIT 1',
                    ['eid' => $employee['id'], 'd' => $date]
                );
                if ($exists) {
                    continue;
                }

                $onLeave = $db->fetch(
                    'SELECT id FROM leave_requests
                     WHERE employee_id = :eid AND status = "approved"
                     AND start_date <= :d1 AND end_date >= :d2 AND deleted_at IS NULL LIMIT 1',
                    ['eid' => $employee['id'], 'd1' => $date, 'd2' => $date]
                );

                $holiday = $db->fetch(
                    'SELECT id FROM holidays
                     WHERE company_id = :cid AND holiday_date = :d AND deleted_at IS NULL
                     AND (branch_id IS NULL OR branch_id = :bid) LIMIT 1',
                    ['cid' => $employee['company_id'], 'd' => $date, 'bid' => $employee['branch_id']]
                );

                $status = 'absent';
                if ($onLeave) {
                    $status = 'on_leave';
                } elseif ($holiday) {
                    $status = 'holiday';
                } elseif ((int) date('N', strtotime($date)) >= 6) {
                    $status = 'weekend';
                }

                if (in_array($status, ['weekend', 'holiday'], true) && !$onLeave) {
                    // Skip creating weekend/holiday rows unless configured
                    $createWeekend = (string) setting('create_weekend_attendance', '0') === '1';
                    if (!$createWeekend && $status !== 'on_leave') {
                        continue;
                    }
                }

                $data = random_bytes(16);
                $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
                $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
                $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

                $db->insert('attendance', [
                    'uuid' => $uuid,
                    'employee_id' => $employee['id'],
                    'company_id' => $employee['company_id'],
                    'branch_id' => $employee['branch_id'],
                    'shift_id' => $employee['shift_id'],
                    'attendance_date' => $date,
                    'status' => $status,
                    'verification_status' => 'verified',
                    'leave_request_id' => $onLeave['id'] ?? null,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $created++;
            }
        }
        $logger->info("mark_absences: created {$created} records for {$dateFrom} to {$dateTo}");
        echo "mark_absences: {$created}\n";
    },

    'auto_checkout' => static function () use ($db, $logger): void {
        $autoHours = 10;
        $rows = $db->fetchAll(
            'SELECT id, check_in_at, status FROM attendance
             WHERE check_in_at IS NOT NULL AND check_out_at IS NULL
               AND status NOT IN ("on_leave","holiday","weekend","absent","missing_checkout")
               AND deleted_at IS NULL
               AND TIMESTAMPDIFF(MINUTE, check_in_at, NOW()) >= :mins',
            ['mins' => $autoHours * 60]
        );
        $count = 0;
        foreach ($rows as $row) {
            $autoCheckOut = date('Y-m-d H:i:s', strtotime($row['check_in_at']) + ($autoHours * 3600));
            $workMinutes = $autoHours * 60;
            $db->update('attendance', [
                'check_out_at' => $autoCheckOut,
                'work_minutes' => $workMinutes,
                'remarks' => 'Auto checked-out after ' . $autoHours . ' hours',
                'verification_status' => 'flagged',
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => $row['id']]);
            $count++;
        }
        $logger->info("auto_checkout: {$count} records auto checked-out");
        echo "auto_checkout: {$count}\n";
    },

    'missing_checkouts' => static function () use ($db, $logger): void {
        $rows = $db->fetchAll(
            'SELECT id FROM attendance
             WHERE check_in_at IS NOT NULL AND check_out_at IS NULL
             AND attendance_date < CURDATE() AND status NOT IN ("missing_checkout","on_leave","holiday","weekend","absent")
             AND deleted_at IS NULL'
        );
        foreach ($rows as $row) {
            $db->update('attendance', [
                'status' => 'missing_checkout',
                'verification_status' => 'flagged',
            ], 'id = :id', ['id' => $row['id']]);
        }
        $logger->info('missing_checkouts: ' . count($rows));
        echo 'missing_checkouts: ' . count($rows) . "\n";
    },

    'leave_accrual' => static function () use ($db, $logger): void {
        // Monthly accrual on the 1st only
        if ((int) date('j') !== 1 && ($GLOBALS['force_cron'] ?? false) !== true) {
            echo "leave_accrual: skipped (not 1st)\n";
            return;
        }
        $year = (int) date('Y');
        $types = $db->fetchAll('SELECT id, company_id FROM leave_types WHERE is_active = 1 AND deleted_at IS NULL AND is_paid = 1');
        $updated = 0;
        foreach ($types as $type) {
            $employees = $db->fetchAll(
                'SELECT id FROM employees WHERE company_id = :cid AND employment_status IN ("active","probation") AND deleted_at IS NULL',
                ['cid' => $type['company_id']]
            );
            foreach ($employees as $employee) {
                $balance = $db->fetch(
                    'SELECT id, accrued FROM employee_leave_balances WHERE employee_id = :e AND leave_type_id = :t AND year = :y',
                    ['e' => $employee['id'], 't' => $type['id'], 'y' => $year]
                );
                $monthly = (float) setting('monthly_leave_accrual', '1.67');
                if ($balance) {
                    $db->update('employee_leave_balances', [
                        'accrued' => (float) $balance['accrued'] + $monthly,
                    ], 'id = :id', ['id' => $balance['id']]);
                    $db->query(
                        'UPDATE employee_leave_balances
                         SET closing_balance = opening_balance + accrued + carried_forward + adjusted - used - pending - encashed
                         WHERE id = :id',
                        ['id' => $balance['id']]
                    );
                } else {
                    $db->insert('employee_leave_balances', [
                        'employee_id' => $employee['id'],
                        'leave_type_id' => $type['id'],
                        'year' => $year,
                        'opening_balance' => 0,
                        'accrued' => $monthly,
                        'closing_balance' => $monthly,
                    ]);
                }
                $updated++;
            }
        }
        $logger->info("leave_accrual: {$updated}");
        echo "leave_accrual: {$updated}\n";
    },

    'cleanup_attendance_evidence' => static function () use ($db, $logger): void {
        $imageDays = (int) (setting('attendance_image_retention_days', (string) config('app.attendance.image_retention_days', 365)));
        $locationDays = (int) (setting('attendance_location_retention_days', (string) config('app.attendance.location_retention_days', 365)));

        $images = $db->fetchAll(
            'SELECT id, path, stored_name, filename FROM attendance_images
             WHERE created_at < DATE_SUB(NOW(), INTERVAL ' . $imageDays . ' DAY)'
        );
        $deletedImages = 0;
        foreach ($images as $image) {
            $file = config('app.paths.attendance_images') . '/' . ($image['stored_name'] ?? $image['filename'] ?? '');
            if (is_file($file)) {
                @unlink($file);
            }
            $db->delete('attendance_images', 'id = :id', ['id' => $image['id']]);
            $deletedImages++;
        }

        $db->query(
            'DELETE FROM attendance_locations WHERE created_at < DATE_SUB(NOW(), INTERVAL ' . $locationDays . ' DAY)'
        );

        $logger->info("cleanup_attendance_evidence: images={$deletedImages}");
        echo "cleanup_attendance_evidence: images={$deletedImages}\n";
    },

    'expiry_reminders' => static function () use ($db, $logger): void {
        $docs = $db->fetchAll(
            'SELECT ed.*, e.user_id, e.first_name, e.last_name
             FROM employee_documents ed
             INNER JOIN employees e ON e.id = ed.employee_id
             WHERE ed.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
             AND ed.deleted_at IS NULL'
        );
        $notifier = new \App\Services\NotificationService();
        foreach ($docs as $doc) {
            if (empty($doc['user_id'])) {
                continue;
            }
            $notifier->notify(
                (int) $doc['user_id'],
                'Document expiring soon',
                sprintf('Document "%s" expires on %s.', $doc['title'] ?? $doc['document_type'] ?? 'Document', $doc['expiry_date']),
                '/employee/documents',
                'document_expiry'
            );
        }

        $contracts = $db->fetchAll(
            'SELECT id, user_id, first_name, last_name, contract_end FROM employees
             WHERE contract_end BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
             AND deleted_at IS NULL AND employment_status IN ("active","probation")'
        );
        foreach ($contracts as $emp) {
            if (empty($emp['user_id'])) {
                continue;
            }
            $notifier->notify(
                (int) $emp['user_id'],
                'Contract expiring soon',
                'Your contract ends on ' . $emp['contract_end'] . '.',
                '/employee/profile',
                'contract_expiry'
            );
        }

        $logger->info('expiry_reminders: docs=' . count($docs) . ' contracts=' . count($contracts));
        echo 'expiry_reminders: docs=' . count($docs) . ' contracts=' . count($contracts) . "\n";
    },

    'birthday_reminders' => static function () use ($logger): void {
        $sent = (new \App\Services\BirthdayReminderService())->sendDueReminders();
        $logger->info('birthday_reminders: sent=' . $sent);
        echo 'birthday_reminders: ' . $sent . "\n";
    },

    'cleanup_monitoring_evidence' => static function () use ($db, $logger): void {
        $result = (new \App\Services\Monitoring\MonitoringRetentionService())->cleanup(500);
        $logger->info(sprintf(
            'cleanup_monitoring_evidence: screenshots=%d activities=%d heartbeats=%d',
            $result['screenshots'],
            $result['activities'],
            $result['heartbeats']
        ));
        echo sprintf(
            "cleanup_monitoring_evidence: screenshots=%d activities=%d heartbeats=%d\n",
            $result['screenshots'],
            $result['activities'],
            $result['heartbeats']
        );
    },
];

try {
    if ($job === 'all') {
        foreach ($jobs as $name => $callback) {
            echo "== {$name} ==\n";
            $callback();
        }
    } elseif (isset($jobs[$job])) {
        $jobs[$job]();
    } else {
        echo "Unknown job: {$job}\nAvailable: " . implode(', ', array_keys($jobs)) . ", all\n";
        exit(1);
    }
} catch (Throwable $e) {
    $logger->error('Cron failed: ' . $e->getMessage());
    echo 'ERROR: ' . $e->getMessage() . "\n";
    exit(1);
}
