<?php
/**
 * <Badge /> — status chip. Prefer status_badge() in app/Helpers/functions.php
 * for known attendance/leave/payroll statuses; use this for freeform badges.
 *
 *   ui('badge', ['label' => 'Beta', 'variant' => 'info']);
 *
 * @var string $label
 * @var string $variant primary|success|danger|warning|info|secondary
 * @var bool   $live    Adds a pulsing dot (e.g. "Live", "Recording").
 */

$label   = $label   ?? '';
$variant = $variant ?? 'secondary';
$live    = !empty($live);

$map = [
    'primary'   => 'primary',
    'success'   => 'success',
    'danger'    => 'danger',
    'warning'   => 'warning',
    'info'      => 'info',
    'secondary' => 'secondary',
];
$bsVariant = $map[$variant] ?? 'secondary';
?>
<span class="badge bg-<?= e($bsVariant) ?><?= $live ? ' ds-badge-pulse' : '' ?>"><?= e($label) ?></span>
