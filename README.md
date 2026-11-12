# Sistem Prediksi Tren Produk Fashion Menggunakan Algoritma K-Nearest Neighbor (KNN)

Aplikasi analitik berbasis web untuk memprediksi dan mengklasifikasikan tren produk fashion e-commerce (data penjualan Tokopedia) ke dalam tiga kategori tren: **Tren**, **Kurang Tren**, dan **Tidak Tren** menggunakan algoritma *Supervised Learning* **K-Nearest Neighbor (KNN)** dengan normalisasi *Min-Max Feature Scaling* dan pembagian data *Stratified Holdout*.

Dikembangkan menggunakan arsitektur modular **PHP Native** dan **MySQL**, dirancang secara profesional untuk keperluan penelitian skripsi, tugas akhir, dan demonstrasi akademik.

---

## 1. Fitur Utama Sistem

1. **Autentikasi & Hak Akses Berbasis Peran (RBAC)**:
   - **Admin**: Akses menyeluruh ke seluruh modul (CRUD data produk, import CSV, kelola label data latih, preprocessing, eksekusi KNN, evaluasi, visualisasi, riwayat, dan manajemen pengguna).
   - **Kaunit (Kepala Unit)**: Akses monitoring analitik (Dashboard, Data Produk, Data Training, Hasil Prediksi, Evaluasi Model, Visualisasi, dan Riwayat Proses).
   - **Pemohon**: Akses operasional (Dashboard, Data Produk, Hasil Prediksi, dan Visualisasi Tren).
2. **Dashboard Statistik Real-Time**:
   - Total produk, volume data training, total terprediksi, dan hitungan masing-masing kategori tren.
   - Grafik interaktif *Doughnut Chart* distribusi kategori tren.
   - Ringkasan performa model KNN terakhir (Akurasi, Nilai K, Rasio Split, Tanggal Proses).
   - Tabel 8 produk terbaru dan 5 aktivitas eksekusi proses KNN terakhir.
3. **Manajemen Data Produk**:
   - Operasi CRUD (Create, Read, Update, Delete) dengan validasi data di sisi server.
   - Pencarian berdasarkan nama produk, filter kategori label, sorting multi-kolom, dan paginasi.
   - Pemisahan tegas antara **Label Aktual (Ground Truth)** dan **Hasil Prediksi Model**.
4. **Import Dataset CSV Terverifikasi**:
   - Upload berkas CSV dengan verifikasi format MIME dan ukuran berkas.
   - Fitur **Download Template CSV**.
   - **Pratinjau (Preview) Sebelum Import**: Memeriksa kelengkapan kolom, tipe data numerik, batas nilai rating (0–5), angka non-negatif, keabsahan label, dan deteksi data duplikat terhadap basis data.
   - Penyimpanan data menggunakan **Database Transaction** (`BEGIN`, `COMMIT`, `ROLLBACK`).
5. **Manajemen Data Training (Ground Truth)**:
   - Pengelompokan khusus data berlabel yang menjadi acuan klasifikasi KNN.
   - Visualisasi persentase distribusi kelas dan deteksi *class imbalance*.
   - Admin dapat memperbarui label aktual secara langsung.
6. **Dokumentasi & Visualisasi Preprocessing**:
   - **Seleksi Fitur**: Penggunaan 3 atribut numerik kontinu: *Sold*, *Rating*, dan *Price Now*.
   - **Pemeriksaan Kualitas Data**: Pengecekan *missing values*, data duplikat, dan status kelayakan data.
   - **Normalisasi Min-Max**: Penjelasan matematis rumus, batas minimum-maksimum fitur, dan tabel perbandingan nilai sebelum vs sesudah normalisasi [0, 1].
   - **Simulasi Pembagian Dataset**: Rasio 70:30 dan 80:20 dengan *Stratified Random Split*.
