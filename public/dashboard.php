<?php
require_once dirname(__DIR__) . '/app/config/database.php';
require_once APP_PATH . '/helpers/auth.php';
require_once APP_PATH . '/helpers/functions.php';
require_once APP_PATH . '/middleware/access.php';

check_access('dashboard');
$page_title   = 'Dashboard';
$current_page = 'dashboard';

$error_msg   = '';
$success_msg = '';

// --- Handle Admin Master Reset Data ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_all_data') {
    if (role() !== 'admin') {
        http_response_code(403);
        die('Akses ditolak: Hanya administrator yang dapat melakukan reset data.');
    }
    csrf_check();

    $admin_password = $_POST['admin_password'] ?? '';

    // Ambil data admin saat ini untuk verifikasi kata sandi
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user']['id']]);
    $current_admin = $stmt->fetch();

    $password_verified = false;
    if ($current_admin) {
        if ($current_admin['password'] === $admin_password) {
            $password_verified = true;
        } elseif (password_verify($admin_password, $current_admin['password'])) {
            $password_verified = true;
        }
    }

    if ($password_verified) {
        try {
            $pdo->beginTransaction();
            // Matikan sementara foreign key checks agar proses aman
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
            $pdo->exec("DELETE FROM knn_predictions;");
            $pdo->exec("ALTER TABLE knn_predictions AUTO_INCREMENT = 1;");
            $pdo->exec("DELETE FROM knn_norm_params;");
            $pdo->exec("ALTER TABLE knn_norm_params AUTO_INCREMENT = 1;");
            $pdo->exec("DELETE FROM knn_processes;");
            $pdo->exec("ALTER TABLE knn_processes AUTO_INCREMENT = 1;");
            $pdo->exec("DELETE FROM classifications;");
            $pdo->exec("ALTER TABLE classifications AUTO_INCREMENT = 1;");
            $pdo->exec("DELETE FROM products;");
            $pdo->exec("ALTER TABLE products AUTO_INCREMENT = 1;");
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
            $pdo->commit();

            flash_set('success', 'Seluruh data produk, riwayat proses KNN, evaluasi, dan prediksi berhasil dikosongkan. Data akun pengguna (users) tetap tersimpan aman.');
            header('Location: dashboard.php');
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error_msg = 'Gagal mengosongkan data: ' . $e->getMessage();
        }
    } else {
        $error_msg = 'Verifikasi kata sandi admin gagal! Seluruh data aman dan tidak diubah.';
    }
}

// --- Query Data Statistik ---
$total_produk   = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$total_berlabel = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE label IS NOT NULL AND label != ''")->fetchColumn();
$total_prediksi = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE prediction IS NOT NULL AND prediction != ''")->fetchColumn();

// Distribusi label aktual
$dist_label_rows = $pdo->query("SELECT label, COUNT(*) as n FROM products WHERE label IS NOT NULL AND label != '' GROUP BY label")->fetchAll();
$dist_label = ['tren' => 0, 'kurang tren' => 0, 'tidak tren' => 0];
foreach ($dist_label_rows as $r) {
    $lbl = strtolower(trim($r['label']));
    if (isset($dist_label[$lbl])) {
        $dist_label[$lbl] = (int)$r['n'];
    }
}

// Distribusi hasil prediksi
$dist_pred_rows = $pdo->query("SELECT prediction, COUNT(*) as n FROM products WHERE prediction IS NOT NULL AND prediction != '' GROUP BY prediction")->fetchAll();
$dist_pred = ['tren' => 0, 'kurang tren' => 0, 'tidak tren' => 0];
foreach ($dist_pred_rows as $r) {
    $prd = strtolower(trim($r['prediction']));
    if (isset($dist_pred[$prd])) {
        $dist_pred[$prd] = (int)$r['n'];
    }
}

// Jika sudah ada prediksi gunakan prediksi, jika belum gunakan label
$chart_dist = ($total_prediksi > 0) ? $dist_pred : $dist_label;

