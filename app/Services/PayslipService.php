<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\PayrollRecord;
use Dompdf\Dompdf;
use Dompdf\Options;
use RuntimeException;

class PayslipService
{
    private Database $db;
    private PayrollRecord $records;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->records = new PayrollRecord();
    }

    public function generate(int $recordId, ?int $userId = null): array
    {
        $record = $this->records->findDetailed($recordId);
        if (!$record) {
            throw new RuntimeException('Payroll record not found.');
        }

        $earnings = $this->db->fetchAll(
            'SELECT * FROM payroll_earnings WHERE payroll_record_id = :id ORDER BY id',
            ['id' => $recordId]
        );
        $deductions = $this->db->fetchAll(
            'SELECT * FROM payroll_deductions WHERE payroll_record_id = :id ORDER BY id',
            ['id' => $recordId]
        );

        $snapshot = [
            'record' => $record,
            'earnings' => $earnings,
            'deductions' => $deductions,
            'generated_at' => date('Y-m-d H:i:s'),
        ];

        $html = $this->renderHtml($record, $earnings, $deductions);
        $pdfPath = $this->generatePdf($record, $html);

        $existing = $this->db->fetch(
            'SELECT id FROM payslips WHERE payroll_record_id = :rid LIMIT 1',
            ['rid' => $recordId]
        );

        $payslipNumber = 'PS-' . $record['period_year'] . str_pad((string) $record['period_month'], 2, '0', STR_PAD_LEFT) . '-' . $record['employee_code'];
        $data = [
            'payroll_record_id' => $recordId,
            'employee_id' => (int) $record['employee_id'],
            'payroll_period_id' => (int) $record['payroll_period_id'],
            'payslip_number' => $payslipNumber,
            'issue_date' => date('Y-m-d'),
            'gross_earnings' => $record['gross_earnings'],
            'total_deductions' => $record['total_deductions'],
            'net_salary' => $record['net_salary'],
            'currency' => $record['currency'],
            'file_path' => $pdfPath,
            'file_mime' => 'application/pdf',
            'status' => 'generated',
            'snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
            'generated_by' => $userId,
        ];

        if ($existing) {
            $this->db->update('payslips', $data, 'id = :id', ['id' => $existing['id']]);
            $payslipId = (int) $existing['id'];
        } else {
            $data['uuid'] = $this->uuid();
            $payslipId = $this->db->insert('payslips', $data);
        }

        try {
            $periodLabel = ($record['period_year'] ?? '') . '-' . str_pad((string) ($record['period_month'] ?? 0), 2, '0', STR_PAD_LEFT);
            (new \App\Services\Chat\ChatHrBridge())->notifyPayslipReady((int) $record['employee_id'], $periodLabel);
        } catch (\Throwable) {
        }

        return ['payslip_id' => $payslipId, 'file_path' => $pdfPath, 'payslip_number' => $payslipNumber];
    }

    public function generateForPeriod(int $periodId, ?int $userId = null): int
    {
        $records = $this->records->listByPeriod($periodId);
        $count = 0;
        foreach ($records as $record) {
            $this->generate((int) $record['id'], $userId);
            $count++;
        }
        return $count;
    }

    private function renderHtml(array $record, array $earnings, array $deductions): string
    {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <style>
                body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1e293b; }
                .header { border-bottom: 2px solid #2563eb; padding-bottom: 10px; margin-bottom: 20px; }
                .title { font-size: 18px; font-weight: bold; color: #2563eb; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
                th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; }
                th { background: #f1f5f9; }
                .text-right { text-align: right; }
                .summary { background: #f8fafc; padding: 10px; border: 1px solid #cbd5e1; }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="title"><?= htmlspecialchars((string) $record['company_name']) ?></div>
                <div>Payslip — <?= htmlspecialchars((string) $record['period_name']) ?></div>
            </div>
            <table>
                <tr><th>Employee</th><td><?= htmlspecialchars(trim($record['first_name'] . ' ' . $record['last_name'])) ?></td>
                    <th>Code</th><td><?= htmlspecialchars((string) $record['employee_code']) ?></td></tr>
                <tr><th>Period</th><td><?= htmlspecialchars(format_date($record['period_start'])) ?> — <?= htmlspecialchars(format_date($record['period_end'])) ?></td>
                    <th>Issue Date</th><td><?= date('Y-m-d') ?></td></tr>
            </table>
            <h4>Earnings</h4>
            <table>
                <thead><tr><th>Component</th><th class="text-right">Amount</th></tr></thead>
                <tbody>
                <?php foreach ($earnings as $e): ?>
                    <tr><td><?= htmlspecialchars((string) $e['component_name']) ?></td>
                        <td class="text-right"><?= format_money($e['amount'], $record['currency']) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <h4>Deductions</h4>
            <table>
                <thead><tr><th>Component</th><th class="text-right">Amount</th></tr></thead>
                <tbody>
                <?php foreach ($deductions as $d): ?>
                    <tr><td><?= htmlspecialchars((string) $d['component_name']) ?></td>
                        <td class="text-right"><?= format_money($d['amount'], $record['currency']) ?></td></tr>
                <?php endforeach; ?>
                <?php if (empty($deductions)): ?>
                    <tr><td colspan="2">No deductions</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            <div class="summary">
                <strong>Gross:</strong> <?= format_money($record['gross_earnings'], $record['currency']) ?> &nbsp;|&nbsp;
                <strong>Deductions:</strong> <?= format_money($record['total_deductions'], $record['currency']) ?> &nbsp;|&nbsp;
                <strong>Net Pay:</strong> <?= format_money($record['net_salary'], $record['currency']) ?>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean() ?: '';
    }

    private function generatePdf(array $record, string $html): string
    {
        $dir = config('app.paths.payslips');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = 'payslip_' . $record['employee_code'] . '_' . $record['payroll_period_id'] . '.pdf';
        $path = $dir . '/' . $filename;

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        file_put_contents($path, $dompdf->output());

        return $path;
    }

    private function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