7. **Mesin Klasifikasi K-Nearest Neighbor (KNN)**:
   - Penentuan parameter Nilai K (1 s.d. 25), rasio split, dan random seed.
   - Perhitungan jarak **Euclidean Distance** 3-dimensi pada ruang fitur ternormalisasi.
   - **Mekanisme Tie-Breaking**: Jika terjadi kesamaan jumlah suara tetangga, sistem memilih kelas dengan rata-rata jarak Euclidean terdekat.
   - **Evaluasi Tanpa Data Leakage**: Data testing dirahasiakan selama pelatihan, dievaluasi terhadap ground truth, lalu seluruh data berlabel digunakan untuk memprediksi produk baru.
8. **Evaluasi Performa Model Multiclass**:
   - Matriks konfusi visual 3&times;3 (*Confusion Matrix*).
   - Perhitungan matematis: *Accuracy*, *Precision*, *Recall*, dan *F1-Score* untuk setiap kelas.
   - Rata-rata makro (*Macro Average*).
9. **Visualisasi Interaktif (Chart.js)**:
   - Doughnut chart proporsi distribusi tren.
   - Bar chart kuantitas produk per kategori.
   - Bar chart perbandingan label aktual vs hasil prediksi model.
   - Pie chart distribusi data training vs data testing.
   - Bar chart performa metrik per kelas (Precision, Recall, F1).
   - Filter visualisasi berdasarkan riwayat proses KNN.
10. **Riwayat Klasifikasi & Detail Prediksi**:
    - Log riwayat setiap eksekusi KNN beserta parameter dan parameter batas normalisasi yang digunakan.
    - Halaman detail per produk yang menampilkan daftar tetangga terdekat, jarak Euclidean masing-masing tetangga, dan rincian voting.
11. **Keamanan Sistem**:
    - Menggunakan **PDO Prepared Statements** (kebal SQL Injection).
    - Hashing kata sandi menggunakan `password_hash()` dan `password_verify()`.
    - Regenerasi ID sesi setelah login (`session_regenerate_id(true)`).
    - Perlindungan **CSRF Token** pada seluruh formulir mutasi data.
    - Sanitasi output dengan `htmlspecialchars()`.
    - Direktori upload dilindungi dari eksekusi skrip langsung (`.htaccess`).

---

## 2. Metodologi Penelitian

### A. Kategori Klasifikasi
1. **Tren**: Produk fashion dengan volume penjualan tinggi dan tingkat kepuasan ulasan yang baik.
2. **Kurang Tren**: Produk dengan penjualan sedang atau ulasan moderat.
3. **Tidak Tren**: Produk dengan penjualan rendah atau ulasan kurang memuaskan.

### B. Atribut / Fitur Klasifikasi
Hanya atribut numerik independen yang digunakan sebagai fitur masukan ($d = 3$):
1. $X_1$ = **Sold**: Jumlah produk yang berhasil terjual.
2. $X_2$ = **Rating**: Penilaian rata-rata dari pembeli (skala 0.00 – 5.00).
3. $X_3$ = **Price Now**: Harga jual produk saat ini dalam mata uang Rupiah.

### C. Normalisasi Min-Max
Karena fitur memiliki satuan dan skala nilai yang berbeda secara ekstrem (misal: harga ratusan ribu vs rating satuan 0–5), fitur harus ditransformasikan ke dalam interval $[0, 1]$ agar perhitungan jarak Euclidean tidak didominasi oleh fitur dengan rentang nilai terbesar:

$$X' = \frac{X - X_{\min}}{X_{\max} - X_{\min}}$$

Keterangan:
- $X'$ = Nilai fitur hasil normalisasi.
- $X$ = Nilai fitur asli.
- $X_{\min}$ = Nilai terendah dari fitur tersebut pada data training.
- $X_{\max}$ = Nilai tertinggi dari fitur tersebut pada data training.
- Jika $X_{\max} = X_{\min}$, sistem menetapkan $X' = 0.0$ untuk mencegah pembagian dengan nol.

