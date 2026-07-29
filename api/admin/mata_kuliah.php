<?php
define('SIAKAD_APP', true);
require __DIR__ . '/../_config/constants.php';
require __DIR__ . '/../_config/database.php';
require __DIR__ . '/../_config/auth.php';
require __DIR__ . '/../_helpers/functions.php';
require __DIR__ . '/../_helpers/layout.php';

$user = siakad_require_role('admin');
$pdo = siakad_db();
$self = '/api/admin/mata_kuliah.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    siakad_csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'simpan') {
        $id = (int) ($_POST['id'] ?? 0);
        $kode = trim($_POST['kode_mk'] ?? '');
        $nama = trim($_POST['nama_mk'] ?? '');
        $sks = (int) ($_POST['sks'] ?? 0);
        $semester = (int) ($_POST['semester'] ?? 0);
        $dosenId = $_POST['dosen_id'] !== '' ? (int) $_POST['dosen_id'] : null;
        $hari = $_POST['hari'] !== '' ? $_POST['hari'] : null;
        $jamMulai = trim($_POST['jam_mulai'] ?? '') ?: null;
        $jamSelesai = trim($_POST['jam_selesai'] ?? '') ?: null;
        $ruangan = trim($_POST['ruangan'] ?? '') ?: null;

        if ($kode === '' || $nama === '' || $sks < 1 || $semester < 1) {
            redirect_with_flash($self, 'error', 'Kode, nama, SKS, dan semester wajib diisi dengan benar.');
        }

        try {
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE mata_kuliah SET kode_mk=?, nama_mk=?, sks=?, semester=?, dosen_id=?, hari=?, jam_mulai=?, jam_selesai=?, ruangan=? WHERE id=?");
                $stmt->execute([$kode, $nama, $sks, $semester, $dosenId, $hari, $jamMulai, $jamSelesai, $ruangan, $id]);
                redirect_with_flash($self, 'success', 'Mata kuliah berhasil diperbarui.');
            } else {
                $stmt = $pdo->prepare("INSERT INTO mata_kuliah (kode_mk, nama_mk, sks, semester, dosen_id, hari, jam_mulai, jam_selesai, ruangan) VALUES (?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$kode, $nama, $sks, $semester, $dosenId, $hari, $jamMulai, $jamSelesai, $ruangan]);
                redirect_with_flash($self, 'success', 'Mata kuliah baru berhasil ditambahkan.');
            }
        } catch (PDOException $e) {
            $msg = str_contains($e->getMessage(), 'Duplicate') ? 'Kode mata kuliah sudah dipakai.' : 'Gagal menyimpan data.';
            redirect_with_flash($self, 'error', $msg);
        }
    }

    if ($action === 'hapus') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM mata_kuliah WHERE id = ?");
        $stmt->execute([$id]);
        redirect_with_flash($self, 'success', 'Mata kuliah dihapus (KRS & nilai terkait ikut terhapus).');
    }
}

$formMode = $_GET['action'] ?? 'list';
$editData = null;
if ($formMode === 'edit') {
    $stmt = $pdo->prepare("SELECT * FROM mata_kuliah WHERE id = ?");
    $stmt->execute([(int) ($_GET['id'] ?? 0)]);
    $editData = $stmt->fetch();
    if (!$editData) {
        redirect_with_flash($self, 'error', 'Mata kuliah tidak ditemukan.');
    }
}