// Info proses KNN terakhir
$last_process = null;
$recent_procs = [];
try {
    $last_process = $pdo->query("SELECT * FROM knn_processes ORDER BY id DESC LIMIT 1")->fetch();
    $recent_procs = $pdo->query("SELECT * FROM knn_processes ORDER BY id DESC LIMIT 5")->fetchAll();
} catch (Exception $e) {}

// Produk terbaru (8 item)
$recent_products = $pdo->query("SELECT * FROM products ORDER BY id DESC LIMIT 8")->fetchAll();

// Data untuk Chart.js
$chart_labels = json_encode(['Tren', 'Kurang Tren', 'Tidak Tren'], JSON_HEX_TAG | JSON_HEX_AMP);
$chart_data   = json_encode([(int)$chart_dist['tren'], (int)$chart_dist['kurang tren'], (int)$chart_dist['tidak tren']], JSON_HEX_TAG | JSON_HEX_AMP);

include APP_PATH . '/views/layouts/header.php';
?>

<!-- Header Halaman & Aksi Cepat -->
<div class="page-header flex justify-between items-center flex-wrap gap-3">
  <div>
    <h1 class="page-title">Dashboard Analitik</h1>
    <p class="page-subtitle">Ringkasan data penjualan Tokopedia dan hasil klasifikasi tren produk fashion berbasis K-Nearest Neighbor.</p>
  </div>
  <div class="flex gap-2 flex-wrap items-center">
    <?php if (role() === 'admin'): ?>
      <button type="button" class="btn btn-sm btn-danger-soft flex items-center gap-1.5" onclick="openResetModal()" style="padding: 7px 14px; font-weight: 600;">
        <i data-lucide="trash-2" style="width: 15px; height: 15px;"></i>
        <span>Reset Seluruh Data</span>
      </button>
      <a href="<?= BASE_URL ?>/dataset.php" class="btn btn-sm btn-primary flex items-center gap-1.5" style="padding: 7px 14px; font-weight: 600;">
        <i data-lucide="upload-cloud" style="width: 15px; height: 15px;"></i>
        <span>Import CSV</span>
      </a>
    <?php endif; ?>
  </div>
</div>

<?= flash_html() ?>

<?php if ($error_msg): ?>
  <div class="alert alert-error mb-4 flex items-center gap-2">
    <i data-lucide="alert-triangle" style="width: 18px; height: 18px; flex-shrink: 0;"></i>
    <span><?= h($error_msg) ?></span>
  </div>
<?php endif; ?>

