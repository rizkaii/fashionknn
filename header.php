<?php require_login(); ?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Fashion Trend KNN</title><link rel="stylesheet" href="assets/style.css"></head><body><div class="layout"><aside class="sidebar"><a class="logo" href="dashboard.php"><span>F</span><div>FASHION<span> TREND KNN</span></div></a><div class="side-label">MENU UTAMA</div><nav>
<a href="dashboard.php">▦ <span>Dashboard</span></a><a href="products.php">▣ <span>Data Produk</span></a>
<?php if(role()==='admin'): ?><a href="dataset.php">⇧ <span>Dataset CSV</span></a><a href="process.php">◉ <span>Preprocessing & KNN</span></a><?php endif;?>
<a href="results.php">▤ <span>Hasil Klasifikasi</span></a><a href="visualization.php">▥ <span>Visualisasi Tren</span></a>
<?php if(role()==='admin'): ?><a href="users.php">♙ <span>Manajemen Pengguna</span></a><?php endif;?>
</nav><div class="sidebar-bottom"><div class="user-chip"><div class="avatar"><?=strtoupper(substr($_SESSION['user']['name'],0,1))?></div><div><b><?=h($_SESSION['user']['name'])?></b><small><?=h(ucfirst(role()))?></small></div></div><a class="logout" href="logout.php">↪ Keluar</a></div></aside><main class="main"><header class="topbar"><span>Analisis Data Penjualan Tokopedia</span><span class="top-user"><?=h($_SESSION['user']['name'])?></span></header><div class="content">
