<?php
/**
 * migrate.php — Database Migration Runner
 *
 * Jalankan dari command line:
 *   php migrate.php
 *
 * Atau via browser jika IP diizinkan (hanya development):
 *   http://localhost/fashiontrendknn/migrate.php
 *
 * PERINGATAN:
 * - Jangan tinggalkan file ini accessible di production tanpa proteksi IP.
 * - Pastikan backup database sudah dilakukan sebelum menjalankan migration.
 * - Setiap migration hanya akan dijalankan satu kali (dicatat di schema_migrations).
 */

// ==============================================================
// PROTEKSI AKSES — hanya CLI atau IP lokal
// ==============================================================
$isCli = php_sapi_name() === 'cli';

if (!$isCli) {
    // Proteksi akses via browser: hanya izinkan dari localhost
    $allowedIps = ['127.0.0.1', '::1', 'localhost'];
    $clientIp   = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!in_array($clientIp, $allowedIps, true)) {
        http_response_code(403);
        die('Migration runner hanya dapat diakses dari localhost atau via CLI.');
    }
}

// ==============================================================
// BOOTSTRAP
// ==============================================================
require_once __DIR__ . '/app/config/env.php';
load_env(__DIR__ . '/.env');

$dbHost = env('DB_HOST', '127.0.0.1');
$dbPort = env('DB_PORT', '3306');
$dbName = env('DB_NAME', 'fashion_knn');
$dbUser = env('DB_USER', 'root');
$dbPass = env('DB_PASS', '');

function out(string $msg): void {
    global $isCli;
    if ($isCli) {
        echo $msg . PHP_EOL;
    } else {
        echo nl2br(htmlspecialchars($msg, ENT_QUOTES, 'UTF-8')) . '<br>';
    }
    if (ob_get_level()) ob_flush();
    flush();
}

if (!$isCli) {
    echo '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8">'
        . '<title>Migration Runner</title>'
        . '<style>body{font-family:monospace;background:#0f172a;color:#94a3b8;padding:24px;}'
        . 'strong{color:#34d399;} .err{color:#f87171;} .warn{color:#fbbf24;}</style></head><body>';
    echo '<strong>FashionTrendKNN — Database Migration Runner</strong><br><br>';
}

