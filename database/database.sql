CREATE DATABASE IF NOT EXISTS fashion_knn CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fashion_knn;

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

-- DATA AWAL PENGGUNA (PASSWORD SEMUA: password)
-- Hanya tabel users yang memiliki data awal, tabel lain dalam kondisi kosong
INSERT INTO users (name, username, password, role) VALUES
('Administrator', 'admin', 'password', 'admin'),
('Kepala Unit', 'kaunit', 'password', 'kaunit'),
('Pemohon', 'pemohon', 'password', 'pemohon')
ON DUPLICATE KEY UPDATE password = VALUES(password);
