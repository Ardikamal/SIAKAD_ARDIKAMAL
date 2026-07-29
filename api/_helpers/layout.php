<?php
/**
 * layout.php — kerangka tampilan dashboard (AdminLTE + Bootstrap 5) yang
 * dipakai bersama oleh semua halaman admin/dosen/mahasiswa, supaya topbar,
 * sidebar, dan footer identitas konsisten di seluruh aplikasi tanpa perlu
 * mengulang HTML yang sama di setiap file.
 */
if (!defined('SIAKAD_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * @param string $title       Judul halaman (tampil di <title> & topbar)
 * @param array  $user        Hasil siakad_current_user()
 * @param string $activeMenu  Key menu yang sedang aktif (lihat siakad_menu_role())
 */
function layout_header(string $title, array $user, string $activeMenu = ''): void
{
    $role = $user['role'];
    $menu = siakad_menu_role($role);
    $roleLabel = match ($role) {
        'admin'     => 'Administrator',
        'dosen'     => 'Dosen',
        'mahasiswa' => 'Mahasiswa',
        default     => $role,
    };
    ?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($title) ?> — <?= APP_NAME ?></title>
<link rel="icon" href="data:,">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.4/styles/overlayscrollbars.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/admin-lte/4.0.1/css/adminlte.min.css">
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
<div class="app-wrapper">

  <nav class="app-header navbar navbar-expand bg-body">
    <div class="container-fluid">
      <ul class="navbar-nav">
        <li class="nav-item">
          <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button"><i class="bi bi-list fs-4"></i></a>
        </li>
      </ul>
      <ul class="navbar-nav ms-auto align-items-center">
        <li class="nav-item d-none d-md-block">
          <span class="badge rounded-pill role-badge role-badge-<?= h($role) ?> me-2"><?= h($roleLabel) ?></span>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" role="button" data-bs-toggle="dropdown">
            <span class="avatar-circle"><?= h(mb_substr($user['nama'], 0, 1)) ?></span>
            <span class="d-none d-sm-inline fw-semibold"><?= h($user['nama']) ?></span>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><h6 class="dropdown-header"><?= h($user['identifier']) ?></h6></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="/api/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Keluar</a></li>
          </ul>
        </li>
      </ul>
    </div>
  </nav>

  <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
    <div class="sidebar-brand">
      <a href="<?= h(siakad_dashboard_url($role)) ?>" class="brand-link">
        <i class="bi bi-mortarboard-fill brand-icon"></i>
        <span class="brand-text fw-bold"><?= APP_NAME ?></span>
      </a>
    </div>
    <div class="sidebar-wrapper">
      <nav class="mt-2">
        <ul class="nav sidebar-menu flex-column" role="menu">
          <?php foreach ($menu as $key => $item): ?>
          <li class="nav-item">
            <a href="<?= h($item['url']) ?>" class="nav-link <?= $activeMenu === $key ? 'active' : '' ?>">
              <i class="nav-icon bi <?= h($item['icon']) ?>"></i>
              <p><?= h($item['label']) ?></p>
            </a>
          </li>
          <?php endforeach; ?>
        </ul>
      </nav>
    </div>
  </aside>

  <main class="app-main">
    <div class="app-content-header">
      <div class="container-fluid">
        <h3 class="mb-0"><?= h($title) ?></h3>
      </div>
    </div>
    <div class="app-content">
      <div class="container-fluid">
        <?= render_flash() ?>
    <?php
}

function layout_footer(): void
{
    ?>
      </div>
    </div>
  </main>

  <footer class="app-footer">
    <div class="footer-identitas">
      <strong><?= APP_NAME ?></strong> — <?= APP_SUBTITLE ?> · <?= KAMPUS_NAMA ?>
      <span class="d-none d-md-inline"> · Dibuat oleh <?= PENULIS_NAMA ?> (NIM <?= PENULIS_NIM ?>, Kelas <?= PENULIS_KELAS ?>) — <?= PENULIS_PRODI ?>, <?= PENULIS_FAKULTAS ?></span>
    </div>
  </footer>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.4/browser/overlayscrollbars.browser.es6.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/admin-lte/4.0.1/js/adminlte.min.js"></script>
<script src="/assets/js/app.js"></script>
</body>
</html>
    <?php
}

/** Konfigurasi menu sidebar per role. Key dipakai untuk menyorot menu aktif ($activeMenu). */
function siakad_menu_role(string $role): array
{
    return match ($role) {
        'admin' => [
            'dashboard'      => ['label' => 'Dashboard',        'url' => '/api/admin/dashboard.php',      'icon' => 'bi-speedometer2'],
            'mahasiswa'      => ['label' => 'Kelola Mahasiswa', 'url' => '/api/admin/mahasiswa.php',      'icon' => 'bi-people-fill'],
            'dosen'          => ['label' => 'Kelola Dosen',     'url' => '/api/admin/dosen.php',          'icon' => 'bi-person-workspace'],
            'mata_kuliah'    => ['label' => 'Mata Kuliah',      'url' => '/api/admin/mata_kuliah.php',    'icon' => 'bi-journal-bookmark-fill'],
            'tahun_akademik' => ['label' => 'Tahun Akademik',   'url' => '/api/admin/tahun_akademik.php', 'icon' => 'bi-calendar3'],
            'krs'            => ['label' => 'Persetujuan KRS',  'url' => '/api/admin/krs.php',            'icon' => 'bi-clipboard-check-fill'],
            'nilai'          => ['label' => 'Rekap Nilai',      'url' => '/api/admin/nilai.php',          'icon' => 'bi-award-fill'],
        ],
        'dosen' => [
            'dashboard' => ['label' => 'Dashboard', 'url' => '/api/dosen/dashboard.php', 'icon' => 'bi-speedometer2'],
            'nilai'     => ['label' => 'Isi Nilai',  'url' => '/api/dosen/nilai.php',     'icon' => 'bi-pencil-square'],
        ],
        'mahasiswa' => [
            'dashboard' => ['label' => 'Dashboard',        'url' => '/api/mahasiswa/dashboard.php', 'icon' => 'bi-speedometer2'],
            'krs'       => ['label' => 'Isi KRS',           'url' => '/api/mahasiswa/krs.php',       'icon' => 'bi-journal-plus'],
            'jadwal'    => ['label' => 'Jadwal Kuliah',     'url' => '/api/mahasiswa/jadwal.php',    'icon' => 'bi-calendar-week'],
            'nilai'     => ['label' => 'Nilai & Transkrip', 'url' => '/api/mahasiswa/nilai.php',     'icon' => 'bi-award-fill'],
            'profil'    => ['label' => 'Profil',            'url' => '/api/mahasiswa/profil.php',    'icon' => 'bi-person-circle'],
        ],
        default => [],
    };
}
