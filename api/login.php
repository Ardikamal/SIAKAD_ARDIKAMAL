<?php
define('SIAKAD_APP', true);
require __DIR__ . '/_config/constants.php';
require __DIR__ . '/_config/database.php';
require __DIR__ . '/_config/auth.php';
require __DIR__ . '/_helpers/functions.php';

// Sudah login? Langsung ke dashboard masing-masing, tidak perlu login lagi.
$already = siakad_current_user();
if ($already !== null) {
    redirect(siakad_dashboard_url($already['role']));
}

$errorMsg = null;
$selectedRole = $_GET['role'] ?? $_POST['role'] ?? 'mahasiswa';
if (!in_array($selectedRole, SIAKAD_ROLES, true)) {
    $selectedRole = 'mahasiswa';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'] ?? '';
    $identifier = trim($_POST['identifier'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if (!in_array($role, SIAKAD_ROLES, true) || $identifier === '' || $password === '') {
        $errorMsg = 'Lengkapi semua kolom terlebih dahulu.';
    } else {
        $pdo = siakad_db();
        [$table, $idField] = match ($role) {
            'admin'     => ['admin', 'username'],
            'dosen'     => ['dosen', 'nip'],
            'mahasiswa' => ['mahasiswa', 'nim'],
        };

        $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE {$idField} = ? LIMIT 1");
        $stmt->execute([$identifier]);
        $row = $stmt->fetch();

        if ($row && siakad_verify_password($password, $row['password'])) {
            siakad_login($role, $row, $idField, 'nama_lengkap');
            redirect(siakad_dashboard_url($role));
        } else {
            $errorMsg = 'Kombinasi ' . ($role === 'admin' ? 'username' : ($role === 'dosen' ? 'NIP' : 'NIM')) . ' dan password salah.';
            $selectedRole = $role;
        }
    }
}

$roleMeta = [
    'admin'     => ['label' => 'Admin',     'field_label' => 'Username',      'icon' => 'bi-shield-lock-fill', 'placeholder' => 'mis. admin'],
    'dosen'     => ['label' => 'Dosen',     'field_label' => 'NIP',           'icon' => 'bi-person-workspace', 'placeholder' => 'mis. D001'],
    'mahasiswa' => ['label' => 'Mahasiswa', 'field_label' => 'NIM',           'icon' => 'bi-mortarboard-fill', 'placeholder' => 'mis. 301230023'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Masuk — <?= APP_NAME ?></title>
<link rel="icon" href="data:,">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="login-body">

<div class="login-shell">

  <div class="login-brand-panel">
    <svg class="brand-pattern" viewBox="0 0 600 800" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
      <g stroke="#3B82F6" stroke-width="1" opacity="0.35">
        <line x1="40" y1="80" x2="180" y2="180"/>
        <line x1="180" y1="180" x2="120" y2="320"/>
        <line x1="180" y1="180" x2="340" y2="140"/>
        <line x1="340" y1="140" x2="500" y2="240"/>
        <line x1="120" y1="320" x2="260" y2="400"/>
        <line x1="260" y1="400" x2="460" y2="360"/>
        <line x1="460" y1="360" x2="500" y2="240"/>
        <line x1="260" y1="400" x2="220" y2="560"/>
        <line x1="220" y1="560" x2="360" y2="640"/>
        <line x1="360" y1="640" x2="460" y2="360"/>
        <line x1="220" y1="560" x2="120" y2="700"/>
        <line x1="360" y1="640" x2="440" y2="740"/>
      </g>
      <g fill="#60A5FA">
        <circle cx="40" cy="80" r="5"/>
        <circle cx="180" cy="180" r="6"/>
        <circle cx="340" cy="140" r="4"/>
        <circle cx="500" cy="240" r="6"/>
        <circle cx="120" cy="320" r="4"/>
        <circle cx="260" cy="400" r="7"/>
        <circle cx="460" cy="360" r="4"/>
        <circle cx="220" cy="560" r="5"/>
        <circle cx="360" cy="640" r="6"/>
        <circle cx="120" cy="700" r="4"/>
        <circle cx="440" cy="740" r="5"/>
      </g>
    </svg>
    <div class="login-brand-content">
      <div class="login-brand-mark"><i class="bi bi-mortarboard-fill"></i></div>
      <h1><?= APP_NAME ?></h1>
      <p class="login-brand-tagline"><?= APP_SUBTITLE ?> — <?= KAMPUS_NAMA ?></p>
      <ul class="login-brand-points">
        <li><i class="bi bi-journal-plus"></i> Isi KRS tiap semester dari mana saja</li>
        <li><i class="bi bi-pencil-square"></i> Dosen input nilai langsung untuk kelasnya</li>
        <li><i class="bi bi-graph-up-arrow"></i> IPK &amp; IPS terhitung otomatis</li>
      </ul>
    </div>
  </div>

  <div class="login-form-panel">
    <div class="login-form-card">
      <h2 class="login-title">Masuk ke akun Anda</h2>
      <p class="login-subtitle">Pilih peran, lalu masukkan kredensial Anda.</p>

      <div class="role-tabs" role="tablist">
        <?php foreach ($roleMeta as $key => $meta): ?>
        <button type="button" class="role-tab <?= $selectedRole === $key ? 'active' : '' ?>" data-role="<?= $key ?>">
          <i class="bi <?= $meta['icon'] ?>"></i> <?= $meta['label'] ?>
        </button>
        <?php endforeach; ?>
      </div>

      <?php if ($errorMsg): ?>
      <div class="alert alert-danger d-flex align-items-center gap-2 py-2" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i><div><?= h($errorMsg) ?></div>
      </div>
      <?php endif; ?>

      <form method="post" autocomplete="off">
        <input type="hidden" name="role" id="role-input" value="<?= h($selectedRole) ?>">
        <div class="mb-3">
          <label class="form-label" id="identifier-label" for="identifier"><?= $roleMeta[$selectedRole]['field_label'] ?></label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi <?= $roleMeta[$selectedRole]['icon'] ?>" id="identifier-icon"></i></span>
            <input type="text" class="form-control" id="identifier" name="identifier" placeholder="<?= h($roleMeta[$selectedRole]['placeholder']) ?>" required autofocus>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label" for="password">Password</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
            <input type="password" class="form-control" id="password" name="password" placeholder="••••••••" required>
            <button class="btn btn-outline-secondary" type="button" id="toggle-password"><i class="bi bi-eye"></i></button>
          </div>
        </div>
        <button type="submit" class="btn btn-primary w-100 login-submit">Masuk <i class="bi bi-arrow-right ms-1"></i></button>
      </form>

      <div class="demo-hint">
        <i class="bi bi-info-circle"></i>
        Akun demo: lihat <code>database/schema.sql</code> atau README.md
      </div>
    </div>
  </div>

</div>

<script>
const roleMeta = <?= json_encode($roleMeta) ?>;
const tabs = document.querySelectorAll('.role-tab');
const roleInput = document.getElementById('role-input');
const idLabel = document.getElementById('identifier-label');
const idIcon = document.getElementById('identifier-icon');
const idInput = document.getElementById('identifier');

tabs.forEach(tab => {
  tab.addEventListener('click', () => {
    tabs.forEach(t => t.classList.remove('active'));
    tab.classList.add('active');
    const role = tab.dataset.role;
    roleInput.value = role;
    idLabel.textContent = roleMeta[role].field_label;
    idIcon.className = 'bi ' + roleMeta[role].icon;
    idInput.placeholder = roleMeta[role].placeholder;
    idInput.value = '';
    idInput.focus();
  });
});

document.getElementById('toggle-password').addEventListener('click', function () {
  const pw = document.getElementById('password');
  const icon = this.querySelector('i');
  if (pw.type === 'password') {
    pw.type = 'text';
    icon.className = 'bi bi-eye-slash';
  } else {
    pw.type = 'password';
    icon.className = 'bi bi-eye';
  }
});
</script>
</body>
</html>
