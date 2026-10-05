<?php
$action = '/admin/reports/documents';
$exportType = 'documents';
include config('app.paths.views') . '/partials/report-header.php';
include config('app.paths.views') . '/partials/report-filters.php';
$s = $analytics['summary'] ?? [];
$showCharts = in_array($viewMode, ['graphical', 'combined'], true);
$showTables = in_array($viewMode, ['table', 'combined'], true);
?>
<?php if ($showCharts): ?>
<div class="card ems-card mb-3"><div class="card-header">Documents by Category</div><div class="card-body chart-panel"><canvas id="docTypes"></canvas></div></div>
<?php endif; ?>

<?php if ($showTables): ?>
<div class="card ems-card">
    <div class="card-header">Document Register</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead><tr><th>Title</th><th>Type</th><th>Employee</th><th>Issued</th><th>Expires</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($analytics['rows'])): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No documents found.</td></tr>
            <?php else: foreach ($analytics['rows'] as $r): ?>
                <tr>
                    <td><?= e($r['title']) ?></td>
                    <td><?= e(ucwords(str_replace('_',' ', (string)$r['document_type']))) ?></td>
                    <td><?= e($r['employee_name']) ?></td>
                    <td><?= e(format_date($r['issue_date'] ?? null)) ?></td>
                    <td><?= e(format_date($r['expiry_date'] ?? null)) ?></td>
                    <td class="text-end text-nowrap">
                        <?php
                        $actions = [
                            [
                                'type' => 'link',
                                'icon' => 'eye',
                                'label' => 'View document',
                                'href' => '/files/document/' . (int) $r['id'],
                                'attrs' => ['target' => '_blank', 'rel' => 'noopener'],
                            ],
                            ['type' => 'link', 'icon' => 'download', 'label' => 'Download document', 'href' => '/files/document/' . (int) $r['id'] . '?download=1'],
                        ];
                        include config('app.paths.views') . '/partials/table-actions.php';
                        ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function(){
    const a = <?= json_encode($analytics, JSON_UNESCAPED_UNICODE) ?>;
    if (!window.EMSCharts) return;
    EMSCharts.doughnut('docTypes', (a.by_type||[]).map(r=>r.label), (a.by_type||[]).map(r=>Number(r.total)));
});
</script>
