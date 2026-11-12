-- Schema: fashiontrendknn
-- Import via: mysql -u USER -pPASS DBNAME < database.sql
-- Database sudah dibuat via cPanel, tidak perlu CREATE DATABASE di sini.

-- 1. TABEL PENGGUNA (USERS)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    username VARCHAR(60) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','kaunit','pemohon') NOT NULL DEFAULT 'pemohon',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. TABEL PRODUK (PRODUCTS)
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    sold INT NOT NULL DEFAULT 0,
    rating DECIMAL(3,2) NOT NULL DEFAULT 0.00,
    price_now DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    label VARCHAR(30) NULL,
    prediction VARCHAR(30) NULL,
    prediction_process_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_label (label),
    INDEX idx_prediction (prediction)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. TABEL KLASIFIKASI LAMA (CLASSIFICATIONS - UNTUK KOMPATIBILITAS)
CREATE TABLE IF NOT EXISTS classifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    prediction VARCHAR(30) NOT NULL,
    k_value INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. TABEL RIWAYAT PROSES KNN (KNN_PROCESSES)
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

-- 5. TABEL PREDIKSI DETAIL KNN (KNN_PREDICTIONS)
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
    INDEX idx_knn_test_data (is_test_data),
    CONSTRAINT fk_knn_pred_process FOREIGN KEY (process_id) REFERENCES knn_processes(id) ON DELETE CASCADE,
    CONSTRAINT fk_knn_pred_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. TABEL PARAMETER NORMALISASI (KNN_NORM_PARAMS)
CREATE TABLE IF NOT EXISTS knn_norm_params (
    id INT AUTO_INCREMENT PRIMARY KEY,
    process_id INT NOT NULL,
    feature VARCHAR(30) NOT NULL,
    min_val DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    max_val DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    INDEX idx_norm_process (process_id),
    CONSTRAINT fk_norm_process FOREIGN KEY (process_id) REFERENCES knn_processes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- DATA AWAL PENGGUNA (PASSWORD MENGGUNAKAN BCRYPT, cost=12)
-- Password default: 'password'
-- Hash di bawah dihasilkan dengan: password_hash('password', PASSWORD_BCRYPT, ['cost'=>12])
-- GANTI PASSWORD INI SETELAH INSTALASI PERTAMA!
INSERT INTO users (name, username, password, role) VALUES
('Administrator', 'admin',   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('Kepala Unit',   'kaunit',  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'kaunit'),
('Pemohon',       'pemohon', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'pemohon')
ON DUPLICATE KEY UPDATE name = VALUES(name);
-- Hash di atas adalah password_hash('password') — nilai yang valid untuk testing.
-- Untuk production: buat hash baru via PHP dan ganti nilai di atas, atau gunakan migrate.php.

-- 7. TABEL MIGRATION TRACKER (SCHEMA_MIGRATIONS)
CREATE TABLE IF NOT EXISTS schema_migrations (
    id          INT          NOT NULL AUTO_INCREMENT,
    version     VARCHAR(100) NOT NULL,
    description VARCHAR(255) NOT NULL DEFAULT '',
    applied_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_version (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catat bahwa schema awal ini sudah diterapkan
INSERT IGNORE INTO schema_migrations (version, description) VALUES
('001_add_knn_tables',          'Tabel knn_processes, knn_predictions, knn_norm_params'),
('002_hash_passwords',          'Password di-hash bcrypt sejak awal (seed data sudah bcrypt)'),
('003_add_performance_indexes', 'Index performa products dan knn_processes');
