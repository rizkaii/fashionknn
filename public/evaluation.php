<?php
require_once dirname(__DIR__) . '/app/config/database.php';
require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/helpers/functions.php';
require_once dirname(__DIR__) . '/app/middleware/access.php';
require_once dirname(__DIR__) . '/app/services/EvaluationService.php';

check_access('evaluation');
$page_title = 'Evaluasi Performa Model KNN';
$current_page = 'evaluation';

// 1. Ambil daftar seluruh proses KNN yang pernah dijalankan
$all_processes = [];
try {
    $all_processes = $pdo->query(
        "SELECT id, k_value, split_ratio, random_seed, accuracy, f1_score, processed_at
         FROM knn_processes
         ORDER BY id DESC"
    )->fetchAll();
} catch (Exception $e) {}

// 2. Tentukan ID proses yang sedang dilihat
$selected_process_id = (int)($_GET['process_id'] ?? ($all_processes[0]['id'] ?? 0));
$current_proc = null;
$test_predictions = [];
$metrics = null;

if ($selected_process_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM knn_processes WHERE id = ?");
    $stmt->execute([$selected_process_id]);
    $current_proc = $stmt->fetch();

    if ($current_proc) {
        // Ambil baris prediksi yang merupakan data testing
        $test_stmt = $pdo->prepare(
            "SELECT kp.*, p.name as product_name, p.sold, p.rating, p.price_now
             FROM knn_predictions kp
             JOIN products p ON p.id = kp.product_id
             WHERE kp.process_id = ? AND kp.is_test_data = 1
             ORDER BY kp.id ASC"
        );
        $test_stmt->execute([$selected_process_id]);
        $test_predictions = $test_stmt->fetchAll();

        // Hitung metrik secara dinamis menggunakan EvaluationService
        $eval_svc = new EvaluationService();
        $calc_input = [];
        foreach ($test_predictions as $tp) {
            $calc_input[] = [
                'actual'    => $tp['actual_label'],
                'predicted' => $tp['prediction'],
            ];
        }
        $metrics = $eval_svc->calculate($calc_input);
    }
}

include dirname(__DIR__) . '/app/views/layouts/header.php';
?>

<div class="page-header flex justify-between items-center flex-wrap gap-3">
  <div>
    <h1 class="page-title">Evaluasi Performa Model KNN</h1>
    <p class="page-subtitle">Analisis metrik evaluasi klasifikasi multiclass (Akurasi, Presisi, Recall, F1-Score) dan matriks konfusi pada data pengujian.</p>
  </div>
  <?php if (!empty($all_processes)): ?>
    <div style="background: #FFFFFF; border: 1px solid var(--border); border-radius: 8px; padding: 6px 12px; box-shadow: var(--shadow-sm);">
      <form method="get" class="flex items-center gap-2" style="margin: 0;">
        <i data-lucide="history" style="width: 16px; height: 16px; color: var(--primary); flex-shrink: 0;"></i>
        <label for="proc_select" style="font-size: 12.5px; font-weight: 600; color: #475569; margin: 0; white-space: nowrap;">Riwayat:</label>
        <select name="process_id" id="proc_select" class="form-select" onchange="this.form.submit()" style="min-width: 260px; font-size: 12.5px; padding: 4px 10px; height: 32px; border: 1px solid #CBD5E1; border-radius: 6px;">
          <?php foreach ($all_processes as $p): ?>
            <option value="<?= $p['id'] ?>" <?= $p['id'] == $selected_process_id ? 'selected' : '' ?>>
              Proses #<?= $p['id'] ?> (K=<?= $p['k_value'] ?>, Akurasi: <?= fmt_percent($p['accuracy'] * 100) ?>) &ndash; <?= date('d/m/Y H:i', strtotime($p['processed_at'])) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>
  <?php endif; ?>
</div>

