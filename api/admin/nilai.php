<?php
define('SIAKAD_APP', true);
require __DIR__ . '/../_config/constants.php';
require __DIR__ . '/../_config/database.php';
require __DIR__ . '/../_config/auth.php';
require __DIR__ . '/../_helpers/functions.php';
require __DIR__ . '/../_helpers/layout.php';

$user = siakad_require_role('admin');
$pdo = siakad_db();

$taFilter = (int) ($_GET['ta'] ?? 0);
$daftarTA = $pdo->query("SELECT * FROM tahun_akademik ORDER BY tahun DESC, semester ASC")->fetchAll();
if ($taFilter === 0 && $daftarTA) {
    $aktif = array_values(array_filter($daftarTA, fn($t) => (int) $t['is_aktif'] === 1));
    $taFilter = $aktif ? (int) $aktif[0]['id'] : (int) $daftarTA[0]['id'];
}

$stmt = $pdo->prepare("
    SELECT n.*, m.nama_lengkap AS nama_mhs, m.nim, mk.nama_mk, mk.kode_mk, mk.sks, d.nama_lengkap AS nama_dosen
    FROM nilai n
    JOIN mahasiswa m ON m.id = n.mahasiswa_id
    JOIN mata_kuliah mk ON mk.id = n.mata_kuliah_id
    LEFT JOIN dosen d ON d.id = mk.dosen_id
    WHERE n.tahun_akademik_id = ?
    ORDER BY m.nama_lengkap ASC, mk.kode_mk ASC
");
$stmt->execute([$taFilter]);
$daftarNilai = $stmt->fetchAll();

layout_header('Rekap Nilai', $user, 'nilai');
?>

<div class="card app-card mb-3">
  <div class="card-body">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-md-5">
        <label class="form-label small text-muted mb-1">Tahun Akademik</label>
        <select name="ta" class="form-select" onchange="this.form.submit()">
          <?php foreach ($daftarTA as $ta): ?>
          <option value="<?= $ta['id'] ?>" <?= $taFilter === (int) $ta['id'] ? 'selected' : '' ?>>
            <?= h($ta['tahun'] . ' - ' . $ta['semester']) ?><?= $ta['is_aktif'] ? ' (Aktif)' : '' ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>
  </div>
</div>

<div class="card app-card">
  <div class="card-header"><i class="bi bi-award-fill me-2"></i>Nilai Semua Mahasiswa</div>
  <div class="card-body p-0">
    <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Mahasiswa</th><th>Mata Kuliah</th><th>Dosen</th><th class="text-center">Nilai</th><th class="text-center">Huruf</th><th class="text-center">Bobot</th></tr></thead>
      <tbody>
        <?php if (!$daftarNilai): ?>
          <tr><td colspan="6" class="text-center text-muted py-4">Belum ada nilai untuk tahun akademik ini.</td></tr>
        <?php endif; ?>
        <?php foreach ($daftarNilai as $n): ?>
        <tr>
          <td><?= h($n['nama_mhs']) ?><div class="text-muted small font-monospace"><?= h($n['nim']) ?></div></td>
          <td><?= h($n['kode_mk']) ?> — <?= h($n['nama_mk']) ?></td>
          <td><?= h($n['nama_dosen'] ?: '-') ?></td>
          <td class="text-center fw-semibold"><?= number_format((float) $n['nilai_angka'], 1) ?></td>
          <td class="text-center"><span class="badge nilai-huruf-badge"><?= h($n['nilai_huruf']) ?></span></td>
          <td class="text-center"><?= number_format((float) $n['bobot'], 2) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>
</div>

<?php layout_footer(); ?>
