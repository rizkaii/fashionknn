<?php
require_once dirname(__DIR__) . '/app/config/database.php';
require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/helpers/functions.php';
require_once dirname(__DIR__) . '/app/middleware/access.php';
require_once dirname(__DIR__) . '/app/services/EvaluationService.php';

check_access('visualization');
$page_title = 'Visualisasi Hasil Klasifikasi & Analisis Tren';
$current_page = 'visualization';

// Ambil riwayat proses KNN untuk filter
$all_processes = [];
try {
    $all_processes = $pdo->query(
        "SELECT id, k_value, accuracy, processed_at FROM knn_processes ORDER BY id DESC"
    )->fetchAll();
} catch (Exception $e) {}

$selected_proc_id = (int)($_GET['process_id'] ?? ($all_processes[0]['id'] ?? 0));

// 1. Data Distribusi Prediksi Produk Saat Ini
$dist_pred_raw = $pdo->query(
    "SELECT COALESCE(prediction, 'Belum Diprediksi') as cat, COUNT(*) as cnt
     FROM products
     GROUP BY cat"
)->fetchAll();
$pred_counts = ['tren' => 0, 'kurang tren' => 0, 'tidak tren' => 0, 'belum' => 0];
foreach ($dist_pred_raw as $r) {
    $c = strtolower(trim($r['cat']));
    if (isset($pred_counts[$c])) {
        $pred_counts[$c] = (int)$r['cnt'];
    } elseif ($c === 'belum diprediksi') {
        $pred_counts['belum'] = (int)$r['cnt'];
    }
}

// 2. Data Distribusi Label Aktual
$dist_label_raw = $pdo->query(
    "SELECT label, COUNT(*) as cnt FROM products WHERE label IS NOT NULL AND label != '' GROUP BY label"
)->fetchAll();
$label_counts = ['tren' => 0, 'kurang tren' => 0, 'tidak tren' => 0];
foreach ($dist_label_raw as $r) {
    $c = strtolower(trim($r['label']));
    if (isset($label_counts[$c])) {
        $label_counts[$c] = (int)$r['cnt'];
    }
}

// 3. Data Evaluasi dari Proses Terpilih
$proc_data = null;
$train_count = 0;
$test_count = 0;
$per_class_metrics = ['tren' => ['precision' => 0, 'recall' => 0, 'f1' => 0], 'kurang tren' => ['precision' => 0, 'recall' => 0, 'f1' => 0], 'tidak tren' => ['precision' => 0, 'recall' => 0, 'f1' => 0]];

if ($selected_proc_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM knn_processes WHERE id = ?");
    $stmt->execute([$selected_proc_id]);
    $proc_data = $stmt->fetch();

    if ($proc_data) {
        $train_count = (int)$proc_data['train_count'];
        $test_count  = (int)$proc_data['test_count'];

        // Ambil data testing untuk hitung metrik per kelas
        $t_stmt = $pdo->prepare(
            "SELECT actual_label, prediction FROM knn_predictions
             WHERE process_id = ? AND is_test_data = 1"
        );
        $t_stmt->execute([$selected_proc_id]);
        $eval_rows = $t_stmt->fetchAll();

        if (!empty($eval_rows)) {
            $eval_svc = new EvaluationService();
            $calc_in = [];
            foreach ($eval_rows as $er) {
                $calc_in[] = ['actual' => $er['actual_label'], 'predicted' => $er['prediction']];
            }
            $calc_res = $eval_svc->calculate($calc_in);
            if (isset($calc_res['per_class'])) {
                foreach ($calc_res['per_class'] as $k => $v) {
                    $per_class_metrics[$k] = $v;
                }
            }
        }
    }
}

// Tambahkan script Chart.js di header/footer
$extra_js = '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';

include dirname(__DIR__) . '/app/views/layouts/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Visualisasi Tren & Evaluasi KNN</h1>
    <p class="page-subtitle">Penyajian grafis interaktif distribusi kategori tren produk fashion dan visualisasi metrik performa model.</p>
  </div>
  <?php if (!empty($all_processes)): ?>
    <div>
      <form method="get" class="flex items-center gap-2">
        <label for="proc_select" class="text-muted" style="font-size: 13px;">Proses KNN:</label>
        <select name="process_id" id="proc_select" class="form-select" onchange="this.form.submit()" style="min-width: 220px;">
          <?php foreach ($all_processes as $p): ?>
            <option value="<?= $p['id'] ?>" <?= $p['id'] == $selected_proc_id ? 'selected' : '' ?>>
              Proses #<?= $p['id'] ?> (K=<?= $p['k_value'] ?>, Akurasi <?= fmt_percent($p['accuracy'] * 100) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>
  <?php endif; ?>