// ==============================================================
// KONEKSI DATABASE
// ==============================================================
try {
    $pdo = new PDO(
        "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
        $dbUser, $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    out("[OK] Koneksi database berhasil: {$dbName}@{$dbHost}");
} catch (PDOException $e) {
    out("[ERROR] Koneksi gagal: " . $e->getMessage());
    exit(1);
}

// ==============================================================
// STEP 1: Pastikan tabel schema_migrations ada
// ==============================================================
$pdo->exec("
    CREATE TABLE IF NOT EXISTS schema_migrations (
        id          INT          NOT NULL AUTO_INCREMENT,
        version     VARCHAR(100) NOT NULL,
        description VARCHAR(255) NOT NULL DEFAULT '',
        applied_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_version (version)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
out("[OK] Tabel schema_migrations siap.");

// ==============================================================
// HELPER: cek apakah migration sudah dijalankan
// ==============================================================
function migration_applied(PDO $pdo, string $version): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM schema_migrations WHERE version = ?");
    $stmt->execute([$version]);
    return (int)$stmt->fetchColumn() > 0;
}

function mark_migration(PDO $pdo, string $version, string $description): void {
    $pdo->prepare("INSERT IGNORE INTO schema_migrations (version, description) VALUES (?, ?)")
        ->execute([$version, $description]);
}

// ==============================================================
// DAFTAR MIGRATION
// ==============================================================
$migrations = [];

// --------------------------------------------------------------
// Migration 001: Buat tabel KNN (knn_processes, knn_predictions, knn_norm_params)
// --------------------------------------------------------------
$migrations[] = [
    'version'     => '001_add_knn_tables',
    'description' => 'Tambah tabel knn_processes, knn_predictions, knn_norm_params dan index',
    'run'         => function (PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS knn_processes (
                id              INT          NOT NULL AUTO_INCREMENT,
                k_value         INT          NOT NULL,
                split_ratio     VARCHAR(10)  NOT NULL DEFAULT '70:30',
                random_seed     INT          NOT NULL DEFAULT 42,
                train_count     INT          NOT NULL DEFAULT 0,
                test_count      INT          NOT NULL DEFAULT 0,
                accuracy        DECIMAL(8,4) NOT NULL DEFAULT 0.0000,
                precision_score DECIMAL(8,4) NOT NULL DEFAULT 0.0000,
                recall_score    DECIMAL(8,4) NOT NULL DEFAULT 0.0000,
                f1_score        DECIMAL(8,4) NOT NULL DEFAULT 0.0000,
                confusion_matrix TEXT NULL,
                processed_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_by      INT          NULL,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS knn_predictions (
                id                  INT           NOT NULL AUTO_INCREMENT,
                process_id          INT           NOT NULL,
                product_id          INT           NOT NULL,
                prediction          VARCHAR(30)   NOT NULL,
                normalized_sold     DECIMAL(10,6) NOT NULL DEFAULT 0.000000,
                normalized_rating   DECIMAL(10,6) NOT NULL DEFAULT 0.000000,
                normalized_price    DECIMAL(10,6) NOT NULL DEFAULT 0.000000,
                neighbors_json      TEXT          NULL,
                is_test_data        TINYINT(1)    NOT NULL DEFAULT 0,
                actual_label        VARCHAR(30)   NULL,
                is_correct          TINYINT(1)    NULL,
                created_at          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_knn_pred_process (process_id),
                KEY idx_knn_pred_product (product_id),
                KEY idx_knn_pred_testdata (is_test_data),
                CONSTRAINT fk_knnpred_process  FOREIGN KEY (process_id) REFERENCES knn_processes(id) ON DELETE CASCADE,
                CONSTRAINT fk_knnpred_product  FOREIGN KEY (product_id) REFERENCES products(id)      ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS knn_norm_params (
                id          INT           NOT NULL AUTO_INCREMENT,
                process_id  INT           NOT NULL,
                feature     VARCHAR(30)   NOT NULL,
                min_val     DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
                max_val     DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
                PRIMARY KEY (id),
                KEY idx_knn_norm_process (process_id),
                CONSTRAINT fk_knnnorm_process FOREIGN KEY (process_id) REFERENCES knn_processes(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Tambah kolom prediction_process_id ke products jika belum ada
        $cols = $pdo->query("SHOW COLUMNS FROM products LIKE 'prediction_process_id'")->fetchAll();
        if (empty($cols)) {
            $pdo->exec("ALTER TABLE products ADD COLUMN prediction_process_id INT NULL");
            out("    + Kolom prediction_process_id ditambahkan ke tabel products");
        }

        // Tambah index products jika belum ada
        $indexes = $pdo->query("SHOW INDEX FROM products WHERE Key_name IN ('idx_products_label', 'idx_products_prediction')")->fetchAll();
        $existingIdx = array_column($indexes, 'Key_name');
        if (!in_array('idx_products_label', $existingIdx)) {
            $pdo->exec("ALTER TABLE products ADD INDEX idx_products_label (label)");
            out("    + Index idx_products_label ditambahkan");
        }
        if (!in_array('idx_products_prediction', $existingIdx)) {
            $pdo->exec("ALTER TABLE products ADD INDEX idx_products_prediction (prediction)");
            out("    + Index idx_products_prediction ditambahkan");
        }
    }
];

// --------------------------------------------------------------
// Migration 002: Upgrade password plain-text ke bcrypt
// --------------------------------------------------------------
$migrations[] = [
    'version'     => '002_hash_passwords',
    'description' => 'Upgrade password plain-text pengguna ke bcrypt (cost=12)',
    'run'         => function (PDO $pdo): void {
        $users = $pdo->query("SELECT id, username, password FROM users")->fetchAll();
        $hashed = 0;
        $skipped = 0;

        foreach ($users as $u) {
            // Cek apakah sudah bcrypt (prefix $2y$ atau $2a$)
            if (str_starts_with($u['password'], '$2y$') || str_starts_with($u['password'], '$2a$')) {
                $skipped++;
                continue;
            }
            // Hash password plain text
            $newHash = password_hash($u['password'], PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$newHash, $u['id']]);
            out("    + User '{$u['username']}' password di-hash ke bcrypt");
            $hashed++;
        }

        if ($skipped > 0) {
            out("    ~ {$skipped} user sudah menggunakan bcrypt, dilewati");
        }
        out("    + Total {$hashed} user password berhasil di-upgrade ke bcrypt");
    }
];

// --------------------------------------------------------------
// Migration 003: Tambah indeks performa tambahan
// --------------------------------------------------------------
$migrations[] = [
    'version'     => '003_add_performance_indexes',
    'description' => 'Tambah index performa pada tabel products dan users',
    'run'         => function (PDO $pdo): void {
        // Index pada products.created_at untuk sorting
        $existing = $pdo->query("SHOW INDEX FROM products WHERE Key_name = 'idx_products_created'")->fetchAll();
        if (empty($existing)) {
            $pdo->exec("ALTER TABLE products ADD INDEX idx_products_created (created_at)");
            out("    + Index idx_products_created ditambahkan");
        } else {
            out("    ~ Index idx_products_created sudah ada, dilewati");
        }

        // Index pada knn_processes.processed_at
        $existing2 = $pdo->query("SHOW INDEX FROM knn_processes WHERE Key_name = 'idx_knn_proc_time'")->fetchAll();
        if (empty($existing2)) {
            $pdo->exec("ALTER TABLE knn_processes ADD INDEX idx_knn_proc_time (processed_at)");
            out("    + Index idx_knn_proc_time ditambahkan");
        } else {
            out("    ~ Index idx_knn_proc_time sudah ada, dilewati");
        }
    }
];

// ==============================================================
// JALANKAN MIGRATION
// ==============================================================
out("\n=== Memulai Migration ===\n");

$totalRun    = 0;
$totalSkipped = 0;

foreach ($migrations as $migration) {
    $version = $migration['version'];
    $desc    = $migration['description'];

    if (migration_applied($pdo, $version)) {
        out("[SKIP] {$version}: {$desc}");
        $totalSkipped++;
        continue;
    }

    out("[RUN]  {$version}: {$desc}");
    try {
        $pdo->beginTransaction();
        ($migration['run'])($pdo);
        mark_migration($pdo, $version, $desc);
        $pdo->commit();
        out("[DONE] {$version} selesai.\n");
        $totalRun++;
    } catch (Throwable $e) {
        $pdo->rollBack();
        out("[FAIL] {$version} GAGAL: " . $e->getMessage());
        out("[STOP] Migration dihentikan karena error. Perbaiki masalah lalu jalankan ulang.");
        exit(1);
    }
}

out("\n=== Migration Selesai ===");
out("Dijalankan : {$totalRun}");
out("Dilewati   : {$totalSkipped}");
out("Total      : " . ($totalRun + $totalSkipped));

if (!$isCli) {
    echo '</body></html>';
}
exit(0);
