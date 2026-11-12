# Panduan Deployment — FashionTrendKNN

**Versi:** 1.0  
**Target Hosting:** ArenHost Shared Hosting  
**Stack:** PHP 8.1+, MySQL 8.0+, Apache, Git

---

## Daftar Isi

1. [Persiapan Hosting ArenHost](#1-persiapan-hosting-arenhost)
2. [Persiapan Domain](#2-persiapan-domain)
3. [Aktivasi SSL](#3-aktivasi-ssl)
4. [Pembuatan Database MySQL](#4-pembuatan-database-mysql)
5. [Konfigurasi File .env](#5-konfigurasi-file-env)
6. [Pengaturan Document Root](#6-pengaturan-document-root)
7. [Upload dan Clone Repository](#7-upload-dan-clone-repository)
8. [Konfigurasi SSH Deploy Key](#8-konfigurasi-ssh-deploy-key)
9. [Konfigurasi GitHub Secrets](#9-konfigurasi-github-secrets)
10. [Pengaturan GitHub Actions](#10-pengaturan-github-actions)
11. [Deployment Pertama](#11-deployment-pertama)
12. [Deployment Update Berikutnya](#12-deployment-update-berikutnya)
13. [Database Migration](#13-database-migration)
14. [Backup Database](#14-backup-database)
15. [Restore Database](#15-restore-database)
16. [Panduan Git](#16-panduan-git)
17. [Troubleshooting](#17-troubleshooting)

---

## 1. Persiapan Hosting ArenHost

### Informasi yang Diperlukan dari ArenHost

Setelah membeli hosting, siapkan informasi berikut dari control panel (cPanel/Plesk):

| Informasi         | Contoh                           | Keterangan                     |
|-------------------|----------------------------------|--------------------------------|
| IP Server / Host  | `server123.arenhost.com`         | Dari welcome email ArenHost    |
| SSH Port          | `22` (atau port custom)          | Cek di panel SSH Access        |
| SSH Username      | `username_cpanel`                | Username cPanel Anda           |
| Home Directory    | `/home/username_cpanel`          | Path root akun hosting         |
| Document Root     | `/home/username_cpanel/public_html` | Default webroot              |
| MySQL Host        | `localhost` atau `127.0.0.1`     | Biasanya localhost di shared   |

### Pastikan Fitur Tersedia

- [ ] PHP 8.1 atau lebih baru (cek di PHP Selector di cPanel)
- [ ] MySQL / MariaDB tersedia
- [ ] SSH Access aktif
- [ ] Git terinstall di server (`git --version`)
- [ ] Mod_rewrite Apache aktif

### Cara Cek Versi PHP di ArenHost

1. Login ke cPanel
2. Cari menu **Select PHP Version** atau **PHP Selector**
3. Pilih PHP 8.1 atau 8.2
4. Aktifkan ekstensi: `pdo`, `pdo_mysql`, `mbstring`, `json`, `fileinfo`

---

## 2. Persiapan Domain

### Jika Domain Baru di ArenHost

1. Login cPanel → **Addon Domains** atau **Domains**
2. Tambahkan domain: `domainkamu.com`
3. Tentukan document root (akan dikonfigurasi di langkah 6)
4. Tunggu propagasi DNS (bisa 1–24 jam)

### Jika Domain dari Registrar Lain

1. Dapatkan nameserver ArenHost dari welcome email (contoh: `ns1.arenhost.com`)
2. Update nameserver di registrar domain Anda
3. Tunggu propagasi DNS

---

## 3. Aktivasi SSL

### Via cPanel (Let's Encrypt gratis)

1. Login cPanel → **SSL/TLS** → **Let's Encrypt SSL**
2. Pilih domain → klik **Issue**
3. Tunggu beberapa menit hingga sertifikat aktif
4. Verifikasi: buka `https://domainkamu.com` di browser

### Aktifkan HTTPS Redirect di .htaccess

Setelah SSL aktif, uncomment 3 baris di `public/.htaccess`:

```apache
# Sebelum (komentar):
# RewriteCond %{HTTPS} off
# RewriteCond %{HTTP_HOST} !^localhost [NC]
# RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Sesudah (aktifkan):
RewriteCond %{HTTPS} off
RewriteCond %{HTTP_HOST} !^localhost [NC]
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

Juga uncomment HSTS header:
```apache
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
```

Dan update `.env` di server:
```
SESSION_SECURE=true
APP_URL=https://domainkamu.com
```

---

## 4. Pembuatan Database MySQL

### Via cPanel

1. Login cPanel → **MySQL Databases**
2. **Create New Database** → Nama: `username_fashionknn`
   - Nama database di shared hosting biasanya prefixed: `username_namadb`
3. **Create New User** → 
   - Username: `username_fknnuser`
   - Password: **buat password kuat** (minimal 16 karakter, huruf besar, kecil, angka, simbol)
4. **Add User to Database** → pilih user dan database → **All Privileges**
5. Catat informasi:
   - DB Host: `localhost`
   - DB Name: `username_fashionknn`
   - DB User: `username_fknnuser`
   - DB Pass: `[password yang dibuat]`

> **PENTING:** Jangan gunakan root MySQL di production. Buat user terpisah dengan hak akses terbatas.

---

## 5. Konfigurasi File .env

### Di Server Production (via SSH)

```bash
# Masuk ke direktori project
cd /home/USERNAME/fashiontrendknn

# Salin .env.example menjadi .env
cp .env.example .env

# Edit .env dengan kredensial production
nano .env
```

### Isi .env Production

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domainkamu.com
APP_NAME=Fashion KNN

DB_HOST=localhost
DB_PORT=3306
DB_NAME=username_fashionknn
DB_USER=username_fknnuser
DB_PASS=PasswordKuatAnda123!

SESSION_LIFETIME=7200
SESSION_SECURE=true
SESSION_SAMESITE=Lax

UPLOAD_MAX_SIZE_MB=5
APP_TIMEZONE=Asia/Jakarta
```

### Proteksi File .env

```bash
# Batasi permission: hanya owner yang bisa baca
chmod 600 /home/USERNAME/fashiontrendknn/.env
```

---

## 6. Pengaturan Document Root

### Opsi A: Document Root Langsung ke public/ (IDEAL)

Jika ArenHost memungkinkan custom document root:

1. Login cPanel → **Domains** atau **Addon Domains**
2. Edit domain → ubah document root ke:
   ```
   /home/USERNAME/fashiontrendknn/public
   ```

Jika menggunakan `.htaccess` di cPanel:
```apache
# Di /home/USERNAME/.htaccess atau via cPanel Redirects
# (Ini hanya jika domain root menunjuk ke home directory)
```

### Opsi B: Clone ke public_html/fashiontrendknn (Fallback)

Jika ArenHost tidak mendukung custom document root:

```bash
# Clone ke subdirektori public_html
cd /home/USERNAME/public_html
git clone https://github.com/USERNAME/fashiontrendknn.git fashiontrendknn

# URL akses: https://domainkamu.com/fashiontrendknn/public/
```

Update `.env`:
```dotenv
APP_URL=https://domainkamu.com/fashiontrendknn/public
```

Update `app/config/database.php` sudah otomatis membaca `APP_URL` dari `.env`.

### Opsi C: Symlink public/ ke public_html (Jika SSH tersedia)

```bash
# Pastikan fashiontrendknn ada di home directory
cd /home/USERNAME
git clone git@github.com:USERNAME/fashiontrendknn.git

# Hapus atau backup public_html lama
mv public_html public_html_backup

# Buat symlink
ln -s /home/USERNAME/fashiontrendknn/public /home/USERNAME/public_html
```

---

## 7. Upload dan Clone Repository

### Pastikan SSH Key untuk GitHub Sudah Disiapkan (lihat langkah 8)

```bash
# Login ke server via SSH
ssh USERNAME@server.arenhost.com -p 22

# Navigasi ke home directory
cd /home/USERNAME

# Clone repository menggunakan SSH
git clone git@github.com:USERNAME/fashiontrendknn.git

# Atau jika repository private via HTTPS + Personal Access Token
git clone https://USERNAME:TOKEN@github.com/USERNAME/fashiontrendknn.git
```

### Struktur di Server Setelah Clone

```
/home/USERNAME/
└── fashiontrendknn/
    ├── .git/
    ├── app/
    ├── database/
    ├── public/          ← document root
    ├── storage/
    ├── .env             ← BUAT MANUAL, tidak ada di git
    ├── .env.example
    ├── migrate.php
    └── ...
```

---

## 8. Konfigurasi SSH Deploy Key

Agar GitHub Actions dapat melakukan `git pull` di server, server perlu bisa mengakses GitHub repository.

### Langkah 1: Generate SSH Key di Server

```bash
# Login ke server via SSH
ssh USERNAME@server.arenhost.com

# Generate key pair khusus untuk deploy
ssh-keygen -t ed25519 -C "deploy@fashiontrendknn" -f ~/.ssh/github_deploy_key

# Tekan Enter untuk passphrase kosong (diperlukan untuk automated deploy)
```

### Langkah 2: Tambahkan Deploy Key ke GitHub

```bash
# Tampilkan public key
cat ~/.ssh/github_deploy_key.pub
```

1. Buka repository GitHub → **Settings** → **Deploy keys**
2. Klik **Add deploy key**
3. Title: `ArenHost Production Server`
4. Key: paste output `github_deploy_key.pub`
5. **Allow write access**: JANGAN centang (read-only cukup untuk pull)
6. Klik **Add key**

### Langkah 3: Konfigurasi SSH di Server

```bash
# Buat atau edit ~/.ssh/config
nano ~/.ssh/config
```

Tambahkan:
```
Host github.com
    HostName github.com
    User git
    IdentityFile ~/.ssh/github_deploy_key
    IdentitiesOnly yes
```

```bash
# Set permission
chmod 600 ~/.ssh/github_deploy_key
chmod 644 ~/.ssh/github_deploy_key.pub
chmod 600 ~/.ssh/config
```

### Langkah 4: Test Koneksi

```bash
ssh -T git@github.com
# Output: Hi USERNAME/fashiontrendknn! You've successfully authenticated...
```

---

## 9. Konfigurasi GitHub Secrets

GitHub Actions membutuhkan akses ke server untuk deploy.

### Langkah 1: Buat SSH Key untuk GitHub Actions

> Ini berbeda dari deploy key di langkah 8. Ini adalah key untuk GitHub Actions yang SSH ke SERVER.

```bash
# Di komputer lokal (atau server, generate terpisah)
ssh-keygen -t ed25519 -C "github-actions-deploy" -f ~/.ssh/actions_deploy_key
# Tekan Enter untuk passphrase kosong
```

### Langkah 2: Tambahkan Public Key ke Server

```bash
# Tambahkan public key ke authorized_keys di server
# (jalankan ini di server, atau paste manual)
cat ~/.ssh/actions_deploy_key.pub >> /home/USERNAME/.ssh/authorized_keys
chmod 600 /home/USERNAME/.ssh/authorized_keys
```

### Langkah 3: Tambahkan Secrets ke GitHub

1. Buka repository GitHub → **Settings** → **Secrets and variables** → **Actions**
2. Klik **New repository secret** untuk setiap item:

| Secret Name       | Nilai                                           |
|-------------------|-------------------------------------------------|
| `HOST`            | `server123.arenhost.com` (hostname/IP server)  |
| `USERNAME`        | `username_cpanel` (username SSH hosting)        |
| `SSH_PRIVATE_KEY` | Isi lengkap file `~/.ssh/actions_deploy_key`    |
| `PORT`            | `22` (atau port SSH custom hosting)             |

Untuk `SSH_PRIVATE_KEY`, tampilkan dengan:
```bash
cat ~/.ssh/actions_deploy_key
# Salin SELURUH output termasuk baris -----BEGIN... dan -----END...
```

### Langkah 4: Buat Environment Production di GitHub (Opsional, Disarankan)

1. GitHub repository → **Settings** → **Environments**
2. Klik **New environment** → nama: `production`
3. Tambahkan **Required reviewers** jika perlu approval sebelum deploy
4. Simpan

---

## 10. Pengaturan GitHub Actions

Workflow sudah tersedia di `.github/workflows/`:

- `test.yml` — Berjalan saat push ke `main` atau `develop`, dan saat PR ke `main`
- `deploy.yml` — Berjalan saat push ke `main`, memerlukan `test.yml` berhasil

### Verifikasi Workflow Aktif

1. Push ke branch `main`
2. Buka tab **Actions** di GitHub repository
3. Pastikan workflow `PHP Tests & Validation` berjalan
4. Pastikan workflow `Deploy to ArenHost` berjalan setelah test berhasil

### Manual Trigger Deploy

1. GitHub → **Actions** → **Deploy to ArenHost**
2. Klik **Run workflow** → pilih branch `main` → **Run workflow**

---

## 11. Deployment Pertama

### Urutan Langkah Deployment Pertama

```bash
# =============================================
# LANGKAH 1: Login ke server via SSH
# =============================================
ssh USERNAME@server.arenhost.com -p 22

# =============================================
# LANGKAH 2: Clone repository
# =============================================
cd /home/USERNAME
git clone git@github.com:USERNAME/fashiontrendknn.git
cd fashiontrendknn

# =============================================
# LANGKAH 3: Buat dan konfigurasi .env
# =============================================
cp .env.example .env
nano .env
# Isi dengan konfigurasi production (lihat langkah 5)
chmod 600 .env

# =============================================
# LANGKAH 4: Siapkan folder storage
# =============================================
mkdir -p storage/logs
mkdir -p storage/uploads
chmod 755 storage/logs
chmod 755 storage/uploads

# =============================================
# LANGKAH 5: Import database schema
# =============================================
mysql -u username_fknnuser -p username_fashionknn < database/database.sql
# Masukkan password database saat diminta

# =============================================
# LANGKAH 6: Jalankan migration
# =============================================
php migrate.php
# Pastikan semua migration berhasil

# =============================================
# LANGKAH 7: Verifikasi konfigurasi
# =============================================
php -r "require 'app/config/database.php'; echo 'DB: OK' . PHP_EOL;"

# =============================================
# LANGKAH 8: Test akses web
# =============================================
# Buka browser: https://domainkamu.com
# Coba login dengan akun yang sudah dibuat
```

### Ganti Password Default Setelah Deploy Pertama

Segera ganti password akun demo setelah login pertama:

1. Login sebagai `admin` (password: `password`)
2. Navigasi ke **Manajemen Pengguna**
3. Hapus akun demo `kaunit` dan `pemohon` jika tidak diperlukan
4. Atau: update password via MySQL langsung:

```bash
# Di server, generate hash bcrypt baru
php -r "echo password_hash('PasswordBaruAnda', PASSWORD_BCRYPT, ['cost'=>12]) . PHP_EOL;"

# Update di database
mysql -u username_fknnuser -p username_fashionknn
```
```sql
UPDATE users SET password = '[hash_baru]' WHERE username = 'admin';
```

---

## 12. Deployment Update Berikutnya

### Via GitHub Actions (Otomatis)

1. Lakukan commit dan push ke branch `main`
2. GitHub Actions akan otomatis:
   - Menjalankan PHP lint dan validasi struktur
   - Jika berhasil, deploy ke server via SSH `git pull --ff-only`

### Via SSH Manual

```bash
ssh USERNAME@server.arenhost.com -p 22
cd /home/USERNAME/fashiontrendknn

# Pull update terbaru
git pull --ff-only origin main

# Jika ada migration baru
php migrate.php
```

### Workflow Development yang Disarankan

```bash
# Di komputer lokal:

# 1. Buat branch fitur baru
git checkout -b feature/nama-fitur

# 2. Kerjakan perubahan
# ... edit file ...

# 3. Commit
git add app/services/NamaService.php public/halaman-baru.php
git commit -m "feat: tambah fitur nama fitur"

# 4. Push branch
git push -u origin feature/nama-fitur

# 5. Buat Pull Request ke main di GitHub
# 6. Review, approve, dan merge
# 7. GitHub Actions otomatis deploy ke production
```

---

## 13. Database Migration

### Prinsip Migration yang Aman

- Setiap migration **hanya dijalankan sekali** (dicatat di `schema_migrations`)
- **Selalu backup sebelum migration** (lihat langkah 14)
- Migration yang destruktif (DROP TABLE, ALTER COLUMN) harus di-review manual
- Gunakan `migrate.php`, **bukan** jalankan SQL langsung di phpMyAdmin tanpa review

### Jalankan Migration

```bash
# Via CLI di server
cd /home/USERNAME/fashiontrendknn
php migrate.php

# Output normal:
# [OK] Koneksi database berhasil: ...
# [OK] Tabel schema_migrations siap.
# [SKIP] 001_add_knn_tables: sudah dijalankan
# [SKIP] 002_hash_passwords: sudah dijalankan
# [DONE] Migration Selesai
```

### Cek Migration yang Sudah Dijalankan

```sql
SELECT version, description, applied_at
FROM schema_migrations
ORDER BY applied_at;
```

### Tambah Migration Baru

Edit `migrate.php`, tambahkan entry baru ke array `$migrations`:

```php
$migrations[] = [
    'version'     => '004_nama_migration',
    'description' => 'Deskripsi singkat perubahan',
    'run'         => function (PDO $pdo): void {
        // SQL migration yang aman
        $pdo->exec("ALTER TABLE products ADD COLUMN sku VARCHAR(50) NULL");
    }
];
```

### Rollback Manual

Migration tidak memiliki rollback otomatis (untuk keamanan). Jika perlu rollback:

```sql
-- 1. Hapus catatan migration dari tracking table
DELETE FROM schema_migrations WHERE version = '004_nama_migration';

-- 2. Jalankan SQL rollback secara manual:
ALTER TABLE products DROP COLUMN sku;
```

---

## 14. Backup Database

### Backup Manual via SSH

```bash
# Format: mysqldump -u USER -p DBNAME > FILENAME.sql
mysqldump \
  -u username_fknnuser \
  -p \
  --single-transaction \
  --routines \
  --triggers \
  username_fashionknn \
  > /home/USERNAME/backups/fashion_knn_$(date +%Y%m%d_%H%M%S).sql

# Masukkan password saat diminta
```

### Backup via cPanel

1. Login cPanel → **Backup** atau **phpMyAdmin**
2. phpMyAdmin → pilih database → **Export** → **Go**
3. Simpan file SQL yang diunduh

### Jadwal Backup Otomatis via Cron Job

Di cPanel → **Cron Jobs**, tambahkan:

```
# Backup harian pukul 02:00
0 2 * * * mysqldump -u username_fknnuser -pPASSWORD username_fashionknn > /home/USERNAME/backups/fashion_knn_$(date +\%Y\%m\%d).sql
```

Pastikan folder `/home/USERNAME/backups/` sudah ada:
```bash
mkdir -p /home/USERNAME/backups
chmod 700 /home/USERNAME/backups
```

---

## 15. Restore Database

### Dari File SQL Backup

```bash
# PERINGATAN: Ini akan menimpa data yang ada!
# Pastikan backup sudah disiapkan

mysql \
  -u username_fknnuser \
  -p \
  username_fashionknn \
  < /home/USERNAME/backups/fashion_knn_20261112_020000.sql

# Masukkan password saat diminta
```

### Via phpMyAdmin

1. Login cPanel → phpMyAdmin
2. Pilih database `username_fashionknn`
3. Tab **Import** → pilih file SQL
4. Klik **Go**

---

## 16. Panduan Git

### Setup Awal Repository GitHub

```bash
# Di komputer lokal
cd c:\laragon\www\fashiontrendknn

# 1. Inisialisasi git (jika belum)
git init
git branch -M main

# 2. Tambahkan remote origin
git remote add origin https://github.com/USERNAME/fashiontrendknn.git

# 3. Commit awal
git add .
git commit -m "feat: initial production-ready setup"

# 4. Push ke GitHub
git push -u origin main
```

### Workflow Harian

```bash
# Cek status
git status

# Tambahkan file spesifik (hindari git add -A)
git add app/services/KNNService.php
git add public/process.php

# Commit dengan pesan deskriptif
git commit -m "fix: perbaiki tie-breaking pada KNN dengan 1 tetangga"

# Push ke branch develop dulu, bukan langsung main
git push origin develop

# Buat Pull Request di GitHub, review, lalu merge ke main
```

### Branching Strategy

```
main       ← production, hanya merge dari develop atau hotfix
develop    ← staging/testing, merge dari feature branches
feature/*  ← pengembangan fitur baru
hotfix/*   ← perbaikan bug kritis langsung ke main
```

```bash
# Contoh workflow feature
git checkout -b feature/tambah-export-pdf develop
# ... kerjakan ...
git push -u origin feature/tambah-export-pdf
# Buat PR ke develop di GitHub
```

---

## 17. Troubleshooting

### Error: "Koneksi database gagal"

**Penyebab:** Credential DB salah atau MySQL tidak aktif.

```bash
# Cek file .env sudah benar
cat /home/USERNAME/fashiontrendknn/.env

# Test koneksi manual
mysql -h localhost -u username_fknnuser -p username_fashionknn
```

### Error: "500 Internal Server Error"

**Penyebab:** PHP error tersembunyi di production.

```bash
# Lihat log error PHP
tail -f /home/USERNAME/fashiontrendknn/storage/logs/php_errors.log

# Atau lihat error log Apache
tail -f ~/logs/error_log
```

### Error: ".env tidak ditemukan"

```bash
cd /home/USERNAME/fashiontrendknn
cp .env.example .env
nano .env  # Isi kredensial production
chmod 600 .env
```

### Error: "git pull gagal — not fast-forward"

**Penyebab:** Ada perubahan lokal di server yang konflik dengan remote.

```bash
cd /home/USERNAME/fashiontrendknn

# Lihat status
git status
git log --oneline -5

# JANGAN gunakan --force. Reset hanya jika yakin tidak ada perubahan penting:
git stash
git pull --ff-only origin main
```

### Error: "Permission denied (storage/logs)"

```bash
chmod 755 /home/USERNAME/fashiontrendknn/storage/logs
chmod 755 /home/USERNAME/fashiontrendknn/storage/uploads
```

### Error: "Session tidak bekerja" atau Redirect Loop Login

**Penyebab:** `session_name` sudah diset tapi cookie belum dihapus.

1. Hapus cookie `fknn_sess` di browser
2. Buka halaman login kembali

### Error: "CSRF token tidak valid"

**Penyebab:** Session expired atau tab browser terlalu lama terbuka.

1. Refresh halaman
2. Jika terus terjadi, cek `SESSION_LIFETIME` di `.env`

### Error: GitHub Actions "Host key verification failed"

```bash
# Di server, tambahkan GitHub ke known_hosts
ssh-keyscan github.com >> ~/.ssh/known_hosts
```

### Upload CSV Gagal di Hosting

**Penyebab:** Batas upload PHP lebih kecil dari ukuran file.

Cek batas di `.htaccess` atau hubungi ArenHost untuk naikkan:
- `upload_max_filesize`
- `post_max_size`

Atau kompres dataset CSV sebelum upload (reduksi kolom yang tidak perlu).

### Website Dapat Diakses tapi Aset CSS/JS Tidak Muncul

**Penyebab:** `BASE_URL` di `.env` tidak sesuai dengan URL aktual.

```dotenv
# Jika akses via subdirectory:
APP_URL=https://domainkamu.com/fashiontrendknn/public

# Jika akses via root domain:
APP_URL=https://domainkamu.com
```

---

## Kontak & Referensi

- Dokumentasi ArenHost: Hubungi support ArenHost untuk panduan spesifik hosting
- GitHub Actions SSH: https://github.com/appleboy/ssh-action
- PHP Password Hashing: https://www.php.net/manual/en/function.password-hash.php
- Let's Encrypt SSL: https://letsencrypt.org/
