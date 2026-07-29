<?php
define('SIAKAD_APP', true);
require __DIR__ . '/../_config/constants.php';
require __DIR__ . '/../_config/database.php';
require __DIR__ . '/../_config/auth.php';
require __DIR__ . '/../_helpers/functions.php';
require __DIR__ . '/../_helpers/layout.php';

$user = siakad_require_role('mahasiswa');
$pdo = siakad_db();
$self = '/api/mahasiswa/krs.php';

$tahunAktif = $pdo->query("SELECT * FROM tahun_akademik WHERE is_aktif = 1 LIMIT 1")->fetch();

if (!$tahunAktif) {
    layout_header('Isi KRS', $user, 'krs');
    echo '<div class="alert alert-warning">Belum ada tahun akademik aktif. Silakan hubungi admin.</div>';
    layout_footer();
    exit;
}

$taId = (int) $tahunAktif['id'];
$ipsSebelumnya = ips_semester_terakhir($pdo, $user['id'], $taId);
$batasSks = batas_sks_dari_ips($ipsSebelumnya);

// -----------------------------------------------------------------------
// Ambil KRS mahasiswa saat ini utk semester aktif (+ tandai mana yg sudah dinilai)
// -----------------------------------------------------------------------
function ambil_krs_sekarang(PDO $pdo, int $mahasiswaId, int $taId): array
{
    $stmt = $pdo->prepare("
        SELECT krs.mata_kuliah_id, krs.status, (nilai.id IS NOT NULL) AS sudah_dinilai
        FROM krs
        LEFT JOIN nilai ON nilai.mahasiswa_id = krs.mahasiswa_id AND nilai.mata_kuliah_id = krs.mata_kuliah_id AND nilai.tahun_akademik_id = krs.tahun_akademik_id
        WHERE krs.mahasiswa_id = ? AND krs.tahun_akademik_id = ?
    ");
    $stmt->execute([$mahasiswaId, $taId]);
    $out = [];
    foreach ($stmt->fetchAll() as $r) {
        $out[(int) $r['mata_kuliah_id']] = ['status' => $r['status'], 'dinilai' => (bool) $r['sudah_dinilai']];
    }
    return $out;
}

// -----------------------------------------------------------------------
// Proses submit (POST)
// -----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    siakad_csrf_check();
    $dipilih = array_map('intval', $_POST['mata_kuliah'] ?? []);
    $krsSekarang = ambil_krs_sekarang($pdo, $user['id'], $taId);

    // Course yang sudah dinilai TIDAK BOLEH dilepas walau checkbox-nya tidak dikirim.
    foreach ($krsSekarang as $mkId => $info) {
        if ($info['dinilai'] && !in_array($mkId, $dipilih, true)) {
            $dipilih[] = $mkId;
        }
    }

    // Validasi total SKS terhadap batas
    if ($dipilih) {
        $in = implode(',', array_fill(0, count($dipilih), '?'));
        $stmt = $pdo->prepare("SELECT id, sks FROM mata_kuliah WHERE id IN ($in)");
        $stmt->execute($dipilih);
        $totalSks = array_sum(array_column($stmt->fetchAll(), 'sks'));
    } else {
        $totalSks = 0;
    }

    if ($totalSks > $batasSks) {
        redirect_with_flash($self, 'error', "Total {$totalSks} SKS melebihi batas maksimal {$batasSks} SKS semester ini.");
    }

    $pdo->beginTransaction();
    // Hapus mata kuliah yang di-uncheck (dan belum dinilai — sudah difilter di atas)
    foreach ($krsSekarang as $mkId => $info) {
        if (!in_array($mkId, $dipilih, true)) {
            $del = $pdo->prepare("DELETE FROM krs WHERE mahasiswa_id = ? AND mata_kuliah_id = ? AND tahun_akademik_id = ?");
            $del->execute([$user['id'], $mkId, $taId]);
        }
    }
    // Tambahkan yang baru dipilih (yang belum ada barisnya)
    $ins = $pdo->prepare("INSERT INTO krs (mahasiswa_id, mata_kuliah_id, tahun_akademik_id, status) VALUES (?,?,?,'Diajukan')");
    foreach ($dipilih as $mkId) {
        if (!isset($krsSekarang[$mkId])) {
            $ins->execute([$user['id'], $mkId, $taId]);
        }
    }
    $pdo->commit();

    redirect_with_flash($self, 'success', 'KRS berhasil disimpan (' . $totalSks . ' SKS).');
}

