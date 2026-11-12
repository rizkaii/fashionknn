<?php
require_once dirname(__DIR__) . '/app/config/database.php';
require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/helpers/functions.php';
require_once dirname(__DIR__) . '/app/middleware/access.php';
require_once dirname(__DIR__) . '/app/services/DatasetService.php';

check_access('dataset');
$datasetService = new DatasetService($pdo);

// 1. Handle Download Template CSV
if (isset($_GET['action']) && $_GET['action'] === 'template') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=template_dataset_fashion.csv');
    echo $datasetService->generateTemplateCsv();
    exit;
}

$page_title = 'Import Dataset CSV';
$current_page = 'dataset';

$error = null;
$success = null;
$preview_data = null;

// 2. Handle Form: Preview CSV
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'preview') {
    csrf_check();

    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Silakan pilih file CSV yang valid untuk diunggah.';
    } else {
        $file = $_FILES['csv_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if ($ext !== 'csv') {
            $error = 'Format file tidak didukung. Harap unggah file berekstensi .csv.';
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $error = 'Ukuran file melebihi batas maksimal 5 MB.';
        } else {
            try {
                $preview_data = $datasetService->parseAndValidate($file['tmp_name']);
                $_SESSION['pending_import'] = [
                    'filename'   => $file['name'],
                    'valid_rows' => $preview_data['valid_rows'],
                ];
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    }
}

// 3. Handle Form: Confirm Import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_import') {
    csrf_check();

    if (empty($_SESSION['pending_import']['valid_rows'])) {
        $error = 'Tidak ada data valid yang siap diimpor. Silakan unggah file CSV kembali.';
    } else {
        $skip_dup = isset($_POST['skip_duplicates']) && $_POST['skip_duplicates'] === '1';
        try {
            $result = $datasetService->importRows($_SESSION['pending_import']['valid_rows'], $skip_dup);
            $success = "Proses import berhasil! {$result['imported']} data berhasil dimasukkan ke sistem." .
                       ($result['skipped'] > 0 ? " ({$result['skipped']} data duplikat dilewati)." : "");
            unset($_SESSION['pending_import']);
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// 4. Handle Cancel Import Preview
if (isset($_GET['cancel_preview'])) {
    unset($_SESSION['pending_import']);
    header('Location: ' . BASE_URL . '/dataset.php');
    exit;
}

include dirname(__DIR__) . '/app/views/layouts/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Import Dataset CSV</h1>
    <p class="page-subtitle">Unggah dataset produk fashion dalam format CSV untuk menambah data training maupun pengujian.</p>
  </div>
  <div>
    <a href="?action=template" class="btn btn-outline">
      <i data-lucide="download"></i> Unduh Template CSV
    </a>
  </div>
</div>

<?php if ($error): ?>
  <div class="alert alert-error">
    <i data-lucide="alert-triangle"></i>
    <span><?= h($error) ?></span>
  </div>
<?php endif; ?>

<?php if ($success): ?>
  <div class="alert alert-success">
    <i data-lucide="check-circle"></i>
    <span><?= h($success) ?></span>
  </div>
<?php endif; ?>

<!-- Tahap 1: Upload Form -->
<?php if (!$preview_data && empty($_SESSION['pending_import'])): ?>
<div class="card" style="max-width: 720px; margin: 0 auto;">
  <div class="card-header">
    <h3 class="card-title">Unggah Berkas CSV</h3>
    <p class="card-subtitle">Pastikan berkas CSV Anda memiliki kolom wajib: <code>name</code>, <code>sold</code>, <code>rating</code>, <code>price_now</code>. Kolom <code>label</code> bersifat opsional.</p>
  </div>
  <div class="card-body">
    <form action="" method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="preview">

      <div class="form-group">
        <label class="form-label" for="csv_file">Pilih Berkas CSV (.csv)</label>
        <input type="file" name="csv_file" id="csv_file" accept=".csv" required class="form-control">
        <small class="form-hint">Maksimal ukuran file: 5 MB. Encoding disarankan UTF-8.</small>
      </div>

      <div class="alert alert-info mt-3" style="font-size: 13px;">
        <strong>Ketentuan Validasi:</strong>
        <ul style="margin: 6px 0 0 18px; line-height: 1.6;">
          <li><code>name</code>: teks nama produk, tidak boleh kosong.</li>
          <li><code>sold</code>: bilangan bulat non-negatif (&ge; 0).</li>
          <li><code>rating</code>: angka desimal skala 0.00 hingga 5.00.</li>
          <li><code>price_now</code>: angka harga produk non-negatif (&ge; 0).</li>
          <li><code>label</code>: opsional ('tren', 'kurang tren', 'tidak tren', atau kosong).</li>
        </ul>
      </div>

      <div class="mt-4 flex gap-3">
        <button type="submit" class="btn btn-primary">
          <i data-lucide="eye"></i> Validasi & Preview CSV
        </button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- Tahap 2: Preview & Verifikasi Hasil Validasi -->
<?php if ($preview_data): ?>
<div class="card mb-4">
  <div class="card-header flex justify-between items-center">
    <div>
      <h3 class="card-title">Hasil Pemeriksaan & Preview CSV</h3>
      <p class="card-subtitle">Periksa ringkasan keabsahan data sebelum melakukan penyimpanan ke basis data.</p>
    </div>
    <a href="?cancel_preview=1" class="btn btn-sm btn-secondary">
      <i data-lucide="x"></i> Batal / Upload Ulang
    </a>
  </div>
  <div class="card-body">
    <div class="stats-grid mb-4">
      <div class="stat-card">
        <div class="stat-card-label">Total Baris</div>
        <div class="stat-card-value"><?= $preview_data['total_rows'] ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-card-label">Data Valid</div>
        <div class="stat-card-value text-success"><?= $preview_data['valid_count'] ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-card-label">Data Tidak Valid</div>
        <div class="stat-card-value text-danger"><?= $preview_data['invalid_count'] ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-card-label">Terdeteksi Duplikat</div>
        <div class="stat-card-value text-warning"><?= $preview_data['duplicate_count'] ?></div>
      </div>
    </div>

    <!-- Konfirmasi Simpan Form -->
    <?php if ($preview_data['valid_count'] > 0): ?>
      <form action="" method="post" class="mb-4 p-3" style="background: var(--bg); border: 1px solid var(--border); border-radius: 8px;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="confirm_import">
        <div class="flex items-center gap-3">
          <label style="display: flex; align-items: center; gap: 8px; font-weight: 500; cursor: pointer;">
            <input type="checkbox" name="skip_duplicates" value="1" checked>
            Lewati data yang namanya sudah terdaftar di sistem (Hindari duplikasi)
          </label>
        </div>
        <div class="mt-3">
          <button type="submit" class="btn btn-primary">
            <i data-lucide="check"></i> Simpan <?= $preview_data['valid_count'] ?> Data Valid ke Database
          </button>
        </div>
      </form>
    <?php else: ?>
      <div class="alert alert-error">
        Semua baris dalam berkas CSV tidak memenuhi kriteria validasi. Silakan perbaiki berkas Anda.
      </div>
    <?php endif; ?>

    <!-- Tabel Data Valid -->
    <h4 style="font-size: 15px; margin: 20px 0 10px;">Daftar Data Valid (Siap Diimpor)</h4>
    <div class="table-container mb-4">
      <table>
        <thead>
          <tr>
            <th>Baris</th>
            <th>Nama Produk</th>
            <th>Sold</th>
            <th>Rating</th>
            <th>Harga</th>
            <th>Label Aktual</th>
            <th>Status Duplikat</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($preview_data['valid_rows'])): ?>
            <tr><td colspan="7" class="text-center text-muted">Tidak ada data valid.</td></tr>
          <?php else: ?>
            <?php foreach (array_slice($preview_data['valid_rows'], 0, 30) as $row): ?>
              <tr>
                <td>#<?= $row['row_num'] ?></td>
                <td><strong><?= h($row['name']) ?></strong></td>
                <td><?= fmt_number($row['sold']) ?></td>
                <td><?= h($row['rating']) ?> ★</td>
                <td><?= fmt_currency($row['price_now']) ?></td>
                <td><?= label_badge($row['label']) ?></td>
                <td>
                  <?php if ($row['is_duplicate']): ?>
                    <span class="badge badge-kurang">Duplikat (<?= $row['dup_source'] ?>)</span>
                  <?php else: ?>
                    <span class="badge badge-gray">Baru</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (count($preview_data['valid_rows']) > 30): ?>
              <tr><td colspan="7" class="text-center text-muted">... dan <?= count($preview_data['valid_rows']) - 30 ?> baris valid lainnya.</td></tr>
            <?php endif; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Tabel Data Tidak Valid Jika Ada -->
    <?php if (!empty($preview_data['invalid_rows'])): ?>
      <h4 style="font-size: 15px; margin: 20px 0 10px; color: var(--tidak-text);">Daftar Data Tidak Valid (Akan Dilewati)</h4>
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>Baris</th>
              <th>Nama Produk</th>
              <th>Sold</th>
              <th>Rating</th>
              <th>Harga</th>
              <th>Keterangan Kesalahan</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($preview_data['invalid_rows'] as $inv): ?>
              <tr style="background: var(--tidak-bg);">
                <td>#<?= $inv['row_num'] ?></td>
                <td><?= h($inv['name'] ?: '(Kosong)') ?></td>
                <td><?= h($inv['sold']) ?></td>
                <td><?= h($inv['rating']) ?></td>
                <td><?= h($inv['price_now']) ?></td>
                <td>
                  <ul style="margin: 0; padding-left: 16px; color: var(--tidak-text);">
                    <?php foreach ($inv['errors'] as $err): ?>
                      <li><?= h($err) ?></li>
                    <?php endforeach; ?>
                  </ul>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php include dirname(__DIR__) . '/app/views/layouts/footer.php'; ?>

