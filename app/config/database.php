<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('BASE_PATH', dirname(__DIR__, 2));
define('APP_PATH', dirname(__DIR__));
define('BASE_URL', '/fashiontrendknn/public');

$host = 'localhost';
$db   = 'fashion_knn';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    // Pastikan tabel-tabel pendukung KNN sudah ada (Idempotent Migration)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS knn_processes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            k_value INT NOT NULL,
            split_ratio VARCHAR(10) NOT NULL DEFAULT '70:30',
            random_seed INT NOT NULL DEFAULT 42,
            train_count INT NOT NULL DEFAULT 0,
            test_count INT NOT NULL DEFAULT 0,
            accuracy DECIMAL(8,4) NOT NULL DEFAULT 0.0000,
            precision_score DECIMAL(8,4) NOT NULL DEFAULT 0.0000,
            recall_score DECIMAL(8,4) NOT NULL DEFAULT 0.0000,
            f1_score DECIMAL(8,4) NOT NULL DEFAULT 0.0000,
            confusion_matrix TEXT NULL,
            processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_by INT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS knn_predictions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            process_id INT NOT NULL,
            product_id INT NOT NULL,
            prediction VARCHAR(30) NOT NULL,
            normalized_sold DECIMAL(10,6) NOT NULL DEFAULT 0.000000,
            normalized_rating DECIMAL(10,6) NOT NULL DEFAULT 0.000000,
            normalized_price DECIMAL(10,6) NOT NULL DEFAULT 0.000000,
            neighbors_json TEXT NULL,
            is_test_data TINYINT(1) NOT NULL DEFAULT 0,
            actual_label VARCHAR(30) NULL,
            is_correct TINYINT(1) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_knn_process (process_id),
            INDEX idx_knn_product (product_id),
            INDEX idx_knn_test_data (is_test_data)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS knn_norm_params (
            id INT AUTO_INCREMENT PRIMARY KEY,
            process_id INT NOT NULL,
            feature VARCHAR(30) NOT NULL,
            min_val DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
            max_val DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
            INDEX idx_norm_process (process_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // Pastikan kolom prediction_process_id ada pada tabel products
    $cols = $pdo->query("SHOW COLUMNS FROM products LIKE 'prediction_process_id'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE products ADD COLUMN prediction_process_id INT NULL");
    }

    // Pastikan password akun demo menggunakan plain text 'password'
    $pdo->exec("UPDATE users SET password = 'password' WHERE username IN ('admin', 'kaunit', 'pemohon') AND password LIKE '$2y$%'");
} catch (PDOException $e) {
    die('Koneksi database gagal. Pastikan MySQL aktif dan database fashion_knn sudah diimpor: ' . htmlspecialchars($e->getMessage()));
}
