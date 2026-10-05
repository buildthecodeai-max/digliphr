<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Database;

/**
 * Receives ADMS push data from ZKTeco / compatible fingerprint & RFID devices.
 *
 * Configure the device:
 *   Server address: your-domain.com
 *   Server port:    80 (or 443 for HTTPS)
 *   Server path:    /iclock/cdata
 *
 * ZKTeco InOutStatus codes → punch_type:
 *   0 = Check-In, 1 = Check-Out, 2 = Break-Out, 3 = Break-In,
 *   4 = OT-In,    5 = OT-Out
 *
 * ZKTeco VerifyCode → verify_type:
 *   0=other, 1=fingerprint, 4=card/RFID, 15=face
 */
class ZKTecoController extends Controller
{
    private Database $db;

    public function __construct(
        \App\Core\Request $request,
        \App\Core\Response $response
    ) {
        parent::__construct($request, $response);
        $this->db = Database::getInstance();
    }

    // GET /iclock/cdata  — device startup handshake
    public function handshake(): void
    {
        $sn = trim((string) ($_GET['SN'] ?? ''));
        if ($sn !== '') {
            $device = $this->findDevice($sn);
            if ($device) {
                $this->db->query(
                    'UPDATE biometric_devices SET last_seen_at = NOW() WHERE id = :id',
                    ['id' => $device['id']]
                );
            }
        }

        header('Content-Type: text/plain');
        echo "GET OPTION FROM: $sn\n";
        echo "ATTLOGStamp=None\n";
        echo "OPERLOGStamp=9999\n";
        echo "ATTPHOTOStamp=None\n";
        echo "ErrorDelay=30\n";
        echo "Delay=10\n";
        echo "TransTimes=00:00;14:05\n";
        echo "TransInterval=1\n";
        echo "TransFlag=31\n";
        echo "Realtime=1\n";
        echo "Encrypt=None\n";
        exit;
    }

    // POST /iclock/cdata  — device pushes attendance records
    public function push(): void
    {
        $sn    = trim((string) ($_GET['SN'] ?? ''));
        $table = strtoupper(trim((string) ($_GET['table'] ?? '')));

        header('Content-Type: text/plain');

        $device = $this->findDevice($sn);
        if ($device) {
            $this->db->query(
                'UPDATE biometric_devices SET last_seen_at = NOW() WHERE id = :id',
                ['id' => $device['id']]
            );
        }

        if ($table === 'ATTLOG') {
            $body = (string) file_get_contents('php://input');
            foreach (explode("\n", $body) as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $parts = explode("\t", $line);
                if (count($parts) < 2) {
                    continue;
                }
                $pin        = trim($parts[0]);
                $rawDt      = trim($parts[1]);
                $verifyCode = (int) ($parts[2] ?? 0);
                $inOut      = (int) ($parts[3] ?? 0);
                $this->processPunch($device, $sn, $pin, $rawDt, $verifyCode, $inOut, $line);
            }
        }

        echo "OK";
        exit;
    }

    // GET /iclock/getrequest  — device polls for pending commands
    public function getRequest(): void
    {
        header('Content-Type: text/plain');
        echo "OK";
        exit;
    }

    // POST /iclock/devicecmd  — device reports command result
    public function deviceCmd(): void
    {
        header('Content-Type: text/plain');
        echo "OK";
        exit;
    }

    // ─── private helpers ─────────────────────────────────────────────────────

    private function findDevice(string $sn): ?array
    {
        if ($sn === '') {
            return null;
        }
        $d = $this->db->fetch(
            "SELECT * FROM biometric_devices WHERE serial_number = :sn AND status = 'active' AND deleted_at IS NULL LIMIT 1",
            ['sn' => $sn]
        );
        return $d ?: null;
    }

