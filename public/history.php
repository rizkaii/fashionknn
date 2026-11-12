<?php
require_once dirname(__DIR__) . '/app/config/database.php';
require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/helpers/functions.php';
require_once dirname(__DIR__) . '/app/middleware/access.php';

check_access('history');
$page_title = 'Riwayat Proses Klasifikasi KNN';
$current_page = 'history';

// Cek apakah sedang melihat detail suatu proses (?id=N)
$detail_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$detail_proc = null;
$detail_norm_params = [];
$detail_predictions = [];

if ($detail_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM knn_processes WHERE id = ?");
    $stmt->execute([$detail_id]);
    $detail_proc = $stmt->fetch();

    if ($detail_proc) {
        // Ambil parameter normalisasi
        $np_stmt = $pdo->prepare("SELECT * FROM knn_norm_params WHERE process_id = ?");
        $np_stmt->execute([$detail_id]);
        $detail_norm_params = $np_stmt->fetchAll();

        // Ambil sampel data pengujian
        $p_stmt = $pdo->prepare(
            "SELECT kp.*, p.name as product_name, p.sold, p.rating, p.price_now
             FROM knn_predictions kp
             JOIN products p ON p.id = kp.product_id
             WHERE kp.process_id = ? AND kp.is_test_data = 1
             ORDER BY kp.id ASC"
        );
        $p_stmt->execute([$detail_id]);
        $detail_predictions = $p_stmt->fetchAll();
    }
}

// Ambil seluruh daftar proses jika tidak sedang di detail (atau untuk tabel utama)
$processes = [];
try {
    $processes = $pdo->query(
        "SELECT kp.*, u.name as executor_name
         FROM knn_processes kp
         LEFT JOIN users u ON u.id = kp.created_by
         ORDER BY kp.id DESC"
    )->fetchAll();
} catch (Exception $e) {}

include dirname(__DIR__) . '/app/views/layouts/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Riwayat Proses Klasifikasi KNN</h1>
    <p class="page-subtitle">Log audit komprehensif seluruh eksekusi algoritma K-Nearest Neighbor, konfigurasi parameter, dan catatan performa model.</p>
  </div>
  <?php if ($detail_proc): ?>
    <div>
      <a href="<?= BASE_URL ?>/history.php" class="btn btn-secondary">
        <i data-lucide="arrow-left"></i> Kembali ke Daftar Riwayat
      </a>
    </div>
  <?php elseif (role() === 'admin'): ?>
    <div>
      <a href="<?= BASE_URL ?>/process.php" class="btn btn-primary">
        <i data-lucide="play"></i> Jalankan Proses Baru
      </a>
    </div>
  <?php endif; ?>
</div>

