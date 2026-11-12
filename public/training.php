<?php
require_once dirname(__DIR__) . '/app/config/database.php';
require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/helpers/functions.php';
require_once dirname(__DIR__) . '/app/middleware/access.php';

check_access('training');
$page_title = 'Data Training (Ground Truth)';
$current_page = 'training';

$msg_success = null;
$msg_error = null;

// Handle Update Label Produk (Khusus Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_label') {
    if (role() !== 'admin') {
        http_response_code(403);
        die('Hanya admin yang dapat mengubah label aktual data training.');
    }
    csrf_check();

    $product_id = (int)($_POST['product_id'] ?? 0);
    $new_label  = trim($_POST['label'] ?? '');
    $valid_labels = ['tren', 'kurang tren', 'tidak tren', ''];

    if (!in_array(strtolower($new_label), $valid_labels)) {
        $msg_error = 'Kategori label tidak valid.';
    } else {
        $val = $new_label !== '' ? strtolower($new_label) : null;
        $stmt = $pdo->prepare("UPDATE products SET label = ? WHERE id = ?");
        $stmt->execute([$val, $product_id]);
        $msg_success = 'Label aktual produk #' . $product_id . ' berhasil diperbarui.';
    }
}

// 1. Ambil Statistik Distribusi Label
$total_products = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$labeled_count  = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE label IS NOT NULL AND label != ''")->fetchColumn();
$unlabeled_count = $total_products - $labeled_count;

$stats_raw = $pdo->query(
    "SELECT label, COUNT(*) as cnt FROM products
     WHERE label IS NOT NULL AND label != ''
     GROUP BY label"
)->fetchAll();

$class_counts = ['tren' => 0, 'kurang tren' => 0, 'tidak tren' => 0];
foreach ($stats_raw as $sr) {
    if (isset($class_counts[$sr['label']])) {
        $class_counts[$sr['label']] = (int)$sr['cnt'];
    }
}

