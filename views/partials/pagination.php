<?php
/** @var array $paginator */
/** @var string $baseUrl */
$current = $paginator['current_page'] ?? 1;
$last = $paginator['last_page'] ?? 1;
if ($last <= 1) {
    return;
}
$sep = str_contains($baseUrl ?? '', '?') ? '&' : '?';
?>
<nav aria-label="Pagination" class="mt-3">
    <ul class="pagination pagination-sm mb-0">
        <?php for ($i = max(1, $current - 2); $i <= min($last, $current + 2); $i++): ?>
            <li class="page-item<?= $i === $current ? ' active' : '' ?>">
                <a class="page-link" href="<?= e(($baseUrl ?? '') . $sep . 'page=' . $i) ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
