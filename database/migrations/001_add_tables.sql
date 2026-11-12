-- ============================================================
-- Migration 001: Add KNN tables & indexes (safe, idempotent)
-- ============================================================

-- Table: knn_processes
CREATE TABLE IF NOT EXISTS knn_processes (
    id              INT          NOT NULL AUTO_INCREMENT,
    k_value         INT          NOT NULL,
    split_ratio     VARCHAR(10)  NOT NULL,
    random_seed     INT          NULL,
    train_count     INT          NOT NULL DEFAULT 0,
    test_count      INT          NOT NULL DEFAULT 0,
    accuracy        DECIMAL(8,4) NULL,
    precision_score DECIMAL(8,4) NULL,
    recall_score    DECIMAL(8,4) NULL,
    f1_score        DECIMAL(8,4) NULL,
    processed_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by      INT          NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: knn_predictions
CREATE TABLE IF NOT EXISTS knn_predictions (
    id                  INT           NOT NULL AUTO_INCREMENT,
    process_id          INT           NOT NULL,
    product_id          INT           NOT NULL,
    prediction          VARCHAR(30)   NULL,
    normalized_sold     DECIMAL(10,6) NULL,
    normalized_rating   DECIMAL(10,6) NULL,
    normalized_price    DECIMAL(10,6) NULL,
    neighbors_json      TEXT          NULL,
    is_test_data        TINYINT       NOT NULL DEFAULT 0,
    actual_label        VARCHAR(30)   NULL,
    is_correct          TINYINT       NULL,
    created_at          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_knnpred_process  FOREIGN KEY (process_id) REFERENCES knn_processes(id) ON DELETE CASCADE,
    CONSTRAINT fk_knnpred_product  FOREIGN KEY (product_id) REFERENCES products(id)      ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: knn_norm_params
CREATE TABLE IF NOT EXISTS knn_norm_params (
    id          INT           NOT NULL AUTO_INCREMENT,
    process_id  INT           NOT NULL,
    feature     VARCHAR(30)   NOT NULL,
    min_val     DECIMAL(14,4) NOT NULL,
    max_val     DECIMAL(14,4) NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_knnnorm_process FOREIGN KEY (process_id) REFERENCES knn_processes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Extend products table: prediction_process_id (MySQL 8+ supports IF NOT EXISTS on ADD COLUMN)
ALTER TABLE products
    ADD COLUMN IF NOT EXISTS prediction_process_id INT NULL;

-- Indexes (guard with IF NOT EXISTS equivalent: use CREATE INDEX IF NOT EXISTS – MySQL 8.0.21+)
CREATE INDEX IF NOT EXISTS idx_knn_pred_process  ON knn_predictions(process_id);
CREATE INDEX IF NOT EXISTS idx_knn_pred_product  ON knn_predictions(product_id);
CREATE INDEX IF NOT EXISTS idx_products_label      ON products(label);
CREATE INDEX IF NOT EXISTS idx_products_prediction ON products(prediction);
