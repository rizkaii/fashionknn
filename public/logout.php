<?php
/**
 * logout.php — Proses keluar dari sistem
 *
 * Langkah keamanan:
 * 1. Muat konfigurasi (session sudah dimulai di database.php)
 * 2. Hapus semua data session
 * 3. Destroy session
 * 4. Hapus cookie session secara eksplisit
 * 5. Redirect ke halaman login
 */
require_once dirname(__DIR__) . '/app/config/database.php';

// Hapus semua variabel session
session_unset();

// Destroy session di server
session_destroy();

// Hapus session cookie di browser agar tidak bisa di-reuse
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Redirect ke halaman login
header('Location: index.php');
exit;