### D. Perhitungan Jarak Euclidean (Euclidean Distance)
Jarak geometris antara vektor fitur produk uji ($x$) dengan produk latih ($y$) dihitung menggunakan rumus:

$$d(x, y) = \sqrt{\sum_{i=1}^{n} (x_i - y_i)^2} = \sqrt{(x_{\text{sold}}' - y_{\text{sold}}')^2 + (x_{\text{rating}}' - y_{\text{rating}}')^2 + (x_{\text{price}}' - y_{\text{price}}')^2}$$

### E. Penentuan Tetangga & Voting (Majority Vote & Tie-Breaking)
1. Seluruh jarak diurutkan secara menaik (*ascending*).
2. Diambil $K$ tetangga terdekat dengan jarak terkecil.
3. Dihitung jumlah frekuensi kemunculan kelas pada $K$ tetangga tersebut.
4. Kelas dengan suara terbanyak menjadi label prediksi.
5. **Aturan Tie-Breaking**: Jika dua atau lebih kelas memiliki perolehan suara terbanyak yang sama, sistem menghitung rata-rata jarak Euclidean untuk masing-masing kelas yang seri tersebut. Kelas dengan rata-rata jarak terkecil dinyatakan sebagai pemenang prediksi.

### F. Evaluasi Model (Confusion Matrix & Metrik)
Evaluasi dihitung dari hasil pengujian terhadap data testing yang tidak diikutsertakan pada tahap pelatihan (*unseen test data*):

#### 1. Confusion Matrix 3&times;3
| Aktual \ Prediksi | Tren | Kurang Tren | Tidak Tren |
|:---|:---:|:---:|:---:|
| **Tren** | True Positive (TP) | False Negative (FN) | False Negative (FN) |
| **Kurang Tren** | False Positive (FP) | True Positive (TP) | False Negative (FN) |
| **Tidak Tren** | False Positive (FP) | False Positive (FP) | True Positive (TP) |

#### 2. Formula Metrik
- **Akurasi (Accuracy)**:
  $$\text{Accuracy} = \frac{\sum \text{Prediksi Tepat}}{\text{Total Data Testing}}$$

- **Presisi Per Kelas (Precision)**:
  $$\text{Precision}_c = \frac{\text{TP}_c}{\text{TP}_c + \text{FP}_c}$$

- **Recall Per Kelas (Sensitivity)**:
  $$\text{Recall}_c = \frac{\text{TP}_c}{\text{TP}_c + \text{FN}_c}$$

- **F1-Score Per Kelas**:
  $$\text{F1-Score}_c = 2 \times \frac{\text{Precision}_c \times \text{Recall}_c}{\text{Precision}_c + \text{Recall}_c}$$

- **Macro Average**:
  $$\text{Macro Precision} = \frac{1}{|C|} \sum_{c \in C} \text{Precision}_c, \quad \text{Macro Recall} = \frac{1}{|C|} \sum_{c \in C} \text{Recall}_c, \quad \text{Macro F1} = \frac{1}{|C|} \sum_{c \in C} \text{F1}_c$$

---

## 3. Struktur Direktori Proyek

