<?php
define('SIAKAD_APP', true);
require __DIR__ . '/../_config/constants.php';
require __DIR__ . '/../_config/database.php';
require __DIR__ . '/../_config/auth.php';
require __DIR__ . '/../_helpers/functions.php';
require __DIR__ . '/../_helpers/layout.php';

$user = siakad_require_role('mahasiswa');
$pdo = siakad_db();

$stmt = $pdo->prepare("
    SELECT ta.id AS ta_id, ta.tahun, ta.semester,
           mk.kode_mk, mk.nama_mk, mk.sks,
           n.nilai_angka, n.nilai_huruf, n.bobot
    FROM nilai n
    JOIN mata_kuliah mk ON mk.id = n.mata_kuliah_id
    JOIN tahun_akademik ta ON ta.id = n.tahun_akademik_id
    WHERE n.mahasiswa_id = ?
    ORDER BY ta.id ASC, mk.kode_mk ASC
");
$stmt->execute([$user['id']]);
$rows = $stmt->fetchAll();

$bySemester = [];
foreach ($rows as $r) {
    $bySemester[$r['ta_id']]['label'] = $r['tahun'] . ' - ' . $r['semester'];
    $bySemester[$r['ta_id']]['items'][] = $r;
}

$ipkTotal = hitung_ipk($rows);

layout_header('Nilai & Transkrip', $user, 'nilai');
?>

<div class="transcript-summary mb-3">
  <div class="row g-3 text-center">
    <div class="col-4">
      <div class="fs-3 fw-bold"><?= number_format($ipkTotal['ipk'], 2) ?></div>
      <div class="text-muted small">IPK Kumulatif</div>
    </div>
    <div class="col-4">
      <div class="fs-3 fw-bold"><?= $ipkTotal['total_sks'] ?></div>
      <div class="text-muted small">Total SKS Lulus</div>
    </div>
    <div class="col-4">
      <div class="fs-3 fw-bold"><?= count($bySemester) ?></div>
      <div class="text-muted small">Semester Ditempuh</div>
    </div>
  </div>
</div>

<?php if (!$bySemester): ?>
  <div class="alert alert-secondary">Belum ada nilai yang tercatat.</div>
<?php endif; ?>

<?php foreach ($bySemester as $taId => $data): ?>
  <?php $ips = hitung_ipk($data['items']); ?>
  <div class="card app-card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span><i class="bi bi-journal-text me-2"></i><?= h($data['label']) ?></span>
      <span class="badge text-bg-primary">IPS <?= number_format($ips['ipk'], 2) ?></span>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead><tr><th>Kode</th><th>Mata Kuliah</th><th class="text-center">SKS</th><th class="text-center">Nilai</th><th class="text-center">Huruf</th><th class="text-center">Bobot</th></tr></thead>
        <tbody>
          <?php foreach ($data['items'] as $it): ?>
          <tr>
            <td class="font-monospace"><?= h($it['kode_mk']) ?></td>
            <td><?= h($it['nama_mk']) ?></td>
            <td class="text-center"><?= (int) $it['sks'] ?></td>
            <td class="text-center"><?= number_format((float) $it['nilai_angka'], 1) ?></td>
            <td class="text-center"><span class="badge nilai-huruf-badge"><?= h($it['nilai_huruf']) ?></span></td>
            <td class="text-center"><?= number_format((float) $it['bobot'], 2) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </div>
  </div>
<?php endforeach; ?>

<?php layout_footer(); ?>
