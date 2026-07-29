<?php
define('SIAKAD_APP', true);
require __DIR__ . '/../_config/constants.php';
require __DIR__ . '/../_config/database.php';
require __DIR__ . '/../_config/auth.php';
require __DIR__ . '/../_helpers/functions.php';
require __DIR__ . '/../_helpers/layout.php';

$user = siakad_require_role('admin');
$pdo = siakad_db();
$self = '/api/admin/dosen.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    siakad_csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'simpan') {
        $id = (int) ($_POST['id'] ?? 0);
        $nip = trim($_POST['nip'] ?? '');
        $nama = trim($_POST['nama_lengkap'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        if ($nip === '' || $nama === '') {
            redirect_with_flash($self, 'error', 'NIP dan nama wajib diisi.');
        }

        try {
            if ($id > 0) {
                if ($password !== '') {
                    $stmt = $pdo->prepare("UPDATE dosen SET nip=?, nama_lengkap=?, email=?, password=? WHERE id=?");
                    $stmt->execute([$nip, $nama, $email, siakad_hash_password($password), $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE dosen SET nip=?, nama_lengkap=?, email=? WHERE id=?");
                    $stmt->execute([$nip, $nama, $email, $id]);
                }
                redirect_with_flash($self, 'success', 'Data dosen berhasil diperbarui.');
            } else {
                if ($password === '') {
                    redirect_with_flash($self, 'error', 'Password wajib diisi untuk dosen baru.');
                }
                $stmt = $pdo->prepare("INSERT INTO dosen (nip, password, nama_lengkap, email) VALUES (?,?,?,?)");
                $stmt->execute([$nip, siakad_hash_password($password), $nama, $email]);
                redirect_with_flash($self, 'success', 'Dosen baru berhasil ditambahkan.');
            }
        } catch (PDOException $e) {
            $msg = str_contains($e->getMessage(), 'Duplicate') ? 'NIP sudah terdaftar.' : 'Gagal menyimpan data.';
            redirect_with_flash($self, 'error', $msg);
        }
    }

    if ($action === 'hapus') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM dosen WHERE id = ?");
        $stmt->execute([$id]);
        redirect_with_flash($self, 'success', 'Data dosen dihapus. Mata kuliah yang diampu jadi tanpa dosen pengampu.');
    }
}

$formMode = $_GET['action'] ?? 'list';
$editData = null;
if ($formMode === 'edit') {
    $stmt = $pdo->prepare("SELECT * FROM dosen WHERE id = ?");
    $stmt->execute([(int) ($_GET['id'] ?? 0)]);
    $editData = $stmt->fetch();
    if (!$editData) {
        redirect_with_flash($self, 'error', 'Data dosen tidak ditemukan.');
    }
}

$daftarDosen = $pdo->query("
    SELECT d.*, (SELECT COUNT(*) FROM mata_kuliah mk WHERE mk.dosen_id = d.id) AS jumlah_matkul
    FROM dosen d ORDER BY d.nama_lengkap ASC
")->fetchAll();

layout_header('Kelola Dosen', $user, 'dosen');
?>

<?php if ($formMode === 'tambah' || $formMode === 'edit'): ?>

  <div class="card app-card">
    <div class="card-header"><i class="bi bi-<?= $formMode === 'edit' ? 'pencil-square' : 'person-plus-fill' ?> me-2"></i><?= $formMode === 'edit' ? 'Edit Dosen' : 'Tambah Dosen' ?></div>
    <div class="card-body">
      <form method="post" class="row g-3">
        <?= siakad_csrf_field() ?>
        <input type="hidden" name="action" value="simpan">
        <input type="hidden" name="id" value="<?= h((string) ($editData['id'] ?? '')) ?>">

        <div class="col-md-4">
          <label class="form-label">NIP</label>
          <input type="text" name="nip" class="form-control" required value="<?= h($editData['nip'] ?? '') ?>">
        </div>
        <div class="col-md-8">
          <label class="form-label">Nama Lengkap (+ gelar)</label>
          <input type="text" name="nama_lengkap" class="form-control" required value="<?= h($editData['nama_lengkap'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" value="<?= h($editData['email'] ?? '') ?>">
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

  <div class="d-flex justify-content-end mb-3">
    <a href="<?= $self ?>?action=tambah" class="btn btn-primary"><i class="bi bi-person-plus-fill me-1"></i>Tambah Dosen</a>
  </div>

  <div class="card app-card">
    <div class="card-body p-0">
      <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead><tr><th>NIP</th><th>Nama</th><th>Email</th><th>Mata Kuliah Diampu</th><th class="text-end">Aksi</th></tr></thead>
        <tbody>
          <?php if (!$daftarDosen): ?>
            <tr><td colspan="5" class="text-center text-muted py-4">Tidak ada data dosen.</td></tr>
          <?php endif; ?>
          <?php foreach ($daftarDosen as $d): ?>
          <tr>
            <td class="font-monospace"><?= h($d['nip']) ?></td>
            <td><?= h($d['nama_lengkap']) ?></td>
            <td><?= h($d['email'] ?: '-') ?></td>
            <td><span class="badge text-bg-secondary"><?= (int) $d['jumlah_matkul'] ?> matkul</span></td>
            <td class="text-end">
              <a href="<?= $self ?>?action=edit&id=<?= $d['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
              <form method="post" class="d-inline" onsubmit="return confirm('Hapus dosen <?= h(addslashes($d['nama_lengkap'])) ?>?');">
                <?= siakad_csrf_field() ?>
                <input type="hidden" name="action" value="hapus">
                <input type="hidden" name="id" value="<?= $d['id'] ?>">
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