<!-- JIKA MELIHAT DETAIL PROSES SPESIFIK -->
<?php if ($detail_proc): ?>
  <div class="card mb-4" style="border-left: 4px solid var(--primary);">
    <div class="card-header flex justify-between items-center">
      <div>
        <h3 class="card-title">Detail Log Proses #<?= $detail_proc['id'] ?></h3>
        <p class="card-subtitle">Waktu Eksekusi: <?= h($detail_proc['processed_at']) ?></p>
      </div>
      <div class="flex gap-2">
        <a href="<?= BASE_URL ?>/evaluation.php?process_id=<?= $detail_proc['id'] ?>" class="btn btn-sm btn-primary">
          <i data-lucide="bar-chart-2"></i> Evaluasi Lengkap
        </a>
      </div>
    </div>
    <div class="card-body">
      <!-- Stat Metrik Proses -->
      <div class="stats-grid mb-4">
        <div class="stat-card">
          <div class="stat-card-label">Nilai K</div>
          <div class="stat-card-value"><?= $detail_proc['k_value'] ?></div>
          <small class="text-muted">Tetangga terdekat</small>
        </div>
        <div class="stat-card">
          <div class="stat-card-label">Rasio Pembagian Data</div>
          <div class="stat-card-value"><?= h($detail_proc['split_ratio']) ?></div>
          <small class="text-muted">Seed: <?= $detail_proc['random_seed'] ?></small>
        </div>
        <div class="stat-card">
          <div class="stat-card-label">Akurasi Pengujian</div>
          <div class="stat-card-value text-success"><?= fmt_percent($detail_proc['accuracy'] * 100) ?></div>
          <small class="text-muted"><?= $detail_proc['test_count'] ?> sampel data uji</small>
        </div>
        <div class="stat-card">
          <div class="stat-card-label">Macro F1-Score</div>
          <div class="stat-card-value" style="color: var(--primary);"><?= fmt_percent($detail_proc['f1_score'] * 100) ?></div>
          <small class="text-muted">Precision: <?= fmt_percent($detail_proc['precision_score'] * 100) ?> &bull; Recall: <?= fmt_percent($detail_proc['recall_score'] * 100) ?></small>
        </div>
      </div>

      <!-- Parameter Batas Normalisasi yang Digunakan -->
      <?php if (!empty($detail_norm_params)): ?>
        <h4 style="font-size: 14px; margin-bottom: 8px;">Parameter Batas Normalisasi (Tersimpan untuk Proses Ini):</h4>
        <div class="table-container mb-4">
          <table>
            <thead>
              <tr>
                <th>Fitur</th>
                <th>Nilai Minimum (X_min)</th>
                <th>Nilai Maksimum (X_max)</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($detail_norm_params as $np): ?>
                <tr>
                  <td><strong><?= h(ucwords(str_replace('_', ' ', $np['feature']))) ?></strong></td>
                  <td><?= number_format($np['min_val'], 2) ?></td>
                  <td><?= number_format($np['max_val'], 2) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <!-- Sampel Data Uji yang Dievaluasi -->
      <h4 style="font-size: 14px; margin-bottom: 8px;">Hasil Pengujian Sampel Test Set (<?= count($detail_predictions) ?> data):</h4>
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>Nama Produk</th>
              <th>Sold</th>
              <th>Rating</th>
              <th>Harga</th>
              <th>Label Aktual</th>
              <th>Prediksi Model</th>
              <th>Status</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($detail_predictions as $dp): ?>
              <tr>
                <td><strong><?= h($dp['product_name']) ?></strong></td>
                <td><?= fmt_number($dp['sold']) ?></td>
                <td><?= h($dp['rating']) ?> ★</td>
                <td><?= fmt_currency($dp['price_now']) ?></td>
                <td><?= label_badge($dp['actual_label']) ?></td>
                <td><?= pred_badge($dp['prediction']) ?></td>
                <td>
                  <?php if ($dp['actual_label'] === $dp['prediction']): ?>
                    <span class="badge badge-tren">Tepat</span>
                  <?php else: ?>
                    <span class="badge badge-tidak">Meleset</span>
                  <?php endif; ?>
                </td>
                <td>
                  <a href="<?= BASE_URL ?>/product_detail.php?id=<?= $dp['product_id'] ?>" class="btn btn-sm btn-outline">Detail KNN</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
<?php endif; ?>

<!-- TABEL DAFTAR SELURUH RIWAYAT PROSES -->
<div class="card">
  <div class="card-header flex justify-between items-center">
    <h3 class="card-title">Daftar Seluruh Eksekusi Klasifikasi KNN (<?= count($processes) ?> proses tercatat)</h3>
  </div>
  <div class="card-body">
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Waktu Eksekusi</th>
            <th>Nilai K</th>
            <th>Rasio Data</th>
            <th>Data Training</th>
            <th>Data Testing</th>
            <th>Akurasi</th>
            <th>Macro Precision</th>
            <th>Macro Recall</th>
            <th>Macro F1</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($processes)): ?>
            <tr>
              <td colspan="11" class="text-center text-muted" style="padding: 30px;">
                Belum ada riwayat proses klasifikasi yang tersimpan.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($processes as $p): ?>
              <tr <?= ($detail_id === (int)$p['id']) ? 'style="background: #EEF2FF;"' : '' ?>>
                <td><strong>#<?= $p['id'] ?></strong></td>
                <td><?= h($p['processed_at']) ?></td>
                <td><span class="badge badge-gray">K = <?= $p['k_value'] ?></span></td>
                <td><?= h($p['split_ratio']) ?></td>
                <td><?= $p['train_count'] ?></td>
                <td><?= $p['test_count'] ?></td>
                <td><strong class="text-success"><?= fmt_percent($p['accuracy'] * 100) ?></strong></td>
                <td><?= fmt_percent($p['precision_score'] * 100) ?></td>
                <td><?= fmt_percent($p['recall_score'] * 100) ?></td>
                <td><strong style="color: var(--primary);"><?= fmt_percent($p['f1_score'] * 100) ?></strong></td>
                <td>
                  <div class="flex gap-2">
                    <a href="?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline">
                      <i data-lucide="eye"></i> Detail
                    </a>
                    <a href="<?= BASE_URL ?>/evaluation.php?process_id=<?= $p['id'] ?>" class="btn btn-sm btn-secondary" title="Buka Evaluasi">
                      <i data-lucide="bar-chart-2"></i> Evaluasi
                    </a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include dirname(__DIR__) . '/app/views/layouts/footer.php'; ?>

