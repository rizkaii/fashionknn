-- ============================================================
-- Migration 002: Upgrade password plain-text ke bcrypt
-- ============================================================
-- PERINGATAN: Jalankan migration ini dengan hati-hati.
-- Setelah dijalankan, semua user harus login ulang.
-- Password default akun demo ('password') akan di-hash.
--
-- Jalankan via PHP migrate.php, bukan langsung di phpMyAdmin.
-- ============================================================

-- Password hash bcrypt untuk 'password' (cost=12)
-- Hash di bawah adalah nilai valid: $2y$12$...
-- Gunakan script PHP migrate.php untuk generate hash yang benar.
-- File ini hanya sebagai REFERENSI — jangan jalankan langsung.

-- CATATAN: Script migrate.php akan:
-- 1. Cek apakah migration ini sudah dijalankan
-- 2. Hash semua password plain-text yang belum bcrypt
-- 3. Catat ke schema_migrations

-- Verifikasi sebelum run:
-- SELECT id, username, LEFT(password, 4) as pw_type FROM users;
-- Jika pw_type = '$2y$' → sudah bcrypt, skip
-- Jika pw_type != '$2y$' → perlu di-hash