<!-- 1. Grid 6 Kartu Statistik Utama -->
<div class="stats-grid mb-4">
  <!-- Card 1: Total Produk -->
  <div class="card" style="padding: 18px 20px;">
    <div class="flex justify-between items-center mb-2">
      <span style="font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted);">Total Produk</span>
      <div style="width: 34px; height: 34px; border-radius: 8px; background: #EEF2FF; color: var(--primary); display: flex; align-items: center; justify-content: center;">
        <i data-lucide="package" style="width: 18px; height: 18px;"></i>
      </div>
    </div>
    <div style="font-size: 26px; font-weight: 800; color: var(--text-main); line-height: 1.2;"><?= fmt_number($total_produk) ?></div>
    <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 4px;">Entri di basis data</div>
  </div>

  <!-- Card 2: Data Training -->
  <div class="card" style="padding: 18px 20px;">
    <div class="flex justify-between items-center mb-2">
      <span style="font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted);">Data Training</span>
      <div style="width: 34px; height: 34px; border-radius: 8px; background: #E0F2FE; color: #0284C7; display: flex; align-items: center; justify-content: center;">
        <i data-lucide="database" style="width: 18px; height: 18px;"></i>
      </div>
    </div>
    <div style="font-size: 26px; font-weight: 800; color: #0284C7; line-height: 1.2;"><?= fmt_number($total_berlabel) ?></div>
    <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 4px;">Produk memiliki label aktual</div>
  </div>

  <!-- Card 3: Sudah Diprediksi -->
  <div class="card" style="padding: 18px 20px;">
    <div class="flex justify-between items-center mb-2">
      <span style="font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted);">Hasil Prediksi</span>
      <div style="width: 34px; height: 34px; border-radius: 8px; background: #ECFDF5; color: #059669; display: flex; align-items: center; justify-content: center;">
        <i data-lucide="list-checks" style="width: 18px; height: 18px;"></i>
      </div>
    </div>
    <div style="font-size: 26px; font-weight: 800; color: #059669; line-height: 1.2;"><?= fmt_number($total_prediksi) ?></div>
    <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 4px;">Produk telah terklasifikasi</div>
  </div>

  <!-- Card 4: Kategori Tren -->
  <div class="card" style="padding: 18px 20px;">
    <div class="flex justify-between items-center mb-2">
      <span style="font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: var(--tren-text);">Kategori Tren</span>
      <div style="width: 34px; height: 34px; border-radius: 8px; background: var(--tren-bg); color: var(--tren-text); display: flex; align-items: center; justify-content: center;">
        <i data-lucide="trending-up" style="width: 18px; height: 18px;"></i>
      </div>
    </div>
    <div style="font-size: 26px; font-weight: 800; color: var(--tren-text); line-height: 1.2;"><?= fmt_number($chart_dist['tren']) ?></div>
    <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 4px;">Volume penjualan tinggi</div>
  </div>

  <!-- Card 5: Kategori Kurang Tren -->
  <div class="card" style="padding: 18px 20px;">
    <div class="flex justify-between items-center mb-2">
      <span style="font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: var(--kurang-text);">Kurang Tren</span>
      <div style="width: 34px; height: 34px; border-radius: 8px; background: var(--kurang-bg); color: var(--kurang-text); display: flex; align-items: center; justify-content: center;">
        <i data-lucide="minus-circle" style="width: 18px; height: 18px;"></i>
      </div>
    </div>
    <div style="font-size: 26px; font-weight: 800; color: var(--kurang-text); line-height: 1.2;"><?= fmt_number($chart_dist['kurang tren']) ?></div>
    <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 4px;">Volume penjualan sedang</div>
  </div>

  <!-- Card 6: Kategori Tidak Tren -->
  <div class="card" style="padding: 18px 20px;">
    <div class="flex justify-between items-center mb-2">
      <span style="font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: var(--tidak-text);">Tidak Tren</span>
      <div style="width: 34px; height: 34px; border-radius: 8px; background: var(--tidak-bg); color: var(--tidak-text); display: flex; align-items: center; justify-content: center;">
        <i data-lucide="trending-down" style="width: 18px; height: 18px;"></i>
      </div>
    </div>
    <div style="font-size: 26px; font-weight: 800; color: var(--tidak-text); line-height: 1.2;"><?= fmt_number($chart_dist['tidak tren']) ?></div>
    <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 4px;">Volume penjualan rendah</div>
  </div>
</div>

