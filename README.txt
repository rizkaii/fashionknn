FASHION TREND KNN - PHP + MySQL (XAMPP)

INSTALASI
1. Ekstrak folder fashion_knn_web ke C:\xampp\htdocs\
2. Jalankan Apache dan MySQL dari XAMPP Control Panel.
3. Buka http://localhost/phpmyadmin
4. Import file database.sql.
5. Buka http://localhost/fashion_knn_web/

AKUN DEMO (password semua: password)
admin / password
kaunit / password
pemohon / password

ALUR PENGGUNAAN
1. Login sebagai admin.
2. Buka Data Produk, masukkan produk dan label data latih.
3. Atau import CSV melalui menu Dataset. Header wajib name,sold,rating,price_now; label opsional.
4. Buka Preprocessing & KNN, tentukan K dan rasio latih/uji, lalu jalankan.
5. Lihat Hasil Klasifikasi dan Visualisasi Tren.
6. Uji login sebagai kaunit dan pemohon untuk melihat pembatasan akses.

CATATAN METODOLOGI PENTING
KNN adalah supervised learning, sehingga data latih perlu memiliki label kelas.
Laporan menyebut tiga kelas: tren, kurang tren, tidak tren, tetapi belum menetapkan
aturan rinci untuk memberi label pada setiap baris data. Karena itu aplikasi meminta
admin mengisi label data latih berdasarkan kriteria penelitian yang disetujui dosen.
Jangan menganggap data contoh bawaan sebagai hasil penelitian sebenarnya.
Implementasi ini memakai normalisasi Min-Max dan pembagian data berurutan sederhana
untuk prototipe. Untuk evaluasi final, sebaiknya gunakan pembagian acak/stratified,
random seed tetap, confusion matrix, dan aturan label yang terdokumentasi.