$daftarDosen = $pdo->query("SELECT id, nama_lengkap FROM dosen ORDER BY nama_lengkap ASC")->fetchAll();
$daftarMatkul = $pdo->query("
    SELECT mk.*, d.nama_lengkap AS nama_dosen
    FROM mata_kuliah mk LEFT JOIN dosen d ON d.id = mk.dosen_id
    ORDER BY mk.semester ASC, mk.kode_mk ASC
")->fetchAll();

layout_header('Mata Kuliah', $user, 'mata_kuliah');
?>

<?php if ($formMode === 'tambah' || $formMode === 'edit'): ?>

  <div class="card app-card">
    <div class="card-header"><i class="bi bi-<?= $formMode === 'edit' ? 'pencil-square' : 'journal-plus' ?> me-2"></i><?= $formMode === 'edit' ? 'Edit Mata Kuliah' : 'Tambah Mata Kuliah' ?></div>
    <div class="card-body">
      <form method="post" class="row g-3">
        <?= siakad_csrf_field() ?>
        <input type="hidden" name="action" value="simpan">
        <input type="hidden" name="id" value="<?= h((string) ($editData['id'] ?? '')) ?>">

        <div class="col-md-3">
          <label class="form-label">Kode MK</label>
          <input type="text" name="kode_mk" class="form-control" required value="<?= h($editData['kode_mk'] ?? '') ?>">
        </div>
        <div class="col-md-9">
          <label class="form-label">Nama Mata Kuliah</label>
          <input type="text" name="nama_mk" class="form-control" required value="<?= h($editData['nama_mk'] ?? '') ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">SKS</label>
          <input type="number" name="sks" class="form-control" required min="1" max="6" value="<?= h((string) ($editData['sks'] ?? 3)) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Semester Kurikulum</label>
          <input type="number" name="semester" class="form-control" required min="1" max="8" value="<?= h((string) ($editData['semester'] ?? 1)) ?>">
        </div>
        <div class="col-md-7">
          <label class="form-label">Dosen Pengampu</label>
          <select name="dosen_id" class="form-select">
            <option value="">— Belum ditentukan —</option>
            <?php foreach ($daftarDosen as $d): ?>
            <option value="<?= $d['id'] ?>" <?= (int) ($editData['dosen_id'] ?? 0) === (int) $d['id'] ? 'selected' : '' ?>><?= h($d['nama_lengkap']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Hari</label>
          <select name="hari" class="form-select">
            <option value="">—</option>
            <?php foreach (nama_hari_urut() as $hr): ?>
            <option value="<?= $hr ?>" <?= ($editData['hari'] ?? '') === $hr ? 'selected' : '' ?>><?= $hr ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Jam Mulai</label>
          <input type="time" name="jam_mulai" class="form-control" value="<?= h($editData['jam_mulai'] ?? '') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Jam Selesai</label>
          <input type="time" name="jam_selesai" class="form-control" value="<?= h($editData['jam_selesai'] ?? '') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Ruangan</label>
          <input type="text" name="ruangan" class="form-control" value="<?= h($editData['ruangan'] ?? '') ?>">
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
    <a href="<?= $self ?>?action=tambah" class="btn btn-primary"><i class="bi bi-journal-plus me-1"></i>Tambah Mata Kuliah</a>
  </div>

  <div class="card app-card">
    <div class="card-body p-0">
      <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Kode</th><th>Nama Mata Kuliah</th><th>SKS</th><th>Smt</th><th>Dosen</th><th>Jadwal</th><th class="text-end">Aksi</th></tr></thead>
        <tbody>
          <?php if (!$daftarMatkul): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">Belum ada mata kuliah.</td></tr>
          <?php endif; ?>
          <?php foreach ($daftarMatkul as $mk): ?>
          <tr>
            <td class="font-monospace"><?= h($mk['kode_mk']) ?></td>
            <td><?= h($mk['nama_mk']) ?></td>
            <td><?= (int) $mk['sks'] ?></td>
            <td><?= (int) $mk['semester'] ?></td>
            <td><?= h($mk['nama_dosen'] ?: '-') ?></td>
            <td class="small text-muted">
              <?= $mk['hari'] ? h($mk['hari'] . ' ' . $mk['jam_mulai'] . '–' . $mk['jam_selesai']) : '-' ?>
              <?= $mk['ruangan'] ? '<br>' . h($mk['ruangan']) : '' ?>
            </td>
            <td class="text-end">
              <a href="<?= $self ?>?action=edit&id=<?= $mk['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
              <form method="post" class="d-inline" onsubmit="return confirm('Hapus mata kuliah <?= h(addslashes($mk['nama_mk'])) ?>?');">
                <?= siakad_csrf_field() ?>
                <input type="hidden" name="action" value="hapus">
                <input type="hidden" name="id" value="<?= $mk['id'] ?>">
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