<!-- 2. Row: Chart Distribusi + Info Model Terakhir -->
<div class="grid two mb-4">
  <!-- Panel Grafik Doughnut -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Distribusi Kategori Tren</h3>
      <span class="badge badge-gray" style="font-size: 11px;">
        <?= $total_prediksi > 0 ? 'Data Prediksi KNN' : 'Data Label Aktual' ?>
      </span>
    </div>
    <div class="card-body">
      <?php if (array_sum($chart_dist) > 0): ?>
        <div style="position: relative; height: 260px; width: 100%;">
          <canvas id="chartDoughnutDashboard"></canvas>
        </div>
      <?php else: ?>
        <div class="empty-state" style="padding: 40px 20px;">
          <i data-lucide="pie-chart" style="width: 40px; height: 40px; margin-bottom: 8px;"></i>
          <p style="margin: 0;">Belum ada data distribusi tren produk.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Panel Performa Model KNN Terakhir -->
  <div class="card">
    <div class="card-header flex justify-between items-center">
      <h3 class="card-title">Performa Model Terakhir</h3>
      <?php if ($last_process): ?>
        <a href="<?= BASE_URL ?>/evaluation.php?process_id=<?= $last_process['id'] ?>" class="btn btn-sm btn-outline" style="font-size: 12px; padding: 4px 10px;">
          Evaluasi Detail &rarr;
        </a>
      <?php endif; ?>
    </div>
    <div class="card-body">
      <?php if ($last_process): ?>
        <div class="stats-grid mb-3" style="grid-template-columns: 1fr 1fr; gap: 12px;">
          <div style="background: #F8FAFC; border: 1px solid var(--border); border-radius: 8px; padding: 12px 14px;">
            <div style="font-size: 11px; font-weight: 600; color: var(--text-muted); text-transform: uppercase;">Akurasi Uji</div>
            <div style="font-size: 22px; font-weight: 800; color: var(--tren-text); margin-top: 2px;">
              <?= fmt_percent((float)$last_process['accuracy'] * 100) ?>
            </div>
          </div>
          <div style="background: #F8FAFC; border: 1px solid var(--border); border-radius: 8px; padding: 12px 14px;">
            <div style="font-size: 11px; font-weight: 600; color: var(--text-muted); text-transform: uppercase;">Macro F1-Score</div>
            <div style="font-size: 22px; font-weight: 800; color: var(--primary); margin-top: 2px;">
              <?= fmt_percent((float)$last_process['f1_score'] * 100) ?>
            </div>
          </div>
        </div>

        <table class="detail-table">
          <tr>
            <th style="padding: 8px 0; color: var(--text-muted); font-size: 12.5px;">Nilai K (Tetangga Terdekat)</th>
            <td style="padding: 8px 0; font-weight: 600; text-align: right; font-size: 12.5px;">
              <span class="badge badge-gray">K = <?= $last_process['k_value'] ?></span>
            </td>
          </tr>
          <tr>
            <th style="padding: 8px 0; color: var(--text-muted); font-size: 12.5px;">Rasio Pembagian Data</th>
            <td style="padding: 8px 0; font-weight: 600; text-align: right; font-size: 12.5px;"><?= h($last_process['split_ratio']) ?></td>
          </tr>
          <tr>
            <th style="padding: 8px 0; color: var(--text-muted); font-size: 12.5px;">Sampel Latih / Uji</th>
            <td style="padding: 8px 0; font-weight: 600; text-align: right; font-size: 12.5px;">
              <?= $last_process['train_count'] ?> latih &bull; <?= $last_process['test_count'] ?> uji
            </td>
          </tr>
          <tr>
            <th style="padding: 8px 0; color: var(--text-muted); font-size: 12.5px;">Waktu Eksekusi</th>
            <td style="padding: 8px 0; font-weight: 500; text-align: right; font-size: 12.5px; color: var(--text-muted);">
              <?= date('d M Y, H:i', strtotime($last_process['processed_at'])) ?>
            </td>
          </tr>
        </table>
      <?php else: ?>
        <div class="empty-state" style="padding: 40px 20px;">
          <i data-lucide="cpu" style="width: 40px; height: 40px; margin-bottom: 8px;"></i>
          <p style="margin: 0 0 12px;">Belum ada riwayat proses KNN.</p>
          <?php if (role() === 'admin'): ?>
            <a href="<?= BASE_URL ?>/process.php" class="btn btn-sm btn-primary">
              <i data-lucide="play" style="width: 14px; height: 14px;"></i> Jalankan KNN
            </a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- 3. Tabel Produk Terbaru (Responsive Full Width, Zero Overflow) -->
