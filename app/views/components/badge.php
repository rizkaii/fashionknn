<?php
/**
 * Badge Component
 * Usage: include with $label (tren|kurang tren|tidak tren) or $text + $class
 */
if (isset($label)) {
    echo label_badge($label);
} elseif (isset($text, $class)) {
    echo '<span class="badge ' . h($class) . '">' . h($text) . '</span>';
}
