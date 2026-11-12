<?php
require_once dirname(__DIR__) . '/app/config/database.php';
require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/helpers/functions.php';
require_once dirname(__DIR__) . '/app/middleware/access.php';
require_once dirname(__DIR__) . '/app/services/PreprocessingService.php';

check_access('preprocessing');
$page_title = 'Preprocessing Data & Normalisasi';
$current_page = 'preprocessing';

$preService = new PreprocessingService($pdo);
$stats = $preService->getDataStats();
$labeled_data = $preService->getLabeledData();
$norm_params = $preService->calcNormParams($labeled_data);
$preview_rows = $preService->getNormalizationPreview($labeled_data, $norm_params);

// Simulasi Split 70:30 dan 80:20 untuk dokumentasi
$split_70 = !empty($labeled_data) ? $preService->splitData($labeled_data, 70, 42) : ['train' => [], 'test' => []];
$split_80 = !empty($labeled_data) ? $preService->splitData($labeled_data, 80, 42) : ['train' => [], 'test' => []];

include dirname(__DIR__) . '/app/views/layouts/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Preprocessing Data & Normalisasi</h1>
    <p class="page-subtitle">Dokumentasi sistematis tahapan persiapan data numerik produk fashion sebelum diproses oleh algoritma K-Nearest Neighbor (KNN).</p>
  </div>
  <div>
    <a href="<?= BASE_URL ?>/process.php" class="btn btn-primary">
      <i data-lucide="cpu"></i> Lanjut ke Proses Klasifikasi KNN
    </a>
  </div>
</div>

<!-- TAHAP 1: SELEKSI FITUR -->
<div class="card mb-4">
  <div class="card-header">
    <div class="flex items-center gap-2">
      <span class="badge badge-gray" style="font-weight:700;">TAHAP 1</span>
      <h3 class="card-title">Seleksi Fitur (Feature Selection)</h3>
    </div>
    <p class="card-subtitle">Pemilihan atribut numerik independen yang relevan untuk mengukur kesamaan (similarity) tren produk.</p>
  </div>
  <div class="card-body">
    <p style="font-size: 13px; line-height: 1.6; color: var(--text-muted); margin-bottom: 16px;">
      Berdasarkan metodologi analisis tren produk fashion e-commerce (Tokopedia), klasifikasi menggunakan 3 fitur kuantitatif kontinu:
    </p>
    <div class="stats-grid">
      <div class="stat-card" style="border-top: 3px solid var(--primary);">
        <div style="font-weight: 700; color: var(--primary); margin-bottom: 4px;">1. Sold (X₁)</div>
        <div class="stat-card-label">Volume Terjual</div>
        <p style="font-size: 12px; color: var(--text-muted); margin-top: 8px;">
          Mengindikasikan tingkat permintaan dan penerimaan pasar terhadap produk.
        </p>
      </div>
      <div class="stat-card" style="border-top: 3px solid var(--primary);">
        <div style="font-weight: 700; color: var(--primary); margin-bottom: 4px;">2. Rating (X₂)</div>
        <div class="stat-card-label">Penilaian Pembeli (0.0 - 5.0)</div>
        <p style="font-size: 12px; color: var(--text-muted); margin-top: 8px;">
          Menggambarkan kepuasan pelanggan dan kualitas produk menurut ulasan konsumen.
        </p>
      </div>
      <div class="stat-card" style="border-top: 3px solid var(--primary);">
        <div style="font-weight: 700; color: var(--primary); margin-bottom: 4px;">3. Price Now (X₃)</div>
        <div class="stat-card-label">Harga Jual Saat Ini (Rp)</div>
        <p style="font-size: 12px; color: var(--text-muted); margin-top: 8px;">
          Segmen harga produk yang mempengaruhi daya beli target pasar konsumen.
        </p>
      </div>
    </div>
    <div class="alert alert-info mt-3" style="font-size: 12px;">
      <i data-lucide="info"></i>
      <span><strong>Catatan:</strong> Atribut <code>label</code> (Tren, Kurang Tren, Tidak Tren) berfungsi sebagai target/ground truth dan sama sekali tidak dimasukkan sebagai fitur masukan perhitungan jarak.</span>
    </div>
  </div>
</div>