<div class="card mb-4">
  <div class="card-header flex justify-between items-center">
    <div>
      <h3 class="card-title">Produk Terbaru Ditambahkan</h3>
      <p class="card-subtitle">8 produk terakhir yang masuk ke dalam sistem.</p>
    </div>
    <a href="<?= BASE_URL ?>/products.php" class="btn btn-sm btn-outline" style="font-size: 12px;">
      Lihat Semua Data Produk &rarr;
    </a>
  </div>
  <div class="card-body p-0">
    <div class="table-container" style="border: none; margin-bottom: 0;">
      <table>
        <thead>
          <tr>
            <th style="width: 60px;">ID</th>
            <th>Nama Produk</th>
            <th>Terjual</th>
            <th>Rating</th>
            <th>Harga Saat Ini</th>
            <th>Label Aktual</th>
            <th>Prediksi Model</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($recent_products)): ?>
            <tr>
              <td colspan="7" class="text-center text-muted" style="padding: 30px;">
                Belum ada data produk di dalam sistem.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($recent_products as $p): ?>
              <tr>
                <td><strong>#<?= $p['id'] ?></strong></td>
                <td>
                  <strong style="color: var(--text-main); font-size: 13px;"><?= h($p['name']) ?></strong>
                </td>
                <td><?= fmt_number($p['sold']) ?> unit</td>
                <td><?= h($p['rating']) ?> ★</td>
                <td><?= fmt_currency($p['price_now']) ?></td>
                <td><?= label_badge($p['label']) ?></td>
                <td><?= pred_badge($p['prediction']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal Reset Data Admin (Dialog Verifikasi Kata Sandi) -->
<?php if (role() === 'admin'): ?>
<div id="resetModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.65); z-index: 9999; align-items: center; justify-content: center; padding: 16px; backdrop-filter: blur(4px);">
  <div class="card" style="width: 100%; max-width: 500px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35); border-radius: 16px; border: 1px solid #E2E8F0; overflow: hidden; animation: modalSlide 0.2s cubic-bezier(0.16, 1, 0.3, 1);">
    
    <!-- Modal Header -->
    <div style="padding: 20px 24px; border-bottom: 1px solid #F1F5F9; display: flex; align-items: center; justify-content: space-between; background: #FFFFFF;">
      <div style="display: flex; align-items: center; gap: 14px;">
        <div style="width: 44px; height: 44px; border-radius: 12px; background: #FEE2E2; color: #DC2626; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
          <i data-lucide="alert-triangle" style="width: 22px; height: 22px;"></i>
        </div>
        <div>
          <h3 style="font-size: 16px; font-weight: 700; color: #0F172A; margin: 0; line-height: 1.3;">Konfirmasi Reset Data</h3>
          <p style="font-size: 12.5px; color: #64748B; margin: 2px 0 0 0;">Tindakan destruktif ini tidak dapat dibatalkan</p>
        </div>
      </div>
      <button type="button" onclick="closeResetModal()" style="width: 32px; height: 32px; border-radius: 8px; border: none; background: #F8FAFC; color: #64748B; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.15s ease;" onmouseover="this.style.background='#F1F5F9'; this.style.color='#0F172A';" onmouseout="this.style.background='#F8FAFC'; this.style.color='#64748B';">
        <i data-lucide="x" style="width: 17px; height: 17px;"></i>
      </button>
    </div>

    <!-- Modal Body -->
    <div style="padding: 22px 24px; background: #FFFFFF;">
      <!-- Alert Notice -->
      <div style="background: #FFF7ED; border: 1px solid #FFEDD5; border-radius: 10px; padding: 14px 16px; margin-bottom: 14px;">
        <p style="font-size: 13px; color: #9A3412; line-height: 1.55; margin: 0;">
          <strong>Perhatian:</strong> Tindakan ini akan <strong>mengosongkan seluruh produk, data training, riwayat proses KNN, parameter normalisasi, dan hasil evaluasi</strong>.
        </p>
      </div>

      <!-- Safe Users Table Badge -->
      <div style="background: #F0FDF4; border: 1px solid #DCFCE7; border-radius: 10px; padding: 12px 14px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
        <i data-lucide="shield-check" style="width: 18px; height: 18px; color: #16A34A; flex-shrink: 0;"></i>
        <span style="font-size: 12.5px; color: #166534; line-height: 1.4;">
          <strong>Tabel Akun Pengguna (users) AMAN</strong> dan tidak akan terhapus. Anda tetap dapat login dengan normal.
        </span>
      </div>

      <!-- Form Verifikasi Sandi -->
      <form method="POST" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="reset_all_data">

        <div class="form-group" style="margin-bottom: 22px;">
          <label class="form-label" for="admin_password" style="font-weight: 600; font-size: 13px; color: #1E293B; margin-bottom: 6px;">
            Masukkan Kata Sandi Admin untuk Verifikasi:
          </label>
          <div style="position: relative;">
            <input
              type="password"
              id="admin_password"
              name="admin_password"
              class="form-control"
              placeholder="Ketik password admin Anda"
              required
              autocomplete="current-password"
              style="padding-right: 42px; height: 42px; font-size: 13.5px; border-radius: 8px; border: 1.5px solid #CBD5E1;"
            >
            <button
              type="button"
              onclick="toggleAdminPasswordModal()"
              style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #94A3B8; cursor: pointer; padding: 4px; display: flex; align-items: center;"
              title="Lihat kata sandi"
            >
              <i id="toggleAdminPassIcon" data-lucide="eye" style="width: 17px; height: 17px;"></i>
            </button>
          </div>
          <p style="font-size: 11.5px; color: #64748B; margin-top: 5px; margin-bottom: 0;">
            Gunakan kata sandi akun admin saat ini (<code style="background: #F1F5F9; padding: 1px 5px; border-radius: 4px; color: #475569;">admin / password</code>).
          </p>
        </div>

        <!-- Footer Buttons -->
        <div style="display: flex; gap: 10px; justify-content: flex-end; align-items: center; border-top: 1px solid #F1F5F9; padding-top: 18px;">
          <button type="button" class="btn btn-secondary" onclick="closeResetModal()" style="font-weight: 600; padding: 10px 18px; border-radius: 8px;">
            Batal
          </button>
          <button type="submit" class="btn btn-danger" style="background: #DC2626 !important; color: #FFFFFF !important; font-weight: 600; padding: 10px 20px; border-radius: 8px; border: none; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 2px 6px rgba(220, 38, 38, 0.35); cursor: pointer;">
            <i data-lucide="trash-2" style="width: 15px; height: 15px; stroke: #FFFFFF;"></i>
            <span style="color: #FFFFFF !important;">Ya, Kosongkan Seluruh Data</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  function openResetModal() {
    const m = document.getElementById('resetModal');
    if (m) {
      m.style.display = 'flex';
      if (window.lucide) {
        lucide.createIcons();
      }
      setTimeout(() => {
        const inp = document.getElementById('admin_password');
        if (inp) inp.focus();
      }, 50);
    }
  }

  function closeResetModal() {
    const m = document.getElementById('resetModal');
    if (m) {
      m.style.display = 'none';
      const inp = document.getElementById('admin_password');
      if (inp) {
        inp.value = '';
        inp.type = 'password';
      }
      const icon = document.getElementById('toggleAdminPassIcon');
      if (icon) {
        icon.setAttribute('data-lucide', 'eye');
        if (window.lucide) lucide.createIcons();
      }
    }
  }

  function toggleAdminPasswordModal() {
    const inp = document.getElementById('admin_password');
    const icon = document.getElementById('toggleAdminPassIcon');
    if (inp) {
      if (inp.type === 'password') {
        inp.type = 'text';
        if (icon) icon.setAttribute('data-lucide', 'eye-off');
      } else {
        inp.type = 'password';
        if (icon) icon.setAttribute('data-lucide', 'eye');
      }
      if (window.lucide) lucide.createIcons();
    }
  }

  // Tutup modal jika klik di luar box atau tekan tombol Escape
  window.addEventListener('click', function(e) {
    const m = document.getElementById('resetModal');
    if (e.target === m) {
      closeResetModal();
    }
  });

  window.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      closeResetModal();
    }
  });
</script>
<?php endif; ?>

<?php if (array_sum($chart_dist) > 0): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const labels = <?= $chart_labels ?>;
    const data   = <?= $chart_data ?>;
    const colors = ['#16A34A', '#D97706', '#DC2626'];
    const ctx = document.getElementById('chartDoughnutDashboard').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: colors,
                borderWidth: 2,
                borderColor: '#FFFFFF'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 12,
                        padding: 14,
                        font: { size: 12, family: "'Inter', sans-serif" }
                    }
                }
            },
            cutout: '68%'
        }
    });
});
</script>
<?php endif; ?>

<?php include APP_PATH . '/views/layouts/footer.php'; ?>
