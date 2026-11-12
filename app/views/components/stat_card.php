<?php
/**
 * Stat Card Component
 * Usage: include with $icon, $value, $label, $color (optional: tren|kurang|tidak|primary)
 */
$color = $color ?? 'primary';
$icon  = $icon  ?? 'bar-chart-2';
$value = $value ?? '0';
$label = $label ?? '';
?>
<div class="stat-card">
    <div class="stat-card-icon stat-icon-<?= h($color) ?>">
        <i data-lucide="<?= h($icon) ?>"></i>
    </div>
    <div class="stat-card-body">
        <div class="stat-card-value"><?= h($value) ?></div>
        <div class="stat-card-label"><?= h($label) ?></div>
    </div>
</div>
