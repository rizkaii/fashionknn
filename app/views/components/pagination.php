<?php
/**
 * Pagination Component
 * Usage: include with $pag (result of paginate()), $url_pattern (e.g. '?page=%d&search=...')
 */
if (!isset($pag) || $pag['total_pages'] <= 1) return;
$url_pattern = $url_pattern ?? '?page=%d';
?>
<div class="pagination">
    <?php if ($pag['has_prev']): ?>
        <a class="page-item page-link" href="<?= sprintf(h($url_pattern), $pag['prev_page']) ?>">
            <i data-lucide="chevron-left"></i>
        </a>
    <?php else: ?>
        <span class="page-item page-link disabled"><i data-lucide="chevron-left"></i></span>
    <?php endif; ?>

    <?php
    $start = max(1, $pag['current_page'] - 2);
    $end   = min($pag['total_pages'], $pag['current_page'] + 2);
    if ($start > 1): ?>
        <a class="page-item page-link" href="<?= sprintf(h($url_pattern), 1) ?>">1</a>
        <?php if ($start > 2): ?><span class="page-item page-link disabled">&hellip;</span><?php endif; ?>
    <?php endif; ?>

    <?php for ($i = $start; $i <= $end; $i++): ?>
        <a class="page-item page-link <?= $i === $pag['current_page'] ? 'active' : '' ?>"
           href="<?= sprintf(h($url_pattern), $i) ?>"><?= $i ?></a>
    <?php endfor; ?>

    <?php if ($end < $pag['total_pages']): ?>
        <?php if ($end < $pag['total_pages'] - 1): ?><span class="page-item page-link disabled">&hellip;</span><?php endif; ?>
        <a class="page-item page-link" href="<?= sprintf(h($url_pattern), $pag['total_pages']) ?>"><?= $pag['total_pages'] ?></a>
    <?php endif; ?>

    <?php if ($pag['has_next']): ?>
        <a class="page-item page-link" href="<?= sprintf(h($url_pattern), $pag['next_page']) ?>">
            <i data-lucide="chevron-right"></i>
        </a>
    <?php else: ?>
        <span class="page-item page-link disabled"><i data-lucide="chevron-right"></i></span>
    <?php endif; ?>

    <span class="page-info">
        Hal <?= $pag['current_page'] ?> dari <?= $pag['total_pages'] ?> (<?= fmt_number($pag['total']) ?> data)
    </span>
</div>
