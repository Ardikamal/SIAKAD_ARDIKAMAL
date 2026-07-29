<?php
define('SIAKAD_APP', true);
require __DIR__ . '/../_config/constants.php';
require __DIR__ . '/../_config/database.php';
require __DIR__ . '/../_config/auth.php';
require __DIR__ . '/../_helpers/functions.php';
require __DIR__ . '/../_helpers/layout.php';

$user = siakad_require_role('admin');
$pdo = siakad_db();
$self = '/api/admin/tahun_akademik.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    siakad_csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'tambah') {
        $tahun = trim($_POST['tahun'] ?? '');
        $semester = $_POST['semester'] ?? '';
        if ($tahun === '' || !in_array($semester, ['Ganjil', 'Genap'], true)) {
            redirect_with_flash($self, 'error', 'Tahun dan semester wajib diisi.');
        }
        try {
            $stmt = $pdo->prepare("INSERT INTO tahun_akademik (tahun, semester, is_aktif) VALUES (?,?,0)");
            $stmt->execute([$tahun, $semester]);
            redirect_with_flash($self, 'success', 'Tahun akademik ditambahkan.');
        } catch (PDOException $e) {
            redirect_with_flash($self, 'error', 'Tahun akademik + semester tersebut sudah ada.');
        }
    }

    if ($action === 'aktifkan') {
        $id = (int) ($_POST['id'] ?? 0);
        $pdo->beginTransaction();
        $pdo->exec("UPDATE tahun_akademik SET is_aktif = 0");
        $stmt = $pdo->prepare("UPDATE tahun_akademik SET is_aktif = 1 WHERE id = ?");
        $stmt->execute([$id]);
        $pdo->commit();
        redirect_with_flash($self, 'success', 'Tahun akademik aktif berhasil diperbarui.');
    }

    if ($action === 'hapus') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM tahun_akademik WHERE id = ?");
        $stmt->execute([$id]);
        redirect_with_flash($self, 'success', 'Tahun akademik dihapus.');
    }
}

$daftarTA = $pdo->query("SELECT * FROM tahun_akademik ORDER BY tahun DESC, semester ASC")->fetchAll();

layout_header('Tahun Akademik', $user, 'tahun_akademik');
?>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card app-card">
      <div class="card-header"><i class="bi bi-plus-circle me-2"></i>Tambah Tahun Akademik</div>
      <div class="card-body">
        <form method="post" class="row g-3">
          <?= siakad_csrf_field() ?>
          <input type="hidden" name="action" value="tambah">
          <div class="col-12">
            <label class="form-label">Tahun Ajaran</label>
            <input type="text" name="tahun" class="form-control" placeholder="mis. 2026/2027" required>
          </div>
          <div class="col-12">
            <label class="form-label">Semester</label>
            <select name="semester" class="form-select">
              <option value="Ganjil">Ganjil</option>
              <option value="Genap">Genap</option>
            </select>
          </div>
          <div class="col-12">
            <button class="btn btn-primary w-100"><i class="bi bi-check-lg me-1"></i>Tambahkan</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="card app-card">
      <div class="card-header"><i class="bi bi-calendar3 me-2"></i>Daftar Tahun Akademik</div>
      <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead><tr><th>Tahun</th><th>Semester</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
          <tbody>
            <?php if (!$daftarTA): ?>
              <tr><td colspan="4" class="text-center text-muted py-4">Belum ada data.</td></tr>
            <?php endif; ?>
            <?php foreach ($daftarTA as $ta): ?>
            <tr>
              <td><?= h($ta['tahun']) ?></td>
              <td><?= h($ta['semester']) ?></td>
              <td>
                <?php if ($ta['is_aktif']): ?>
                  <span class="badge text-bg-success"><i class="bi bi-check-circle-fill me-1"></i>Aktif</span>
                <?php else: ?>
                  <span class="badge text-bg-secondary">Tidak aktif</span>
                <?php endif; ?>
              </td>
              <td class="text-end">
                <?php if (!$ta['is_aktif']): ?>
                <form method="post" class="d-inline">
                  <?= siakad_csrf_field() ?>
                  <input type="hidden" name="action" value="aktifkan">
                  <input type="hidden" name="id" value="<?= $ta['id'] ?>">
                  <button class="btn btn-sm btn-outline-success">Jadikan Aktif</button>
                </form>
                <form method="post" class="d-inline" onsubmit="return confirm('Hapus tahun akademik ini? KRS/nilai yang terkait ikut terhapus.');">
                  <?= siakad_csrf_field() ?>
                  <input type="hidden" name="action" value="hapus">
                  <input type="hidden" name="id" value="<?= $ta['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php layout_footer(); ?>
