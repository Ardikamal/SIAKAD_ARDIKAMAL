<?php
define('SIAKAD_APP', true);
require __DIR__ . '/../_config/constants.php';
require __DIR__ . '/../_config/database.php';
require __DIR__ . '/../_config/auth.php';
require __DIR__ . '/../_helpers/functions.php';
require __DIR__ . '/../_helpers/layout.php';

$user = siakad_require_role('admin');
$pdo = siakad_db();
$self = '/api/admin/krs.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    siakad_csrf_check();
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if (in_array($action, ['setujui', 'tolak'], true)) {
        $status = $action === 'setujui' ? 'Disetujui' : 'Ditolak';
        $stmt = $pdo->prepare("UPDATE krs SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        redirect_with_flash($self, 'success', 'Status KRS diperbarui menjadi ' . $status . '.');
    }
}

$taFilter = (int) ($_GET['ta'] ?? 0);
$statusFilter = $_GET['status'] ?? '';

$daftarTA = $pdo->query("SELECT * FROM tahun_akademik ORDER BY tahun DESC, semester ASC")->fetchAll();
if ($taFilter === 0) {
    $aktif = array_values(array_filter($daftarTA, fn($t) => (int) $t['is_aktif'] === 1));
    $taFilter = $aktif ? (int) $aktif[0]['id'] : ($daftarTA[0]['id'] ?? 0);
}

$sql = "
    SELECT krs.*, m.nama_lengkap AS nama_mhs, m.nim, mk.nama_mk, mk.kode_mk, mk.sks
    FROM krs
    JOIN mahasiswa m ON m.id = krs.mahasiswa_id
    JOIN mata_kuliah mk ON mk.id = krs.mata_kuliah_id
    WHERE krs.tahun_akademik_id = ?
";
$params = [$taFilter];
if (in_array($statusFilter, ['Diajukan', 'Disetujui', 'Ditolak'], true)) {
    $sql .= " AND krs.status = ?";
    $params[] = $statusFilter;
}
$sql .= " ORDER BY krs.status = 'Diajukan' DESC, m.nama_lengkap ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$daftarKrs = $stmt->fetchAll();

layout_header('Persetujuan KRS', $user, 'krs');
?>

<div class="card app-card mb-3">
  <div class="card-body">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label small text-muted mb-1">Tahun Akademik</label>
        <select name="ta" class="form-select" onchange="this.form.submit()">
          <?php foreach ($daftarTA as $ta): ?>
          <option value="<?= $ta['id'] ?>" <?= $taFilter === (int) $ta['id'] ? 'selected' : '' ?>>
            <?= h($ta['tahun'] . ' - ' . $ta['semester']) ?><?= $ta['is_aktif'] ? ' (Aktif)' : '' ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label small text-muted mb-1">Status</label>
        <select name="status" class="form-select" onchange="this.form.submit()">
          <option value="">Semua Status</option>
          <?php foreach (['Diajukan', 'Disetujui', 'Ditolak'] as $s): ?>
          <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>
  </div>
</div>

<div class="card app-card">
  <div class="card-body p-0">
    <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Mahasiswa</th><th>Mata Kuliah</th><th>SKS</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
      <tbody>
        <?php if (!$daftarKrs): ?>
          <tr><td colspan="5" class="text-center text-muted py-4">Tidak ada pengajuan KRS untuk filter ini.</td></tr>
        <?php endif; ?>
        <?php foreach ($daftarKrs as $k): ?>
        <tr>
          <td><?= h($k['nama_mhs']) ?><div class="text-muted small font-monospace"><?= h($k['nim']) ?></div></td>
          <td><?= h($k['kode_mk']) ?> — <?= h($k['nama_mk']) ?></td>
          <td><?= (int) $k['sks'] ?></td>
          <td><?= badge_status_krs($k['status']) ?></td>
          <td class="text-end">
            <?php if ($k['status'] !== 'Disetujui'): ?>
            <form method="post" class="d-inline">
              <?= siakad_csrf_field() ?>
              <input type="hidden" name="action" value="setujui">
              <input type="hidden" name="id" value="<?= $k['id'] ?>">
              <button class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i> Setujui</button>
            </form>
            <?php endif; ?>
            <?php if ($k['status'] !== 'Ditolak'): ?>
            <form method="post" class="d-inline">
              <?= siakad_csrf_field() ?>
              <input type="hidden" name="action" value="tolak">
              <input type="hidden" name="id" value="<?= $k['id'] ?>">
              <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg"></i> Tolak</button>
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

<?php layout_footer(); ?>
