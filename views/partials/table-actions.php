<?php
/**
 * Compact table row actions.
 *
 * @var array<int, array<string, mixed>> $actions
 *
 * Supported keys per action:
 * - type: link|button|form|modal (default: link)
 * - icon: lucide icon name (required)
 * - label: tooltip / aria label (required)
 * - href: for link
 * - action: for form
 * - method: form method (default POST)
 * - confirm: optional data-confirm message for forms
 * - variant: default|danger|success|warning
 * - target: modal selector for type=modal
 * - attrs: associative array of extra HTML attributes
 * - fields: associative array of hidden inputs for forms
 */
$actions = array_values(array_filter($actions ?? []));
if ($actions === []) {
    return;
}
?>
<div class="table-actions">
<?php foreach ($actions as $action): ?>
    <?php
    $type = $action['type'] ?? 'link';
    $icon = (string) ($action['icon'] ?? 'circle');
    $label = (string) ($action['label'] ?? 'Action');
    $variant = (string) ($action['variant'] ?? 'default');
    $class = 'action-btn' . ($variant !== 'default' ? ' action-btn-' . $variant : '');
    $attrs = $action['attrs'] ?? [];
    $attrHtml = '';
    foreach ($attrs as $attrKey => $attrVal) {
        $attrHtml .= ' ' . e((string) $attrKey) . '="' . e((string) $attrVal) . '"';
    }
    ?>
    <?php if ($type === 'form'): ?>
        <form method="<?= e((string) ($action['method'] ?? 'POST')) ?>"
              action="<?= e((string) ($action['action'] ?? '#')) ?>"
              class="table-action-form"
              <?= !empty($action['confirm']) ? ' data-confirm="' . e((string) $action['confirm']) . '"' : '' ?>>
            <?= csrf_field() ?>
            <?php foreach (($action['fields'] ?? []) as $fieldName => $fieldValue): ?>
                <input type="hidden" name="<?= e((string) $fieldName) ?>" value="<?= e((string) $fieldValue) ?>">
            <?php endforeach; ?>
            <button type="submit"
                    class="<?= e($class) ?>"
                    title="<?= e($label) ?>"
                    aria-label="<?= e($label) ?>"
                    data-bs-toggle="tooltip"
                    data-bs-placement="top"<?= $attrHtml ?>>
                <i data-lucide="<?= e($icon) ?>"></i>
            </button>
        </form>
    <?php elseif ($type === 'modal'): ?>
        <button type="button"
                class="<?= e($class) ?>"
                title="<?= e($label) ?>"
                aria-label="<?= e($label) ?>"
                data-bs-toggle="modal"
                data-bs-target="<?= e((string) ($action['target'] ?? '')) ?>"
                data-bs-placement="top"<?= $attrHtml ?>>
            <i data-lucide="<?= e($icon) ?>"></i>
        </button>
    <?php elseif ($type === 'button'): ?>
        <button type="button"
                class="<?= e($class) ?>"
                title="<?= e($label) ?>"
                aria-label="<?= e($label) ?>"
                data-bs-toggle="tooltip"
                data-bs-placement="top"<?= $attrHtml ?>>
            <i data-lucide="<?= e($icon) ?>"></i>
        </button>
    <?php else: ?>
        <a href="<?= e((string) ($action['href'] ?? '#')) ?>"
           class="<?= e($class) ?>"
           title="<?= e($label) ?>"
           aria-label="<?= e($label) ?>"
           data-bs-toggle="tooltip"
           data-bs-placement="top"<?= $attrHtml ?>>
            <i data-lucide="<?= e($icon) ?>"></i>
        </a>
    <?php endif; ?>
<?php endforeach; ?>
</div>
