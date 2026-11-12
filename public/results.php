<?php
require_once __DIR__ . '/../app/config/database.php';
require_once APP_PATH . '/helpers/auth.php';
require_once APP_PATH . '/helpers/functions.php';
require_once APP_PATH . '/middleware/access.php';
check_access('results');

// --- Filters & Pagination ---
$search      = trim($_GET['search']      ?? '');
$pred_filter = trim($_GET['pred_filter'] ?? '');
$status_f    = trim($_GET['status']      ?? '');
$sort        = in_array($_GET['sort'] ?? '', ['name','sold','rating','price_now','prediction','label']) ? $_GET['sort'] : 'id';
$dir         = strtoupper($_GET['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
$page        = max(1, (int)($_GET['page'] ?? 1));
$per_page    = 20;

// Column map (safe whitelist)
$sort_col_map = [
    'id'         => 'p.id',
    'name'       => 'p.name',
    'sold'       => 'p.sold',
    'rating'     => 'p.rating',
    'price_now'  => 'p.price_now',
    'prediction' => 'p.prediction',
    'label'      => 'p.label',
];
$order_col = $sort_col_map[$sort] ?? 'p.id';

// --- Check if knn_predictions table exists ---
$has_knn = false;
try {
    $pdo->query("SELECT 1 FROM knn_predictions LIMIT 1");
    $has_knn = true;
} catch (Exception $e) {
    $has_knn = false;
}

// --- Build WHERE clause ---
$where   = ['1=1'];
$params  = [];

if ($search !== '') {
    $where[]  = 'p.name LIKE :search';
    $params[':search'] = '%' . $search . '%';
}
if ($pred_filter !== '') {
    if ($pred_filter === 'belum') {
        $where[] = 'p.prediction IS NULL';
    } else {
        $where[]             = 'p.prediction = :pred_filter';
        $params[':pred_filter'] = $pred_filter;
    }
}
// Status filter handled in PHP after fetch (we need both label & prediction)

$where_sql = implode(' AND ', $where);

// --- Main Query ---
if ($has_knn) {
    $base_sql = "
        SELECT p.*,
               kp.normalized_sold, kp.normalized_rating, kp.normalized_price,
               kp.neighbors_json, kp.is_test_data, kp.is_correct, kp.process_id AS kp_process_id
        FROM products p
        LEFT JOIN knn_predictions kp
            ON kp.product_id = p.id
            AND kp.process_id = (
                SELECT MAX(kp2.process_id) FROM knn_predictions kp2 WHERE kp2.product_id = p.id
            )
        WHERE $where_sql
    ";
} else {
    $base_sql = "SELECT p.* FROM products p WHERE $where_sql";
}

// Count total (for pagination) — wrap in subquery
try {
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM ($base_sql) AS sub");
    foreach ($params as $k => $v) $count_stmt->bindValue($k, $v);
    $count_stmt->execute();
    $total_rows = (int)$count_stmt->fetchColumn();
} catch (Exception $e) {
    $total_rows = 0;
}

$pag = paginate($total_rows, $per_page, $page);

// Fetch page data
$rows = [];
try {
    $data_stmt = $pdo->prepare("$base_sql ORDER BY $order_col $dir LIMIT :limit OFFSET :offset");
    foreach ($params as $k => $v) $data_stmt->bindValue($k, $v);
    $data_stmt->bindValue(':limit',  $per_page, PDO::PARAM_INT);
    $data_stmt->bindValue(':offset', $pag['offset'], PDO::PARAM_INT);
    $data_stmt->execute();
    $rows = $data_stmt->fetchAll();
} catch (Exception $e) {
    $rows = [];
}

// --- Summary stats ---
$stat_total  = 0;
$stat_benar  = 0;
$stat_salah  = 0;
$stat_belum  = 0;
try {
    $s = $pdo->query("SELECT COUNT(*) as n, 
        SUM(CASE WHEN prediction IS NOT NULL AND label IS NOT NULL AND prediction=label THEN 1 ELSE 0 END) as benar,
        SUM(CASE WHEN prediction IS NOT NULL AND label IS NOT NULL AND prediction!=label THEN 1 ELSE 0 END) as salah,
        SUM(CASE WHEN prediction IS NULL OR label IS NULL THEN 1 ELSE 0 END) as belum
        FROM products")->fetch();
    $stat_total = (int)$s['n'];
    $stat_benar = (int)$s['benar'];
    $stat_salah = (int)$s['salah'];
    $stat_belum = (int)$s['belum'];
} catch (Exception $e) {}

// Apply status filter in PHP (after fetch)
if ($status_f !== '') {
    $rows = array_filter($rows, function($r) use ($status_f) {
        $st = row_status($r);
        return $st['key'] === $status_f;
    });
}

// --- Helper: determine row status ---
function row_status($r) {
    if (empty($r['prediction'])) {
        return ['key' => 'belum', 'label' => 'Belum Diprediksi',         'cls' => 'badge-gray'];
    }
    if (empty($r['label'])) {
        return ['key' => 'belum', 'label' => 'Belum Dapat Dievaluasi',   'cls' => 'badge-gray'];
    }
    if ($r['label'] === $r['prediction']) {
        return ['key' => 'benar', 'label' => 'Benar',  'cls' => 'badge-tren'];
    }
    return ['key' => 'salah', 'label' => 'Salah', 'cls' => 'badge-tidak'];
}

// --- Build pagination URL ---
function build_url($page_num) {
    $p = $_GET;
    $p['page'] = $page_num;
    return '?' . http_build_query($p);
}
function sort_url($col) {
    $p = $_GET;
    $p['sort'] = $col;
    $p['dir']  = (($p['sort'] ?? '') === $col && strtoupper($p['dir'] ?? 'ASC') === 'ASC') ? 'DESC' : 'ASC';
    $p['page'] = 1;
    return '?' . http_build_query($p);
}

$page_title  = 'Hasil Prediksi';
$current_page = 'results';
require_once APP_PATH . '/views/layouts/header.php';
?>

<div class="page-header">
  <h1 class="page-title"><i data-lucide="list-checks"></i> Hasil Prediksi KNN</h1>
  <p class="page-subtitle">Daftar seluruh produk beserta hasil prediksi kategori tren</p>
</div>

<?= flash_html() ?>

<!-- Summary Bar -->
<div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(170px,1fr));margin-bottom:1.5rem">
  <div class="stat-card">
    <div class="stat-icon" style="background:#EEF2FF"><i data-lucide="layers" style="color:#4F46E5"></i></div>
    <div class="stat-body">
      <span class="stat-label">Total Prediksi</span>
      <span class="stat-value"><?= fmt_number($stat_total) ?></span>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:#DCFCE7"><i data-lucide="check-circle" style="color:#16A34A"></i></div>
    <div class="stat-body">
      <span class="stat-label">Benar</span>
      <span class="stat-value"><?= fmt_number($stat_benar) ?></span>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:#FEE2E2"><i data-lucide="x-circle" style="color:#DC2626"></i></div>
    <div class="stat-body">
      <span class="stat-label">Salah</span>
      <span class="stat-value"><?= fmt_number($stat_salah) ?></span>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:#F1F5F9"><i data-lucide="minus-circle" style="color:#64748B"></i></div>
    <div class="stat-body">
      <span class="stat-label">Belum Dievaluasi</span>
      <span class="stat-value"><?= fmt_number($stat_belum) ?></span>
    </div>
  </div>
</div>

<!-- Filter & Search -->
<div class="card" style="margin-bottom:1.25rem">
  <div class="card-body">
    <form method="GET" action="" class="filter-row">
      <div class="input-group">
        <i data-lucide="search" class="input-icon"></i>
        <input type="text" name="search" class="form-control input-with-icon"
               placeholder="Cari nama produk…" value="<?= h($search) ?>">
      </div>
      <select name="pred_filter" class="form-control">
        <option value="">Semua Prediksi</option>
        <option value="tren"        <?= $pred_filter==='tren'        ? 'selected':'' ?>>Tren</option>
        <option value="kurang tren" <?= $pred_filter==='kurang tren' ? 'selected':'' ?>>Kurang Tren</option>
        <option value="tidak tren"  <?= $pred_filter==='tidak tren'  ? 'selected':'' ?>>Tidak Tren</option>
        <option value="belum"       <?= $pred_filter==='belum'       ? 'selected':'' ?>>Belum Diprediksi</option>
      </select>
      <select name="status" class="form-control">
        <option value="">Semua Status</option>
        <option value="benar" <?= $status_f==='benar' ? 'selected':'' ?>>Benar</option>
        <option value="salah" <?= $status_f==='salah' ? 'selected':'' ?>>Salah</option>
        <option value="belum" <?= $status_f==='belum' ? 'selected':'' ?>>Belum</option>
      </select>
      <input type="hidden" name="sort" value="<?= h($sort) ?>">
      <input type="hidden" name="dir"  value="<?= h($dir) ?>">
      <button type="submit" class="btn btn-primary">
        <i data-lucide="filter"></i> Filter
      </button>
      <a href="results.php" class="btn btn-secondary">
        <i data-lucide="refresh-cw"></i> Reset
      </a>
    </form>
  </div>
</div>

<!-- Table Card -->
<div class="card">
  <div class="card-header">
    <h3 class="card-title">Data Produk &amp; Prediksi</h3>
    <span class="card-subtitle"><?= fmt_number($total_rows) ?> produk ditemukan</span>
  </div>
  <div class="card-body p-0">
    <?php if (empty($rows)): ?>
      <div class="empty-state">
        <i data-lucide="inbox"></i>
        <p>Belum ada data yang sesuai filter.</p>
      </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th><a href="<?= sort_url('id') ?>" class="sort-link">ID <?= $sort==='id' ? ($dir==='ASC'?'↑':'↓') : '' ?></a></th>
            <th><a href="<?= sort_url('name') ?>" class="sort-link">Nama Produk <?= $sort==='name' ? ($dir==='ASC'?'↑':'↓') : '' ?></a></th>
            <th class="text-right"><a href="<?= sort_url('sold') ?>" class="sort-link">Sold <?= $sort==='sold' ? ($dir==='ASC'?'↑':'↓') : '' ?></a></th>
            <th class="text-right"><a href="<?= sort_url('rating') ?>" class="sort-link">Rating <?= $sort==='rating' ? ($dir==='ASC'?'↑':'↓') : '' ?></a></th>
            <th class="text-right"><a href="<?= sort_url('price_now') ?>" class="sort-link">Harga <?= $sort==='price_now' ? ($dir==='ASC'?'↑':'↓') : '' ?></a></th>
            <th>Label Aktual</th>
            <th>Hasil Prediksi</th>
            <th>Status</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): 
              $st = row_status($r);
          ?>
          <tr>
            <td class="text-muted"><?= h($r['id']) ?></td>
            <td>
              <a href="product_detail.php?id=<?= (int)$r['id'] ?>" class="table-link">
                <?= h($r['name']) ?>
              </a>
            </td>
            <td class="text-right"><?= fmt_number($r['sold'] ?? 0) ?></td>
            <td class="text-right">★ <?= number_format((float)($r['rating'] ?? 0), 1) ?></td>
            <td class="text-right"><?= fmt_currency($r['price_now'] ?? 0) ?></td>
            <td><?= label_badge($r['label'] ?? '') ?></td>
            <td><?= pred_badge($r['prediction'] ?? '') ?></td>
            <td><span class="badge <?= h($st['cls']) ?>"><?= h($st['label']) ?></span></td>
            <td>
              <a href="product_detail.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-secondary">
                <i data-lucide="eye"></i> Detail
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
  <?php if ($pag['total_pages'] > 1): ?>
  <div class="card-footer">
    <?php
      // Build URL pattern for pagination component
      $pag_params = $_GET;
      unset($pag_params['page']);
      $url_base    = '?' . http_build_query($pag_params) . '&page=%d';
      $url_pattern = $url_base;
      include APP_PATH . '/views/components/pagination.php';
    ?>
  </div>
  <?php endif; ?>
</div>

<?php require_once APP_PATH . '/views/layouts/footer.php'; ?>