    private function processPunch(
        ?array $device,
        string $sn,
        string $pin,
        string $rawDt,
        int $verifyCode,
        int $inOut,
        string $rawLine
    ): void {
        $punchTime  = date('Y-m-d H:i:s', strtotime($rawDt)) ?: date('Y-m-d H:i:s');
        $punchDate  = date('Y-m-d', strtotime($punchTime));
        $companyId  = $device ? (int) $device['company_id'] : null;
        $deviceId   = $device ? (int) $device['id'] : null;
        $deviceName = $device['name'] ?? $sn;

        $verifyType = match ($verifyCode) {
            1  => 'fingerprint',
            4  => 'card',
            15 => 'face',
            default => 'other',
        };

        $punchType = match ($inOut) {
            0 => 'check_in',
            1 => 'check_out',
            2 => 'break_out',
            3 => 'break_in',
            4 => 'overtime_in',
            5 => 'overtime_out',
            default => 'check_in',
        };

        // Lookup employee by device_pin in this company
        $employee = null;
        if ($companyId) {
            $employee = $this->db->fetch(
                'SELECT * FROM employees WHERE device_pin = :pin AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
                ['pin' => $pin, 'cid' => $companyId]
            );
        }

        $employeeId = $employee ? (int) $employee['id'] : null;

        // Log the raw punch
        $logId = $this->db->insert('biometric_device_logs', [
            'device_id'     => $deviceId,
            'serial_number' => $sn,
            'company_id'    => $companyId,
            'employee_id'   => $employeeId,
            'device_pin'    => $pin,
            'punch_time'    => $punchTime,
            'punch_type'    => $punchType,
            'verify_type'   => $verifyType,
            'raw_data'      => $rawLine,
            'status'        => $employeeId ? 'matched' : 'unmatched',
        ]);

        if (!$employeeId || !$companyId) {
            return;
        }

        $notes = 'Device: ' . $deviceName . ' / ' . $verifyType;

        if ($punchType === 'check_in') {
            // Skip if already checked in today
            $already = $this->db->fetch(
                'SELECT id FROM attendance WHERE employee_id = :eid AND attendance_date = :dt AND deleted_at IS NULL LIMIT 1',
                ['eid' => $employeeId, 'dt' => $punchDate]
            );
            if ($already) {
                return;
            }
            $uuid = $this->makeUuid();
            $this->db->insert('attendance', [
                'uuid'            => $uuid,
                'company_id'      => $companyId,
                'employee_id'     => $employeeId,
                'attendance_date' => $punchDate,
                'check_in_at'     => $punchTime,
                'source'          => 'biometric',
                'status'          => 'present',
                'verification_status' => 'auto_verified',
                'remarks'         => $notes,
                'created_at'      => date('Y-m-d H:i:s'),
                'updated_at'      => date('Y-m-d H:i:s'),
            ]);
            $this->db->query(
                "UPDATE biometric_device_logs SET status = 'processed' WHERE id = :id",
                ['id' => $logId]
            );
        } elseif ($punchType === 'check_out') {
            $active = $this->db->fetch(
                'SELECT id FROM attendance WHERE employee_id = :eid AND attendance_date = :dt AND check_out_at IS NULL AND deleted_at IS NULL ORDER BY id DESC LIMIT 1',
                ['eid' => $employeeId, 'dt' => $punchDate]
            );
            if ($active) {
                $checkIn = $this->db->fetch(
                    'SELECT check_in_at FROM attendance WHERE id = :id',
                    ['id' => $active['id']]
                );
                $workMins = 0;
                if ($checkIn && $checkIn['check_in_at']) {
                    $workMins = (int) round(
                        (strtotime($punchTime) - strtotime($checkIn['check_in_at'])) / 60
                    );
                }
                $this->db->query(
                    'UPDATE attendance SET check_out_at = :co, work_minutes = :wm, updated_at = NOW() WHERE id = :id',
                    ['co' => $punchTime, 'wm' => $workMins, 'id' => $active['id']]
                );
                $this->db->query(
                    "UPDATE biometric_device_logs SET status = 'processed' WHERE id = :id",
                    ['id' => $logId]
                );
            }
        }
    }

    private function makeUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
