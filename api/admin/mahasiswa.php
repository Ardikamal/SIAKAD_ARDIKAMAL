<?php
define('SIAKAD_APP', true);
require __DIR__ . '/../_config/constants.php';
require __DIR__ . '/../_config/database.php';
require __DIR__ . '/../_config/auth.php';
require __DIR__ . '/../_helpers/functions.php';
require __DIR__ . '/../_helpers/layout.php';

$user = siakad_require_role('admin');
$pdo = siakad_db();
$self = '/api/admin/mahasiswa.php';

// ---------------------------------------------------------------------------
// Proses form (POST) lebih dulu, lalu redirect (pola Post/Redirect/Get supaya
// data tidak terkirim ulang kalau halaman di-refresh).
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    siakad_csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'simpan') {
        $id = (int) ($_POST['id'] ?? 0);
        $nim = trim($_POST['nim'] ?? '');
        $nama = trim($_POST['nama_lengkap'] ?? '');
        $prodi = trim($_POST['prodi'] ?? '') ?: 'S1 Teknik Informatika';
        $angkatan = (int) ($_POST['angkatan'] ?? 0);
        $status = $_POST['status_akademik'] ?? 'Aktif';
        $password = (string) ($_POST['password'] ?? '');

        if ($nim === '' || $nama === '' || $angkatan < 2000) {
            redirect_with_flash($self, 'error', 'NIM, nama, dan angkatan wajib diisi dengan benar.');
        }

        try {
            if ($id > 0) {
                if ($password !== '') {
                    $stmt = $pdo->prepare("UPDATE mahasiswa SET nim=?, nama_lengkap=?, prodi=?, angkatan=?, status_akademik=?, password=? WHERE id=?");
                    $stmt->execute([$nim, $nama, $prodi, $angkatan, $status, siakad_hash_password($password), $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE mahasiswa SET nim=?, nama_lengkap=?, prodi=?, angkatan=?, status_akademik=? WHERE id=?");
                    $stmt->execute([$nim, $nama, $prodi, $angkatan, $status, $id]);
                }
                redirect_with_flash($self, 'success', 'Data mahasiswa berhasil diperbarui.');
            } else {
                if ($password === '') {
                    redirect_with_flash($self, 'error', 'Password wajib diisi untuk mahasiswa baru.');
                }
                $stmt = $pdo->prepare("INSERT INTO mahasiswa (nim, password, nama_lengkap, prodi, angkatan, status_akademik) VALUES (?,?,?,?,?,?)");
                $stmt->execute([$nim, siakad_hash_password($password), $nama, $prodi, $angkatan, $status]);
                redirect_with_flash($self, 'success', 'Mahasiswa baru berhasil ditambahkan.');
            }
        } catch (PDOException $e) {
            $msg = str_contains($e->getMessage(), 'Duplicate') ? 'NIM sudah terdaftar.' : 'Gagal menyimpan data.';
            redirect_with_flash($self, 'error', $msg);
        }
    }

    if ($action === 'hapus') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM mahasiswa WHERE id = ?");
        $stmt->execute([$id]);
        redirect_with_flash($self, 'success', 'Data mahasiswa dihapus (KRS & nilai terkait ikut terhapus).');
    }
}

// ---------------------------------------------------------------------------
// Tampilan (GET)
// ---------------------------------------------------------------------------
$formMode = $_GET['action'] ?? 'list';
$editData = null;
if ($formMode === 'edit') {
    $stmt = $pdo->prepare("SELECT * FROM mahasiswa WHERE id = ?");
    $stmt->execute([(int) ($_GET['id'] ?? 0)]);
    $editData = $stmt->fetch();
    if (!$editData) {
        redirect_with_flash($self, 'error', 'Data mahasiswa tidak ditemukan.');
    }
}

$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $stmt = $pdo->prepare("SELECT * FROM mahasiswa WHERE nama_lengkap LIKE ? OR nim LIKE ? ORDER BY angkatan DESC, nama_lengkap ASC");
    $like = "%{$q}%";
    $stmt->execute([$like, $like]);
} else {
    $stmt = $pdo->query("SELECT * FROM mahasiswa ORDER BY angkatan DESC, nama_lengkap ASC");
}
$daftarMahasiswa = $stmt->fetchAll();

