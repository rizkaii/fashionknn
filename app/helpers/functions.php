<?php
function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function fmt_number($n) {
    return number_format((float)$n, 0, ',', '.');
}

function fmt_currency($n) {
    return 'Rp ' . number_format((float)$n, 0, ',', '.');
}

function fmt_percent($n, $dec = 2) {
    return number_format((float)$n, $dec) . '%';
}

function label_badge($label) {
    $label = strtolower(trim((string)$label));
    $map = [
        'tren'       => ['badge-tren',   'Tren'],
        'kurang tren'=> ['badge-kurang', 'Kurang Tren'],
        'tidak tren' => ['badge-tidak',  'Tidak Tren'],
    ];
    if (isset($map[$label])) {
        [$cls, $txt] = $map[$label];
    } else {
        $cls = 'badge-gray';
        $txt = h($label ?: '-');
    }
    return '<span class="badge ' . $cls . '">' . $txt . '</span>';
}

function pred_badge($pred) {
    return label_badge($pred);
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function csrf_check() {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        http_response_code(403);
        die('CSRF token tidak valid. Silakan refresh halaman dan coba lagi.');
    }
}

function paginate($total, $per_page, $current_page) {
    $total        = max(0, (int)$total);
    $per_page     = max(1, (int)$per_page);
    $current_page = max(1, (int)$current_page);
    $total_pages  = (int)ceil($total / $per_page);
    $current_page = min($current_page, max(1, $total_pages));
    $offset       = ($current_page - 1) * $per_page;

    return [
        'total'        => $total,
        'per_page'     => $per_page,
        'current_page' => $current_page,
        'total_pages'  => $total_pages,
        'offset'       => $offset,
        'has_prev'     => $current_page > 1,
        'has_next'     => $current_page < $total_pages,
        'prev_page'    => $current_page - 1,
        'next_page'    => $current_page + 1,
    ];
}

function flash_set($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_get() {
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

function flash_html() {
    $f = flash_get();
    if (!$f) return '';
    $cls = [
        'success' => 'alert-success',
        'error'   => 'alert-error',
        'warning' => 'alert-warning',
        'info'    => 'alert-info',
    ][$f['type']] ?? 'alert-info';
    return '<div class="alert ' . $cls . '" data-auto-dismiss>' . h($f['message']) . '</div>';
}
