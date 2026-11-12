<?php
/**
 * env.php — Lightweight .env file loader
 *
 * Membaca file .env dari root project dan mengisi $_ENV serta getenv().
 * Mendukung komentar (#), nilai dikutip, dan nilai kosong.
 * Tidak menimpa variabel environment yang sudah didefinisikan di server
 * (sehingga konfigurasi server/hosting tetap lebih prioritas).
 *
 * Tidak memerlukan library Composer apapun.
 */

function load_env(string $filePath): void {
    if (!file_exists($filePath) || !is_readable($filePath)) {
        return; // .env tidak wajib ada (pakai default atau env server)
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);

        // Abaikan baris komentar dan baris kosong
        if ($line === '' || $line[0] === '#') {
            continue;
        }

        // Abaikan baris tanpa tanda =
        if (strpos($line, '=') === false) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name  = trim($name);
        $value = trim($value);

        // Hapus komentar inline setelah nilai (mis. VALUE=xyz # komentar)
        if (strpos($value, ' #') !== false) {
            $value = trim(explode(' #', $value, 2)[0]);
        }

        // Lepas tanda kutip di sekitar nilai (single atau double)
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        // Jangan timpa env yang sudah ada dari server/hosting
        if (!isset($_ENV[$name]) && getenv($name) === false) {
            $_ENV[$name]    = $value;
            putenv("$name=$value");
            $_SERVER[$name] = $value;
        }
    }
}

/**
 * Ambil nilai environment variable dengan nilai default.
 *
 * @param  string $key     Nama variabel environment
 * @param  mixed  $default Nilai default jika tidak ditemukan
 * @return mixed
 */
function env(string $key, mixed $default = null): mixed {
    $val = $_ENV[$key] ?? getenv($key);
    if ($val === false || $val === null) {
        return $default;
    }
    // Konversi string boolean
    return match (strtolower((string)$val)) {
        'true',  '1', 'yes', 'on'  => true,
        'false', '0', 'no',  'off' => false,
        'null',  ''                 => null,
        default                     => $val,
    };
}
