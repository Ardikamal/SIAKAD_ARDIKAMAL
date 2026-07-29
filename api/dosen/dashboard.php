<?php
define('SIAKAD_APP', true);
require __DIR__ . '/../_config/constants.php';
require __DIR__ . '/../_config/database.php';
require __DIR__ . '/../_config/auth.php';
require __DIR__ . '/../_helpers/functions.php';
require __DIR__ . '/../_helpers/layout.php';

$user = siakad_require_role('dosen');
$pdo = siakad_db();

$tahunAktif = $pdo->query("SELECT * FROM tahun_akademik WHERE is_aktif = 1 LIMIT 1")->fetch();

$stmt = $pdo->prepare("
    SELECT mk.*,
        (SELECT COUNT(*) FROM krs WHERE krs.mata_kuliah_id = mk.id AND krs.status = 'Disetujui' AND krs.tahun_akademik_id = ?) AS jumlah_mhs,
        (SELECT COUNT(*) FROM krs
            JOIN nilai ON nilai.mahasiswa_id = krs.mahasiswa_id AND nilai.mata_kuliah_id = krs.mata_kuliah_id AND nilai.tahun_akademik_id = krs.tahun_akademik_id
            WHERE krs.mata_kuliah_id = mk.id AND krs.status = 'Disetujui' AND krs.tahun_akademik_id = ?) AS jumlah_dinilai
    FROM mata_kuliah mk
    WHERE mk.dosen_id = ?
    ORDER BY mk.semester ASC, mk.kode_mk ASC
");
$taId = $tahunAktif['id'] ?? 0;
$stmt->execute([$taId, $taId, $user['id']]);
$daftarMatkul = $stmt->fetchAll();

$totalMhs = array_sum(array_column($daftarMatkul, 'jumlah_mhs'));
$totalBelumNilai = array_sum(array_map(fn($m) => max(0, (int) $m['jumlah_mhs'] - (int) $m['jumlah_dinilai']), $daftarMatkul));

layout_header('Dashboard', $user, 'dashboard');
?>

<div class="row g-3 mb-2">
  <div class="col-6 col-lg-3">
    <div class="stat-card stat-card-primary">
      <div class="stat-icon"><i class="bi bi-journal-bookmark-fill"></i></div>
      <div><div class="stat-value"><?= count($daftarMatkul) ?></div><div class="stat-label">Mata Kuliah Diampu</div></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card stat-card-info">
      <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
      <div><div class="stat-value"><?= $totalMhs ?></div><div class="stat-label">Mahasiswa Bimbingan</div></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card stat-card-warning">
      <div class="stat-icon"><i class="bi bi-pencil-square"></i></div>
      <div><div class="stat-value"><?= $totalBelumNilai ?></div><div class="stat-label">Belum Dinilai</div></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card stat-card-success">
      <div class="stat-icon"><i class="bi bi-calendar3"></i></div>
      <div><div class="stat-value fs-5"><?= $tahunAktif ? h($tahunAktif['semester']) : '-' ?></div><div class="stat-label"><?= $tahunAktif ? h($tahunAktif['tahun']) : 'Belum ada TA aktif' ?></div></div>
    </div>
  </div>
</div>

<div class="card app-card">
  <div class="card-header"><i class="bi bi-journal-bookmark-fill me-2"></i>Mata Kuliah yang Diampu — Semester Aktif</div>
  <div class="card-body p-0">
    <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Kode</th><th>Mata Kuliah</th><th>SKS</th><th>Jadwal</th><th>Mahasiswa</th><th>Progres Nilai</th><th class="text-end">Aksi</th></tr></thead>
      <tbody>
        <?php if (!$daftarMatkul): ?>
          <tr><td colspan="7" class="text-center text-muted py-4">Anda belum ditugaskan mengampu mata kuliah. Hubungi admin.</td></tr>
        <?php endif; ?>
        <?php foreach ($daftarMatkul as $mk): ?>
        <?php $pct = $mk['jumlah_mhs'] > 0 ? round($mk['jumlah_dinilai'] / $mk['jumlah_mhs'] * 100) : 0; ?>
        <tr>
          <td class="font-monospace"><?= h($mk['kode_mk']) ?></td>
          <td><?= h($mk['nama_mk']) ?></td>
          <td><?= (int) $mk['sks'] ?></td>
          <td class="small text-muted"><?= $mk['hari'] ? h($mk['hari'] . ' ' . $mk['jam_mulai'] . '–' . $mk['jam_selesai']) : '-' ?></td>
          <td><?= (int) $mk['jumlah_mhs'] ?> mhs</td>
          <td style="min-width:140px">
            <div class="progress" style="height:8px">
              <div class="progress-bar <?= $pct == 100 ? 'bg-success' : '' ?>" style="width:<?= $pct ?>%"></div>
            </div>
            <span class="small text-muted"><?= (int) $mk['jumlah_dinilai'] ?>/<?= (int) $mk['jumlah_mhs'] ?> dinilai</span>
          </td>
          <td class="text-end">
            <a href="/api/dosen/nilai.php?mk=<?= $mk['id'] ?>" class="btn btn-sm btn-primary"><i class="bi bi-pencil-square me-1"></i>Isi Nilai</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>
</div>

<?php layout_footer(); ?>