<!-- TAHAP 2: PEMERIKSAAN KUALITAS DATA -->
<div class="card mb-4">
  <div class="card-header">
    <div class="flex items-center gap-2">
      <span class="badge badge-gray" style="font-weight:700;">TAHAP 2</span>
      <h3 class="card-title">Pemeriksaan Kualitas Dataset (Data Quality Check)</h3>
    </div>
    <p class="card-subtitle">Verifikasi integritas dataset, missing values, dan deteksi data duplikat.</p>
  </div>
  <div class="card-body">
    <div class="stats-grid mb-3">
      <div class="stat-card">
        <div class="stat-card-label">Total Entri Produk</div>
        <div class="stat-card-value"><?= fmt_number($stats['total']) ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-card-label">Data Berlabel (Training)</div>
        <div class="stat-card-value text-success"><?= fmt_number($stats['labeled']) ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-card-label">Data Tanpa Label (Prediksi)</div>
        <div class="stat-card-value text-muted"><?= fmt_number($stats['unlabeled']) ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-card-label">Nilai Kosong (Missing Values)</div>
        <div class="stat-card-value <?= $stats['missing_values'] > 0 ? 'text-danger' : 'text-success' ?>">
          <?= $stats['missing_values'] ?>
        </div>
      </div>
    </div>

    <?php if ($stats['duplicates'] > 0): ?>
      <div class="alert alert-warning">
        <i data-lucide="alert-triangle"></i>
        <span>Terdeteksi <?= $stats['duplicates'] ?> entri produk yang memiliki fitur dan label yang identik (potensi duplikat).</span>
      </div>
    <?php else: ?>
      <div class="alert alert-success">
        <i data-lucide="check-circle"></i>
        <span>Kualitas dataset baik: Tidak ditemukan missing values pada fitur numerik.</span>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- TAHAP 3: NORMALISASI MIN-MAX SCALING -->
