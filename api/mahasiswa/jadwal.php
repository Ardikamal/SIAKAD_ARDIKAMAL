<?php
define('SIAKAD_APP', true);
require __DIR__ . '/../_config/constants.php';
require __DIR__ . '/../_config/database.php';
require __DIR__ . '/../_config/auth.php';
require __DIR__ . '/../_helpers/functions.php';
require __DIR__ . '/../_helpers/layout.php';

$user = siakad_require_role('mahasiswa');
$pdo = siakad_db();

$tahunAktif = $pdo->query("SELECT * FROM tahun_akademik WHERE is_aktif = 1 LIMIT 1")->fetch();

$jadwalPerHari = array_fill_keys(nama_hari_urut(), []);
if ($tahunAktif) {
    $stmt = $pdo->prepare("
        SELECT mk.kode_mk, mk.nama_mk, mk.sks, mk.hari, mk.jam_mulai, mk.jam_selesai, mk.ruangan, d.nama_lengkap AS nama_dosen
        FROM krs
        JOIN mata_kuliah mk ON mk.id = krs.mata_kuliah_id
        LEFT JOIN dosen d ON d.id = mk.dosen_id
        WHERE krs.mahasiswa_id = ? AND krs.tahun_akademik_id = ? AND krs.status = 'Disetujui' AND mk.hari IS NOT NULL
        ORDER BY mk.jam_mulai ASC
    ");
    $stmt->execute([$user['id'], $tahunAktif['id']]);
    foreach ($stmt->fetchAll() as $r) {
        $jadwalPerHari[$r['hari']][] = $r;
    }
}

layout_header('Jadwal Kuliah', $user, 'jadwal');
?>

<?php if (!$tahunAktif): ?>
  <div class="alert alert-warning">Belum ada tahun akademik aktif.</div>
<?php else: ?>
  <p class="text-muted mb-3">Jadwal berdasarkan KRS yang <strong>sudah disetujui</strong> untuk <?= h($tahunAktif['tahun'] . ' - ' . $tahunAktif['semester']) ?>.</p>

  <div class="row g-3">
    <?php foreach ($jadwalPerHari as $hari => $items): ?>
    <div class="col-md-6 col-xl-4">
      <div class="card app-card h-100">
        <div class="card-header"><i class="bi bi-calendar-day me-2"></i><?= $hari ?></div>
        <div class="card-body">
          <?php if (!$items): ?>
            <p class="text-muted small mb-0">Tidak ada jadwal.</p>
          <?php endif; ?>
          <?php foreach ($items as $it): ?>
          <div class="jadwal-item">
            <div class="jadwal-jam"><?= h($it['jam_mulai'] . ' – ' . $it['jam_selesai']) ?></div>
            <div class="fw-semibold"><?= h($it['nama_mk']) ?></div>
            <div class="text-muted small"><?= h($it['nama_dosen'] ?: '-') ?> · <?= h($it['ruangan'] ?: '-') ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php layout_footer(); ?>