</div>

<!-- BARIS 1: DOUGHNUT DISTRIBUSI PREDIKSI & BAR JUMLAH PER KATEGORI -->
<div class="grid two" style="margin-bottom: 24px;">
  <!-- Chart 1: Doughnut Distribusi Tren -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">1. Proporsi Distribusi Prediksi Tren</h3>
      <p class="card-subtitle">Persentase jumlah produk fashion pada setiap klasifikasi tren.</p>
    </div>
    <div class="card-body">
      <div style="position: relative; height: 280px; width: 100%;">
        <canvas id="chartDoughnutTren"></canvas>
      </div>
    </div>
  </div>

  <!-- Chart 2: Bar Jumlah Produk Per Kategori -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">2. Kuantitas Produk Per Kategori</h3>
      <p class="card-subtitle">Jumlah volume produk terklasifikasi Tren, Kurang Tren, dan Tidak Tren.</p>
    </div>
    <div class="card-body">
      <div style="position: relative; height: 280px; width: 100%;">
        <canvas id="chartBarKategori"></canvas>
      </div>
    </div>
  </div>
</div>

<!-- BARIS 2: PERBANDINGAN LABEL AKTUAL VS PREDIKSI & DATASET SPLIT -->
<div class="grid two" style="margin-bottom: 24px;">
  <!-- Chart 3: Perbandingan Ground Truth vs Prediksi -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">3. Perbandingan Label Aktual (Ground Truth) vs Hasil Prediksi</h3>
      <p class="card-subtitle">Melihat keselarasan distribusi label awal dengan hasil klasifikasi model KNN.</p>
    </div>
    <div class="card-body">
      <div style="position: relative; height: 280px; width: 100%;">
        <canvas id="chartBarComparison"></canvas>
      </div>
    </div>
  </div>

  <!-- Chart 4: Pembagian Data Training vs Testing -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">4. Distribusi Data Training vs Testing</h3>
      <p class="card-subtitle">Jumlah sampel pada proses KNN terpilih (#<?= $selected_proc_id ?>).</p>
    </div>
    <div class="card-body">
      <div style="position: relative; height: 280px; width: 100%;">
        <canvas id="chartPieSplit"></canvas>
      </div>
    </div>
  </div>
</div>

<!-- BARIS 3: METRIK PER KELAS (PRECISION, RECALL, F1) -->
<div class="card">
  <div class="card-header">
    <h3 class="card-title">5. Performa Klasifikasi Per Kategori (Precision, Recall, F1-Score)</h3>
    <p class="card-subtitle">Nilai metrik evaluasi pada data pengujian untuk proses #<?= $selected_proc_id ?>.</p>
  </div>
  <div class="card-body">
    <div style="position: relative; height: 300px; width: 100%;">
      <canvas id="chartBarMetrics"></canvas>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const colors = {
    tren: '#16A34A',
    kurang: '#D97706',
    tidak: '#DC2626',
    primary: '#4F46E5',
    primaryLight: '#818CF8',
    secondary: '#0EA5E9',
    amber: '#F59E0B'
  };

  // 1. Doughnut Chart: Distribusi Prediksi
  const ctxDoughnut = document.getElementById('chartDoughnutTren').getContext('2d');
  new Chart(ctxDoughnut, {
    type: 'doughnut',
    data: {
      labels: ['Tren', 'Kurang Tren', 'Tidak Tren'],
      datasets: [{
        data: [<?= $pred_counts['tren'] ?>, <?= $pred_counts['kurang tren'] ?>, <?= $pred_counts['tidak tren'] ?>],
        backgroundColor: [colors.tren, colors.kurang, colors.tidak],
        borderWidth: 2,
        borderColor: '#ffffff'
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { position: 'bottom' }
      }
    }
  });

  // 2. Bar Chart: Jumlah Produk Per Kategori
  const ctxBarCat = document.getElementById('chartBarKategori').getContext('2d');
  new Chart(ctxBarCat, {
    type: 'bar',
    data: {
      labels: ['Tren', 'Kurang Tren', 'Tidak Tren'],
      datasets: [{
        label: 'Jumlah Produk',
        data: [<?= $pred_counts['tren'] ?>, <?= $pred_counts['kurang tren'] ?>, <?= $pred_counts['tidak tren'] ?>],
        backgroundColor: [colors.tren, colors.kurang, colors.tidak],
        borderRadius: 6
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        y: { beginAtZero: true, ticks: { stepSize: 1 } }
      },
      plugins: { legend: { display: false } }
    }
  });

  // 3. Bar Comparison: Label vs Prediksi
  const ctxComp = document.getElementById('chartBarComparison').getContext('2d');
  new Chart(ctxComp, {
    type: 'bar',
    data: {
      labels: ['Tren', 'Kurang Tren', 'Tidak Tren'],
      datasets: [
        {
          label: 'Label Aktual (Ground Truth)',
          data: [<?= $label_counts['tren'] ?>, <?= $label_counts['kurang tren'] ?>, <?= $label_counts['tidak tren'] ?>],
          backgroundColor: '#94A3B8',
          borderRadius: 6
        },
        {
          label: 'Hasil Prediksi Model KNN',
          data: [<?= $pred_counts['tren'] ?>, <?= $pred_counts['kurang tren'] ?>, <?= $pred_counts['tidak tren'] ?>],
          backgroundColor: colors.primary,
          borderRadius: 6
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        y: { beginAtZero: true, ticks: { stepSize: 1 } }
      },
      plugins: {
        legend: { position: 'bottom' }
      }
    }
  });

  // 4. Pie Split: Data Training vs Testing
  const ctxSplit = document.getElementById('chartPieSplit').getContext('2d');
  new Chart(ctxSplit, {
    type: 'pie',
    data: {
      labels: ['Data Training (<?= $train_count ?>)', 'Data Testing (<?= $test_count ?>)'],
      datasets: [{
        data: [<?= $train_count ?>, <?= $test_count ?>],
        backgroundColor: [colors.primary, colors.amber],
        borderWidth: 2,
        borderColor: '#ffffff'
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { position: 'bottom' }
      }
    }
  });

  // 5. Bar Metrics Per Kategori
  const ctxMetrics = document.getElementById('chartBarMetrics').getContext('2d');
  new Chart(ctxMetrics, {
    type: 'bar',
    data: {
      labels: ['Kategori Tren', 'Kategori Kurang Tren', 'Kategori Tidak Tren'],
      datasets: [
        {
          label: 'Precision (%)',
          data: [
            <?= round(($per_class_metrics['tren']['precision'] ?? 0) * 100, 1) ?>,
            <?= round(($per_class_metrics['kurang tren']['precision'] ?? 0) * 100, 1) ?>,
            <?= round(($per_class_metrics['tidak tren']['precision'] ?? 0) * 100, 1) ?>
          ],
          backgroundColor: colors.primary,
          borderRadius: 4
        },
        {
          label: 'Recall (%)',
          data: [
            <?= round(($per_class_metrics['tren']['recall'] ?? 0) * 100, 1) ?>,
            <?= round(($per_class_metrics['kurang tren']['recall'] ?? 0) * 100, 1) ?>,
            <?= round(($per_class_metrics['tidak tren']['recall'] ?? 0) * 100, 1) ?>
          ],
          backgroundColor: colors.secondary,
          borderRadius: 4
        },
        {
          label: 'F1-Score (%)',
          data: [
            <?= round(($per_class_metrics['tren']['f1'] ?? 0) * 100, 1) ?>,
            <?= round(($per_class_metrics['kurang tren']['f1'] ?? 0) * 100, 1) ?>,
            <?= round(($per_class_metrics['tidak tren']['f1'] ?? 0) * 100, 1) ?>
          ],
          backgroundColor: colors.tren,
          borderRadius: 4
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        y: { beginAtZero: true, max: 100, ticks: { callback: v => v + '%' } }
      },
      plugins: {
        legend: { position: 'bottom' }
      }
    }
  });
});
</script>

<?php include dirname(__DIR__) . '/app/views/layouts/footer.php'; ?>

