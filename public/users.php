<?php
require_once dirname(__DIR__) . '/app/config/database.php';
require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/helpers/functions.php';
require_once dirname(__DIR__) . '/app/middleware/access.php';

check_access('users');
$page_title = 'Manajemen Pengguna';
$current_page = 'users';

$msg_success = null;
$msg_error   = null;

// 1. Handle Tambah Pengguna Baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_user') {
    csrf_check();

    $name     = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = trim($_POST['role'] ?? 'pemohon');

    $valid_roles = ['admin', 'kaunit', 'pemohon'];

    if ($name === '' || $username === '' || $password === '') {
        $msg_error = 'Seluruh bidang formulir wajib diisi.';
    } elseif (strlen($password) < 6) {
        $msg_error = 'Kata sandi minimal terdiri dari 6 karakter.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $msg_error = 'Username hanya boleh berisi huruf, angka, dan underscore.';
    } elseif (!in_array($role, $valid_roles, true)) {
        $msg_error = 'Role pengguna yang dipilih tidak valid.';
    } else {
        // Cek username unik
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ((int)$stmt->fetchColumn() > 0) {
            $msg_error = "Username '" . h($username) . "' sudah digunakan. Silakan gunakan username lain.";
        } else {
            // Hash password dengan bcrypt sebelum disimpan
            $hashedPassword = hash_password($password);
            $ins = $pdo->prepare("INSERT INTO users (name, username, password, role, created_at) VALUES (?, ?, ?, ?, NOW())");
            $ins->execute([$name, $username, $hashedPassword, $role]);
            $msg_success = "Pengguna baru '" . h($name) . "' ({$role}) berhasil didaftarkan.";
        }
    }
}

// 2. Handle Hapus Pengguna
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    csrf_check();

    $del_id = (int)($_POST['user_id'] ?? 0);
    $current_id = (int)($_SESSION['user']['id'] ?? 0);

    if ($del_id === $current_id) {
        $msg_error = 'Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif.';
    } else {
        $del_stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $del_stmt->execute([$del_id]);
        $msg_success = 'Pengguna berhasil dihapus dari sistem.';
    }
}

// Ambil seluruh pengguna
$users = $pdo->query("SELECT id, name, username, role, created_at FROM users ORDER BY id ASC")->fetchAll();

include dirname(__DIR__) . '/app/views/layouts/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Manajemen Pengguna</h1>
    <p class="page-subtitle">Kelola akun pengguna dan hak akses peran (Admin, Kaunit, Pemohon) ke seluruh modul sistem.</p>
  </div>
</div>

<?php if ($msg_success): ?>
  <div class="alert alert-success"><i data-lucide="check-circle"></i> <span><?= h($msg_success) ?></span></div>
<?php endif; ?>
<?php if ($msg_error): ?>
  <div class="alert alert-error"><i data-lucide="alert-triangle"></i> <span><?= h($msg_error) ?></span></div>
<?php endif; ?>

<div class="grid two">
  <!-- Tabel Pengguna -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Daftar Pengguna Sistem (<?= count($users) ?> akun)</h3>
    </div>
    <div class="card-body">
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>Nama Lengkap</th>
              <th>Username</th>
              <th>Hak Akses (Role)</th>
              <th>Terdaftar</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $u): ?>
              <tr>
                <td><strong><?= h($u['name']) ?></strong></td>
                <td><code><?= h($u['username']) ?></code></td>
                <td>
                  <?php if ($u['role'] === 'admin'): ?>
                    <span class="badge" style="background: #EEF2FF; color: var(--primary);">Admin</span>
                  <?php elseif ($u['role'] === 'kaunit'): ?>
                    <span class="badge badge-kurang">Kaunit</span>
                  <?php else: ?>
                    <span class="badge badge-gray">Pemohon</span>
                  <?php endif; ?>
                </td>
                <td><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                <td>
                  <?php if ((int)$u['id'] !== (int)($_SESSION['user']['id'] ?? 0)): ?>
                    <form method="post" style="margin: 0; display: inline;" onsubmit="return confirm('Yakin ingin menghapus pengguna ini?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete_user">
                      <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                      <button type="submit" class="btn btn-sm btn-danger" style="padding: 4px 8px;" title="Hapus Akun">
                        <i data-lucide="trash-2"></i> Hapus
                      </button>
                    </form>
                  <?php else: ?>
                    <span class="text-muted" style="font-size: 12px;">Akun Anda</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="alert alert-info mt-3" style="font-size: 12px;">
        <strong>Catatan:</strong> Password disimpan menggunakan enkripsi bcrypt. Pastikan gunakan password yang kuat minimal 8 karakter untuk akun production.
      </div>
    </div>
  </div>

  <!-- Form Tambah Pengguna -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Tambah Pengguna Baru</h3>
      <p class="card-subtitle">Buat akun dengan hak akses tertentu.</p>
    </div>
    <div class="card-body">
      <form action="" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create_user">

        <div class="form-group mb-3">
          <label class="form-label" for="u_name">Nama Lengkap</label>
          <input type="text" name="name" id="u_name" required class="form-control" placeholder="Contoh: Budi Santoso">
        </div>

        <div class="form-group mb-3">
          <label class="form-label" for="u_username">Username</label>
          <input type="text" name="username" id="u_username" required class="form-control" placeholder="Contoh: budi_s">
        </div>

        <div class="form-group mb-3">
          <label class="form-label" for="u_password">Password</label>
          <input type="password" name="password" id="u_password" required minlength="6" class="form-control" placeholder="Minimal 6 karakter">
        </div>

        <div class="form-group mb-4">
          <label class="form-label" for="u_role">Peran / Role</label>
          <select name="role" id="u_role" required class="form-select">
            <option value="pemohon">Pemohon (Viewer Hasil Prediksi)</option>
            <option value="kaunit">Kaunit (Viewer & Evaluasi)</option>
            <option value="admin">Admin (Akses Penuh)</option>
          </select>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%;">
          <i data-lucide="user-plus"></i> Daftarkan Pengguna
        </button>
      </form>
    </div>
  </div>
</div>

<?php include dirname(__DIR__) . '/app/views/layouts/footer.php'; ?>

