-- ============================================================
-- Migration 000: Buat tabel schema_migrations
-- Jalankan ini PERTAMA KALI sebelum migration lainnya.
-- File ini aman dijalankan berulang (idempotent).
-- ============================================================

CREATE TABLE IF NOT EXISTS schema_migrations (
    id          INT          NOT NULL AUTO_INCREMENT,
    version     VARCHAR(100) NOT NULL,
    description VARCHAR(255) NOT NULL DEFAULT '',
    applied_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_version (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
