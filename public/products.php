<?php
require_once '../app/config/database.php';
require_once '../app/helpers/auth.php';
require_once '../app/helpers/functions.php';
require_once '../app/middleware/access.php';

check_access('products');
$page_title   = 'Data Produk';
$current_page = 'products';

$is_admin = (role() === 'admin');
$errors   = [];
$success  = '';

// --- Valid label values ---
$valid_labels = ['', 'tren', 'kurang tren', 'tidak tren'];

// --- POST handlers ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if (($action === 'add' || $action === 'edit') && $is_admin) {
        $name      = trim($_POST['name']      ?? '');
        $sold      = $_POST['sold']            ?? '';
        $rating    = $_POST['rating']          ?? '';
        $price_now = $_POST['price_now']       ?? '';
        $label     = strtolower(trim($_POST['label'] ?? ''));

        // Validate
        if ($name === '')                              $errors[] = 'Nama produk wajib diisi.';
        if (!is_numeric($sold) || (int)$sold < 0)     $errors[] = 'Sold harus angka >= 0.';
        if (!is_numeric($rating) || $rating < 0 || $rating > 5) $errors[] = 'Rating harus antara 0 – 5.';
        if (!is_numeric($price_now) || $price_now < 0) $errors[] = 'Harga harus angka >= 0.';
        if (!in_array($label, $valid_labels))          $errors[] = 'Label tidak valid.';

        if (!$errors) {
            $label_val = $label !== '' ? $label : null;
            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO products (name, sold, rating, price_now, label, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$name, (int)$sold, (float)$rating, (float)$price_now, $label_val]);
                flash_set('success', 'Produk berhasil ditambahkan.');
            } else {
                $pid = (int)($_POST['product_id'] ?? 0);
                $stmt = $pdo->prepare("UPDATE products SET name=?, sold=?, rating=?, price_now=?, label=? WHERE id=?");
                $stmt->execute([$name, (int)$sold, (float)$rating, (float)$price_now, $label_val, $pid]);
                flash_set('success', 'Produk berhasil diperbarui.');
            }
            header('Location: ' . BASE_URL . '/products.php');
            exit;
        }
    } elseif ($action === 'delete' && $is_admin) {
        $pid = (int)($_POST['product_id'] ?? 0);
        if ($pid > 0) {
            $pdo->prepare("DELETE FROM products WHERE id=?")->execute([$pid]);
            flash_set('success', 'Produk berhasil dihapus.');
        }
        header('Location: ' . BASE_URL . '/products.php');
        exit;
    }
}

// --- Filters & Sorting ---
$search       = trim($_GET['search']       ?? '');
$label_filter = trim($_GET['label_filter'] ?? '');
$sort         = $_GET['sort'] ?? 'id';
$dir          = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
$cur_page_num = max(1, (int)($_GET['page'] ?? 1));
$per_page     = 20;

$allowed_sorts = ['id','name','sold','rating','price_now','created_at'];
if (!in_array($sort, $allowed_sorts)) $sort = 'id';

// Build WHERE
$where  = '1=1';
$params = [];
if ($search !== '') {
    $where   .= ' AND name LIKE ?';
    $params[] = '%' . $search . '%';
}
if ($label_filter !== '' && in_array($label_filter, ['tren','kurang tren','tidak tren'])) {
    $where   .= ' AND LOWER(label) = ?';
    $params[] = $label_filter;
}

// Count
$total_count = (int)$pdo->prepare("SELECT COUNT(*) FROM products WHERE $where")->execute($params) ? (function() use ($pdo, $where, $params) {
    $s = $pdo->prepare("SELECT COUNT(*) FROM products WHERE $where");
    $s->execute($params);
    return (int)$s->fetchColumn();
})() : 0;
// Cleaner approach:
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE $where");
$count_stmt->execute($params);
$total_count = (int)$count_stmt->fetchColumn();

