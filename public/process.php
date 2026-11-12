<?php
require_once dirname(__DIR__) . '/app/config/database.php';
require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/helpers/functions.php';
require_once dirname(__DIR__) . '/app/middleware/access.php';
require_once dirname(__DIR__) . '/app/services/KNNService.php';

check_access('process');
$page_title = 'Proses Klasifikasi KNN';
$current_page = 'process';

$error   = null;
$result  = null;
$knn_svc = new KNNService($pdo);

// Ambil jumlah data latih berlabel saat ini
$labeled_count = (int)$pdo->query(
    "SELECT COUNT(*) FROM products WHERE label IS NOT NULL AND label != ''"
)->fetchColumn();

// Handle Eksekusi Proses KNN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'run_knn') {
    csrf_check();

    $k     = max(1, min(25, (int)($_POST['k'] ?? 3)));
    $split = in_array((int)($_POST['split'] ?? 70), [70, 80]) ? (int)$_POST['split'] : 70;
    $seed  = (int)($_POST['seed'] ?? 42);

    try {
        $result = $knn_svc->runProcess($k, $split, $seed);
        $_SESSION['flash_success'] = "Klasifikasi KNN berhasil dijalankan! Model mengevaluasi data testing dan memperbarui prediksi seluruh produk.";
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Ambil riwayat proses terakhir jika belum ada eksekusi baru di request ini
$latest_process = null;
if (!$result) {
    try {
        $latest_process = $pdo->query("SELECT * FROM knn_processes ORDER BY id DESC LIMIT 1")->fetch();
    } catch (Exception $e) {}
}

include dirname(__DIR__) . '/app/views/layouts/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Proses Klasifikasi K-Nearest Neighbor (KNN)</h1>
    <p class="page-subtitle">Konfigurasi parameter, lakukan normalisasi Min-Max, hitung jarak Euclidean, dan evaluasi performa model klasifikasi tren.</p>
  </div>
</div>

<?php if (isset($_SESSION['flash_success'])): ?>
  <div class="alert alert-success">
    <i data-lucide="check-circle"></i>
    <span><?= h($_SESSION['flash_success']) ?></span>
  </div>
  <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>

<?php if ($error): ?>
  <div class="alert alert-error">
    <i data-lucide="alert-triangle"></i>
    <span><?= h($error) ?></span>
  </div>
<?php endif; ?>

<!-- Form Konfigurasi & Status Data -->
<div class="grid two" style="margin-bottom: 24px; align-items: stretch;">
  <!-- Form Parameter KNN -->
  <div class="card" style="display: flex; flex-direction: column;">
    <div class="card-header" style="border-bottom: 1px solid var(--border); padding: 18px 24px;">
      <div style="display: flex; align-items: center; gap: 10px;">
        <div style="width: 36px; height: 36px; border-radius: 8px; background: #EEF2FF; color: var(--primary); display: flex; align-items: center; justify-content: center;">
          <i data-lucide="sliders" style="width: 18px; height: 18px;"></i>
        </div>
        <div>
          <h3 class="card-title" style="margin: 0; font-size: 16px;">Konfigurasi Parameter Model</h3>
          <p class="card-subtitle" style="margin: 2px 0 0 0;">Tentukan nilai K dan skenario pembagian dataset.</p>
        </div>
      </div>
    </div>
    <div class="card-body" style="padding: 24px; flex: 1;">
      <form action="" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="run_knn">

        <!-- Input Nilai K -->
        <div class="form-group" style="margin-bottom: 20px;">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
            <label class="form-label" for="k_input" style="font-weight: 600; margin-bottom: 0;">
              Nilai K (Jumlah Tetangga Terdekat)
            </label>
            <div style="display: flex; gap: 4px;">
              <button type="button" class="btn btn-sm btn-secondary" onclick="document.getElementById('k_input').value=3;" style="padding: 2px 8px; font-size: 11px;">K=3</button>
              <button type="button" class="btn btn-sm btn-secondary" onclick="document.getElementById('k_input').value=5;" style="padding: 2px 8px; font-size: 11px;">K=5</button>
              <button type="button" class="btn btn-sm btn-secondary" onclick="document.getElementById('k_input').value=7;" style="padding: 2px 8px; font-size: 11px;">K=7</button>
              <button type="button" class="btn btn-sm btn-secondary" onclick="document.getElementById('k_input').value=9;" style="padding: 2px 8px; font-size: 11px;">K=9</button>
            </div>
          </div>
          <input type="number" name="k" id="k_input" min="1" max="25" value="3" required class="form-control" style="font-weight: 600; font-size: 14px;">
          <small class="form-hint" style="color: var(--text-muted); font-size: 12px; margin-top: 4px; display: block;">
            Disarankan memilih bilangan ganjil (1, 3, 5, 7, ...) untuk menghindari hasil voting seri (*tie*).
          </small>
        </div>

        <!-- Input Rasio Split -->
        <div class="form-group" style="margin-bottom: 20px;">
          <label class="form-label" for="split_select" style="font-weight: 600; margin-bottom: 6px;">
            Rasio Pembagian Data (Holdout Split)
          </label>
          <select name="split" id="split_select" class="form-select" style="font-size: 13.5px;">
            <option value="70">70% Data Training &bull; 30% Data Testing</option>
            <option value="80">80% Data Training &bull; 20% Data Testing</option>
          </select>
          <small class="form-hint" style="color: var(--text-muted); font-size: 12px; margin-top: 4px; display: block;">
            Data dibagi secara stratifikasi agar distribusi setiap kelas tetap proporsional.
          </small>
        </div>

        <!-- Input Random Seed -->
        <div class="form-group" style="margin-bottom: 24px;">
          <label class="form-label" for="seed_input" style="font-weight: 600; margin-bottom: 6px;">
            Random Seed (Reproduksibilitas)
          </label>
          <input type="number" name="seed" id="seed_input" value="42" required class="form-control" style="font-size: 13.5px;">
          <small class="form-hint" style="color: var(--text-muted); font-size: 12px; margin-top: 4px; display: block;">
            Seed memastikan pengacakan data menghasilkan sampel yang sama untuk kebutuhan pengujian ulang.
          </small>
        </div>

        <?php if ($labeled_count < 3): ?>
          <div class="alert alert-warning mb-3" style="font-size: 13px;">
            <i data-lucide="alert-circle" style="width: 18px; height: 18px; flex-shrink: 0;"></i>
            <span>Data berlabel saat ini hanya <strong><?= $labeled_count ?></strong>. Tambahkan minimal 3 produk berlabel di menu Data Produk sebelum memulai.</span>
          </div>
          <button type="button" disabled class="btn btn-primary" style="width: 100%; opacity: 0.6; cursor: not-allowed; justify-content: center; padding: 11px;">
            <i data-lucide="play" style="width: 16px; height: 16px;"></i> Mulai Klasifikasi KNN
          </button>
        <?php else: ?>
          <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 11px; font-weight: 600; font-size: 14px; box-shadow: 0 2px 6px rgba(79, 70, 229, 0.3);">
            <i data-lucide="play" style="width: 16px; height: 16px;"></i> Jalankan Preprocessing & Klasifikasi KNN
          </button>
        <?php endif; ?>
      </form>
    </div>
  </div>

  <!-- Panel Panduan Metodologi -->
  <div class="card" style="display: flex; flex-direction: column;">
    <div class="card-header" style="border-bottom: 1px solid var(--border); padding: 18px 24px;">
      <div style="display: flex; align-items: center; gap: 10px;">
        <div style="width: 36px; height: 36px; border-radius: 8px; background: #EEF2FF; color: var(--primary); display: flex; align-items: center; justify-content: center;">
          <i data-lucide="git-commit" style="width: 18px; height: 18px;"></i>
        </div>
        <div>
          <h3 class="card-title" style="margin: 0; font-size: 16px;">Tahapan Komputasi Algoritma</h3>
          <p class="card-subtitle" style="margin: 2px 0 0 0;">Alur komputasi klasifikasi data mining.</p>
        </div>
      </div>
    </div>
    <div class="card-body" style="padding: 24px; flex: 1; display: flex; flex-direction: column; gap: 14px;">
      
      <!-- Step 1 -->
      <div style="display: flex; gap: 12px; align-items: flex-start;">
        <div style="width: 26px; height: 26px; border-radius: 50%; background: #EEF2FF; color: var(--primary); font-size: 12px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 2px;">1</div>
        <div>
          <div style="font-weight: 600; font-size: 13.5px; color: var(--text-main); margin-bottom: 2px;">Min-Max Scaling</div>
          <div style="font-size: 12.5px; color: var(--text-muted); line-height: 1.45;">
            Menormalisasi fitur numerik (<code style="background: #F1F5F9; padding: 1px 4px; border-radius: 4px;">sold</code>, <code style="background: #F1F5F9; padding: 1px 4px; border-radius: 4px;">rating</code>, <code style="background: #F1F5F9; padding: 1px 4px; border-radius: 4px;">price_now</code>) ke rentang skala [0, 1].
          </div>
        </div>
      </div>

      <!-- Step 2 -->
      <div style="display: flex; gap: 12px; align-items: flex-start;">
        <div style="width: 26px; height: 26px; border-radius: 50%; background: #EEF2FF; color: var(--primary); font-size: 12px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 2px;">2</div>
        <div>
          <div style="font-weight: 600; font-size: 13.5px; color: var(--text-main); margin-bottom: 2px;">Stratified Dataset Split</div>
          <div style="font-size: 12.5px; color: var(--text-muted); line-height: 1.45;">
            Membagi data latih dan data uji secara stratifikasi agar komposisi kelas target seimbang.
          </div>
        </div>
      </div>

      <!-- Step 3 -->
      <div style="display: flex; gap: 12px; align-items: flex-start;">
        <div style="width: 26px; height: 26px; border-radius: 50%; background: #EEF2FF; color: var(--primary); font-size: 12px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 2px;">3</div>
        <div>
          <div style="font-weight: 600; font-size: 13.5px; color: var(--text-main); margin-bottom: 2px;">Euclidean Distance</div>
          <div style="font-size: 12.5px; color: var(--text-muted); line-height: 1.45;">
            Menghitung jarak geometris ruang Euclidean antara data uji dengan seluruh data referensi.
          </div>
        </div>
      </div>

      <!-- Step 4 -->
      <div style="display: flex; gap: 12px; align-items: flex-start;">
        <div style="width: 26px; height: 26px; border-radius: 50%; background: #EEF2FF; color: var(--primary); font-size: 12px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 2px;">4</div>
        <div>
          <div style="font-weight: 600; font-size: 13.5px; color: var(--text-main); margin-bottom: 2px;">K-Nearest Majority Voting</div>
          <div style="font-size: 12.5px; color: var(--text-muted); line-height: 1.45;">
            Mengambil K tetangga terdekat. Jika suara seimbang, jarak rata-rata terdekat dijadikan pemutus seri.
          </div>
        </div>
      </div>

      <!-- Step 5 -->
      <div style="display: flex; gap: 12px; align-items: flex-start;">
        <div style="width: 26px; height: 26px; border-radius: 50%; background: #EEF2FF; color: var(--primary); font-size: 12px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 2px;">5</div>
        <div>
          <div style="font-weight: 600; font-size: 13.5px; color: var(--text-main); margin-bottom: 2px;">Evaluasi & Prediksi Otomatis</div>
          <div style="font-size: 12.5px; color: var(--text-muted); line-height: 1.45;">
            Mengukur matriks evaluasi pada data testing, lalu memprediksi kategori seluruh produk secara otomatis.
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- JIKA BARU SAJA DIJALANKAN (RESULT DISPLAY) -->
<?php if ($result): ?>
  <?php $m = $result['metrics']; ?>
  <div class="card mb-4" style="border: 1px solid #C7D2FE;">
    <div class="card-header flex justify-between items-center flex-wrap gap-2" style="background: #F8FAFC; border-bottom: 1px solid #E2E8F0; padding: 18px 24px;">
      <div>
        <div style="display: flex; align-items: center; gap: 8px;">
          <span class="badge badge-tren" style="font-size: 11px;">SUKSES DIEKSEKUSI</span>
          <h3 class="card-title" style="margin: 0; font-size: 16px; color: #0F172A;">
            Hasil Klasifikasi & Evaluasi (Proses #<?= $result['process_id'] ?>)
          </h3>
        </div>
        <p class="card-subtitle" style="margin: 3px 0 0 0;">
          Konfigurasi: <strong>K=<?= $result['k'] ?></strong> &bull; Holdout: <strong><?= $result['split_ratio'] ?>%</strong> &bull; Seed: <strong><?= $result['seed'] ?></strong>
        </p>
      </div>
      <div class="flex gap-2">
        <a href="<?= BASE_URL ?>/results.php" class="btn btn-sm btn-primary">
          <i data-lucide="list-checks" style="width: 14px; height: 14px;"></i> Lihat Hasil Prediksi
        </a>
        <a href="<?= BASE_URL ?>/evaluation.php?process_id=<?= $result['process_id'] ?>" class="btn btn-sm btn-secondary">
          <i data-lucide="bar-chart-2" style="width: 14px; height: 14px;"></i> Evaluasi Lengkap
        </a>
      </div>
    </div>
    <div class="card-body" style="padding: 24px;">
      
      <!-- 4 Metric Cards -->
      <div class="stats-grid mb-4">
        <!-- Akurasi -->
        <div class="metric-card">
          <div class="metric-card-top">
            <span class="metric-card-label">Akurasi Pengujian</span>
            <div class="metric-card-icon" style="background: #DCFCE7; color: #16A34A;">
              <i data-lucide="check-circle" style="width: 18px; height: 18px;"></i>
            </div>
          </div>
          <div class="metric-card-value" style="color: #16A34A;"><?= fmt_percent($m['accuracy'] * 100) ?></div>
          <div class="metric-card-desc"><?= $m['correct'] ?> dari <?= $m['total'] ?> sampel uji tepat</div>
        </div>

        <!-- Macro Precision -->
        <div class="metric-card">
          <div class="metric-card-top">
            <span class="metric-card-label">Macro Precision</span>
            <div class="metric-card-icon" style="background: #EEF2FF; color: var(--primary);">
              <i data-lucide="target" style="width: 18px; height: 18px;"></i>
            </div>
          </div>
          <div class="metric-card-value"><?= fmt_percent($m['macro']['precision'] * 100) ?></div>
          <div class="metric-card-desc">Rata-rata presisi tiap kelas</div>
        </div>

        <!-- Macro Recall -->
        <div class="metric-card">
          <div class="metric-card-top">
            <span class="metric-card-label">Macro Recall</span>
            <div class="metric-card-icon" style="background: #FEF3C7; color: #D97706;">
              <i data-lucide="zap" style="width: 18px; height: 18px;"></i>
            </div>
          </div>
          <div class="metric-card-value"><?= fmt_percent($m['macro']['recall'] * 100) ?></div>
          <div class="metric-card-desc">Daya tangkap sampel aktual</div>
        </div>

        <!-- Macro F1-Score -->
        <div class="metric-card">
          <div class="metric-card-top">
            <span class="metric-card-label">Macro F1-Score</span>
            <div class="metric-card-icon" style="background: #EEF2FF; color: var(--primary);">
              <i data-lucide="award" style="width: 18px; height: 18px;"></i>
            </div>
          </div>
          <div class="metric-card-value" style="color: var(--primary);"><?= fmt_percent($m['macro']['f1'] * 100) ?></div>
          <div class="metric-card-desc">Harmonic mean presisi & recall</div>
        </div>
      </div>

      <!-- Confusion Matrix & Per-Class Metrics -->
      <div class="grid two">
        <!-- Matrix 3x3 -->
        <div>
          <h4 style="font-size: 14.5px; font-weight: 700; color: #0F172A; margin-bottom: 10px;">
            Confusion Matrix Multiclass (3x3):
          </h4>
          <div class="table-container mb-2">
            <table class="cm-table" style="text-align: center; width: 100%;">
              <thead>
                <tr>
                  <th rowspan="2" style="vertical-align: middle; background: #F8FAFC; text-align: left; font-size: 12px;">Aktual (Ground Truth)</th>
                  <th colspan="3" style="text-align: center; background: #EEF2FF; color: var(--primary); font-size: 12px;">Prediksi Model KNN</th>
                </tr>
                <tr>
                  <th style="font-size: 12px;">Tren</th>
                  <th style="font-size: 12px;">Kurang Tren</th>
                  <th style="font-size: 12px;">Tidak Tren</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach (['tren', 'kurang tren', 'tidak tren'] as $act): ?>
                  <tr>
                    <td style="font-weight: 600; text-align: left; background: #F8FAFC; font-size: 13px;">
                      <?= label_badge($act) ?>
                    </td>
                    <?php foreach (['tren', 'kurang tren', 'tidak tren'] as $prd): ?>
                      <?php
                        $cnt = $m['confusion_matrix'][$act][$prd] ?? 0;
                        $is_diag = ($act === $prd);
                        $bg = $is_diag ? ($cnt > 0 ? '#DCFCE7' : '#F8FAFC') : ($cnt > 0 ? '#FEE2E2' : '#F8FAFC');
                        $col = $is_diag ? ($cnt > 0 ? '#15803D' : '#94A3B8') : ($cnt > 0 ? '#DC2626' : '#94A3B8');
                      ?>
                      <td style="background: <?= $bg ?>; color: <?= $col ?>; font-weight: 700; font-size: 15px; padding: 10px;">
                        <?= $cnt ?>
                      </td>
                    <?php endforeach; ?>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div style="font-size: 11.5px; color: var(--text-muted); display: flex; gap: 14px; margin-top: 6px;">
            <span style="display: inline-flex; align-items: center; gap: 5px;">
              <span style="width: 10px; height: 10px; border-radius: 2px; background: #DCFCE7; border: 1px solid #16A34A;"></span>
              Prediksi Benar (Diagonal)
            </span>
            <span style="display: inline-flex; align-items: center; gap: 5px;">
              <span style="width: 10px; height: 10px; border-radius: 2px; background: #FEE2E2; border: 1px solid #DC2626;"></span>
              Miskalsifikasi (Off-Diagonal)
            </span>
          </div>
        </div>

        <!-- Per-Class Metrics -->
        <div>
          <h4 style="font-size: 14.5px; font-weight: 700; color: #0F172A; margin-bottom: 10px;">
            Performa Klasifikasi Per Kategori:
          </h4>
          <div class="table-container">
            <table>
              <thead>
                <tr>
                  <th>Kategori</th>
                  <th>Precision</th>
                  <th>Recall</th>
                  <th>F1-Score</th>
                  <th>Support</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach (['tren', 'kurang tren', 'tidak tren'] as $cat): ?>
                  <?php $pc = $m['per_class'][$cat] ?? ['precision' => 0, 'recall' => 0, 'f1' => 0, 'support' => 0]; ?>
                  <tr>
                    <td><strong><?= label_badge($cat) ?></strong></td>
                    <td><?= fmt_percent($pc['precision'] * 100) ?></td>
                    <td><?= fmt_percent($pc['recall'] * 100) ?></td>
                    <td><strong style="color: var(--primary);"><?= fmt_percent($pc['f1'] * 100) ?></strong></td>
                    <td><span class="badge badge-gray"><?= $pc['support'] ?> data</span></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>
  </div>
<?php elseif ($latest_process): ?>
  <!-- Ringkasan Proses Terakhir -->
  <div class="card">
    <div class="card-header flex justify-between items-center flex-wrap gap-2" style="padding: 18px 24px;">
      <div>
        <h3 class="card-title" style="margin: 0; font-size: 16px;">Riwayat Proses Terakhir (Proses #<?= $latest_process['id'] ?>)</h3>
        <p class="card-subtitle" style="margin: 2px 0 0 0;">Dijalankan pada: <?= h($latest_process['processed_at']) ?></p>
      </div>
      <a href="<?= BASE_URL ?>/evaluation.php?process_id=<?= $latest_process['id'] ?>" class="btn btn-sm btn-secondary flex items-center gap-1.5">
        <i data-lucide="external-link" style="width: 14px; height: 14px;"></i> Buka Evaluasi Lengkap
      </a>
    </div>
    <div class="card-body" style="padding: 24px;">
      <div class="stats-grid">
        <div class="metric-card">
          <div class="metric-card-top">
            <span class="metric-card-label">Nilai K</span>
            <div class="metric-card-icon" style="background: #EEF2FF; color: var(--primary);">
              <i data-lucide="hash" style="width: 18px; height: 18px;"></i>
            </div>
          </div>
          <div class="metric-card-value"><?= $latest_process['k_value'] ?></div>
          <div class="metric-card-desc">Tetangga Terdekat</div>
        </div>

        <div class="metric-card">
          <div class="metric-card-top">
            <span class="metric-card-label">Rasio Data</span>
            <div class="metric-card-icon" style="background: #F8FAFC; color: #64748B;">
              <i data-lucide="pie-chart" style="width: 18px; height: 18px;"></i>
            </div>
          </div>
          <div class="metric-card-value"><?= h($latest_process['split_ratio']) ?></div>
          <div class="metric-card-desc">Training : Testing</div>
        </div>

        <div class="metric-card">
          <div class="metric-card-top">
            <span class="metric-card-label">Akurasi Pengujian</span>
            <div class="metric-card-icon" style="background: #DCFCE7; color: #16A34A;">
              <i data-lucide="check-circle" style="width: 18px; height: 18px;"></i>
            </div>
          </div>
          <div class="metric-card-value" style="color: #16A34A;"><?= fmt_percent($latest_process['accuracy'] * 100) ?></div>
          <div class="metric-card-desc">Data Uji Tepat</div>
        </div>

        <div class="metric-card">
          <div class="metric-card-top">
            <span class="metric-card-label">Macro F1-Score</span>
            <div class="metric-card-icon" style="background: #EEF2FF; color: var(--primary);">
              <i data-lucide="award" style="width: 18px; height: 18px;"></i>
            </div>
          </div>
          <div class="metric-card-value" style="color: var(--primary);"><?= fmt_percent($latest_process['f1_score'] * 100) ?></div>
          <div class="metric-card-desc">Harmonic Mean</div>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php include dirname(__DIR__) . '/app/views/layouts/footer.php'; ?>

