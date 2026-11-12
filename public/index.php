<?php
require_once dirname(__DIR__) . '/app/config/database.php';
require_once APP_PATH . '/helpers/auth.php';
require_once APP_PATH . '/helpers/functions.php';

$error = '';

// Jika sudah login, langsung arahkan ke dashboard
if (logged()) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $username = clean_input($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $stmt = $pdo->prepare('SELECT id, name, username, password, role FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        // Verifikasi password: mendukung bcrypt dan plain-text lama (untuk migrasi)
        $is_valid = false;
        if ($user) {
            if (password_verify($password, $user['password'])) {
                // Password sudah di-hash dengan bcrypt — normal
                $is_valid = true;
            } elseif ($user['password'] === $password) {
                // Password masih plain text (data lama) — valid, lalu upgrade ke bcrypt
                $is_valid = true;
                $newHash = hash_password($password);
                $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([$newHash, $user['id']]);
            }
        }

        if ($user && $is_valid) {
            // Cegah session fixation: regenerate ID sebelum simpan data login
            session_regenerate_id(true);

            $_SESSION['user'] = [
                'id'       => (int)$user['id'],
                'name'     => $user['name'],
                'username' => $user['username'],
                'role'     => $user['role'],
            ];
            $_SESSION['_last_activity'] = time();
            $_SESSION['_login_time']    = time();

            // Redirect ke halaman yang diminta sebelumnya (jika ada), atau dashboard
            $redirect = $_SESSION['redirect_after_login'] ?? '';
            unset($_SESSION['redirect_after_login']);
            header('Location: ' . (filter_var($redirect, FILTER_VALIDATE_URL) ? $redirect : 'dashboard.php'));
            exit;
        } else {
            // Pesan generik: jangan beritahu apakah username atau password yang salah
            $error = 'Username atau kata sandi tidak cocok. Silakan periksa kembali.';
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Masuk &bull; Sistem Prediksi Tren Fashion KNN</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <style>
    .auth-wrapper {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: radial-gradient(circle at 15% 15%, #EEF2FF 0%, #F8FAFC 50%, #F1F5F9 100%);
      padding: 24px;
      position: relative;
      overflow: hidden;
    }
    .auth-bg-decor {
      position: absolute;
      width: 500px;
      height: 500px;
      background: linear-gradient(135deg, rgba(79, 70, 229, 0.08) 0%, rgba(99, 102, 241, 0.02) 100%);
      border-radius: 50%;
      filter: blur(60px);
      top: -150px;
      right: -100px;
      pointer-events: none;
    }
    .auth-card {
      background: #FFFFFF;
      border: 1px solid rgba(226, 232, 240, 0.9);
      border-radius: 16px;
      box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.06), 0 20px 25px -5px rgba(15, 23, 42, 0.04);
      width: 100%;
      max-width: 440px;
      padding: 40px 36px;
      position: relative;
      z-index: 10;
    }
    .auth-brand {
      text-align: center;
      margin-bottom: 28px;
    }
    .auth-brand-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 5px 12px;
      background: #EEF2FF;
      color: #4F46E5;
      border-radius: 20px;
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0.5px;
      text-transform: uppercase;
      margin-bottom: 12px;
    }
    .auth-logo-icon {
      width: 48px;
      height: 48px;
      background: linear-gradient(135deg, #4F46E5 0%, #4338CA 100%);
      color: white;
      border-radius: 12px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
      margin-bottom: 14px;
    }
    .auth-title {
      font-size: 22px;
      font-weight: 800;
      color: #0F172A;
      margin-bottom: 6px;
      letter-spacing: -0.02em;
    }
    .auth-desc {
      font-size: 13px;
      color: #64748B;
      line-height: 1.5;
    }
    .input-wrapper {
      position: relative;
    }
    .input-icon {
      position: absolute;
      left: 14px;
      top: 50%;
      transform: translateY(-50%);
      color: #94A3B8;
      width: 18px;
      height: 18px;
      pointer-events: none;
    }
    .auth-input {
      padding-left: 42px !important;
      padding-right: 42px !important;
      height: 44px;
      font-size: 13.5px;
      border-radius: 9px;
      border: 1px solid #E2E8F0;
      background: #FFFFFF;
      transition: all 0.2s ease;
    }
    .auth-input:focus {
      border-color: #4F46E5;
      box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.12);
      outline: none;
    }
    .pw-toggle {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      color: #94A3B8;
      cursor: pointer;
      padding: 4px;
      display: flex;
    }
    .pw-toggle:hover { color: #4F46E5; }
    .demo-pill-btn {
      padding: 6px 10px;
      font-size: 11px;
      font-weight: 600;
      border-radius: 6px;
      border: 1px solid #E2E8F0;
      background: #F8FAFC;
      color: #475569;
      cursor: pointer;
      transition: all 0.15s ease;
    }
    .demo-pill-btn:hover {
      background: #EEF2FF;
      color: #4F46E5;
      border-color: #C7D2FE;
    }
  </style>
</head>
<body>
<div class="auth-wrapper">
  <div class="auth-bg-decor"></div>

  <div class="auth-card">
    <div class="auth-brand">
      <div class="auth-logo-icon">
        <i data-lucide="trending-up" style="width: 24px; height: 24px;"></i>
      </div>
      <div>
        <span class="auth-brand-badge">K-Nearest Neighbor</span>
      </div>
      <h1 class="auth-title">Prediksi Tren Fashion</h1>
      <p class="auth-desc">Klasifikasi produk Tokopedia menggunakan algoritma KNN & Min-Max Scaling.</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-error mb-4" style="border-radius: 8px; font-size: 13px; display: flex; align-items: center; gap: 8px;">
        <i data-lucide="alert-circle" style="width: 16px; height: 16px; flex-shrink: 0;"></i>
        <span><?= h($error) ?></span>
      </div>
    <?php endif; ?>

    <form method="POST" action="">
      <?= csrf_field() ?>
      <div class="form-group mb-3">
        <label class="form-label" for="username" style="font-weight: 600; font-size: 12.5px; color: #334155;">Username</label>
        <div class="input-wrapper">
          <i data-lucide="user" class="input-icon"></i>
          <input
            type="text"
            id="username"
            name="username"
            class="form-control auth-input"
            value="<?= h($_POST['username'] ?? '') ?>"
            placeholder="Masukkan username Anda"
            autocomplete="username"
            required
            autofocus
          >
        </div>
      </div>

      <div class="form-group mb-4">
        <div class="flex justify-between items-center mb-1">
          <label class="form-label mb-0" for="password" style="font-weight: 600; font-size: 12.5px; color: #334155;">Kata Sandi</label>
        </div>
        <div class="input-wrapper">
          <i data-lucide="lock" class="input-icon"></i>
          <input
            type="password"
            id="password"
            name="password"
            class="form-control auth-input"
            placeholder="Masukkan kata sandi"
            autocomplete="current-password"
            required
          >
          <button type="button" id="togglePassword" class="pw-toggle" aria-label="Lihat Password">
            <i data-lucide="eye" id="eyeIcon" style="width: 17px; height: 17px;"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="btn btn-primary w-full" style="height: 44px; font-size: 14px; font-weight: 700; border-radius: 9px; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);">
        Masuk ke Sistem &rarr;
      </button>
    </form>

    <!-- Quick Demo Role Switcher -->
    <div class="mt-4 pt-3" style="border-top: 1px solid #F1F5F9;">
      <div class="flex justify-between items-center mb-2">
        <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748B;">Klik Cepat Akun Demo:</span>
      </div>
      <div class="flex gap-2 flex-wrap">
        <button type="button" class="demo-pill-btn flex-1 text-center" onclick="fillDemo('admin', 'password')">
          <strong>Admin</strong>
        </button>
        <button type="button" class="demo-pill-btn flex-1 text-center" onclick="fillDemo('kaunit', 'password')">
          <strong>Kaunit</strong>
        </button>
        <button type="button" class="demo-pill-btn flex-1 text-center" onclick="fillDemo('pemohon', 'password')">
          <strong>Pemohon</strong>
        </button>
      </div>
    </div>
  </div>
</div>

<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script>
  if (typeof lucide !== 'undefined') lucide.createIcons();

  function fillDemo(username, password) {
    document.getElementById('username').value = username;
    document.getElementById('password').value = password;
    document.getElementById('username').focus();
  }

  const toggleBtn = document.getElementById('togglePassword');
  const pwInput = document.getElementById('password');
  const eyeIcon = document.getElementById('eyeIcon');

  if (toggleBtn && pwInput) {
    toggleBtn.addEventListener('click', function() {
      const isPw = pwInput.getAttribute('type') === 'password';
      pwInput.setAttribute('type', isPw ? 'text' : 'password');
      eyeIcon.setAttribute('data-lucide', isPw ? 'eye-off' : 'eye');
      if (typeof lucide !== 'undefined') lucide.createIcons();
    });
  }
</script>
</body>
</html>