$pagi   = paginate($total_count, $per_page, $cur_page_num);
$offset = $pagi['offset'];

// Fetch products
$stmt = $pdo->prepare("SELECT * FROM products WHERE $where ORDER BY $sort $dir LIMIT $per_page OFFSET $offset");
$stmt->execute($params);
$products = $stmt->fetchAll();

// Product to edit (if ?edit=id)
$edit_product = null;
if ($is_admin && isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $ep = $pdo->prepare("SELECT * FROM products WHERE id=?");
    $ep->execute([$edit_id]);
    $edit_product = $ep->fetch();
}

// Sort toggle helper
function sort_url($col, $cur_sort, $cur_dir, $search, $label_filter) {
    $new_dir = ($cur_sort === $col && $cur_dir === 'asc') ? 'desc' : 'asc';
    $q = http_build_query(['sort'=>$col,'dir'=>$new_dir,'search'=>$search,'label_filter'=>$label_filter]);
    return BASE_URL . '/products.php?' . $q;
}
function sort_icon($col, $cur_sort, $cur_dir) {
    if ($col !== $cur_sort) return '<i data-lucide="chevrons-up-down" style="width:14px;height:14px;vertical-align:middle;opacity:.4"></i>';
    return $cur_dir === 'asc'
        ? '<i data-lucide="chevron-up"   style="width:14px;height:14px;vertical-align:middle;color:var(--primary)"></i>'
        : '<i data-lucide="chevron-down" style="width:14px;height:14px;vertical-align:middle;color:var(--primary)"></i>';
}

// Pagination query string builder
function page_url($p, $sort, $dir, $search, $label_filter) {
    $q = http_build_query(['page'=>$p,'sort'=>$sort,'dir'=>$dir,'search'=>$search,'label_filter'=>$label_filter]);
    return BASE_URL . '/products.php?' . $q;
}

include '../app/views/layouts/header.php';
?>

<?= flash_html() ?>

<?php if ($errors): ?>
<div class="alert alert-error">
    <ul style="margin:0;padding-left:1.25rem;"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<!-- Toolbar -->
<div class="card" style="margin-bottom:1rem;">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/products.php" style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end;">
            <input type="hidden" name="sort" value="<?= h($sort) ?>">
            <input type="hidden" name="dir"  value="<?= h($dir) ?>">
            <div style="flex:1;min-width:200px;">
                <label class="form-label">Cari Produk</label>
                <div style="position:relative;">
                    <i data-lucide="search" style="position:absolute;left:.75rem;top:50%;transform:translateY(-50%);width:16px;height:16px;color:var(--text-muted)"></i>
                    <input class="form-control" style="padding-left:2.25rem;" type="text" name="search" value="<?= h($search) ?>" placeholder="Cari nama produk...">
                </div>
            </div>
            <div>
                <label class="form-label">Filter Label</label>
                <select class="form-control form-select" name="label_filter">
                    <option value="">Semua Label</option>
                    <?php foreach (['tren'=>'Tren','kurang tren'=>'Kurang Tren','tidak tren'=>'Tidak Tren'] as $v=>$t): ?>
                    <option value="<?= h($v) ?>" <?= $label_filter === $v ? 'selected' : '' ?>><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-primary" type="submit">Filter</button>
            <a class="btn btn-outline" href="<?= BASE_URL ?>/products.php">Reset</a>
            <?php if ($is_admin): ?>
            <a class="btn btn-primary" href="<?= BASE_URL ?>/products.php?add=1" style="margin-left:auto;">
                <i data-lucide="plus" style="width:16px;height:16px;vertical-align:middle;margin-right:.25rem"></i> Tambah Produk
            </a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Add / Edit Form -->
