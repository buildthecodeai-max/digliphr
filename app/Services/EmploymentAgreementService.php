<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use Dompdf\Dompdf;
use Dompdf\Options;
use RuntimeException;

final class EmploymentAgreementService
{
    public const VERSION = '2026-09-v2';
    public const PAGE_COUNT = 5;
    public const CONSENT = 'I confirm that I have read, understood, and voluntarily agree to the Employment Agreement, Data Privacy and HIPAA Compliance Policy, Compensation Structure Amendment, and Workplace Professional Conduct Policy. I intend my electronic signature to be legally binding.';

    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function installed(): bool
    {
        return $this->db->tableExists('employment_agreements');
    }

    public function ensureForEmployee(int $employeeId, ?int $userId = null): ?array
    {
        if (!$this->installed()) {
            return null;
        }

        $employee = $this->db->fetch(
            'SELECT id, company_id, user_id FROM employees WHERE id = :id AND deleted_at IS NULL LIMIT 1',
            ['id' => $employeeId]
        );
        if (!$employee) {
            return null;
        }

        $existing = $this->db->fetch(
            'SELECT * FROM employment_agreements WHERE employee_id = :eid AND template_version = :version LIMIT 1',
            ['eid' => $employeeId, 'version' => self::VERSION]
        );
        $resolvedUserId = $userId ?: (!empty($employee['user_id']) ? (int) $employee['user_id'] : null);
        if ($existing) {
            if ($resolvedUserId && empty($existing['user_id'])) {
                $this->db->update('employment_agreements', ['user_id' => $resolvedUserId], 'id = :id', ['id' => $existing['id']]);
                $existing['user_id'] = $resolvedUserId;
            }

            // Recover agreements left marked accepted after their signed
            // document was removed (including records deleted before the
            // document-deletion reset hook existed).
            if (($existing['status'] ?? '') === 'accepted') {
                $documentId = (int) ($existing['signed_document_id'] ?? 0);
                $activeDocument = $documentId > 0
                    ? $this->db->fetch(
                        'SELECT id FROM employee_documents WHERE id = :id AND deleted_at IS NULL LIMIT 1',
                        ['id' => $documentId]
                    )
                    : null;
                if (!$activeDocument) {
                    $this->reopenAgreement((int) $existing['id']);
                    $existing = $this->db->fetch(
                        'SELECT * FROM employment_agreements WHERE id = :id LIMIT 1',
                        ['id' => (int) $existing['id']]
                    ) ?? $existing;
                }
            }
            return $existing;
        }

        $template = $this->templatePdfPath();
        if (!is_file($template)) {
            throw new RuntimeException('Employment agreement template is missing.');
        }

        $id = $this->db->insert('employment_agreements', [
            'employee_id' => $employeeId,
            'company_id' => (int) $employee['company_id'],
            'user_id' => $resolvedUserId,
            'template_version' => self::VERSION,
            'template_filename' => basename($template),
            'template_sha256' => hash_file('sha256', $template),
            'status' => 'pending',
            'assigned_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->db->fetch('SELECT * FROM employment_agreements WHERE id = :id', ['id' => $id]);
    }

    public function pendingForEmployee(int $employeeId, ?int $userId = null): ?array
    {
        $agreement = $this->ensureForEmployee($employeeId, $userId);
        return $agreement && ($agreement['status'] ?? '') !== 'accepted' ? $agreement : null;
    }

    public function markViewed(int $agreementId, int $employeeId): void
    {
        $this->db->query(
            'UPDATE employment_agreements SET viewed_at = COALESCE(viewed_at, :viewed), updated_at = :updated
             WHERE id = :id AND employee_id = :eid',
            ['viewed' => date('Y-m-d H:i:s'), 'updated' => date('Y-m-d H:i:s'), 'id' => $agreementId, 'eid' => $employeeId]
        );
    }

    /**
     * Reopen an accepted agreement when its generated signed document is
     * removed by an administrator. The employee middleware will then send the
     * employee back through the agreement gate on their next request/login.
     */
    public function reopenForDeletedDocument(int $documentId): bool
    {
        if (!$this->installed()) {
            return false;
        }

        $agreement = $this->db->fetch(
            'SELECT id FROM employment_agreements
             WHERE signed_document_id = :document_id AND status = :status
             LIMIT 1',
            ['document_id' => $documentId, 'status' => 'accepted']
        );
        if (!$agreement) {
            return false;
        }

        return $this->reopenAgreement((int) $agreement['id'], $documentId);
    }

    private function reopenAgreement(int $agreementId, ?int $documentId = null): bool
    {
        $where = 'id = :id AND status = :accepted';
        $params = ['id' => $agreementId, 'accepted' => 'accepted'];
        if ($documentId !== null) {
            $where .= ' AND signed_document_id = :document_id';
            $params['document_id'] = $documentId;
        }

        $updated = $this->db->update('employment_agreements', [
            'status' => 'pending',
            'assigned_at' => date('Y-m-d H:i:s'),
            'viewed_at' => null,
            'accepted_at' => null,
            'signer_name' => null,
            'signature_path' => null,
            'signed_document_id' => null,
            'consent_text' => null,
            'accepted_ip' => null,
            'accepted_user_agent' => null,
        ], $where, $params);

        return $updated === 1;
    }

    public function accept(int $employeeId, int $userId, string $signerName, string $signatureData, string $ip, string $userAgent): array
    {
        $agreement = $this->pendingForEmployee($employeeId, $userId);
        if (!$agreement) {
            throw new RuntimeException('No pending employment agreement was found.');
        }

        $signerName = trim($signerName);
        if (mb_strlen($signerName) < 2 || mb_strlen($signerName) > 191) {
            throw new RuntimeException('Enter your full legal name.');
        }
        $signature = $this->decodeSignature($signatureData);
        $employee = $this->employeeDetails($employeeId);
        if (!$employee) {
            throw new RuntimeException('Employee profile was not found.');
        }

        $baseDir = rtrim((string) config('app.paths.employment_agreements'), '/');
        $signatureDir = $baseDir . '/signatures';
        $documentDir = rtrim((string) config('app.paths.employee_documents'), '/');
        foreach ([$baseDir, $signatureDir, $documentDir] as $dir) {
            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new RuntimeException('Unable to create secure agreement storage.');
            }
        }

        $token = bin2hex(random_bytes(12));
        $signatureFilename = 'signature_' . $employeeId . '_' . $token . '.png';
        $signaturePath = $signatureDir . '/' . $signatureFilename;
        $pdfFilename = 'employment_agreement_' . preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $employee['employee_code']) . '_' . date('Ymd_His') . '.pdf';
        $pdfPath = $documentDir . '/' . $pdfFilename;
        if (file_put_contents($signaturePath, $signature, LOCK_EX) === false) {
            throw new RuntimeException('Unable to save the signature.');
        }

        try {
            $this->generateSignedPdf($employee, $signerName, $signaturePath, $pdfPath, date('Y-m-d H:i:s'));
            $this->db->beginTransaction();
            $documentId = $this->db->insert('employee_documents', [
                'employee_id' => $employeeId,
                'document_type' => 'employment_agreement',
                'title' => 'Signed Employment Agreement',
                'description' => 'Electronically accepted employment, data privacy, HIPAA compliance, compensation, and workplace conduct policies (' . self::VERSION . ').',
                'filename' => $pdfFilename,
                'original_filename' => $pdfFilename,
                'path' => $pdfFilename,
                'mime_type' => 'application/pdf',
                'file_size' => filesize($pdfPath) ?: null,
                'document_number' => 'EA-' . strtoupper(substr($token, 0, 12)),
                'issue_date' => date('Y-m-d'),
                'is_verified' => 1,
                'verified_by' => $userId,
                'verified_at' => date('Y-m-d H:i:s'),
                'uploaded_by' => $userId,
            ]);
            $acceptedAt = date('Y-m-d H:i:s');
            $updated = $this->db->update('employment_agreements', [
                'user_id' => $userId,
                'status' => 'accepted',
                'viewed_at' => $agreement['viewed_at'] ?: $acceptedAt,
                'accepted_at' => $acceptedAt,
                'signer_name' => $signerName,
                'signature_path' => 'signatures/' . $signatureFilename,
                'signed_document_id' => $documentId,
                'consent_text' => self::CONSENT,
                'accepted_ip' => substr($ip, 0, 45),
                'accepted_user_agent' => substr($userAgent, 0, 500),
            ], 'id = :id AND employee_id = :employee_id AND status = :pending', [
                'id' => $agreement['id'], 'employee_id' => $employeeId, 'pending' => 'pending',
            ]);
            if ($updated !== 1) {
                throw new RuntimeException('This agreement has already been processed. Please refresh the page.');
            }
            $this->db->commit();
            (new AuditService())->log('accept', 'employment_agreements', (int) $agreement['id'], null, [
                'employee_id' => $employeeId,
                'document_id' => $documentId,
                'template_version' => self::VERSION,
                'accepted_at' => $acceptedAt,
            ], $userId);
            return ['agreement_id' => (int) $agreement['id'], 'document_id' => $documentId];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            @unlink($signaturePath);
            @unlink($pdfPath);
            throw $e;
        }
    }

