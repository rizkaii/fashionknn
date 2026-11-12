<?php
/**
 * Header layout / sidebar
 * Expects: $page_title (string), $current_page (string)
 * Requires: app/config/database.php already included (session started, BASE_URL defined)
 */
require_once APP_PATH . '/helpers/auth.php';
require_once APP_PATH . '/helpers/functions.php';
require_login();
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= h($page_title ?? 'Fashion KNN') ?> | Sistem Prediksi Tren Fashion</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= filemtime(BASE_PATH . '/public/assets/css/style.css') ?>">
  <?php if (isset($extra_css)) echo $extra_css; ?>
</head>
<body>
  <div class="app-layout">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
      <div class="sidebar-header">
        <div class="brand">
          <div class="brand-icon"><i data-lucide="trending-up"></i></div>
          <div class="brand-text">
            <span class="brand-name">Fashion KNN</span>
            <span class="brand-sub">Sistem Prediksi Tren</span>
          </div>
        </div>
        <button class="sidebar-close" id="sidebarClose" aria-label="Tutup Sidebar">
          <i data-lucide="x"></i>
        </button>
      </div>

      <nav class="sidebar-nav">
        <!-- GENERAL -->
        <div class="nav-group">
          <span class="nav-group-label">GENERAL</span>
          <a href="<?= BASE_URL ?>/dashboard.php"
             class="nav-link <?= ($current_page ?? '') === 'dashboard' ? 'active' : '' ?>">
            <i data-lucide="layout-dashboard"></i><span>Dashboard</span>
          </a>
        </div>

        <!-- DATA MANAGEMENT -->
        <div class="nav-group">
          <span class="nav-group-label">DATA MANAGEMENT</span>
          <a href="<?= BASE_URL ?>/products.php"
             class="nav-link <?= ($current_page ?? '') === 'products' ? 'active' : '' ?>">
            <i data-lucide="package"></i><span>Data Produk</span>
          </a>
          <?php if (in_array(role(), ['admin'])): ?>
          <a href="<?= BASE_URL ?>/dataset.php"
             class="nav-link <?= ($current_page ?? '') === 'dataset' ? 'active' : '' ?>">
            <i data-lucide="upload-cloud"></i><span>Dataset CSV</span>
          </a>
          <?php endif; ?>
          <?php if (in_array(role(), ['admin', 'kaunit'])): ?>
          <a href="<?= BASE_URL ?>/training.php"
             class="nav-link <?= ($current_page ?? '') === 'training' ? 'active' : '' ?>">
            <i data-lucide="database"></i><span>Data Training</span>
          </a>
          <?php endif; ?>
        </div>

        <!-- MACHINE LEARNING -->
        <div class="nav-group">
          <span class="nav-group-label">MACHINE LEARNING</span>
          <?php if (role() === 'admin'): ?>
          <a href="<?= BASE_URL ?>/preprocessing.php"
             class="nav-link <?= ($current_page ?? '') === 'preprocessing' ? 'active' : '' ?>">
            <i data-lucide="settings-2"></i><span>Preprocessing</span>
          </a>
          <a href="<?= BASE_URL ?>/process.php"
             class="nav-link <?= ($current_page ?? '') === 'process' ? 'active' : '' ?>">
            <i data-lucide="cpu"></i><span>Proses KNN</span>
          </a>
          <?php endif; ?>
          <a href="<?= BASE_URL ?>/results.php"
             class="nav-link <?= ($current_page ?? '') === 'results' ? 'active' : '' ?>">
            <i data-lucide="list-checks"></i><span>Hasil Prediksi</span>
          </a>
          <?php if (in_array(role(), ['admin', 'kaunit'])): ?>
          <a href="<?= BASE_URL ?>/evaluation.php"
             class="nav-link <?= ($current_page ?? '') === 'evaluation' ? 'active' : '' ?>">
            <i data-lucide="bar-chart-2"></i><span>Evaluasi Model</span>
          </a>
          <?php endif; ?>
          <a href="<?= BASE_URL ?>/visualization.php"
             class="nav-link <?= ($current_page ?? '') === 'visualization' ? 'active' : '' ?>">
            <i data-lucide="pie-chart"></i><span>Visualisasi</span>
          </a>
        </div>

        <!-- SYSTEM -->
        <div class="nav-group">
          <span class="nav-group-label">SYSTEM</span>
          <?php if (role() === 'admin'): ?>
          <a href="<?= BASE_URL ?>/users.php"
             class="nav-link <?= ($current_page ?? '') === 'users' ? 'active' : '' ?>">
            <i data-lucide="users"></i><span>Manajemen Pengguna</span>
          </a>
          <?php endif; ?>
          <?php if (in_array(role(), ['admin', 'kaunit'])): ?>
          <a href="<?= BASE_URL ?>/history.php"
             class="nav-link <?= ($current_page ?? '') === 'history' ? 'active' : '' ?>">
            <i data-lucide="history"></i><span>Riwayat Klasifikasi</span>
          </a>
          <?php endif; ?>
          <a href="<?= BASE_URL ?>/logout.php" class="nav-link nav-link-logout" style="color: var(--tidak-text);" onclick="return confirm('Apakah Anda yakin ingin keluar dari sistem?');">
            <i data-lucide="log-out" style="color: var(--tidak-text);"></i><span>Keluar / Logout</span>
          </a>
        </div>
      </nav>

      <div class="sidebar-footer" style="display: flex; align-items: center; justify-content: space-between;">
        <div class="user-info">
          <div class="user-avatar">
            <?= strtoupper(substr($_SESSION['user']['name'] ?? 'U', 0, 1)) ?>
          </div>
          <div class="user-details">
            <span class="user-name"><?= h($_SESSION['user']['name'] ?? '') ?></span>
            <span class="user-role"><?= h(ucfirst(role())) ?></span>
          </div>
        </div>
        <a href="<?= BASE_URL ?>/logout.php" title="Keluar / Logout" style="color: var(--tidak-text); display: flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 8px; border: 1px solid #fee2e2; background: #fff5f5; text-decoration: none;" onclick="return confirm('Apakah Anda yakin ingin keluar dari sistem?');">
          <i data-lucide="log-out" style="width: 16px; height: 16px;"></i>
        </a>
      </div>
    </aside>

    <!-- Mobile overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Main wrapper -->
    <div class="main-wrapper">
      <header class="topbar">
        <button class="hamburger" id="hamburger" aria-label="Toggle Sidebar" title="Sembunyikan / Tampilkan Sidebar">
          <i data-lucide="menu"></i>
        </button>
        <div class="topbar-title"><?= h($page_title ?? '') ?></div>
        <div class="topbar-right">
          <div class="topbar-profile" title="Akun Pengguna Aktif">
            <div class="topbar-avatar">
              <?= strtoupper(substr($_SESSION['user']['name'] ?? 'U', 0, 1)) ?>
            </div>
            <div class="topbar-user-details">
              <span class="topbar-user-name"><?= h($_SESSION['user']['name'] ?? '') ?></span>
              <span class="topbar-user-role"><?= h(ucfirst(role())) ?></span>
            </div>
          </div>

          <div class="topbar-separator"></div>

          <a href="<?= BASE_URL ?>/logout.php" class="topbar-logout-btn" title="Keluar dari sistem" onclick="return confirm('Apakah Anda yakin ingin keluar dari sistem?');">
            <i data-lucide="log-out"></i>
            <span>Logout</span>
          </a>
        </div>
      </header>
      <main class="content">
