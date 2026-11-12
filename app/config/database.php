<?php
/**
 * database.php — Bootstrap utama aplikasi
 *
 * Bertanggung jawab atas:
 * 1. Memuat environment configuration dari .env
 * 2. Mendefinisikan konstanta aplikasi
 * 3. Mengkonfigurasi session dengan pengaturan keamanan
 * 4. Membuat koneksi PDO ke database
 * 5. Menginisialisasi error handling sesuai environment
 */

// ==============================================================
// 1. MUAT ENV LOADER + FILE .ENV
// ==============================================================
require_once __DIR__ . '/env.php';

$envFile = dirname(__DIR__, 2) . '/.env';
load_env($envFile);

// ==============================================================
// 2. KONFIGURASI ERROR HANDLING
// ==============================================================
$app_env   = env('APP_ENV', 'local');
$app_debug = env('APP_DEBUG', true);

if ($app_env === 'production' || !$app_debug) {
    // Production: sembunyikan error dari pengunjung, catat ke log
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);
    ini_set('log_errors', '1');
    // Pastikan direktori log ada dan writable
    $logDir = dirname(__DIR__, 2) . '/storage/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    ini_set('error_log', $logDir . '/php_errors.log');
} else {
    // Development: tampilkan semua error
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

// ==============================================================
// 3. TIMEZONE
// ==============================================================
$tz = env('APP_TIMEZONE', 'Asia/Jakarta');
date_default_timezone_set($tz);

// ==============================================================
// 4. KONSTANTA APLIKASI
// ==============================================================
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 2));
}
if (!defined('APP_PATH')) {
    define('APP_PATH', dirname(__DIR__));
}
if (!defined('BASE_URL')) {
    // Ambil dari .env, fallback ke subfolder lokal
    define('BASE_URL', rtrim(env('APP_URL', '/fashiontrendknn/public'), '/'));
}
if (!defined('APP_ENV')) {
    define('APP_ENV', $app_env);
}
if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', $app_debug);
}

// ==============================================================
// 5. KONFIGURASI SESSION YANG AMAN
// ==============================================================
if (session_status() === PHP_SESSION_NONE) {
    $sessionLifetime = (int)env('SESSION_LIFETIME', 7200);
    $sessionSamesite = env('SESSION_SAMESITE', 'Lax');

    // Aktifkan secure cookie hanya ketika HTTPS tersedia
    $isHttps = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    );
    $sessionSecure = env('SESSION_SECURE', false);
    if ($app_env === 'production') {
        $sessionSecure = $isHttps; // Otomatis deteksi HTTPS di production
    }

    session_set_cookie_params([
        'lifetime' => 0,          // Cookie mati ketika browser ditutup
        'path'     => '/',
        'domain'   => '',          // Gunakan domain saat ini
        'secure'   => (bool)$sessionSecure,
        'httponly' => true,        // Lindungi dari JavaScript (XSS mitigation)
        'samesite' => $sessionSamesite,
    ]);

    // Hanya simpan session ID di cookie, bukan di URL
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');

    // Gunakan nama session yang tidak mudah ditebak
    session_name('fknn_sess');

    session_start();

    // Session timeout: hapus session jika sudah tidak aktif lebih dari SESSION_LIFETIME
    if (isset($_SESSION['_last_activity'])) {
        if ((time() - $_SESSION['_last_activity']) > $sessionLifetime) {
            session_unset();
            session_destroy();
            session_start();
        }
    }
    $_SESSION['_last_activity'] = time();
}

// ==============================================================
// 6. KONEKSI DATABASE
// ==============================================================
$dbHost = env('DB_HOST', '127.0.0.1');
$dbPort = env('DB_PORT', '3306');
$dbName = env('DB_NAME', 'fashion_knn');
$dbUser = env('DB_USER', 'root');
$dbPass = env('DB_PASS', '');

try {
    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_PERSISTENT         => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
    ]);
} catch (PDOException $e) {
    // Jangan tampilkan detail error ke pengunjung di production
    if (APP_DEBUG) {
        // Development: tampilkan pesan lengkap
        $safeMsg = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        die('<h2>Koneksi database gagal</h2><p>' . $safeMsg . '</p>'
            . '<p>Pastikan MySQL aktif dan database <code>' . htmlspecialchars($dbName, ENT_QUOTES, 'UTF-8') . '</code> sudah diimpor.</p>');
    } else {
        // Production: catat ke log, tampilkan pesan generic
        error_log('[FashionKNN] DB Connection failed: ' . $e->getMessage());
        http_response_code(503);
        die('<h2>Layanan Sementara Tidak Tersedia</h2><p>Terjadi kesalahan pada sistem. Silakan coba beberapa saat lagi.</p>');
    }
}