    public function templatePdfPath(): string
    {
        return rtrim((string) config('app.paths.employment_agreements'), '/') . '/templates/employment-agreement-v2.pdf';
    }

    private function decodeSignature(string $data): string
    {
        if (!preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $data, $matches)) {
            throw new RuntimeException('Draw your signature before agreeing.');
        }
        $binary = base64_decode($matches[1], true);
        if ($binary === false || strlen($binary) < 300 || strlen($binary) > 1000000) {
            throw new RuntimeException('The signature image is invalid.');
        }
        $info = @getimagesizefromstring($binary);
        if (!$info || ($info['mime'] ?? '') !== 'image/png' || $info[0] < 100 || $info[1] < 40) {
            throw new RuntimeException('The signature image is invalid.');
        }
        return $binary;
    }

    private function employeeDetails(int $employeeId): ?array
    {
        return $this->db->fetch(
            'SELECT e.*, d.name AS designation_name, c.name AS company_name
             FROM employees e
             LEFT JOIN designations d ON d.id = e.designation_id
             INNER JOIN companies c ON c.id = e.company_id
             WHERE e.id = :id AND e.deleted_at IS NULL LIMIT 1',
            ['id' => $employeeId]
        );
    }

    private function generateSignedPdf(array $employee, string $signerName, string $signaturePath, string $outputPath, string $acceptedAt): void
    {
        $templateDir = rtrim((string) config('app.paths.employment_agreements'), '/') . '/templates';
        $pages = [];
        for ($i = 1; $i <= self::PAGE_COUNT; $i++) {
            $path = $templateDir . '/employment-agreement-v2-page-' . $i . '.png';
            if (!is_file($path)) {
                throw new RuntimeException('Employment agreement page assets are missing.');
            }
            $pages[$i] = 'data:image/png;base64,' . base64_encode((string) file_get_contents($path));
        }
        $signatureData = 'data:image/png;base64,' . base64_encode((string) file_get_contents($signaturePath));
        $name = htmlspecialchars($signerName, ENT_QUOTES, 'UTF-8');
        $code = htmlspecialchars((string) $employee['employee_code'], ENT_QUOTES, 'UTF-8');
        $cnic = htmlspecialchars((string) ($employee['national_id'] ?: 'Not provided'), ENT_QUOTES, 'UTF-8');
        $designation = htmlspecialchars((string) ($employee['designation_name'] ?: 'Not specified'), ENT_QUOTES, 'UTF-8');
        $joining = htmlspecialchars((string) ($employee['joining_date'] ?: 'Not specified'), ENT_QUOTES, 'UTF-8');
        $accepted = htmlspecialchars($acceptedAt, ENT_QUOTES, 'UTF-8');
        $consent = htmlspecialchars(self::CONSENT, ENT_QUOTES, 'UTF-8');
        $pageHtml = '';
        foreach ($pages as $number => $page) {
            $overlay = '';
            if ($number === 3) {
                $overlay = '<div class="pdf-field page3-name">' . $name . '</div>'
                    . '<div class="pdf-field page3-cnic">' . $cnic . '</div>'
                    . '<div class="pdf-field page3-designation">' . $designation . '</div>'
                    . '<div class="pdf-field page3-joining">' . $joining . '</div>'
                    . '<img class="pdf-signature page3-signature" src="' . $signatureData . '">'
                    . '<div class="pdf-field page3-date">' . date('Y-m-d', strtotime($acceptedAt)) . '</div>';
            } elseif ($number === self::PAGE_COUNT) {
                $overlay = '<div class="pdf-field page5-name">' . $name . '</div>'
                    . '<img class="pdf-signature page5-signature" src="' . $signatureData . '">'
                    . '<div class="pdf-field page5-date">' . date('Y-m-d', strtotime($acceptedAt)) . '</div>';
            }
            $pageHtml .= '<section class="agreement-page"><img class="page-image" src="' . $page . '">' . $overlay . '</section>';
        }
        $html = '<!doctype html><html><head><meta charset="utf-8"><style>
            @page{size:612pt 792pt;margin:0}body{margin:0;font-family:DejaVu Sans,sans-serif;color:#111827}.agreement-page{position:relative;width:612pt;height:792pt;page-break-after:always;overflow:hidden}.page-image{position:absolute;inset:0;width:612pt;height:792pt}.pdf-field{position:absolute;font-size:8.5pt;line-height:9pt;background:#fff;padding:0 2pt;white-space:nowrap}.pdf-signature{position:absolute;object-fit:contain;object-position:left bottom}.page3-name{left:140pt;top:640pt}.page3-cnic{left:78pt;top:661pt}.page3-designation{left:114pt;top:684pt}.page3-joining{left:136pt;top:706pt}.page3-signature{left:458pt;top:631pt;width:104pt;height:20pt}.page3-date{left:374pt;top:661pt}.page5-name{left:140pt;top:630pt}.page5-signature{left:106pt;top:657pt;width:138pt;height:19pt}.page5-date{left:81pt;top:708pt}.certificate{padding:54pt;page-break-after:auto}.certificate h1{font-size:24pt;color:#0f172a;margin:0 0 8pt}.rule{height:3pt;background:#2563eb;margin:0 0 28pt}.certificate table{width:100%;border-collapse:collapse;margin:22pt 0}.certificate td{padding:9pt;border-bottom:1pt solid #cbd5e1;font-size:10pt}.certificate td:first-child{width:34%;font-weight:bold;color:#475569}.signature-box{margin-top:28pt;padding:18pt;border:1pt solid #cbd5e1;background:#f8fafc}.signature-box img{width:180pt;height:58pt;object-fit:contain}.consent{font-size:9pt;line-height:1.55;color:#475569}.hash{font-family:monospace;font-size:7pt;word-break:break-all;color:#64748b}
            </style></head><body>' . $pageHtml . '<section class="certificate"><div class="rule"></div><h1>Electronic Acceptance Certificate</h1><p>This certificate forms part of the attached Employment Agreement.</p><table><tr><td>Employee</td><td>' . $name . '</td></tr><tr><td>Employee code</td><td>' . $code . '</td></tr><tr><td>Designation</td><td>' . $designation . '</td></tr><tr><td>Date of joining</td><td>' . $joining . '</td></tr><tr><td>Accepted at</td><td>' . $accepted . ' (' . htmlspecialchars((string) config('app.timezone'), ENT_QUOTES, 'UTF-8') . ')</td></tr><tr><td>Agreement version</td><td>' . self::VERSION . '</td></tr></table><div class="signature-box"><strong>Employee digital signature</strong><br><img src="' . $signatureData . '"><br><strong>' . $name . '</strong></div><p class="consent">' . $consent . '</p><p class="hash">Template SHA-256: ' . hash_file('sha256', $this->templatePdfPath()) . '</p></section></body></html>';

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();
        if (file_put_contents($outputPath, $dompdf->output(), LOCK_EX) === false) {
            throw new RuntimeException('Unable to save the signed agreement.');
        }
    }
}