<?php if (empty($all_processes) || !$current_proc): ?>
  <div class="card p-5 text-center">
    <div style="font-size: 40px; margin-bottom: 12px; color: var(--primary);">
      <i data-lucide="bar-chart-2"></i>
    </div>
    <h3 style="font-size: 18px; margin-bottom: 8px;">Belum Ada Evaluasi Model</h3>
    <p class="text-muted" style="max-width: 500px; margin: 0 auto 20px;">
      Evaluasi performa model dihitung otomatis saat proses klasifikasi KNN dijalankan pada data pengujian terpisah.
    </p>
    <?php if (role() === 'admin'): ?>
      <div>
        <a href="<?= BASE_URL ?>/process.php" class="btn btn-primary">
          <i data-lucide="play"></i> Jalankan Proses KNN Sekarang
        </a>
      </div>
    <?php endif; ?>
  </div>
<?php else: ?>

  <!-- Metadata Parameter Proses -->
  <div class="card mb-4" style="background: #FFFFFF; border: 1px solid var(--border);">
    <div class="card-body flex justify-between items-center flex-wrap gap-3" style="padding: 14px 20px;">
      <div style="display: flex; align-items: center; gap: 10px;">
        <div style="width: 32px; height: 32px; border-radius: 8px; background: #EEF2FF; color: var(--primary); display: flex; align-items: center; justify-content: center;">
          <i data-lucide="check-circle-2" style="width: 17px; height: 17px;"></i>
        </div>
        <div>
          <strong style="color: #0F172A; font-size: 14px;">Evaluasi Proses #<?= $current_proc['id'] ?></strong>
          <span style="color: var(--text-muted); font-size: 12px; margin-left: 8px;">
            <?= date('d M Y, H:i', strtotime($current_proc['processed_at'])) ?> WIB
          </span>
        </div>
      </div>
      <div class="flex gap-2 flex-wrap items-center" style="font-size: 12px;">
        <span class="badge badge-gray" style="font-weight: 600;">K = <?= $current_proc['k_value'] ?></span>
        <span class="badge badge-gray">Rasio <?= h($current_proc['split_ratio']) ?></span>
        <span class="badge badge-gray">Seed <?= $current_proc['random_seed'] ?></span>
        <span class="badge badge-gray">Data Latih: <?= $current_proc['train_count'] ?></span>
        <span class="badge badge-tren">Data Uji: <?= $current_proc['test_count'] ?></span>
      </div>
    </div>
  </div>

  <!-- 4 STAT METRIK UTAMA -->
  <div class="stats-grid mb-4">
    <!-- Overall Accuracy -->
    <div class="metric-card">
      <div class="metric-card-top">
        <span class="metric-card-label">Overall Accuracy</span>
        <div class="metric-card-icon" style="background: #DCFCE7; color: #16A34A;">
          <i data-lucide="check-circle" style="width: 18px; height: 18px;"></i>
        </div>
      </div>
      <div class="metric-card-value" style="color: #16A34A;"><?= fmt_percent($metrics['accuracy'] * 100) ?></div>
      <div class="metric-card-desc"><?= $metrics['correct'] ?> benar dari <?= $metrics['total'] ?> sampel uji</div>
    </div>

    <!-- Macro Precision -->
    <div class="metric-card">
      <div class="metric-card-top">
        <span class="metric-card-label">Macro Precision</span>
        <div class="metric-card-icon" style="background: #EEF2FF; color: var(--primary);">
          <i data-lucide="target" style="width: 18px; height: 18px;"></i>
        </div>
      </div>
      <div class="metric-card-value"><?= fmt_percent($metrics['macro']['precision'] * 100) ?></div>
      <div class="metric-card-desc">Rata-rata ketepatan prediksi kelas</div>
    </div>

    <!-- Macro Recall -->
    <div class="metric-card">
      <div class="metric-card-top">
        <span class="metric-card-label">Macro Recall</span>
        <div class="metric-card-icon" style="background: #FEF3C7; color: #D97706;">
          <i data-lucide="zap" style="width: 18px; height: 18px;"></i>
        </div>
      </div>
      <div class="metric-card-value"><?= fmt_percent($metrics['macro']['recall'] * 100) ?></div>
      <div class="metric-card-desc">Rata-rata sensitivitas temuan kelas</div>
    </div>

    <!-- Macro F1-Score -->
    <div class="metric-card">
      <div class="metric-card-top">
        <span class="metric-card-label">Macro F1-Score</span>
        <div class="metric-card-icon" style="background: #EEF2FF; color: var(--primary);">
          <i data-lucide="award" style="width: 18px; height: 18px;"></i>
        </div>
      </div>
      <div class="metric-card-value" style="color: var(--primary);"><?= fmt_percent($metrics['macro']['f1'] * 100) ?></div>
      <div class="metric-card-desc">Harmonic mean presisi & recall</div>
    </div>
  </div>

  <!-- ROW 1: CONFUSION MATRIX 3X3 & PER-CLASS BREAKDOWN -->
  <div class="grid two" style="margin-bottom: 24px;">
    <!-- Confusion Matrix -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Confusion Matrix Multiclass (3x3)</h3>
        <p class="card-subtitle">Perbandingan antara label aktual (ground truth) dengan kategori hasil klasifikasi model KNN.</p>
      </div>
      <div class="card-body">
        <div class="table-container mb-3">
          <table class="cm-table" style="text-align: center;">
            <thead>
              <tr>
                <th rowspan="2" style="vertical-align: middle; background: #fafafd; text-align: left;">
                  Aktual (Ground Truth)
                </th>
                <th colspan="3" style="text-align: center; background: #EEF2FF; color: var(--primary);">
                  Prediksi Model KNN
                </th>
                <th rowspan="2" style="vertical-align: middle; background: #fafafd;">Total Sampel</th>
              </tr>
              <tr>
                <th>Tren</th>
                <th>Kurang Tren</th>
                <th>Tidak Tren</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach (['tren', 'kurang tren', 'tidak tren'] as $act): ?>
                <?php
                  $row_total = array_sum($metrics['confusion_matrix'][$act] ?? []);
                ?>
                <tr>
                  <td style="font-weight: 700; text-align: left; background: #fafafd;">
                    <?= label_badge($act) ?>
                  </td>
                  <?php foreach (['tren', 'kurang tren', 'tidak tren'] as $prd): ?>
                    <?php
                      $cnt = $metrics['confusion_matrix'][$act][$prd] ?? 0;
                      $is_diag = ($act === $prd);
                      $bg  = $is_diag ? ($cnt > 0 ? '#DCFCE7' : '#F8FAFC') : ($cnt > 0 ? '#FEE2E2' : '#F8FAFC');
                      $col = $is_diag ? ($cnt > 0 ? '#15803D' : '#94A3B8') : ($cnt > 0 ? '#DC2626' : '#94A3B8');
                    ?>
                    <td style="background: <?= $bg ?>; color: <?= $col ?>; font-weight: 700; font-size: 15px; padding: 10px;">
                      <?= $cnt ?>
                    </td>
                  <?php endforeach; ?>
                  <td style="font-weight: 600; color: #475569; background: #F8FAFC; font-size: 13px;">
                    <?= $row_total ?> sampel
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div style="font-size: 11.5px; color: var(--text-muted); display: flex; gap: 14px; margin-top: 6px;">
          <span style="display: inline-flex; align-items: center; gap: 5px;">
            <span style="width: 10px; height: 10px; border-radius: 2px; background: #DCFCE7; border: 1px solid #16A34A;"></span>
            Diagonal (True Positive / Prediksi Benar)
          </span>
          <span style="display: inline-flex; align-items: center; gap: 5px;">
            <span style="width: 10px; height: 10px; border-radius: 2px; background: #FEE2E2; border: 1px solid #DC2626;"></span>
            Off-Diagonal (Misklasifikasi / Salah)
          </span>
        </div>
      </div>
    </div>

    <!-- Per-Class Metrics -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Metrik Klasifikasi Per Kategori</h3>
        <p class="card-subtitle">Rincian nilai Precision, Recall, dan F1-Score untuk setiap kelas target.</p>
      </div>
      <div class="card-body">
        <div class="table-container mb-3">
          <table>
            <thead>
              <tr>
                <th>Kelas Target</th>
                <th>Precision</th>
                <th>Recall</th>
                <th>F1-Score</th>
                <th>Support</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach (['tren', 'kurang tren', 'tidak tren'] as $cat): ?>
                <?php $pc = $metrics['per_class'][$cat] ?? ['precision' => 0, 'recall' => 0, 'f1' => 0, 'support' => 0]; ?>
                <tr>
                  <td><strong><?= label_badge($cat) ?></strong></td>
                  <td><?= fmt_percent($pc['precision'] * 100) ?></td>
                  <td><?= fmt_percent($pc['recall'] * 100) ?></td>
                  <td><strong style="color: var(--primary);"><?= fmt_percent($pc['f1'] * 100) ?></strong></td>
                  <td><?= $pc['support'] ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr style="background: #F8FAFC; font-weight: 700;">
                <td>Macro Average</td>
                <td><?= fmt_percent($metrics['macro']['precision'] * 100) ?></td>
                <td><?= fmt_percent($metrics['macro']['recall'] * 100) ?></td>
                <td style="color: var(--primary);"><?= fmt_percent($metrics['macro']['f1'] * 100) ?></td>
                <td><?= $metrics['total'] ?></td>
              </tr>
            </tfoot>
          </table>
        </div>

        <div class="alert alert-info" style="font-size: 12px; margin: 0; line-height: 1.5;">
          <strong>Rumus Metrik:</strong><br>
          &bull; <em>Precision</em> = TP / (TP + FP)<br>
          &bull; <em>Recall</em> = TP / (TP + FN)<br>
          &bull; <em>F1-Score</em> = 2 &times; (Precision &times; Recall) / (Precision + Recall)
        </div>
      </div>
    </div>
  </div>

  <!-- TABEL RINCIAN DATA TESTING YANG DIEVALUASI -->
  <div class="card">
    <div class="card-header flex justify-between items-center">
      <div>
        <h3 class="card-title">Rincian Pengujian Sampel Test Set (<?= count($test_predictions) ?> data)</h3>
        <p class="card-subtitle">Evaluasi prediksi tiap sampel data uji yang dirahasiakan saat pelatihan model.</p>
      </div>
      <a href="<?= BASE_URL ?>/results.php" class="btn btn-sm btn-outline">
        <i data-lucide="external-link"></i> Seluruh Produk
      </a>
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
              <th>Label Aktual</th>
              <th>Hasil Prediksi KNN</th>
              <th>Status Evaluasi</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($test_predictions)): ?>
              <tr>
                <td colspan="9" class="text-center text-muted">Tidak ada data testing pada proses ini.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($test_predictions as $tp): ?>
                <?php $is_match = ($tp['actual_label'] === $tp['prediction']); ?>
                <tr>
                  <td>#<?= $tp['product_id'] ?></td>
                  <td><strong><?= h($tp['product_name']) ?></strong></td>
                  <td><?= fmt_number($tp['sold']) ?> unit</td>
                  <td><?= h($tp['rating']) ?> ★</td>
                  <td><?= fmt_currency($tp['price_now']) ?></td>
                  <td><?= label_badge($tp['actual_label']) ?></td>
                  <td><?= pred_badge($tp['prediction']) ?></td>
                  <td>
                    <?php if ($is_match): ?>
                      <span class="badge badge-tren"><i data-lucide="check" style="width:12px;height:12px;vertical-align:middle;"></i> Tepat</span>
                    <?php else: ?>
                      <span class="badge badge-tidak"><i data-lucide="x" style="width:12px;height:12px;vertical-align:middle;"></i> Meleset</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <a href="<?= BASE_URL ?>/product_detail.php?id=<?= $tp['product_id'] ?>" class="btn btn-sm btn-outline" title="Lihat Tetangga & Perhitungan">
                      Detail
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

<?php endif; ?>

<?php include dirname(__DIR__) . '/app/views/layouts/footer.php'; ?>

