<?php
// Page access control
$page_access = [
    'dashboard'     => ['admin', 'kaunit', 'pemohon'],
    'products'      => ['admin', 'kaunit', 'pemohon'],
    'dataset'       => ['admin'],
    'training'      => ['admin', 'kaunit'],
    'preprocessing' => ['admin'],
    'process'       => ['admin'],
    'results'       => ['admin', 'kaunit', 'pemohon'],
    'evaluation'    => ['admin', 'kaunit'],
    'visualization' => ['admin', 'kaunit', 'pemohon'],
    'history'       => ['admin', 'kaunit'],
    'users'         => ['admin'],
];

function check_access($page) {
    global $page_access;
    require_login();
    if (isset($page_access[$page]) && !in_array(role(), $page_access[$page])) {
        http_response_code(403);
        echo '<div style="font-family:sans-serif;padding:40px;text-align:center;">';
        echo '<h2>403 &mdash; Akses Ditolak</h2>';
        echo '<p>Anda tidak memiliki izin untuk mengakses halaman ini.</p>';
        echo '<a href="' . BASE_URL . '/dashboard.php">Kembali ke Dashboard</a>';
        echo '</div>';
        exit;
    }
}