layout_header('Kelola Mahasiswa', $user, 'mahasiswa');
?>

<?php if ($formMode === 'tambah' || $formMode === 'edit'): ?>

  <div class="card app-card">
    <div class="card-header"><i class="bi bi-<?= $formMode === 'edit' ? 'pencil-square' : 'person-plus-fill' ?> me-2"></i><?= $formMode === 'edit' ? 'Edit Mahasiswa' : 'Tambah Mahasiswa' ?></div>
    <div class="card-body">
      <form method="post" class="row g-3">
        <?= siakad_csrf_field() ?>
        <input type="hidden" name="action" value="simpan">
        <input type="hidden" name="id" value="<?= h((string) ($editData['id'] ?? '')) ?>">

        <div class="col-md-4">
          <label class="form-label">NIM</label>
          <input type="text" name="nim" class="form-control" required value="<?= h($editData['nim'] ?? '') ?>">
        </div>
        <div class="col-md-8">
          <label class="form-label">Nama Lengkap</label>
          <input type="text" name="nama_lengkap" class="form-control" required value="<?= h($editData['nama_lengkap'] ?? '') ?>">
        </div>
        <div class="col-md-5">
          <label class="form-label">Program Studi</label>
          <input type="text" name="prodi" class="form-control" value="<?= h($editData['prodi'] ?? 'S1 Teknik Informatika') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Angkatan</label>
          <input type="number" name="angkatan" class="form-control" required min="2000" max="2100" value="<?= h((string) ($editData['angkatan'] ?? date('Y'))) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Status Akademik</label>
          <select name="status_akademik" class="form-select">
            <?php foreach (['Aktif', 'Cuti', 'Lulus', 'Drop Out'] as $s): ?>
            <option value="<?= $s ?>" <?= ($editData['status_akademik'] ?? 'Aktif') === $s ? 'selected' : '' ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Password <?= $formMode === 'edit' ? '<span class="text-muted fw-normal">(kosongkan jika tidak diubah)</span>' : '' ?></label>
          <input type="password" name="password" class="form-control" <?= $formMode === 'tambah' ? 'required' : '' ?> placeholder="<?= $formMode === 'edit' ? '••••••••' : 'Buat password awal' ?>">
        </div>

        <div class="col-12 d-flex gap-2 mt-4">
          <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Simpan</button>
          <a href="<?= $self ?>" class="btn btn-outline-secondary">Batal</a>
        </div>
      </form>
    </div>
  </div>

<?php else: ?>

  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <form method="get" class="d-flex gap-2">
      <input type="search" name="q" class="form-control" placeholder="Cari nama atau NIM..." value="<?= h($q) ?>" style="min-width:240px">
      <button class="btn btn-outline-secondary"><i class="bi bi-search"></i></button>
    </form>
    <a href="<?= $self ?>?action=tambah" class="btn btn-primary"><i class="bi bi-person-plus-fill me-1"></i>Tambah Mahasiswa</a>
  </div>

  <div class="card app-card">
    <div class="card-body p-0">
      <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead><tr><th>NIM</th><th>Nama</th><th>Prodi</th><th>Angkatan</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
        <tbody>
          <?php if (!$daftarMahasiswa): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">Tidak ada data mahasiswa.</td></tr>
          <?php endif; ?>
          <?php foreach ($daftarMahasiswa as $m): ?>
          <tr>
            <td class="font-monospace"><?= h($m['nim']) ?></td>
            <td><?= h($m['nama_lengkap']) ?></td>
            <td><?= h($m['prodi']) ?></td>
            <td><?= h((string) $m['angkatan']) ?></td>
            <td><?= badge_status_akademik($m['status_akademik']) ?></td>
            <td class="text-end">
              <a href="<?= $self ?>?action=edit&id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
              <form method="post" class="d-inline" onsubmit="return confirm('Hapus mahasiswa <?= h(addslashes($m['nama_lengkap'])) ?>? KRS dan nilai terkait ikut terhapus.');">
                <?= siakad_csrf_field() ?>
                <input type="hidden" name="action" value="hapus">
                <input type="hidden" name="id" value="<?= $m['id'] ?>">
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </div>
  </div>

<?php endif; ?>

<?php layout_footer(); ?>
