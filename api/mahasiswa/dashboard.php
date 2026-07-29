<?php
define('SIAKAD_APP', true);
require __DIR__ . '/../_config/constants.php';
require __DIR__ . '/../_config/database.php';
require __DIR__ . '/../_config/auth.php';
require __DIR__ . '/../_helpers/functions.php';
require __DIR__ . '/../_helpers/layout.php';

$user = siakad_require_role('mahasiswa');
$pdo = siakad_db();

$stmt = $pdo->prepare("SELECT * FROM mahasiswa WHERE id = ?");
$stmt->execute([$user['id']]);
$mhs = $stmt->fetch();

$tahunAktif = $pdo->query("SELECT * FROM tahun_akademik WHERE is_aktif = 1 LIMIT 1")->fetch();

// IPK keseluruhan (semua semester)
$stmt = $pdo->prepare("
    SELECT n.bobot, mk.sks FROM nilai n JOIN mata_kuliah mk ON mk.id = n.mata_kuliah_id
    WHERE n.mahasiswa_id = ?
");
$stmt->execute([$user['id']]);
$ipkData = hitung_ipk($stmt->fetchAll());

// IPS per semester (untuk histori)
$stmt = $pdo->prepare("
    SELECT ta.id, ta.tahun, ta.semester, n.bobot, mk.sks
    FROM nilai n
    JOIN mata_kuliah mk ON mk.id = n.mata_kuliah_id
    JOIN tahun_akademik ta ON ta.id = n.tahun_akademik_id
    WHERE n.mahasiswa_id = ?
    ORDER BY ta.id ASC
");
$stmt->execute([$user['id']]);
$rows = $stmt->fetchAll();
$perSemester = [];
foreach ($rows as $r) {
    $key = $r['id'];
    $perSemester[$key]['label'] = $r['tahun'] . ' - ' . $r['semester'];
    $perSemester[$key]['items'][] = $r;
}
$ipsHistori = [];
foreach ($perSemester as $key => $data) {
    $hasil = hitung_ipk($data['items']);
    $ipsHistori[] = ['label' => $data['label'], 'ips' => $hasil['ipk'], 'sks' => $hasil['total_sks']];
}

// KRS semester aktif — ringkasan
$krsAktif = [];
if ($tahunAktif) {
    $stmt = $pdo->prepare("
        SELECT krs.status, mk.nama_mk, mk.kode_mk, mk.sks
        FROM krs JOIN mata_kuliah mk ON mk.id = krs.mata_kuliah_id
        WHERE krs.mahasiswa_id = ? AND krs.tahun_akademik_id = ?
        ORDER BY mk.kode_mk ASC
    ");
    $stmt->execute([$user['id'], $tahunAktif['id']]);
    $krsAktif = $stmt->fetchAll();
}
$sksAktif = array_sum(array_map(fn($k) => (int) $k['sks'], array_filter($krsAktif, fn($k) => $k['status'] !== 'Ditolak')));

$ipsSebelumnya = $tahunAktif ? ips_semester_terakhir($pdo, $user['id'], (int) $tahunAktif['id']) : null;
$batasSks = batas_sks_dari_ips($ipsSebelumnya);

layout_header('Dashboard', $user, 'dashboard');
?>

<div class="row g-3 mb-2">
  <div class="col-6 col-lg-3">
    <div class="stat-card stat-card-primary">
      <div class="stat-icon"><i class="bi bi-graph-up-arrow"></i></div>
      <div><div class="stat-value"><?= number_format($ipkData['ipk'], 2) ?></div><div class="stat-label">IPK Kumulatif</div></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card stat-card-info">
      <div class="stat-icon"><i class="bi bi-journal-check"></i></div>
      <div><div class="stat-value"><?= $ipkData['total_sks'] ?></div><div class="stat-label">Total SKS Lulus</div></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card stat-card-success">
      <div class="stat-icon"><i class="bi bi-clipboard-check-fill"></i></div>
      <div><div class="stat-value"><?= $sksAktif ?>/<?= $batasSks ?></div><div class="stat-label">SKS Semester Ini</div></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card stat-card-warning">
      <div class="stat-icon"><i class="bi bi-person-check-fill"></i></div>
      <div><?= badge_status_akademik($mhs['status_akademik']) ?><div class="stat-label mt-1">Status Akademik</div></div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card app-card h-100">
      <div class="card-header"><i class="bi bi-bar-chart-line-fill me-2"></i>Riwayat IPS per Semester</div>
      <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead><tr><th>Semester</th><th class="text-center">SKS</th><th class="text-center">IPS</th></tr></thead>
          <tbody>
            <?php if (!$ipsHistori): ?>
              <tr><td colspan="3" class="text-center text-muted py-4">Belum ada nilai tercatat.</td></tr>
            <?php endif; ?>
            <?php foreach ($ipsHistori as $h): ?>
            <tr>
              <td><?= h($h['label']) ?></td>
              <td class="text-center"><?= $h['sks'] ?></td>
              <td class="text-center fw-semibold"><?= number_format($h['ips'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card app-card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-journal-plus me-2"></i>KRS Semester Aktif</span>
        <?php if ($tahunAktif): ?><span class="text-muted small"><?= h($tahunAktif['tahun'] . ' - ' . $tahunAktif['semester']) ?></span><?php endif; ?>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead><tr><th>Mata Kuliah</th><th class="text-center">SKS</th><th>Status</th></tr></thead>
          <tbody>
            <?php if (!$krsAktif): ?>
              <tr><td colspan="3" class="text-center text-muted py-4">Belum ada KRS diambil semester ini. <a href="/api/mahasiswa/krs.php">Isi KRS sekarang</a>.</td></tr>
            <?php endif; ?>
            <?php foreach ($krsAktif as $k): ?>
            <tr>
              <td><?= h($k['kode_mk'] . ' — ' . $k['nama_mk']) ?></td>
              <td class="text-center"><?= (int) $k['sks'] ?></td>
              <td><?= badge_status_krs($k['status']) ?></td>
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
