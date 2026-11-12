# Deployment Checklist — FashionTrendKNN

Centang setiap item sebelum mempublikasikan website ke production.

---

## A. Persiapan Lokal (Development)

### Kode & Konfigurasi
- [ ] Semua file PHP lolos PHP lint tanpa error syntax
- [ ] File `.env` **tidak** ada di dalam repository Git
- [ ] File `.env.example` ada dan tidak berisi password asli
- [ ] File `.gitignore` sudah mencakup: `.env`, `storage/uploads/*`, `*.log`
- [ ] `app/config/database.php` membaca kredensial dari `.env` (bukan hardcoded)
- [ ] Semua query database menggunakan PDO prepared statements
- [ ] Semua output HTML menggunakan fungsi `h()` untuk escaping XSS
- [ ] CSRF protection aktif di semua form POST
- [ ] Password disimpan dengan bcrypt (bukan plain text)
- [ ] File `migrate.php` sudah diuji di lokal dan berjalan tanpa error

### Keamanan
- [ ] Tidak ada kredensial production di dalam kode
- [ ] Tidak ada password atau token di commit history
- [ ] Pesan error database tidak ditampilkan ke pengunjung (`APP_DEBUG=false` untuk production)
- [ ] Folder `app/`, `database/`, `storage/` memiliki `.htaccess` proteksi
- [ ] Folder `storage/uploads/` tidak bisa mengeksekusi PHP

### GitHub
- [ ] Repository sudah dibuat di GitHub (private direkomendasikan)
- [ ] Branch utama bernama `main`
- [ ] Semua kode sudah di-push ke `main`
- [ ] Workflow `.github/workflows/test.yml` ada
- [ ] Workflow `.github/workflows/deploy.yml` ada
- [ ] GitHub Actions berjalan dan berhasil di tab Actions

---

## B. Persiapan Hosting ArenHost

### Akun & Akses
- [ ] Akun hosting ArenHost aktif
- [ ] Login cPanel berhasil
- [ ] SSH Access tersedia dan aktif
- [ ] Bisa login via SSH: `ssh USERNAME@server.arenhost.com`

### PHP & Ekstensi
- [ ] PHP versi 8.1 atau lebih baru dipilih di PHP Selector
- [ ] Ekstensi `pdo` aktif
- [ ] Ekstensi `pdo_mysql` aktif
- [ ] Ekstensi `mbstring` aktif
- [ ] Ekstensi `json` aktif
- [ ] Ekstensi `fileinfo` aktif
- [ ] Git terinstall: `git --version` menampilkan versi

### Database
- [ ] Database MySQL dibuat: `username_fashionknn`
- [ ] User database dibuat: `username_fknnuser`
- [ ] User memiliki semua privilege pada database tersebut
- [ ] Password database kuat (minimal 16 karakter)
- [ ] Schema database sudah diimport: `mysql -u USER -p DB < database/database.sql`
- [ ] Migration dijalankan: `php migrate.php` berhasil
- [ ] Verifikasi tabel ada: `users`, `products`, `knn_processes`, `knn_predictions`, `knn_norm_params`, `schema_migrations`

---

## C. Konfigurasi Production

### File .env di Server
- [ ] File `.env` dibuat dari `.env.example`
- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_URL` sesuai dengan domain aktual
- [ ] `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` terisi benar
- [ ] `SESSION_SECURE=true` (setelah SSL aktif)
- [ ] Permission file `.env` adalah `600` (`chmod 600 .env`)

### Folder & Permission
- [ ] Folder `storage/logs/` ada dan writable (`chmod 755`)
- [ ] Folder `storage/uploads/` ada dan writable (`chmod 755`)
- [ ] File `.env` tidak bisa diakses via browser (dilindungi `.htaccess`)

---

## D. Domain & SSL

### Domain
- [ ] Domain sudah diarahkan ke server ArenHost
- [ ] Propagasi DNS selesai (bisa cek di https://dnschecker.org)
- [ ] Website bisa diakses via HTTP: `http://domainkamu.com`

