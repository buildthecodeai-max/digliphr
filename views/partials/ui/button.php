<?php
/**
 * <Button /> — the single button component.
 *
 *   ui('button', ['label' => 'Save', 'variant' => 'primary', 'icon' => 'save']);
 *
 * @var string      $label     Text inside the button.
 * @var string      $variant   primary|secondary|ghost|danger|success|warning
 * @var string      $size      sm|md|lg
 * @var string|null $icon      Lucide icon name rendered before the label.
 * @var string|null $iconAfter Lucide icon name rendered after the label.
 * @var string|null $href      Renders an <a> instead of a <button>.
 * @var string      $type      button|submit|reset (button element only)
 * @var bool        $loading   Show the spinner state immediately.
 * @var bool        $disabled
 * @var bool        $block     Full width.
 * @var bool        $autoLoad  Spin automatically while the parent form submits.
 * @var array       $attrs     Extra HTML attributes as key => value.
 */

$variantMap = [
    'primary'   => 'btn-primary',
    'secondary' => 'btn-soft',
    'ghost'     => 'btn-ghost',
    'danger'    => 'btn-danger',
    'success'   => 'btn-success',
    'warning'   => 'btn-warning',
];

$variant  = $variant  ?? 'secondary';
$size     = $size     ?? 'md';
$type     = $type     ?? 'button';
$label    = $label    ?? '';
$icon     = $icon     ?? null;
$iconAfter = $iconAfter ?? null;
$href     = $href     ?? null;
$loading  = !empty($loading);
$disabled = !empty($disabled);
$block    = !empty($block);
$autoLoad = !empty($autoLoad);
$attrs    = $attrs ?? [];

$classes = ['btn', $variantMap[$variant] ?? 'btn-soft'];
if ($size === 'sm') {
    $classes[] = 'btn-sm';
} elseif ($size === 'lg') {
    $classes[] = 'btn-lg';
}
if ($block) {
    $classes[] = 'w-100';
}
if (!empty($class)) {
    $classes[] = $class;
}

$attrHtml = '';
foreach ($attrs as $key => $value) {
    $attrHtml .= ' ' . e($key) . '="' . e($value) . '"';
}
if ($loading) {
    $attrHtml .= ' data-ds-state="loading"';
}
if ($autoLoad) {
    $attrHtml .= ' data-ds-loading';
}

$inner = '';
if ($icon) {
    $inner .= '<i data-lucide="' . e($icon) . '"></i>';
}
if ($label !== '') {
    $inner .= '<span>' . e($label) . '</span>';
}
if ($iconAfter) {
    $inner .= '<i data-lucide="' . e($iconAfter) . '"></i>';
}
?>
<?php if ($href !== null): ?>
<a href="<?= e($href) ?>" class="<?= e(implode(' ', $classes)) ?>"<?= $disabled ? ' aria-disabled="true" tabindex="-1"' : '' ?><?= $attrHtml ?>><?= $inner ?></a>
<?php else: ?>
<button type="<?= e($type) ?>" class="<?= e(implode(' ', $classes)) ?>"<?= $disabled ? ' disabled' : '' ?><?= $attrHtml ?>><?= $inner ?></button>
<?php endif; ?>