```
fashiontrendknn/
├── app/
│   ├── config/
│   │   └── database.php              # Koneksi PDO, konstanta, & auto-migration
│   ├── helpers/
│   │   ├── auth.php                  # Helper autentikasi & sesi pengguna
│   │   └── functions.php             # Helper sanitasi, badge, format angka, CSRF, paginasi
│   ├── middleware/
│   │   └── access.php                # Kontrol hak akses berbasis peran (RBAC)
│   ├── services/
│   │   ├── DatasetService.php        # Parser CSV, validasi data, template, & transactional import
│   │   ├── PreprocessingService.php  # Seleksi fitur, audit kualitas, Min-Max, stratified split
│   │   ├── KNNService.php            # Euclidean distance, K-neighbors, voting, tie-breaker, training
│   │   └── EvaluationService.php     # Confusion matrix 3x3, precision, recall, F1 multiclass
│   └── views/
│       ├── layouts/
│       │   ├── header.php            # Layout HTML, CSS link, sidebar navigasi, topbar
│       │   └── footer.php            # Penutup layout HTML, skrip Lucide & app.js
│       └── components/
│           ├── badge.php             # Komponen badge kategori tren
│           ├── pagination.php        # Komponen navigasi paginasi
│           └── stat_card.php         # Komponen kartu statistik ringkasan
│
├── public/                           # Web Root / Dokumen Publik
│   ├── assets/
│   │   ├── css/
│   │   │   └── style.css             # Design System lengkap (Inter font, responsif)
│   │   ├── js/
│   │   │   └── app.js                # Drawer mobile, inisialisasi ikon, dialog konfirmasi
│   │   └── images/
│   ├── index.php                     # Halaman Login aplikasi
│   ├── dashboard.php                 # Halaman Dashboard analitik
│   ├── products.php                  # Halaman CRUD Data Produk
│   ├── dataset.php                   # Halaman Import CSV & Validasi Preview
│   ├── training.php                  # Halaman Manajemen Data Training
│   ├── preprocessing.php             # Halaman Dokumentasi Preprocessing & Normalisasi
│   ├── process.php                   # Halaman Eksekusi Preprocessing & KNN
│   ├── results.php                   # Halaman Hasil Klasifikasi Seluruh Produk
│   ├── product_detail.php            # Halaman Rincian Tetangga & Jarak Euclidean Produk
│   ├── evaluation.php                # Halaman Evaluasi Model & Confusion Matrix
│   ├── visualization.php             # Halaman Visualisasi Grafis (Chart.js)
│   ├── history.php                   # Halaman Riwayat Audit Proses Klasifikasi
│   ├── users.php                     # Halaman Manajemen Pengguna
│   └── logout.php                    # Skrip penghentian sesi & logout
│
├── database/
│   ├── database.sql                  # Skrip pembuatan basis data & data awal
│   └── migrations/
│       └── 001_add_tables.sql        # Skrip migrasi penambahan tabel riwayat KNN
│
├── storage/
│   └── uploads/
│       └── .htaccess                 # Proteksi direktori upload
│
├── index.php                         # Root forwarder ke public/index.php
├── sample.csv                        # Berkas contoh dataset fashion untuk pengujian import
└── README.md                         # Dokumentasi lengkap proyek
```

---

## 4. Struktur Basis Data (`fashion_knn`)

1. **`users`**:
   - `id` (INT PK AI), `name` (VARCHAR 100), `username` (VARCHAR 60 UNIQUE), `password` (VARCHAR 255), `role` (ENUM: admin, kaunit, pemohon), `created_at` (TIMESTAMP).
2. **`products`**:
   - `id` (INT PK AI), `name` (VARCHAR 255), `sold` (INT), `rating` (DECIMAL 3,2), `price_now` (DECIMAL 14,2), `label` (VARCHAR 30), `prediction` (VARCHAR 30), `prediction_process_id` (INT NULL), `created_at` (TIMESTAMP).
3. **`knn_processes`**:
   - `id` (INT PK AI), `k_value` (INT), `split_ratio` (VARCHAR 10), `random_seed` (INT), `train_count` (INT), `test_count` (INT), `accuracy` (DECIMAL 8,4), `precision_score` (DECIMAL 8,4), `recall_score` (DECIMAL 8,4), `f1_score` (DECIMAL 8,4), `confusion_matrix` (TEXT NULL), `processed_at` (TIMESTAMP), `created_by` (INT NULL).
4. **`knn_predictions`**:
   - `id` (INT PK AI), `process_id` (INT FK), `product_id` (INT FK), `prediction` (VARCHAR 30), `normalized_sold` (DECIMAL 10,6), `normalized_rating` (DECIMAL 10,6), `normalized_price` (DECIMAL 10,6), `neighbors_json` (TEXT NULL), `is_test_data` (TINYINT 1), `actual_label` (VARCHAR 30 NULL), `is_correct` (TINYINT 1 NULL), `created_at` (TIMESTAMP).