### SSL
- [ ] Sertifikat SSL sudah diterbitkan (Let's Encrypt via cPanel)
- [ ] Website bisa diakses via HTTPS: `https://domainkamu.com`
- [ ] Tidak ada mixed content warning di browser (semua aset via HTTPS)
- [ ] Redirect HTTP → HTTPS aktif di `public/.htaccess` (uncomment 3 baris)
- [ ] HSTS header aktif di `public/.htaccess` (uncomment 1 baris)

---

## E. Document Root

### Konfigurasi Web
- [ ] Document root diarahkan ke `public/` (bukan ke root project)
- [ ] Halaman login dapat diakses: `https://domainkamu.com` atau `https://domainkamu.com/fashiontrendknn/public`
- [ ] Folder `app/` **tidak** bisa diakses via browser (harus dapat 403)
- [ ] Folder `database/` **tidak** bisa diakses via browser (harus dapat 403)
- [ ] Folder `storage/` **tidak** bisa diakses via browser (harus dapat 403)
- [ ] File `.env` **tidak** bisa diunduh via browser

---

## F. CI/CD GitHub Actions

### SSH Deploy Key (Server → GitHub)
- [ ] SSH key pair dibuat di server: `~/.ssh/github_deploy_key`
- [ ] Public key ditambahkan sebagai Deploy Key di GitHub repository
- [ ] `~/.ssh/config` dikonfigurasi untuk menggunakan key tersebut
- [ ] `ssh -T git@github.com` berhasil dari server

### GitHub Secrets (GitHub Actions → Server)
- [ ] Secret `HOST` ditambahkan
- [ ] Secret `USERNAME` ditambahkan
- [ ] Secret `SSH_PRIVATE_KEY` ditambahkan (private key untuk GitHub Actions SSH ke server)
- [ ] Secret `PORT` ditambahkan
- [ ] Public key dari `SSH_PRIVATE_KEY` sudah ada di `~/.ssh/authorized_keys` di server

### Verifikasi Workflow
- [ ] Push ke `main` memicu workflow `test.yml` dan berhasil
- [ ] Push ke `main` memicu workflow `deploy.yml` dan berhasil
- [ ] Log deploy menunjukkan `git pull --ff-only` berhasil
- [ ] Website terupdate setelah deploy

---

## G. Pengujian Fungsional

### Autentikasi
- [ ] Login dengan username dan password yang benar berhasil
- [ ] Login dengan password salah ditolak (pesan generik)
- [ ] Logout berfungsi dan session terhapus
- [ ] Akses halaman setelah logout diredirect ke login
- [ ] Session timeout bekerja (cek setelah 2 jam inaktivitas)

### Role Access Control
- [ ] Admin dapat mengakses semua halaman
- [ ] Kaunit tidak bisa mengakses Dataset CSV (`/dataset.php`)
- [ ] Kaunit tidak bisa mengakses Proses KNN (`/process.php`)
- [ ] Pemohon tidak bisa mengakses Training, Evaluation, History
- [ ] Akses paksa URL terlarang menghasilkan 403

### Fitur Utama
- [ ] Dashboard menampilkan statistik dengan benar
- [ ] CRUD produk berfungsi (tambah, edit, hapus)
- [ ] Import CSV berhasil dengan file valid
- [ ] Import CSV menolak file bukan CSV
- [ ] Import CSV menolak file lebih dari 5 MB
- [ ] Data Training dapat dilihat dan label dapat diubah
- [ ] Halaman Preprocessing menampilkan dokumentasi normalisasi
- [ ] Proses KNN berhasil dijalankan
- [ ] Hasil prediksi tampil di halaman Results
- [ ] Evaluasi / Confusion Matrix tampil dengan benar
- [ ] Visualisasi chart tampil dengan benar
- [ ] Riwayat klasifikasi tampil di History
- [ ] Detail produk dengan voting KNN tampil di Product Detail

### Keamanan
- [ ] Semua form POST memiliki CSRF token (cek view-source halaman)
- [ ] Mencoba submit form tanpa CSRF token menghasilkan 403
- [ ] Directory listing dinonaktifkan (`Options -Indexes` aktif)
- [ ] Mengakses `https://domainkamu.com/app/` menghasilkan 403
- [ ] Mengakses `https://domainkamu.com/database/` menghasilkan 403
- [ ] Mencoba XSS di field pencarian tidak dieksekusi

---

## H. Post-Launch

### Setelah Go Live
- [ ] Password default akun demo sudah diganti
- [ ] Akun demo yang tidak diperlukan sudah dihapus
- [ ] Backup database pertama sudah dilakukan
- [ ] Jadwal backup otomatis sudah dikonfigurasi (Cron Job)
- [ ] Log error dipantau: `storage/logs/php_errors.log`
- [ ] Alert atau monitoring uptime dikonfigurasi (opsional)

---

**Deployment dinyatakan SIAP jika semua item di atas sudah dicentang.**

Tanggal pengecekan: ____________________  
Diperiksa oleh: ____________________
