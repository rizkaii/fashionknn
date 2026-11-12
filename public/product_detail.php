<?php
require_once __DIR__ . '/../app/config/database.php';
require_once APP_PATH . '/helpers/auth.php';
require_once APP_PATH . '/helpers/functions.php';
require_once APP_PATH . '/middleware/access.php';
check_access('results');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: results.php');
    exit;
}

// Fetch product
$product = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch();
} catch (Exception $e) {}

if (!$product) {
    http_response_code(404);
    $page_title   = 'Produk Tidak Ditemukan';
    $current_page = 'results';
    require_once APP_PATH . '/views/layouts/header.php';
    echo '<div class="empty-state"><i data-lucide="alert-circle"></i><p>Produk dengan ID '.h($id).' tidak ditemukan.</p></div>';
    require_once APP_PATH . '/views/layouts/footer.php';
    exit;
}

// Fetch latest KNN prediction
$kp = null;
$neighbors = [];
try {
    $stmt = $pdo->prepare("
        SELECT kp.*, kpr.k_value, kpr.split_ratio, kpr.accuracy
        FROM knn_predictions kp
        LEFT JOIN knn_processes kpr ON kpr.id = kp.process_id
        WHERE kp.product_id = ?
        ORDER BY kp.process_id DESC
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $kp = $stmt->fetch();
    if ($kp && !empty($kp['neighbors_json'])) {
        $neighbors = json_decode($kp['neighbors_json'], true) ?: [];
    }
} catch (Exception $e) {
    $kp = null;
}

// Compute vote counts from neighbors
$votes = [];
foreach ($neighbors as $nb) {
    $lbl = strtolower(trim($nb['label'] ?? ''));
    if ($lbl) $votes[$lbl] = ($votes[$lbl] ?? 0) + 1;
}
arsort($votes);
$total_votes = array_sum($votes);

$page_title   = 'Detail Produk: ' . ($product['name'] ?? '');
$current_page = 'results';
require_once APP_PATH . '/views/layouts/header.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <a href="results.php" class="btn btn-secondary btn-sm" style="margin-bottom:.5rem">
      <i data-lucide="arrow-left"></i> Kembali
    </a>
    <h1 class="page-title"><i data-lucide="box"></i> Detail Produk</h1>
    <p class="page-subtitle">ID #<?= h($product['id']) ?></p>
  </div>
</div>

<?= flash_html() ?>

<div class="detail-grid">

  <!-- ========== INFO PRODUK ========== -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title"><i data-lucide="info"></i> Info Produk</h3>
    </div>
    <div class="card-body">
      <table class="detail-table">
        <tr>
          <th>Nama Produk</th>
          <td><strong><?= h($product['name']) ?></strong></td>
        </tr>
        <tr>
          <th>Terjual</th>
          <td><?= fmt_number($product['sold'] ?? 0) ?> unit</td>
        </tr>
        <tr>
          <th>Rating</th>
          <td>★ <?= number_format((float)($product['rating'] ?? 0), 2) ?></td>
        </tr>
        <tr>
          <th>Harga Sekarang</th>
          <td><?= fmt_currency($product['price_now'] ?? 0) ?></td>
        </tr>
        <?php if (!empty($product['price_before'])): ?>
        <tr>
          <th>Harga Sebelumnya</th>
          <td><?= fmt_currency($product['price_before']) ?></td>
        </tr>
        <?php endif; ?>
        <tr>
          <th>Label Aktual</th>
          <td><?= label_badge($product['label'] ?? '') ?></td>
        </tr>
        <tr>
          <th>Hasil Prediksi</th>
          <td><?= pred_badge($product['prediction'] ?? '') ?></td>
        </tr>
        <?php if (!empty($product['prediction']) && !empty($product['label'])): ?>
        <tr>
          <th>Akurasi</th>
          <td>
            <?php if ($product['label'] === $product['prediction']): ?>
              <span class="badge badge-tren"><i data-lucide="check"></i> Benar</span>
            <?php else: ?>
              <span class="badge badge-tidak"><i data-lucide="x"></i> Salah</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endif; ?>
      </table>
    </div>
  </div>

  <!-- ========== NILAI FITUR ========== -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title"><i data-lucide="sliders"></i> Nilai Fitur</h3>
    </div>
    <div class="card-body">
      <table class="table">
        <thead>
          <tr>
            <th>Fitur</th>
            <th class="text-right">Nilai Asli</th>
            <th class="text-right">Nilai Normalisasi</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>Sold (Terjual)</td>
            <td class="text-right"><?= fmt_number($product['sold'] ?? 0) ?></td>
            <td class="text-right">
              <?= $kp ? number_format((float)$kp['normalized_sold'], 6) : '<span class="text-muted">-</span>' ?>
            </td>
          </tr>
          <tr>
            <td>Rating</td>
            <td class="text-right"><?= number_format((float)($product['rating'] ?? 0), 2) ?></td>
            <td class="text-right">
              <?= $kp ? number_format((float)$kp['normalized_rating'], 6) : '<span class="text-muted">-</span>' ?>
            </td>
          </tr>
          <tr>
            <td>Harga (Price Now)</td>
            <td class="text-right"><?= fmt_currency($product['price_now'] ?? 0) ?></td>
            <td class="text-right">
              <?= $kp ? number_format((float)$kp['normalized_price'], 6) : '<span class="text-muted">-</span>' ?>
            </td>
          </tr>
        </tbody>
      </table>
      <?php if (!$kp): ?>
        <p class="text-muted" style="margin-top:.75rem;font-size:.85rem">
          <i data-lucide="info"></i> Nilai normalisasi belum tersedia. Jalankan Proses KNN terlebih dahulu.
        </p>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($kp): ?>
  <!-- ========== PROSES KNN ========== -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title"><i data-lucide="cpu"></i> Proses KNN</h3>
      <?php if (!empty($kp['k_value'])): ?>
        <span class="badge badge-gray">K = <?= (int)$kp['k_value'] ?></span>
      <?php endif; ?>
    </div>
    <div class="card-body">
      <?php if (!empty($kp['process_id'])): ?>
      <p class="text-muted" style="font-size:.85rem;margin-bottom:.75rem">
        Process ID: #<?= (int)$kp['process_id'] ?>
        <?php if (!empty($kp['accuracy'])): ?>
          &nbsp;|&nbsp; Akurasi Proses: <?= fmt_percent($kp['accuracy'] * 100) ?>
        <?php endif; ?>
        &nbsp;|&nbsp; Tipe: <?= $kp['is_test_data'] ? 'Data Uji' : 'Data Latih' ?>
      </p>
      <?php endif; ?>

      <?php if (!empty($neighbors)): ?>
      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th>Rank</th>
              <th>Nama Produk Tetangga</th>
              <th class="text-right">Jarak Euclidean</th>
              <th>Kategori</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($neighbors as $i => $nb): ?>
            <tr>
              <td class="text-muted"><?= $i + 1 ?></td>
              <td>
                <?php if (!empty($nb['product_id'])): ?>
                  <a href="product_detail.php?id=<?= (int)$nb['product_id'] ?>" class="table-link">
                    <?= h($nb['product_name'] ?? 'Produk #' . $nb['product_id']) ?>
                  </a>
                <?php else: ?>
                  <?= h($nb['product_name'] ?? '-') ?>
                <?php endif; ?>
              </td>
              <td class="text-right font-mono"><?= number_format((float)($nb['distance'] ?? 0), 6) ?></td>
              <td><?= label_badge($nb['label'] ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
        <p class="text-muted">Tidak ada data tetangga tersimpan untuk produk ini.</p>
      <?php endif; ?>
    </div>
  </div>

  <!-- ========== HASIL VOTING ========== -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title"><i data-lucide="vote"></i> Hasil Voting</h3>
    </div>
    <div class="card-body">
      <?php if (!empty($votes)): ?>
      <table class="table" style="margin-bottom:1rem">
        <thead>
          <tr>
            <th>Kategori</th>
            <th class="text-right">Jumlah Suara</th>
            <th class="text-right">Persentase</th>
          </tr>
        </thead>
        <tbody>
          <?php $rank_v = 0; foreach ($votes as $lbl => $cnt): 
              $rank_v++;
              $pct = $total_votes > 0 ? ($cnt / $total_votes * 100) : 0;
              $is_winner = $rank_v === 1;
          ?>
          <tr class="<?= $is_winner ? 'row-highlight' : '' ?>">
            <td>
              <?= label_badge($lbl) ?>
              <?= $is_winner ? ' <span class="badge badge-tren" style="font-size:.7rem">Pemenang</span>' : '' ?>
            </td>
            <td class="text-right"><strong><?= (int)$cnt ?></strong></td>
            <td class="text-right">
              <div class="vote-bar-wrap">
                <div class="vote-bar" style="width:<?= $pct ?>%"></div>
                <span><?= fmt_percent($pct) ?></span>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div style="text-align:center;padding:.75rem;background:var(--gray-bg);border-radius:8px">
        <span style="font-size:.85rem;color:var(--text-muted)">Hasil Akhir Prediksi:</span><br>
        <?= pred_badge($product['prediction'] ?? '') ?>
      </div>
      <?php else: ?>
        <p class="text-muted">Data voting tidak tersedia.</p>
      <?php endif; ?>
    </div>
  </div>
  <?php else: ?>
  <!-- No prediction -->
  <div class="card" style="grid-column:1/-1">
    <div class="card-body">
      <div class="empty-state">
        <i data-lucide="clock"></i>
        <p>Belum ada prediksi untuk produk ini. Jalankan Proses KNN untuk mendapatkan prediksi.</p>
        <?php if (role() === 'admin'): ?>
          <a href="process.php" class="btn btn-primary">
            <i data-lucide="cpu"></i> Jalankan KNN
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

</div><!-- /.detail-grid -->

<style>
.detail-grid { display:grid; grid-template-columns:1fr 1fr; gap:1.25rem; }
@media(max-width:768px){ .detail-grid { grid-template-columns:1fr; } }
.detail-table { width:100%; border-collapse:collapse; }
.detail-table th { width:40%; padding:.5rem .75rem; text-align:left; color:var(--text-muted); font-weight:500; font-size:.875rem; }
.detail-table td { padding:.5rem .75rem; }
.detail-table tr { border-bottom:1px solid var(--border); }
.detail-table tr:last-child { border-bottom:none; }
.row-highlight td { background:#FFFBEB; }
.vote-bar-wrap { display:flex; align-items:center; gap:.5rem; }
.vote-bar { height:8px; background:#4F46E5; border-radius:4px; min-width:2px; transition:width .3s; }
.font-mono { font-family: 'Courier New', monospace; }
</style>

<?php require_once APP_PATH . '/views/layouts/footer.php'; ?>