<div class="card mb-4">
  <div class="card-header">
    <div class="flex items-center gap-2">
      <span class="badge badge-gray" style="font-weight:700;">TAHAP 3</span>
      <h3 class="card-title">Normalisasi Fitur (Min-Max Feature Scaling)</h3>
    </div>
    <p class="card-subtitle">Transformasi linear untuk menyetarakan skala rentang seluruh fitur ke dalam interval [0, 1].</p>
  </div>
  <div class="card-body">
    <!-- Rumus -->
    <div class="p-3 mb-4" style="background: #F1F5F9; border-radius: 8px; border: 1px solid var(--border);">
      <div style="font-weight: 600; margin-bottom: 4px; font-size: 13px;">Rumus Min-Max Normalization:</div>
      <div style="font-family: monospace; font-size: 14px; color: var(--primary);">
        X' = (X - X_min) / (X_max - X_min)
      </div>
      <p style="font-size: 12px; color: var(--text-muted); margin: 6px 0 0;">
        Di mana <code>X</code> adalah nilai aktual, <code>X_min</code> adalah nilai terendah pada dataset training,
        dan <code>X_max</code> adalah nilai tertinggi pada dataset training.
        Jika <code>X_max == X_min</code>, sistem menetapkan hasil <code>0.0</code> secara aman untuk menghindari pembagian dengan nol.
      </p>
    </div>

    <!-- Parameter Min-Max Aktual -->
    <h4 style="font-size: 14px; margin-bottom: 8px;">Parameter Batas Normalisasi (Dari Data Training Berlabel):</h4>
    <div class="table-container mb-4">
      <table>
        <thead>
          <tr>
            <th>Fitur</th>
            <th>Nilai Minimum (X_min)</th>
            <th>Nilai Maksimum (X_max)</th>
            <th>Rentang Skala (Range)</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><strong>Sold (Jumlah Terjual)</strong></td>
            <td><?= fmt_number($norm_params['sold']['min']) ?> unit</td>
            <td><?= fmt_number($norm_params['sold']['max']) ?> unit</td>
            <td><?= fmt_number($norm_params['sold']['max'] - $norm_params['sold']['min']) ?></td>
          </tr>
          <tr>
            <td><strong>Rating (Penilaian)</strong></td>
            <td><?= number_format($norm_params['rating']['min'], 2) ?> ★</td>
            <td><?= number_format($norm_params['rating']['max'], 2) ?> ★</td>
            <td><?= number_format($norm_params['rating']['max'] - $norm_params['rating']['min'], 2) ?></td>
          </tr>
          <tr>
            <td><strong>Price Now (Harga)</strong></td>
            <td><?= fmt_currency($norm_params['price_now']['min']) ?></td>
            <td><?= fmt_currency($norm_params['price_now']['max']) ?></td>
            <td><?= fmt_currency($norm_params['price_now']['max'] - $norm_params['price_now']['min']) ?></td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Tabel Preview Normalisasi -->
    <h4 style="font-size: 14px; margin-bottom: 8px;">Preview Hasil Transformasi Data (10 Sampel Teratas):</h4>
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th rowspan="2">Nama Produk</th>
            <th colspan="3" style="text-align: center; border-right: 1px solid var(--border);">Nilai Asli</th>
            <th colspan="3" style="text-align: center; background: #eef2ff;">Nilai Hasil Normalisasi [0, 1]</th>
            <th rowspan="2">Label Aktual</th>
          </tr>
          <tr>
            <th>Sold</th>
            <th>Rating</th>
            <th style="border-right: 1px solid var(--border);">Price Now</th>
            <th style="background: #eef2ff;">Sold'</th>
            <th style="background: #eef2ff;">Rating'</th>
            <th style="background: #eef2ff;">Price'</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($preview_rows)): ?>
            <tr><td colspan="8" class="text-center text-muted">Belum ada data latih untuk dinormalisasi.</td></tr>
          <?php else: ?>
            <?php foreach ($preview_rows as $pr): ?>
              <tr>
                <td><strong><?= h($pr['name']) ?></strong></td>
                <td><?= fmt_number($pr['sold']) ?></td>
                <td><?= h($pr['rating']) ?></td>
                <td style="border-right: 1px solid var(--border);"><?= fmt_currency($pr['price_now']) ?></td>
                <td style="background: #f8faff; font-family: monospace;"><?= number_format($pr['sold_n'], 4) ?></td>
                <td style="background: #f8faff; font-family: monospace;"><?= number_format($pr['rating_n'], 4) ?></td>
                <td style="background: #f8faff; font-family: monospace;"><?= number_format($pr['price_n'], 4) ?></td>
                <td><?= label_badge($pr['label']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- TAHAP 4: PEMBAGIAN DATASET (DATA SPLITTING) -->
<div class="card">
  <div class="card-header">
    <div class="flex items-center gap-2">
      <span class="badge badge-gray" style="font-weight:700;">TAHAP 4</span>
      <h3 class="card-title">Pembagian Dataset Training & Testing (Stratified Split)</h3>
    </div>
    <p class="card-subtitle">Pemisahan data berlabel menjadi Data Latih dan Data Uji secara proporsional untuk mengukur generalisasi model tanpa bias.</p>
  </div>
  <div class="card-body">
    <div class="grid two" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
      <!-- Opsi 70:30 -->
      <div class="p-4" style="border: 1px solid var(--border); border-radius: 10px; background: #fff;">
        <h4 style="font-size: 15px; margin-bottom: 10px; color: var(--primary);">Opsi A: Rasio 70% Training / 30% Testing</h4>
        <div class="stats-grid mb-3" style="grid-template-columns: 1fr 1fr;">
          <div class="stat-card">
            <div class="stat-card-label">Data Training</div>
            <div class="stat-card-value"><?= count($split_70['train']) ?></div>
          </div>
          <div class="stat-card">
            <div class="stat-card-label">Data Testing</div>
            <div class="stat-card-value text-muted"><?= count($split_70['test']) ?></div>
          </div>
        </div>
        <p style="font-size: 12px; color: var(--text-muted); line-height: 1.5; margin: 0;">
          Rasio standar yang memberikan proporsi data testing yang cukup besar untuk validasi performa jika dataset memiliki lebih dari 30 sampel.
        </p>
      </div>

      <!-- Opsi 80:20 -->
      <div class="p-4" style="border: 1px solid var(--border); border-radius: 10px; background: #fff;">
        <h4 style="font-size: 15px; margin-bottom: 10px; color: var(--primary);">Opsi B: Rasio 80% Training / 20% Testing</h4>
        <div class="stats-grid mb-3" style="grid-template-columns: 1fr 1fr;">
          <div class="stat-card">
            <div class="stat-card-label">Data Training</div>
            <div class="stat-card-value"><?= count($split_80['train']) ?></div>
          </div>
          <div class="stat-card">
            <div class="stat-card-label">Data Testing</div>
            <div class="stat-card-value text-muted"><?= count($split_80['test']) ?></div>
          </div>
        </div>
        <p style="font-size: 12px; color: var(--text-muted); line-height: 1.5; margin: 0;">
          Direkomendasikan apabila dataset berlabel berukuran relatif kecil agar model KNN memiliki referensi tetangga yang lebih kaya saat proses voting.
        </p>
      </div>
    </div>
  </div>
</div>

<?php include dirname(__DIR__) . '/app/views/layouts/footer.php'; ?>

