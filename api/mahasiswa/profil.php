<?php
define('SIAKAD_APP', true);
require __DIR__ . '/../_config/constants.php';
require __DIR__ . '/../_config/database.php';
require __DIR__ . '/../_config/auth.php';
require __DIR__ . '/../_helpers/functions.php';
require __DIR__ . '/../_helpers/layout.php';

$user = siakad_require_role('mahasiswa');
$pdo = siakad_db();
$self = '/api/mahasiswa/profil.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    siakad_csrf_check();
    $lama = (string) ($_POST['password_lama'] ?? '');
    $baru = (string) ($_POST['password_baru'] ?? '');
    $konfirmasi = (string) ($_POST['password_konfirmasi'] ?? '');

    $stmt = $pdo->prepare("SELECT password FROM mahasiswa WHERE id = ?");
    $stmt->execute([$user['id']]);
    $row = $stmt->fetch();

    if (!$row || !siakad_verify_password($lama, $row['password'])) {
        redirect_with_flash($self, 'error', 'Password lama tidak sesuai.');
    } elseif (strlen($baru) < 6) {
        redirect_with_flash($self, 'error', 'Password baru minimal 6 karakter.');
    } elseif ($baru !== $konfirmasi) {
        redirect_with_flash($self, 'error', 'Konfirmasi password baru tidak cocok.');
    } else {
        $stmt = $pdo->prepare("UPDATE mahasiswa SET password = ? WHERE id = ?");
        $stmt->execute([siakad_hash_password($baru), $user['id']]);
        redirect_with_flash($self, 'success', 'Password berhasil diperbarui.');
    }
}

$stmt = $pdo->prepare("SELECT * FROM mahasiswa WHERE id = ?");
$stmt->execute([$user['id']]);
$mhs = $stmt->fetch();

layout_header('Profil', $user, 'profil');
?>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card app-card">
      <div class="card-header"><i class="bi bi-person-circle me-2"></i>Data Diri</div>
      <div class="card-body">
        <table class="table table-borderless mb-0">
          <tr><td class="text-muted" style="width:140px">NIM</td><td class="fw-semibold font-monospace"><?= h($mhs['nim']) ?></td></tr>
          <tr><td class="text-muted">Nama</td><td class="fw-semibold"><?= h($mhs['nama_lengkap']) ?></td></tr>
          <tr><td class="text-muted">Program Studi</td><td><?= h($mhs['prodi']) ?></td></tr>
          <tr><td class="text-muted">Angkatan</td><td><?= h((string) $mhs['angkatan']) ?></td></tr>
          <tr><td class="text-muted">Status</td><td><?= badge_status_akademik($mhs['status_akademik']) ?></td></tr>
        </table>
        <p class="text-muted small mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>Hubungi admin untuk mengubah data diri.</p>
      </div>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="card app-card">
      <div class="card-header"><i class="bi bi-key-fill me-2"></i>Ganti Password</div>
      <div class="card-body">
        <form method="post" class="row g-3">
          <?= siakad_csrf_field() ?>
          <div class="col-12">
            <label class="form-label">Password Lama</label>
            <input type="password" name="password_lama" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Password Baru</label>
            <input type="password" name="password_baru" class="form-control" required minlength="6">
          </div>
          <div class="col-md-6">
            <label class="form-label">Konfirmasi Password Baru</label>
            <input type="password" name="password_konfirmasi" class="form-control" required minlength="6">
          </div>
          <div class="col-12">
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Perbarui Password</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php layout_footer(); ?>
