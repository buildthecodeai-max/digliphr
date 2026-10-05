<?php
/** @var array $sheet */
$dates = $sheet['dates'] ?? [];
$employees = $sheet['employees'] ?? [];
$legendLabels = [
    'P' => 'Present', 'L' => 'Late', 'H' => 'Half Day', 'A' => 'Absent', 'PL' => 'Paid Leave',
    'UL' => 'Unpaid Leave', 'SL' => 'Sick Leave', 'HO' => 'Holiday', 'R' => 'Rest Day',
    'WFH' => 'Work From Home', 'OD' => 'Official Duty', 'MA' => 'Missing Attendance', 'PR' => 'Pending Regularization',
];
$pillColors = [
    'P' => ['#DCFCE7', '#15803D'], 'WFH' => ['#DCFCE7', '#15803D'], 'OD' => ['#E0E7FF', '#3730A3'],
    'L' => ['#FEF3C7', '#B45309'], 'H' => ['#FEF3C7', '#B45309'],
    'A' => ['#FEE2E2', '#B91C1C'], 'MA' => ['#FEE2E2', '#B91C1C'],
    'PL' => ['#DBEAFE', '#1D4ED8'], 'UL' => ['#FCE7F3', '#BE185D'], 'SL' => ['#DBEAFE', '#1D4ED8'],
    'HO' => ['#F1F5F9', '#475569'], 'R' => ['#F1F5F9', '#94A3B8'], 'PR' => ['#FEF3C7', '#B45309'],
];
?>
<div class="card ems-card mb-3 d-print-none">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="small text-muted">Printable monthly matrix — <?= e(format_date($filters['from'])) ?> to <?= e(format_date($filters['to'])) ?></div>
        <button class="btn btn-sm btn-soft" onclick="window.print()"><i data-lucide="printer" class="me-1" style="width:14px;height:14px"></i>Print</button>
    </div>
</div>

<div class="card ems-card">
    <div class="card-header report-card-title"><span class="rct-icon"><i data-lucide="table"></i></span>Monthly Attendance Sheet</div>
    <div class="report-table-shell">
        <table class="table table-sm mb-0 align-middle monthly-sheet-table">
            <thead>
            <tr>
                <th style="min-width:170px">Employee</th>
                <?php foreach ($dates as $d): ?><th class="text-center" title="<?= e($d) ?>"><?= e(date('j', strtotime($d))) ?></th><?php endforeach; ?>
                <th class="text-end">P</th><th class="text-end">A</th><th class="text-end">L</th><th class="text-end">Late</th><th class="text-end">OT Hrs</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$employees): ?>
                <tr><td colspan="<?= count($dates) + 6 ?>"><div class="empty-state mb-0 py-4">No employees match the selected filters.</div></td></tr>
            <?php else: foreach ($employees as $row): $s = $row['summary']; ?>
                <tr>
                    <td><div class="fw-semibold small"><?= e($row['employee_name']) ?></div><div class="text-muted" style="font-size:.7rem"><?= e($row['employee_code']) ?></div></td>
                    <?php foreach ($row['cells'] as $cell): [$bg, $fg] = $pillColors[$cell] ?? ['transparent', 'var(--ems-text-muted)']; ?>
                        <td class="text-center">
                            <?php if ($cell): ?><span class="sheet-pill" style="--pill-bg: <?= e($bg) ?>; --pill-fg: <?= e($fg) ?>"><?= e($cell) ?></span><?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                    <td class="text-end fw-semibold"><?= number_format($s['present_days'], 1) ?></td>
                    <td class="text-end"><?= number_format($s['absent_days'], 1) ?></td>
                    <td class="text-end"><?= number_format(($s['paid_leave_days'] ?? 0) + ($s['unpaid_leave_days'] ?? 0), 1) ?></td>
                    <td class="text-end"><?= (int) $s['late_days'] ?></td>
                    <td class="text-end"><?= number_format(($s['overtime_minutes'] ?? 0) / 60, 1) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white">
        <div class="small text-muted fw-semibold mb-2">Legend</div>
        <div class="sheet-legend">
            <?php foreach ($legendLabels as $code => $label): [$bg, $fg] = $pillColors[$code] ?? ['transparent', 'var(--ems-text-muted)']; ?>
                <span class="sheet-legend-item"><span class="sheet-pill" style="--pill-bg: <?= e($bg) ?>; --pill-fg: <?= e($fg) ?>"><?= e($code) ?></span><?= e($label) ?></span>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<style>@media print { .d-print-none { display: none !important; } }</style>
<script>if (window.lucide) lucide.createIcons();</script>