<?php if ($is_admin && (isset($_GET['add']) || $edit_product)): ?>
<div class="card" style="margin-bottom:1rem;">
    <div class="card-header">
        <h3 class="card-title"><?= $edit_product ? 'Edit Produk' : 'Tambah Produk Baru' ?></h3>
    </div>
    <div class="card-body">
        <form method="POST" action="<?= BASE_URL ?>/products.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $edit_product ? 'edit' : 'add' ?>">
            <?php if ($edit_product): ?>
            <input type="hidden" name="product_id" value="<?= h($edit_product['id']) ?>">
            <?php endif; ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:1rem;">
                <div class="form-group">
                    <label class="form-label">Nama Produk <span style="color:#DC2626">*</span></label>
                    <input class="form-control" type="text" name="name" required
                           value="<?= h($edit_product['name'] ?? ($_POST['name'] ?? '')) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Sold <span style="color:#DC2626">*</span></label>
                    <input class="form-control" type="number" name="sold" min="0" required
                           value="<?= h($edit_product['sold'] ?? ($_POST['sold'] ?? '')) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Rating (0-5) <span style="color:#DC2626">*</span></label>
                    <input class="form-control" type="number" name="rating" min="0" max="5" step="0.01" required
                           value="<?= h($edit_product['rating'] ?? ($_POST['rating'] ?? '')) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Harga <span style="color:#DC2626">*</span></label>
                    <input class="form-control" type="number" name="price_now" min="0" required
                           value="<?= h($edit_product['price_now'] ?? ($_POST['price_now'] ?? '')) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Label</label>
                    <select class="form-control form-select" name="label">
                        <?php $cur_label = strtolower(trim($edit_product['label'] ?? ($_POST['label'] ?? ''))); ?>
                        <option value="">-- Tanpa Label --</option>
                        <?php foreach (['tren'=>'Tren','kurang tren'=>'Kurang Tren','tidak tren'=>'Tidak Tren'] as $v=>$t): ?>
                        <option value="<?= h($v) ?>" <?= $cur_label === $v ? 'selected' : '' ?>><?= $t ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div style="margin-top:1rem;display:flex;gap:.75rem;">
                <button class="btn btn-primary" type="submit"><?= $edit_product ? 'Simpan Perubahan' : 'Tambah Produk' ?></button>
                <a class="btn btn-outline" href="<?= BASE_URL ?>/products.php">Batal</a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Table -->
