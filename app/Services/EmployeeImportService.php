<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Employee;
use RuntimeException;

final class EmployeeImportService
{
    public const HEADERS = ['employee_code', 'first_name', 'last_name', 'company_email', 'phone', 'joining_date', 'employment_type', 'employment_status', 'basic_salary', 'branch_code', 'department_code', 'designation_code', 'shift_code'];

    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function preview(array $file, int $companyId, int $userId): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
            throw new RuntimeException('Choose a CSV file to preview.');
        }
        if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
            throw new RuntimeException('CSV files must be 5 MB or smaller.');
        }
        $handle = fopen((string) $file['tmp_name'], 'rb');
        if (!$handle) {
            throw new RuntimeException('The uploaded CSV could not be read.');
        }
        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            throw new RuntimeException('The CSV is empty.');
        }
        $header = array_map(static fn (string $value): string => strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', $value), '_')), $header);
        foreach (['first_name', 'last_name', 'joining_date'] as $required) {
            if (!in_array($required, $header, true)) {
                fclose($handle);
                throw new RuntimeException("Missing required column: {$required}.");
            }
        }

        $lookups = $this->lookups($companyId);
        $rows = [];
        $errors = [];
        $line = 1;
        while (($values = fgetcsv($handle)) !== false) {
            $line++;
            if ($line > 501) {
                $errors[] = ['row' => $line, 'employee_code' => '', 'message' => 'Only the first 500 data rows can be imported at once.'];
                break;
            }
            if (count(array_filter($values, static fn (mixed $value): bool => trim((string) $value) !== '')) === 0) {
                continue;
            }
            $values = array_pad($values, count($header), '');
            $row = array_combine($header, array_slice($values, 0, count($header))) ?: [];
            $row = array_map(static fn (mixed $value): string => trim((string) $value), $row);
            [$clean, $rowErrors] = $this->validateRow($row, $line, $companyId, $lookups);
            $rows[] = ['row' => $line, 'data' => $clean, 'errors' => $rowErrors];
            foreach ($rowErrors as $message) {
                $errors[] = ['row' => $line, 'employee_code' => $row['employee_code'] ?? '', 'message' => $message];
            }
        }
        fclose($handle);
        $valid = array_values(array_filter($rows, static fn (array $row): bool => $row['errors'] === []));
        $batchId = $this->db->insert('employee_import_batches', [
            'company_id' => $companyId,
            'uploaded_by' => $userId,
            'original_filename' => basename((string) ($file['name'] ?? 'employees.csv')),
            'status' => 'previewed',
            'total_rows' => count($rows),
            'valid_rows' => count($valid),
            'error_rows' => count($rows) - count($valid),
            'preview_data' => json_encode($rows, JSON_UNESCAPED_UNICODE),
            'error_data' => json_encode($errors, JSON_UNESCAPED_UNICODE),
        ]);
        return $this->batch($batchId, $companyId);
    }

    public function import(int $batchId, int $companyId, int $userId): array
    {
        $batch = $this->batch($batchId, $companyId);
        if ($batch['status'] !== 'previewed') {
            throw new RuntimeException('This import batch has already been processed.');
        }
        $rows = json_decode((string) ($batch['preview_data'] ?? '[]'), true) ?: [];
        $valid = array_values(array_filter($rows, static fn (array $row): bool => empty($row['errors'])));
        if ($valid === []) {
            throw new RuntimeException('There are no valid rows to import.');
        }
        $this->db->update('employee_import_batches', ['status' => 'importing'], 'id = :id', ['id' => $batchId]);
        $employeeModel = new Employee();
        $imported = 0;
        $runtimeErrors = json_decode((string) ($batch['error_data'] ?? '[]'), true) ?: [];
        foreach ($valid as $entry) {
            try {
                $data = $entry['data'];
                $data['uuid'] = $this->uuid();
                $data['employee_code'] = $data['employee_code'] ?: $employeeModel->nextCode('EMP', $companyId);
                $employeeModel->create($data);
                $imported++;
            } catch (\Throwable $e) {
                $runtimeErrors[] = ['row' => $entry['row'], 'employee_code' => $entry['data']['employee_code'] ?? '', 'message' => str_contains($e->getMessage(), 'Duplicate') ? 'A unique value already exists.' : 'Database validation failed.'];
            }
        }
        $status = $runtimeErrors === [] ? 'completed' : 'completed_with_errors';
        $this->db->update('employee_import_batches', [
            'status' => $status,
            'imported_rows' => $imported,
            'error_rows' => count($runtimeErrors),
            'error_data' => json_encode($runtimeErrors, JSON_UNESCAPED_UNICODE),
            'completed_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $batchId]);
        (new AuditService())->log('import', 'employees', null, null, ['company_id' => $companyId, 'batch_id' => $batchId, 'imported' => $imported, 'errors' => count($runtimeErrors)], $userId);
        return $this->batch($batchId, $companyId);
    }

    public function batch(int $id, int $companyId): array
    {
        $batch = $this->db->fetch('SELECT * FROM employee_import_batches WHERE id = :id AND company_id = :cid', ['id' => $id, 'cid' => $companyId]);
        if (!$batch) {
            throw new RuntimeException('Import batch not found.');
        }
        $batch['rows'] = json_decode((string) ($batch['preview_data'] ?? '[]'), true) ?: [];
        $batch['errors'] = json_decode((string) ($batch['error_data'] ?? '[]'), true) ?: [];
        return $batch;
    }

    private function validateRow(array $row, int $line, int $companyId, array $lookups): array
    {
        $errors = [];
        $first = trim((string) ($row['first_name'] ?? ''));
        $last = trim((string) ($row['last_name'] ?? ''));
        $joining = trim((string) ($row['joining_date'] ?? ''));
        if (mb_strlen($first) < 2) $errors[] = 'First name must contain at least 2 characters.';
        if (mb_strlen($last) < 2) $errors[] = 'Last name must contain at least 2 characters.';
        if (!$this->validDate($joining)) $errors[] = 'Joining date must use YYYY-MM-DD.';
        $email = trim((string) ($row['company_email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Company email is invalid.';
        $type = $row['employment_type'] ?: 'full_time';
        if (!in_array($type, ['full_time','part_time','contract','intern','temporary','consultant'], true)) $errors[] = 'Employment type is invalid.';
        $status = $row['employment_status'] ?: 'active';
        if (!in_array($status, ['active','probation','notice_period','suspended','terminated','resigned','retired','inactive'], true)) $errors[] = 'Employment status is invalid.';
        $code = trim((string) ($row['employee_code'] ?? ''));
        if ($code !== '' && $this->db->fetchColumn('SELECT COUNT(*) FROM employees WHERE company_id = :cid AND employee_code = :code', ['cid' => $companyId, 'code' => $code])) $errors[] = 'Employee code already exists.';
        $data = [
            'company_id' => $companyId,
            'employee_code' => $code,
            'first_name' => $first,
            'last_name' => $last,
            'company_email' => $email !== '' ? $email : null,
            'phone' => ($row['phone'] ?? '') !== '' ? $row['phone'] : null,
            'joining_date' => $joining,
            'employment_type' => $type,
            'employment_status' => $status,
            'basic_salary' => is_numeric($row['basic_salary'] ?? '') ? max(0, (float) $row['basic_salary']) : 0,
            'branch_id' => $this->lookupId($row, 'branch_code', $lookups['branch'], $errors, 'Branch'),
            'department_id' => $this->lookupId($row, 'department_code', $lookups['department'], $errors, 'Department'),
            'designation_id' => $this->lookupId($row, 'designation_code', $lookups['designation'], $errors, 'Designation'),
            'shift_id' => $this->lookupId($row, 'shift_code', $lookups['shift'], $errors, 'Shift'),
            'remote_attendance_allowed' => 0,
        ];
        return [$data, $errors];
    }

    private function lookupId(array $row, string $field, array $lookup, array &$errors, string $label): ?int
    {
        $code = strtoupper(trim((string) ($row[$field] ?? '')));
        if ($code === '') return null;
        if (!isset($lookup[$code])) {
            $errors[] = "{$label} code '{$code}' was not found.";
            return null;
        }
        return (int) $lookup[$code];
    }

    private function lookups(int $companyId): array
    {
        $result = [];
        foreach (['branch' => 'branches', 'department' => 'departments', 'designation' => 'designations', 'shift' => 'shifts'] as $key => $table) {
            $rows = $this->db->fetchAll("SELECT id, code FROM {$table} WHERE company_id = :cid AND deleted_at IS NULL AND code IS NOT NULL", ['cid' => $companyId]);
            $result[$key] = [];
            foreach ($rows as $row) $result[$key][strtoupper((string) $row['code'])] = (int) $row['id'];
        }
        return $result;
    }

    private function validDate(string $date): bool
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $parsed && $parsed->format('Y-m-d') === $date;
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
