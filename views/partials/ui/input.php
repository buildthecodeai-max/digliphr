<?php
/**
 * <Input /> — form field with label, help text, validation and error animation.
 *
 *   ui('input', ['name' => 'email', 'label' => 'Email address', 'type' => 'email', 'required' => true]);
 *
 * @var string      $name
 * @var string|null $label
 * @var string      $type      text|email|password|number|date|search|textarea|select
 * @var mixed       $value
 * @var array       $options   For type=select: value => label.
 * @var string|null $help
 * @var string|null $error
 * @var bool        $required
 * @var bool        $disabled
 * @var string|null $placeholder
 * @var array       $attrs
 */

$name        = $name        ?? '';
$type        = $type        ?? 'text';
$label       = $label       ?? null;
$value       = $value       ?? old($name, '');
$options     = $options     ?? [];
$help        = $help        ?? null;
$error       = $error       ?? null;
$required    = !empty($required);
$disabled    = !empty($disabled);
$placeholder = $placeholder ?? null;
$attrs       = $attrs       ?? [];

$id = $attrs['id'] ?? ('f_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $name));
unset($attrs['id']);

$describedBy = [];
if ($help)  { $describedBy[] = $id . '_help'; }
if ($error) { $describedBy[] = $id . '_err'; }

$controlClass = $type === 'select' ? 'form-select' : 'form-control';
if ($error) {
    $controlClass .= ' is-invalid';
}

$attrHtml = '';
foreach ($attrs as $key => $val) {
    $attrHtml .= ' ' . e($key) . '="' . e($val) . '"';
}
if ($placeholder !== null) {
    $attrHtml .= ' placeholder="' . e($placeholder) . '"';
}
if ($required) {
    $attrHtml .= ' required';
}
if ($disabled) {
    $attrHtml .= ' disabled';
}
if ($describedBy) {
    $attrHtml .= ' aria-describedby="' . e(implode(' ', $describedBy)) . '"';
}
if ($error) {
    $attrHtml .= ' aria-invalid="true"';
}
if ($type === 'password') {
    $attrHtml .= ' data-ds-password';
}
?>
<div class="mb-3">
    <?php if ($label !== null): ?>
        <label class="form-label" for="<?= e($id) ?>">
            <?= e($label) ?><?= $required ? ' <span class="text-danger" aria-hidden="true">*</span>' : '' ?>
        </label>
    <?php endif; ?>

    <?php if ($type === 'textarea'): ?>
        <textarea class="<?= e($controlClass) ?>" id="<?= e($id) ?>" name="<?= e($name) ?>"<?= $attrHtml ?>><?= e($value) ?></textarea>
    <?php elseif ($type === 'select'): ?>
        <select class="<?= e($controlClass) ?>" id="<?= e($id) ?>" name="<?= e($name) ?>"<?= $attrHtml ?>>
            <?php foreach ($options as $optValue => $optLabel): ?>
                <option value="<?= e($optValue) ?>"<?= (string) $optValue === (string) $value ? ' selected' : '' ?>>
                    <?= e($optLabel) ?>
                </option>
            <?php endforeach; ?>
        </select>
    <?php else: ?>
        <input type="<?= e($type) ?>" class="<?= e($controlClass) ?>" id="<?= e($id) ?>" name="<?= e($name) ?>" value="<?= e($value) ?>"<?= $attrHtml ?>>
    <?php endif; ?>

    <?php if ($help): ?>
        <div class="form-text" id="<?= e($id) ?>_help"><?= e($help) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="invalid-feedback d-block" id="<?= e($id) ?>_err"><?= e($error) ?></div>
    <?php endif; ?>
</div>