// 2. Filter & Pencarian
$filter_label = trim($_GET['label'] ?? '');
$search       = trim($_GET['search'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$per_page     = 15;

$sql_where = "WHERE label IS NOT NULL AND label != ''";
$params = [];

if ($filter_label !== '' && in_array($filter_label, ['tren', 'kurang tren', 'tidak tren'])) {
    $sql_where .= " AND label = ?";
    $params[] = $filter_label;
}

if ($search !== '') {
    $sql_where .= " AND name LIKE ?";
    $params[] = "%{$search}%";
}

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM products $sql_where");
$count_stmt->execute($params);
$total_filtered = (int)$count_stmt->fetchColumn();

$pagination = paginate($total_filtered, $per_page, $page);

$query_sql = "SELECT * FROM products $sql_where ORDER BY id DESC LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}";
$data_stmt = $pdo->prepare($query_sql);
$data_stmt->execute($params);
$training_products = $data_stmt->fetchAll();

include dirname(__DIR__) . '/app/views/layouts/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Data Training (Ground Truth)</h1>
    <p class="page-subtitle">Kumpulan data produk fashion yang telah memiliki label aktual sebagai basis referensi klasifikasi K-Nearest Neighbor (KNN).</p>
  </div>
  <?php if (role() === 'admin'): ?>
    <div>
      <a href="<?= BASE_URL ?>/products.php" class="btn btn-secondary">
        <i data-lucide="plus-circle"></i> Tambah / Kelola Produk
      </a>
    </div>
  <?php endif; ?>
</div>

<?php if ($msg_success): ?>
  <div class="alert alert-success"><i data-lucide="check-circle"></i> <span><?= h($msg_success) ?></span></div>
<?php endif; ?>
<?php if ($msg_error): ?>
  <div class="alert alert-error"><i data-lucide="alert-triangle"></i> <span><?= h($msg_error) ?></span></div>
<?php endif; ?>

<!-- Informasi Metodologi -->
<div class="card mb-4" style="border-left: 4px solid var(--primary);">
  <div class="card-body">
    <h4 style="font-size: 15px; margin-bottom: 6px; display: flex; align-items: center; gap: 8px;">
      <i data-lucide="info" style="color: var(--primary);"></i> Peran Data Training dalam Algoritma KNN
    </h4>
    <p class="text-muted" style="font-size: 13px; line-height: 1.6; margin: 0;">
      Algoritma K-Nearest Neighbor (KNN) merupakan metode <em>Supervised Learning</em> yang tidak membentuk model matematis eksplisit,
      melainkan menyimpan seluruh data latih berlabel ke dalam memori. Saat proses klasifikasi, fitur produk baru dinormalisasi
      dan dihitung jarak Euclidean-nya terhadap seluruh data training ini untuk menentukan label prediksi mayoritas dari <em>K</em> tetangga terdekat.
    </p>
  </div>
</div>

<!-- Statistik Distribusi Kelas -->
<div class="stats-grid mb-4">
  <div class="stat-card">
    <div class="stat-card-label">Total Data Training</div>
    <div class="stat-card-value"><?= fmt_number($labeled_count) ?></div>
    <small class="text-muted">Dari total <?= fmt_number($total_products) ?> produk</small>
  </div>
  <div class="stat-card">
    <div class="stat-card-label">Kategori Tren</div>
    <div class="stat-card-value" style="color: var(--tren-text);"><?= fmt_number($class_counts['tren']) ?></div>
    <small class="text-muted"><?= $labeled_count > 0 ? fmt_percent(($class_counts['tren'] / $labeled_count) * 100) : '0%' ?> dari data training</small>
  </div>
  <div class="stat-card">
    <div class="stat-card-label">Kategori Kurang Tren</div>
    <div class="stat-card-value" style="color: var(--kurang-text);"><?= fmt_number($class_counts['kurang tren']) ?></div>
    <small class="text-muted"><?= $labeled_count > 0 ? fmt_percent(($class_counts['kurang tren'] / $labeled_count) * 100) : '0%' ?> dari data training</small>
  </div>
  <div class="stat-card">
    <div class="stat-card-label">Kategori Tidak Tren</div>
    <div class="stat-card-value" style="color: var(--tidak-text);"><?= fmt_number($class_counts['tidak tren']) ?></div>
    <small class="text-muted"><?= $labeled_count > 0 ? fmt_percent(($class_counts['tidak tren'] / $labeled_count) * 100) : '0%' ?> dari data training</small>
  </div>
</div>

<!-- Peringatan jika representasi kelas minim -->
<?php if ($labeled_count > 0 && ($class_counts['tren'] < 3 || $class_counts['kurang tren'] < 3 || $class_counts['tidak tren'] < 3)): ?>
  <div class="alert alert-warning mb-4">
    <i data-lucide="alert-circle"></i>
    <span>
      <strong>Peringatan Representasi Sampel:</strong> Salah satu kelas kategori memiliki kurang dari 3 sampel data training.
      Untuk evaluasi model KNN yang stabil dan seimbang, disarankan mengisi minimal 3 sampel representatif untuk setiap kategori.
    </span>
  </div>
<?php endif; ?>

<!-- Bar Distribusi Visual -->
<div class="card mb-4">
  <div class="card-header">
    <h3 class="card-title">Proporsi Distribusi Kelas Data Training</h3>
  </div>
  <div class="card-body">
    <?php if ($labeled_count > 0): ?>
      <?php
        $pct_tren   = ($class_counts['tren'] / $labeled_count) * 100;
        $pct_kurang = ($class_counts['kurang tren'] / $labeled_count) * 100;
        $pct_tidak  = ($class_counts['tidak tren'] / $labeled_count) * 100;
      ?>
      <div style="height: 18px; display: flex; border-radius: 9px; overflow: hidden; background: #e2e8f0; margin-bottom: 12px;">
        <div style="width: <?= $pct_tren ?>%; background: var(--tren-text);" title="Tren: <?= fmt_percent($pct_tren) ?>"></div>
        <div style="width: <?= $pct_kurang ?>%; background: var(--kurang-text);" title="Kurang Tren: <?= fmt_percent($pct_kurang) ?>"></div>
        <div style="width: <?= $pct_tidak ?>%; background: var(--tidak-text);" title="Tidak Tren: <?= fmt_percent($pct_tidak) ?>"></div>
      </div>
      <div class="flex gap-4" style="font-size: 13px;">
        <span style="display: flex; align-items: center; gap: 6px;">
          <span style="width: 10px; height: 10px; border-radius: 50%; background: var(--tren-text); display: inline-block;"></span>
          Tren: <?= $class_counts['tren'] ?> (<?= fmt_percent($pct_tren) ?>)
        </span>
        <span style="display: flex; align-items: center; gap: 6px;">
          <span style="width: 10px; height: 10px; border-radius: 50%; background: var(--kurang-text); display: inline-block;"></span>
          Kurang Tren: <?= $class_counts['kurang tren'] ?> (<?= fmt_percent($pct_kurang) ?>)
        </span>
        <span style="display: flex; align-items: center; gap: 6px;">
          <span style="width: 10px; height: 10px; border-radius: 50%; background: var(--tidak-text); display: inline-block;"></span>
          Tidak Tren: <?= $class_counts['tidak tren'] ?> (<?= fmt_percent($pct_tidak) ?>)
        </span>
        <?php if ($unlabeled_count > 0): ?>
          <span class="text-muted ml-auto">
            <?= $unlabeled_count ?> produk belum berlabel (<a href="<?= BASE_URL ?>/products.php">Beri Label</a>)
          </span>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <p class="text-muted">Belum ada data produk yang memiliki label aktual.</p>
    <?php endif; ?>
  </div>
</div>

<!-- Filter dan Pencarian Tabel -->
<div class="card mb-4">
  <div class="card-body">
    <form method="get" class="flex gap-3 items-center flex-wrap">
      <div style="flex: 1; min-width: 220px;">
        <input type="text" name="search" class="form-control" placeholder="Cari nama produk training..." value="<?= h($search) ?>">
      </div>
      <div style="width: 180px;">
        <select name="label" class="form-select">
          <option value="">Semua Kategori</option>
          <option value="tren" <?= $filter_label === 'tren' ? 'selected' : '' ?>>Tren</option>
          <option value="kurang tren" <?= $filter_label === 'kurang tren' ? 'selected' : '' ?>>Kurang Tren</option>
          <option value="tidak tren" <?= $filter_label === 'tidak tren' ? 'selected' : '' ?>>Tidak Tren</option>
        </select>
      </div>
      <button type="submit" class="btn btn-secondary">
        <i data-lucide="filter"></i> Filter
      </button>
      <?php if ($search !== '' || $filter_label !== ''): ?>
        <a href="<?= BASE_URL ?>/training.php" class="btn btn-outline">Reset</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<!-- Tabel Data Training -->
<div class="card">
  <div class="card-header flex justify-between items-center">
    <h3 class="card-title">Daftar Data Training (<?= fmt_number($total_filtered) ?> data)</h3>
  </div>
  <div class="card-body">
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Nama Produk</th>
            <th>Sold (Terjual)</th>
            <th>Rating</th>
            <th>Harga (Price Now)</th>
            <th>Label Aktual (Ground Truth)</th>
            <?php if (role() === 'admin'): ?>
              <th>Aksi Update Label</th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($training_products)): ?>
            <tr>
              <td colspan="<?= role() === 'admin' ? 7 : 6 ?>" class="text-center text-muted" style="padding: 30px;">
                Tidak ada data training yang sesuai dengan filter.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($training_products as $p): ?>
              <tr>
                <td>#<?= $p['id'] ?></td>
                <td><strong><?= h($p['name']) ?></strong></td>
                <td><?= fmt_number($p['sold']) ?> unit</td>
                <td><?= h($p['rating']) ?> ★</td>
                <td><?= fmt_currency($p['price_now']) ?></td>
                <td><?= label_badge($p['label']) ?></td>
                <?php if (role() === 'admin'): ?>
                  <td>
                    <form method="post" class="flex gap-2 items-center" style="margin: 0;">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="update_label">
                      <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                      <select name="label" class="form-select" style="padding: 4px 8px; font-size: 12px; width: 130px;">
                        <option value="tren" <?= $p['label'] === 'tren' ? 'selected' : '' ?>>Tren</option>
                        <option value="kurang tren" <?= $p['label'] === 'kurang tren' ? 'selected' : '' ?>>Kurang Tren</option>
                        <option value="tidak tren" <?= $p['label'] === 'tidak tren' ? 'selected' : '' ?>>Tidak Tren</option>
                        <option value="">Hapus Label</option>
                      </select>
                      <button type="submit" class="btn btn-sm btn-outline" title="Simpan Perubahan Label">
                        <i data-lucide="check"></i>
                      </button>
                    </form>
                  </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($pagination['total_pages'] > 1): ?>
      <div class="mt-4 flex justify-between items-center">
        <span class="text-muted" style="font-size: 13px;">
          Menampilkan <?= $pagination['offset'] + 1 ?> &ndash; <?= min($total_filtered, $pagination['offset'] + $pagination['per_page']) ?> dari <?= $total_filtered ?> data
        </span>
        <div class="pagination">
          <?php if ($pagination['has_prev']): ?>
            <a href="?page=<?= $pagination['current_page'] - 1 ?>&search=<?= urlencode($search) ?>&label=<?= urlencode($filter_label) ?>" class="page-link">&laquo;</a>
          <?php endif; ?>
          <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
            <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&label=<?= urlencode($filter_label) ?>" class="page-link <?= $i === $pagination['current_page'] ? 'active' : '' ?>"><?= $i ?></a>
          <?php endfor; ?>
          <?php if ($pagination['has_next']): ?>
            <a href="?page=<?= $pagination['current_page'] + 1 ?>&search=<?= urlencode($search) ?>&label=<?= urlencode($filter_label) ?>" class="page-link">&raquo;</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include dirname(__DIR__) . '/app/views/layouts/footer.php'; ?>