5. **`knn_norm_params`**:
   - `id` (INT PK AI), `process_id` (INT FK), `feature` (VARCHAR 30), `min_val` (DECIMAL 14,4), `max_val` (DECIMAL 14,4).

---

## 5. Panduan Instalasi & Penggunaan

### Persyaratan Sistem
- Web Server: Apache (Laragon / XAMPP)
- PHP: Versi 8.0 atau yang lebih baru (ekstensi `pdo_mysql` aktif)
- Database: MySQL versi 5.7 atau 8.0 / MariaDB

### Langkah Pemasangan
1. Salin folder proyek ke direktori web server:
   - Laragon: `C:\laragon\www\fashiontrendknn`
   - XAMPP: `C:\xampp\htdocs\fashiontrendknn`
2. Pastikan layanan Apache dan MySQL sudah berjalan.
3. Buka **phpMyAdmin** (`http://localhost/phpmyadmin`) dan import berkas `database/database.sql`.
   *(Catatan: Sistem juga dilengkapi fitur idempotent auto-migration, sehingga tabel-tabel pendukung KNN akan dibuat secara otomatis saat aplikasi dibuka).*
4. Akses aplikasi melalui browser:
   `http://localhost/fashiontrendknn/` atau `http://localhost/fashiontrendknn/public/`

### Akun Demo Bawaan
| Peran (Role) | Username | Password | Hak Akses |
|---|---|---|---|
| **Administrator** | `admin` | `password` | Akses penuh seluruh sistem |
| **Kepala Unit** | `kaunit` | `password` | Monitoring, evaluasi, dan visualisasi |
| **Pemohon** | `pemohon` | `password` | Melihat data produk dan hasil prediksi |

---

## 6. Alur Kerja Pengujian Sistem

1. **Login**: Masuk sebagai `admin`.
2. **Kelola Produk / Import CSV**:
   - Buka menu **Dataset CSV**, lalu unggah berkas `sample.csv` yang disediakan di root proyek.
   - Periksa hasil validasi dan pratinjau data, lalu klik tombol **Simpan Data Valid ke Database**.
3. **Data Training**: Buka menu **Data Training** untuk memeriksa representasi dan sebaran sampel berlabel.
4. **Preprocessing**: Buka menu **Preprocessing** untuk mempelajari tahapan seleksi fitur, statistik kualitas data, parameter batas normalisasi Min-Max, dan simulasi *data split*.
5. **Jalankan KNN**:
   - Buka menu **Proses KNN**, pilih nilai $K$ (misalnya $K = 3$ atau $K = 5$), pilih rasio split (misalnya 70:30), lalu klik **Jalankan Preprocessing & Klasifikasi KNN**.
   - Sistem akan memproses normalisasi, membagi data uji, menghitung jarak Euclidean, melakukan voting, mencatat akurasi data uji, dan memprediksi produk baru.
6. **Evaluasi & Visualisasi**:
   - Buka menu **Evaluasi Model** untuk meneliti matriks konfusi multiclass 3&times;3 serta nilai presisi, recall, dan F1-Score.
   - Buka menu **Hasil Prediksi** untuk melihat status kecocokan dan mengklik tombol **Detail** pada salah satu produk guna meninjau $K$ tetangga terdekat beserta jarak Euclidean-nya.
   - Buka menu **Visualisasi** untuk meninjau diagram grafis interaktif.
7. **Pengujian Hak Akses**: Logout dan lakukan login sebagai `kaunit` atau `pemohon` untuk memverifikasi pembatasan menu dan proteksi sisi server.


---

## Panduan Deployment

Lihat dokumentasi lengkap di:

