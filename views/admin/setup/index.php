<?php $company = $setup['company']; ?>
<div class="page-header d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div><h1>Set up <?= e($company['name'] ?? 'your company') ?></h1><p class="subtitle">Complete the essentials before inviting the wider team.</p></div>
    <?php if (count($companies) > 1): ?><form method="GET" class="d-flex gap-2"><select class="form-select form-select-sm" name="company_id" onchange="this.form.submit()"><?php foreach ($companies as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$c['id']===(int)$company['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></form><?php endif; ?>
</div>
<?php include config('app.paths.views') . '/partials/alerts.php'; ?>
<div class="card ems-card mb-3 setup-hero"><div class="card-body p-4"><div class="d-flex justify-content-between align-items-center mb-2"><strong><?= $setup['completed'] ?> of <?= $setup['total'] ?> complete</strong><span class="filter-chip"><?= $setup['percent'] ?>%</span></div><div class="progress-soft"><span style="width:<?= $setup['percent'] ?>%"></span></div><?php if ($setup['is_complete']): ?><div class="alert alert-success mt-3 mb-0"><i data-lucide="party-popper" class="me-2"></i>Your company foundation is ready.</div><?php endif; ?></div></div>
<div class="row g-3">
<?php foreach ($setup['items'] as $index => $item): ?>
<div class="col-lg-6"><a href="<?= e($item['href']) ?>" class="card ems-card setup-step text-decoration-none h-100 <?= $item['done'] ? 'is-complete' : '' ?>"><div class="card-body d-flex gap-3"><span class="setup-step-icon"><i data-lucide="<?= e($item['done'] ? 'check' : $item['icon']) ?>"></i></span><div><div class="small text-secondary mb-1">Step <?= $index + 1 ?></div><strong class="text-body"><?= e($item['label']) ?></strong><p class="small text-secondary mb-0 mt-1"><?= e($item['description']) ?></p></div><i data-lucide="chevron-right" class="ms-auto text-secondary"></i></div></a></div>
<?php endforeach; ?>
</div>
