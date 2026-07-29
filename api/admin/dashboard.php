<?php
define('SIAKAD_APP', true);
require __DIR__ . '/../_config/constants.php';
require __DIR__ . '/../_config/database.php';
require __DIR__ . '/../_config/auth.php';
require __DIR__ . '/../_helpers/functions.php';
require __DIR__ . '/../_helpers/layout.php';

$user = siakad_require_role('admin');
$pdo = siakad_db();

$totalMahasiswa = (int) $pdo->query("SELECT COUNT(*) c FROM mahasiswa")->fetch()['c'];
$totalDosen = (int) $pdo->query("SELECT COUNT(*) c FROM dosen")->fetch()['c'];
$totalMatkul = (int) $pdo->query("SELECT COUNT(*) c FROM mata_kuliah")->fetch()['c'];
$krsPending = (int) $pdo->query("SELECT COUNT(*) c FROM krs WHERE status = 'Diajukan'")->fetch()['c'];

$tahunAktif = $pdo->query("SELECT * FROM tahun_akademik WHERE is_aktif = 1 LIMIT 1")->fetch();

$statusRows = $pdo->query("SELECT status_akademik, COUNT(*) jumlah FROM mahasiswa GROUP BY status_akademik")->fetchAll();

$krsTerbaru = $pdo->query("
    SELECT krs.id, krs.status, krs.created_at, m.nama_lengkap AS nama_mhs, m.nim, mk.nama_mk
    FROM krs
    JOIN mahasiswa m ON m.id = krs.mahasiswa_id
    JOIN mata_kuliah mk ON mk.id = krs.mata_kuliah_id
    ORDER BY krs.id DESC LIMIT 6
")->fetchAll();

layout_header('Dashboard', $user, 'dashboard');
?>

<div class="row g-3 mb-2">
  <div class="col-6 col-lg-3">
    <div class="stat-card stat-card-primary">
      <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
      <div><div class="stat-value"><?= $totalMahasiswa ?></div><div class="stat-label">Mahasiswa</div></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card stat-card-info">
      <div class="stat-icon"><i class="bi bi-person-workspace"></i></div>
      <div><div class="stat-value"><?= $totalDosen ?></div><div class="stat-label">Dosen</div></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card stat-card-success">
      <div class="stat-icon"><i class="bi bi-journal-bookmark-fill"></i></div>
      <div><div class="stat-value"><?= $totalMatkul ?></div><div class="stat-label">Mata Kuliah</div></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card stat-card-warning">
      <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
      <div><div class="stat-value"><?= $krsPending ?></div><div class="stat-label">KRS Menunggu</div></div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card app-card h-100">
      <div class="card-header"><i class="bi bi-calendar3 me-2"></i>Tahun Akademik Aktif</div>
      <div class="card-body">
        <?php if ($tahunAktif): ?>
          <div class="d-flex align-items-center gap-3">
            <div class="ta-badge"><?= h($tahunAktif['semester']) ?></div>
            <div>
              <div class="fs-4 fw-bold"><?= h($tahunAktif['tahun']) ?></div>
              <div class="text-muted">Semester <?= h($tahunAktif['semester']) ?></div>
            </div>
          </div>
        <?php else: ?>
          <p class="text-muted mb-0">Belum ada tahun akademik yang diaktifkan. Atur di menu <a href="/api/admin/tahun_akademik.php">Tahun Akademik</a>.</p>
        <?php endif; ?>
        <hr>
        <div class="fw-semibold mb-2">Status Akademik Mahasiswa</div>
        <canvas id="chartStatus" height="140"></canvas>
      </div>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="card app-card h-100">
      <div class="card-header"><i class="bi bi-clock-history me-2"></i>Pengajuan KRS Terbaru</div>
      <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead><tr><th>Mahasiswa</th><th>Mata Kuliah</th><th>Status</th></tr></thead>
          <tbody>
            <?php if (!$krsTerbaru): ?>
              <tr><td colspan="3" class="text-center text-muted py-4">Belum ada pengajuan KRS.</td></tr>
            <?php endif; ?>
            <?php foreach ($krsTerbaru as $k): ?>
            <tr>
              <td><?= h($k['nama_mhs']) ?><div class="text-muted small"><?= h($k['nim']) ?></div></td>
              <td><?= h($k['nama_mk']) ?></td>
              <td><?= badge_status_krs($k['status']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        </div>
      </div>
      <div class="card-footer text-end">
        <a href="/api/admin/krs.php" class="btn btn-sm btn-outline-primary">Kelola semua KRS <i class="bi bi-arrow-right"></i></a>
      </div>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.4/chart.umd.min.js"></script>
<script>
if (typeof Chart !== 'undefined') {
  const ctx = document.getElementById('chartStatus');
  const labels = <?= json_encode(array_column($statusRows, 'status_akademik')) ?>;
  const data = <?= json_encode(array_map('intval', array_column($statusRows, 'jumlah'))) ?>;
  new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: labels.length ? labels : ['Belum ada data'],
      datasets: [{
        data: data.length ? data : [1],
        backgroundColor: ['#10B981', '#F59E0B', '#3B82F6', '#EF4444', '#94A3B8'],
        borderWidth: 0,
      }]
    },
    options: {
      plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } },
      maintainAspectRatio: false,
    }
  });
}
</script>

<?php layout_footer(); ?>