- **[DEPLOYMENT.md](DEPLOYMENT.md)** — Panduan lengkap deployment ke ArenHost
- **[DEPLOYMENT_CHECKLIST.md](DEPLOYMENT_CHECKLIST.md)** — Checklist sebelum go-live

---

## Setup Development Lokal (Laragon)

### Prasyarat
- Laragon (Windows) dengan PHP 8.1+ dan MySQL
- Git

### Langkah Setup

```bash
# 1. Clone repository
git clone https://github.com/USERNAME/fashiontrendknn.git
cd fashiontrendknn

# 2. Salin dan konfigurasi environment
copy .env.example .env
# Edit .env jika diperlukan (default sudah sesuai Laragon)

# 3. Import database
# Buka phpMyAdmin di http://localhost/phpmyadmin
# Buat database: fashion_knn
# Import file: database/database.sql

# 4. Jalankan migration
# Buka browser: http://localhost/fashiontrendknn/migrate.php
# Atau via CLI:
# C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe migrate.php

# 5. Akses aplikasi
# http://localhost/fashiontrendknn/public/
```

### Akun Default (Hanya Development)

| Username | Password  | Role    |
|----------|-----------|---------|
| admin    | password  | Admin   |
| kaunit   | password  | Kaunit  |
| pemohon  | password  | Pemohon |

> **Penting:** Ganti semua password default sebelum deployment ke production.

---

## Struktur Folder

```
fashiontrendknn/
├── .github/workflows/     ← CI/CD GitHub Actions
│   ├── test.yml           ← PHP lint & validasi otomatis
│   └── deploy.yml         ← Auto-deploy ke ArenHost
├── app/
│   ├── config/
│   │   ├── database.php   ← Bootstrap: env, session, PDO
│   │   └── env.php        ← .env file loader
│   ├── helpers/
│   │   ├── auth.php       ← Autentikasi & otorisasi
│   │   └── functions.php  ← Helper: h(), csrf, paginate
│   ├── middleware/
│   │   └── access.php     ← Page-level access control
│   ├── services/
│   │   ├── KNNService.php
│   │   ├── PreprocessingService.php
│   │   ├── EvaluationService.php
│   │   └── DatasetService.php
│   └── views/layouts/
│       ├── header.php
│       └── footer.php
├── database/
│   ├── database.sql       ← Schema lengkap + seed data
│   └── migrations/        ← SQL migration files
├── public/                ← DOCUMENT ROOT (satu-satunya folder publik)
│   ├── assets/css/
│   ├── assets/js/
│   ├── .htaccess          ← Security headers, HTTPS redirect
│   └── *.php              ← Halaman aplikasi
├── storage/
│   ├── logs/              ← PHP error logs
│   └── uploads/           ← Upload CSV sementara
├── .env                   ← Konfigurasi lokal (tidak di-commit)
├── .env.example           ← Template konfigurasi
├── .gitignore
├── migrate.php            ← Database migration runner
├── DEPLOYMENT.md
└── DEPLOYMENT_CHECKLIST.md
```

---

## Keamanan

- Password menggunakan bcrypt (cost=12)
- CSRF protection pada semua form POST
- PDO prepared statements untuk semua query
- Output HTML di-escape dengan `htmlspecialchars()`
- Session cookie: HttpOnly, SameSite=Lax, Secure (HTTPS)
- Session timeout: 2 jam inaktivitas
- Folder `app/`, `database/`, `storage/` tidak bisa diakses via browser

---

## Tech Stack

| Komponen | Teknologi |
|----------|-----------|
| Backend  | PHP 8.1+ Native |
| Database | MySQL 8.0+ / MariaDB 10.4+ |
| Frontend | HTML5, CSS3, Vanilla JavaScript |
| Icons    | Lucide Icons (CDN) |
| Charts   | Chart.js (CDN) |
| Fonts    | Google Fonts - Inter |
| Server   | Apache + mod_rewrite |
| CI/CD    | GitHub Actions |
