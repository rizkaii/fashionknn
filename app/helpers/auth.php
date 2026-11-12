<?php
/**
 * auth.php — Helper autentikasi dan otorisasi
 *
 * Fungsi-fungsi yang tersedia:
 * - logged()           → apakah user sudah login
 * - role()             → role user aktif
 * - current_user()     → array data user dari session
 * - require_login()    → redirect ke login jika belum login
 * - require_role()     → 403 jika role tidak sesuai
 * - can_see($page)     → apakah role saat ini boleh mengakses halaman
 */

/**
 * Apakah user sudah login?
 */
function logged(): bool {
    return isset($_SESSION['user']) && !empty($_SESSION['user']['id']);
}

/**
 * Ambil role user yang sedang login.
 */
function role(): string {
    return $_SESSION['user']['role'] ?? '';
}

/**
 * Ambil data user aktif dari session.
 */
function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

/**
 * Wajibkan user sudah login; redirect ke halaman login jika belum.
 */
function require_login(): void {
    if (!logged()) {
        // Simpan URL yang ingin dituju untuk redirect setelah login
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }

    // Validasi integritas session: pastikan data user masih valid
    if (empty($_SESSION['user']['role']) || empty($_SESSION['user']['username'])) {
        session_unset();
        session_destroy();
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

/**
 * Wajibkan user memiliki salah satu role yang diizinkan.
 * Memanggil require_login() terlebih dahulu.
 *
 * @param string|array $roles Role yang diizinkan
 */
function require_role(string|array $roles): void {
    require_login();
    $r = is_array($roles) ? $roles : [$roles];
    if (!in_array(role(), $r, true)) {
        http_response_code(403);
        $forbidden_path = APP_PATH . '/views/layouts/403.php';
        if (file_exists($forbidden_path)) {
            include $forbidden_path;
        } else {
            echo '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8">'
                . '<title>403 Akses Ditolak</title></head><body>'
                . '<div style="font-family:sans-serif;padding:40px;text-align:center;">'
                . '<h1>403 &mdash; Akses Ditolak</h1>'
                . '<p>Anda tidak memiliki izin untuk mengakses halaman ini.</p>'
                . '<a href="' . htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') . '/dashboard.php">Kembali ke Dashboard</a>'
                . '</div></body></html>';
        }
        exit;
    }
}

/**
 * Periksa apakah role saat ini diizinkan mengakses halaman tertentu.
 * Digunakan untuk menentukan visibilitas menu di sidebar.
 *
 * @param string $page Nama halaman (key dari $page_access)
 * @return bool
 */
function can_see(string $page): bool {
    $page_access = [
        'dashboard'      => ['admin', 'kaunit', 'pemohon'],
        'products'       => ['admin', 'kaunit', 'pemohon'],
        'dataset'        => ['admin'],
        'training'       => ['admin', 'kaunit'],
        'preprocessing'  => ['admin'],
        'process'        => ['admin'],
        'results'        => ['admin', 'kaunit', 'pemohon'],
        'evaluation'     => ['admin', 'kaunit'],
        'visualization'  => ['admin', 'kaunit', 'pemohon'],
        'history'        => ['admin', 'kaunit'],
        'users'          => ['admin'],
    ];
    if (!isset($page_access[$page])) {
        return true; // Halaman tidak dikenal: izinkan (lebih baik tangani eksplisit)
    }
    return in_array(role(), $page_access[$page], true);
}

/**
 * Hash password menggunakan bcrypt (PASSWORD_BCRYPT, cost 12).
 */
function hash_password(string $plain): string {
    return password_hash($plain, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verifikasi password.
 */
function verify_password(string $plain, string $hash): bool {
    return password_verify($plain, $hash);
}