<div class="card">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
        <div>
            <h3 class="card-title">Daftar Produk</h3>
            <p class="card-subtitle">Total <?= fmt_number($total_count) ?> produk<?= $search ? ' (dicari: "'.h($search).'")' : '' ?></p>
        </div>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-container" style="border:none;margin-bottom:0;">
            <table>
                <thead>
                    <tr>
                        <th><a href="<?= sort_url('id', $sort, $dir, $search, $label_filter) ?>" style="text-decoration:none;color:inherit;">ID <?= sort_icon('id',$sort,$dir) ?></a></th>
                        <th><a href="<?= sort_url('name', $sort, $dir, $search, $label_filter) ?>" style="text-decoration:none;color:inherit;">Nama Produk <?= sort_icon('name',$sort,$dir) ?></a></th>
                        <th><a href="<?= sort_url('sold', $sort, $dir, $search, $label_filter) ?>" style="text-decoration:none;color:inherit;">Sold <?= sort_icon('sold',$sort,$dir) ?></a></th>
                        <th><a href="<?= sort_url('rating', $sort, $dir, $search, $label_filter) ?>" style="text-decoration:none;color:inherit;">Rating <?= sort_icon('rating',$sort,$dir) ?></a></th>
                        <th><a href="<?= sort_url('price_now', $sort, $dir, $search, $label_filter) ?>" style="text-decoration:none;color:inherit;">Harga <?= sort_icon('price_now',$sort,$dir) ?></a></th>
                        <th>Label Aktual</th>
                        <th>Hasil Prediksi</th>
                        <th><a href="<?= sort_url('created_at', $sort, $dir, $search, $label_filter) ?>" style="text-decoration:none;color:inherit;">Tanggal Input <?= sort_icon('created_at',$sort,$dir) ?></a></th>
                        <?php if ($is_admin): ?><th>Aksi</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($products): foreach ($products as $p): ?>
                    <tr>
                        <td><?= h($p['id']) ?></td>
                        <td style="font-weight:500;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= h($p['name']) ?>"><?= h($p['name']) ?></td>
                        <td><?= fmt_number($p['sold']) ?></td>
                        <td><?= number_format((float)$p['rating'],2) ?></td>
                        <td><?= fmt_currency($p['price_now']) ?></td>
                        <td><?= label_badge($p['label'] ?? '') ?></td>
                        <td><?= pred_badge($p['prediction'] ?? '') ?></td>
                        <td style="font-size:.8rem;color:var(--text-muted)"><?= isset($p['created_at']) ? date('d M Y', strtotime($p['created_at'])) : '-' ?></td>
                        <?php if ($is_admin): ?>
                        <td style="white-space:nowrap;">
                            <a href="<?= BASE_URL ?>/products.php?edit=<?= $p['id'] ?>&search=<?= urlencode($search) ?>&label_filter=<?= urlencode($label_filter) ?>&sort=<?= h($sort) ?>&dir=<?= h($dir) ?>&page=<?= $pagi['current_page'] ?>" class="btn btn-outline" style="padding:.25rem .6rem;font-size:.8rem;">
                                <i data-lucide="pencil" style="width:14px;height:14px"></i>
                            </a>
                            <form method="POST" action="<?= BASE_URL ?>/products.php" style="display:inline;" onsubmit="return confirm('Hapus produk ini?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action"     value="delete">
                                <input type="hidden" name="product_id" value="<?= h($p['id']) ?>">
                                <button type="submit" class="btn" style="padding:.25rem .6rem;font-size:.8rem;background:#FEE2E2;color:#DC2626;border:none;border-radius:6px;cursor:pointer;">
                                    <i data-lucide="trash-2" style="width:14px;height:14px"></i>
                                </button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="<?= $is_admin ? 9 : 8 ?>" style="text-align:center;color:var(--text-muted);padding:3rem 0;">
                        <?= $search || $label_filter ? 'Tidak ada produk yang cocok dengan filter.' : 'Belum ada data produk.' ?>
                    </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <!-- Pagination -->
    <?php if ($pagi['total_pages'] > 1): ?>
    <div class="card-body" style="display:flex;justify-content:space-between;align-items:center;border-top:1px solid var(--border);">
        <span style="font-size:.875rem;color:var(--text-muted)">
            Menampilkan <?= fmt_number($offset + 1) ?>–<?= fmt_number(min($offset + $per_page, $total_count)) ?> dari <?= fmt_number($total_count) ?>
        </span>
        <div style="display:flex;gap:.4rem;">
            <?php if ($pagi['has_prev']): ?>
            <a href="<?= page_url($pagi['prev_page'], $sort, $dir, $search, $label_filter) ?>" class="btn btn-outline" style="padding:.4rem .75rem;">← Prev</a>
            <?php endif; ?>
            <?php
            $start = max(1, $pagi['current_page'] - 2);
            $end   = min($pagi['total_pages'], $pagi['current_page'] + 2);
            for ($pg = $start; $pg <= $end; $pg++):
            ?>
            <a href="<?= page_url($pg, $sort, $dir, $search, $label_filter) ?>"
               class="btn <?= $pg === $pagi['current_page'] ? 'btn-primary' : 'btn-outline' ?>"
               style="padding:.4rem .75rem;"><?= $pg ?></a>
            <?php endfor; ?>
            <?php if ($pagi['has_next']): ?>
            <a href="<?= page_url($pagi['next_page'], $sort, $dir, $search, $label_filter) ?>" class="btn btn-outline" style="padding:.4rem .75rem;">Next →</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include '../app/views/layouts/footer.php'; ?>
