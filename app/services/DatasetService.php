<?php
/**
 * DatasetService
 * Layanan untuk validasi, preview, download template, dan import dataset CSV ke database.
 */
class DatasetService {
    private PDO $pdo;
    private array $allowed_labels = ['tren', 'kurang tren', 'tidak tren'];

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Menghasilkan isi template CSV untuk diunduh.
     */
    public function generateTemplateCsv(): string {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['name', 'sold', 'rating', 'price_now', 'label']);
        fputcsv($out, ['Kemeja Oversize Wanita', '850', '4.8', '79000', 'tren']);
        fputcsv($out, ['Celana Kulot Premium', '420', '4.6', '95000', 'tren']);
        fputcsv($out, ['Cardigan Rajut', '180', '4.4', '125000', 'kurang tren']);
        fputcsv($out, ['Kaos Polos Basic', '45', '4.1', '45000', 'tidak tren']);
        fputcsv($out, ['Rok Plisket Panjang', '310', '4.7', '68000', 'tren']);
        rewind($out);
        $content = stream_get_contents($out);
        fclose($out);
        return $content;
    }

    /**
     * Membaca dan memvalidasi file CSV yang diunggah.
     * Mengembalikan ringkasan: [valid_rows, invalid_rows, summary]
     */
    public function parseAndValidate(string $filePath): array {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new Exception("File CSV tidak dapat dibaca.");
        }

        $f = fopen($filePath, 'r');
        if (!$f) {
            throw new Exception("Gagal membuka file CSV.");
        }

        // Baca header
        $header = fgetcsv($f);
        if (!$header) {
            fclose($f);
            throw new Exception("File CSV kosong atau format header tidak valid.");
        }

        // Normalisasi header (lowercase, trim)
        $cleanHeader = array_map(fn($col) => strtolower(trim((string)$col)), $header);
        // Hapus BOM jika ada
        if (isset($cleanHeader[0])) {
            $cleanHeader[0] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $cleanHeader[0]);
        }

        $map = array_flip($cleanHeader);
        $required = ['name', 'sold', 'rating', 'price_now'];
        foreach ($required as $req) {
            if (!isset($map[$req])) {
                fclose($f);
                throw new Exception("Kolom wajib '$req' tidak ditemukan pada header CSV. Header yang diperlukan: name, sold, rating, price_now, label (opsional).");
            }
        }

        // Ambil nama produk yang sudah ada di database untuk deteksi duplikasi
        $existingNames = $this->pdo->query("SELECT LOWER(TRIM(name)) FROM products")->fetchAll(PDO::FETCH_COLUMN);
        $existingMap = array_fill_keys($existingNames, true);

        $validRows = [];
        $invalidRows = [];
        $seenInFile = [];
        $rowNumber = 1; // baris 1 adalah header

        while (($row = fgetcsv($f)) !== false) {
            $rowNumber++;

            // Abaikan baris kosong
            if (empty(array_filter($row, fn($v) => trim((string)$v) !== ''))) {
                continue;
            }

            $rawName     = trim($row[$map['name']] ?? '');
            $rawSold     = trim($row[$map['sold']] ?? '');
            $rawRating   = trim($row[$map['rating']] ?? '');
            $rawPrice    = trim($row[$map['price_now']] ?? '');
            $rawLabel    = isset($map['label']) ? trim($row[$map['label']] ?? '') : '';

            $errors = [];

            // 1. Validasi Nama
            if ($rawName === '') {
                $errors[] = "Nama produk tidak boleh kosong.";
            }

            // 2. Validasi Sold
            if ($rawSold === '' || !is_numeric($rawSold)) {
                $errors[] = "Sold harus berupa angka numerik.";
            } elseif ((int)$rawSold < 0) {
                $errors[] = "Sold tidak boleh negatif.";
            }

            // 3. Validasi Rating
            if ($rawRating === '' || !is_numeric($rawRating)) {
                $errors[] = "Rating harus berupa angka desimal.";
            } else {
                $ratingVal = (float)$rawRating;
                if ($ratingVal < 0 || $ratingVal > 5) {
                    $errors[] = "Rating harus berada di antara 0.00 hingga 5.00.";
                }
            }

            // 4. Validasi Price Now
            if ($rawPrice === '' || !is_numeric($rawPrice)) {
                $errors[] = "Price Now harus berupa angka numerik.";
            } elseif ((float)$rawPrice < 0) {
                $errors[] = "Price Now tidak boleh bernilai negatif.";
            }

            // 5. Validasi Label (opsional, tapi jika diisi harus valid)
            $cleanLabel = null;
            if ($rawLabel !== '') {
                $lowerLabel = strtolower($rawLabel);
                if (in_array($lowerLabel, $this->allowed_labels)) {
                    $cleanLabel = $lowerLabel;
                } else {
                    $errors[] = "Label '$rawLabel' tidak valid. Pilihan label: 'tren', 'kurang tren', 'tidak tren', atau kosongkan.";
                }
            }

            // 6. Cek duplikasi
            $lowerName = strtolower($rawName);
            $isDuplicateInDb = isset($existingMap[$lowerName]);
            $isDuplicateInFile = isset($seenInFile[$lowerName]);
            $seenInFile[$lowerName] = true;

            $rowData = [
                'row_num'   => $rowNumber,
                'name'      => $rawName,
                'sold'      => (int)$rawSold,
                'rating'    => (float)$rawRating,
                'price_now' => (float)$rawPrice,
                'label'     => $cleanLabel,
                'is_duplicate' => ($isDuplicateInDb || $isDuplicateInFile),
                'dup_source'   => $isDuplicateInDb ? 'Database' : ($isDuplicateInFile ? 'File CSV' : '-'),
                'errors'    => $errors,
            ];

            if (!empty($errors)) {
                $invalidRows[] = $rowData;
            } else {
                $validRows[] = $rowData;
            }
        }
        fclose($f);

        return [
            'valid_rows'   => $validRows,
            'invalid_rows' => $invalidRows,
            'total_rows'   => count($validRows) + count($invalidRows),
            'valid_count'  => count($validRows),
            'invalid_count'=> count($invalidRows),
            'duplicate_count' => count(array_filter($validRows, fn($r) => $r['is_duplicate'])),
        ];
    }

    /**
     * Mengimpor data yang telah tervalidasi ke tabel products menggunakan database transaction.
     * $skipDuplicates: jika true, data yang duplikat dilewati.
     */
    public function importRows(array $validRows, bool $skipDuplicates = false): array {
        if (empty($validRows)) {
            return ['imported' => 0, 'skipped' => 0];
        }

        $imported = 0;
        $skipped = 0;

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO products (name, sold, rating, price_now, label, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())"
            );

            foreach ($validRows as $row) {
                if ($skipDuplicates && !empty($row['is_duplicate'])) {
                    $skipped++;
                    continue;
                }

                $stmt->execute([
                    $row['name'],
                    $row['sold'],
                    $row['rating'],
                    $row['price_now'],
                    $row['label'] // bisa null
                ]);
                $imported++;
            }

            $this->pdo->commit();
            return ['imported' => $imported, 'skipped' => $skipped];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw new Exception("Gagal mengimpor dataset: " . $e->getMessage());
        }
    }
}

