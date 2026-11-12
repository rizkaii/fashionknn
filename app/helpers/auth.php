<?php
function logged() {
    return isset($_SESSION['user']);
}

function role() {
    return $_SESSION['user']['role'] ?? '';
}

function current_user() {
    return $_SESSION['user'] ?? null;
}

function require_login() {
    if (!logged()) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

function require_role($roles) {
    require_login();
    $r = is_array($roles) ? $roles : [$roles];
    if (!in_array(role(), $r)) {
        http_response_code(403);
        $page_title = 'Akses Ditolak';
        $forbidden_path = APP_PATH . '/views/layouts/403.php';
        if (file_exists($forbidden_path)) {
            include $forbidden_path;
        } else {
            echo '<h1>403 - Akses Ditolak</h1><p>Anda tidak memiliki izin untuk mengakses halaman ini.</p>';
        }
        exit;
    }
}

function can_see($page) {
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
    if (!isset($page_access[$page])) return true;
    return in_array(role(), $page_access[$page]);
}