// -----------------------------------------------------------------------
// Tampilan (GET)
// -----------------------------------------------------------------------
$krsSekarang = ambil_krs_sekarang($pdo, $user['id'], $taId);
$semuaMatkul = $pdo->query("
    SELECT mk.*, d.nama_lengkap AS nama_dosen
    FROM mata_kuliah mk LEFT JOIN dosen d ON d.id = mk.dosen_id
    ORDER BY mk.semester ASC, mk.kode_mk ASC
")->fetchAll();

$sksTerpilihSaatIni = 0;
foreach ($semuaMatkul as $mk) {
    if (isset($krsSekarang[$mk['id']]) && $krsSekarang[$mk['id']]['status'] !== 'Ditolak') {
        $sksTerpilihSaatIni += (int) $mk['sks'];
    }
}

layout_header('Isi KRS', $user, 'krs');
?>

<div class="krs-limit-card mb-3">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
      <div class="fw-semibold">Batas SKS Semester Ini</div>
      <div class="text-muted small"><?= h($tahunAktif['tahun'] . ' - ' . $tahunAktif['semester']) ?> · berdasarkan IPS semester sebelumnya (<?= $ipsSebelumnya !== null ? number_format($ipsSebelumnya, 2) : 'belum ada riwayat' ?>)</div>
    </div>
    <div class="krs-sks-counter">
      <span id="sksTerpilih"><?= $sksTerpilihSaatIni ?></span> / <?= $batasSks ?> SKS
    </div>
  </div>
  <div class="progress mt-2" style="height:8px">
    <div class="progress-bar" id="sksProgressBar" style="width:<?= min(100, round($sksTerpilihSaatIni / max(1, $batasSks) * 100)) ?>%"></div>
  </div>
</div>

<form method="post">
  <?= siakad_csrf_field() ?>
  <div class="card app-card">
    <div class="card-header"><i class="bi bi-journal-plus me-2"></i>Pilih Mata Kuliah</div>
    <div class="card-body p-0">
      <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead><tr><th style="width:40px"></th><th>Kode</th><th>Mata Kuliah</th><th class="text-center">SKS</th><th>Dosen</th><th>Jadwal</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($semuaMatkul as $mk): ?>
          <?php
            $info = $krsSekarang[$mk['id']] ?? null;
            $checked = $info && $info['status'] !== 'Ditolak';
            $locked = $info && $info['dinilai'];
          ?>
          <tr>
            <td>
              <input type="checkbox" class="form-check-input mk-checkbox" name="mata_kuliah[]" value="<?= $mk['id'] ?>"
                data-sks="<?= (int) $mk['sks'] ?>" <?= $checked ? 'checked' : '' ?> <?= $locked ? 'disabled' : '' ?>>
              <?php if ($locked): ?><input type="hidden" name="mata_kuliah[]" value="<?= $mk['id'] ?>"><?php endif; ?>
            </td>
            <td class="font-monospace"><?= h($mk['kode_mk']) ?></td>
            <td><?= h($mk['nama_mk']) ?><div class="text-muted small">Semester kurikulum <?= (int) $mk['semester'] ?></div></td>
            <td class="text-center"><?= (int) $mk['sks'] ?></td>
            <td><?= h($mk['nama_dosen'] ?: '-') ?></td>
            <td class="small text-muted"><?= $mk['hari'] ? h($mk['hari'] . ' ' . $mk['jam_mulai'] . '–' . $mk['jam_selesai']) : '-' ?></td>
            <td>
              <?php if ($locked): ?>
                <span class="badge text-bg-info">Sudah dinilai</span>
              <?php elseif ($info): ?>
                <?= badge_status_krs($info['status']) ?>
              <?php else: ?>
                <span class="text-muted small">-</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </div>
    <div class="card-footer text-end">
      <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan KRS</button>
    </div>
  </div>
</form>

<script>
const checkboxes = document.querySelectorAll('.mk-checkbox');
const counterEl = document.getElementById('sksTerpilih');
const barEl = document.getElementById('sksProgressBar');
const batas = <?= $batasSks ?>;

function hitungUlang() {
  let total = 0;
  checkboxes.forEach(cb => { if (cb.checked) total += parseInt(cb.dataset.sks, 10); });
  counterEl.textContent = total;
  barEl.style.width = Math.min(100, Math.round(total / batas * 100)) + '%';
  barEl.classList.toggle('bg-danger', total > batas);
}
checkboxes.forEach(cb => cb.addEventListener('change', hitungUlang));
hitungUlang();
</script>

<?php layout_footer(); ?>
