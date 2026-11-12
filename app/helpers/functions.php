<?php
/**
 * functions.php — Helper fungsi umum aplikasi
 *
 * Berisi:
 * - Escaping output (XSS protection)
 * - Formatting angka dan mata uang
 * - Badge label dan prediksi
 * - CSRF protection
 * - Pagination
 * - Flash messages
 * - Input sanitization
 */

// ==============================================================
// OUTPUT ESCAPING
// ==============================================================

/**
 * Escape HTML untuk output ke browser — cegah XSS.
 */
function h(mixed $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ==============================================================
// FORMATTING
// ==============================================================

function fmt_number(mixed $n): string {
    return number_format((float)$n, 0, ',', '.');
}

function fmt_currency(mixed $n): string {
    return 'Rp ' . number_format((float)$n, 0, ',', '.');
}

function fmt_percent(mixed $n, int $dec = 2): string {
    return number_format((float)$n, $dec) . '%';
}

// ==============================================================
// BADGE HELPERS
// ==============================================================

function label_badge(mixed $label): string {
    $label = strtolower(trim((string)$label));
    $map = [
        'tren'        => ['badge-tren',   'Tren'],
        'kurang tren' => ['badge-kurang', 'Kurang Tren'],
        'tidak tren'  => ['badge-tidak',  'Tidak Tren'],
    ];
    if (isset($map[$label])) {
        [$cls, $txt] = $map[$label];
    } else {
        $cls = 'badge-gray';
        $txt = h($label ?: '-');
    }
    return '<span class="badge ' . $cls . '">' . $txt . '</span>';
}

function pred_badge(mixed $pred): string {
    return label_badge($pred);
}

// ==============================================================
// CSRF PROTECTION
// ==============================================================

/**
 * Buat atau ambil CSRF token dari session.
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Hasilkan hidden input field CSRF.
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

/**
 * Verifikasi CSRF token dari POST request.
 * Menggunakan hash_equals() untuk mencegah timing attack.
 */
function csrf_check(): void {
    $submitted = $_POST['csrf_token'] ?? '';
    $expected  = $_SESSION['csrf_token'] ?? '';

    if (!is_string($submitted) || !is_string($expected) || $expected === '') {
        http_response_code(403);
        die(h('CSRF token tidak valid. Silakan refresh halaman dan coba lagi.'));
    }

    if (!hash_equals($expected, $submitted)) {
        http_response_code(403);
        die(h('CSRF token tidak valid. Silakan refresh halaman dan coba lagi.'));
    }
}

// ==============================================================
// PAGINATION
// ==============================================================

function paginate(int $total, int $per_page, int $current_page): array {
    $total        = max(0, $total);
    $per_page     = max(1, $per_page);
    $current_page = max(1, $current_page);
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

// ==============================================================
// FLASH MESSAGES
// ==============================================================

function flash_set(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_get(): ?array {
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

function flash_html(): string {
    $f = flash_get();
    if (!$f) return '';
    $cls = match ($f['type']) {
        'success' => 'alert-success',
        'error'   => 'alert-error',
        'warning' => 'alert-warning',
        default   => 'alert-info',
    };
    return '<div class="alert ' . $cls . '" data-auto-dismiss>' . h($f['message']) . '</div>';
}

// ==============================================================
// INPUT SANITIZATION
// ==============================================================

/**
 * Sanitasi string input: trim dan hapus karakter null.
 */
function clean_input(mixed $v): string {
    return trim(str_replace("\0", '', (string)$v));
}

/**
 * Ambil integer dari input, kembalikan 0 jika tidak valid.
 */
function int_input(mixed $v, int $default = 0): int {
    $filtered = filter_var($v, FILTER_VALIDATE_INT);
    return ($filtered !== false) ? (int)$filtered : $default;
}

/**
 * Validasi apakah nilai masuk dalam whitelist.
 */
function in_whitelist(mixed $val, array $whitelist, mixed $default = null): mixed {
    return in_array($val, $whitelist, true) ? $val : $default;
}
