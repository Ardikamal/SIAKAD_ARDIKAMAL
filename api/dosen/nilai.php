<?php
define('SIAKAD_APP', true);
require __DIR__ . '/../_config/constants.php';
require __DIR__ . '/../_config/database.php';
require __DIR__ . '/../_config/auth.php';
require __DIR__ . '/../_helpers/functions.php';
require __DIR__ . '/../_helpers/layout.php';

$user = siakad_require_role('dosen');
$pdo = siakad_db();
$self = '/api/dosen/nilai.php';

$tahunAktif = $pdo->query("SELECT * FROM tahun_akademik WHERE is_aktif = 1 LIMIT 1")->fetch();
$taId = (int) ($_GET['ta'] ?? $_POST['ta'] ?? ($tahunAktif['id'] ?? 0));

// -----------------------------------------------------------------------
// Simpan nilai (POST) — hanya untuk mata kuliah yang benar-benar diampu
// dosen yang sedang login (dicek ulang di server, jangan percaya input).
// -----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    siakad_csrf_check();
    $mkId = (int) ($_POST['mk'] ?? 0);

    $stmt = $pdo->prepare("SELECT * FROM mata_kuliah WHERE id = ? AND dosen_id = ?");
    $stmt->execute([$mkId, $user['id']]);
    $matkul = $stmt->fetch();

    if (!$matkul) {
        redirect_with_flash($self, 'error', 'Anda tidak berhak mengisi nilai mata kuliah ini.');
    }

    $nilaiInput = $_POST['nilai'] ?? [];
    $upsert = $pdo->prepare("
        INSERT INTO nilai (mahasiswa_id, mata_kuliah_id, tahun_akademik_id, nilai_angka, nilai_huruf, bobot)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE nilai_angka = VALUES(nilai_angka), nilai_huruf = VALUES(nilai_huruf), bobot = VALUES(bobot)
    ");

    $disimpan = 0;
    foreach ($nilaiInput as $mahasiswaId => $angka) {
        if ($angka === '' || $angka === null) {
            continue; // kolom dikosongkan -> lewati, tidak menimpa dengan 0
        }
        $angka = max(0, min(100, (float) $angka));
        $hb = nilai_ke_huruf_bobot($angka);
        $upsert->execute([(int) $mahasiswaId, $mkId, $taId, $angka, $hb['huruf'], $hb['bobot']]);
        $disimpan++;
    }

    redirect_with_flash($self . '?mk=' . $mkId . '&ta=' . $taId, 'success', "Nilai berhasil disimpan untuk {$disimpan} mahasiswa.");
}

// -----------------------------------------------------------------------
// Daftar mata kuliah yang diampu dosen ini (untuk pemilih di atas)
// -----------------------------------------------------------------------
$stmtMk = $pdo->prepare("SELECT * FROM mata_kuliah WHERE dosen_id = ? ORDER BY semester ASC, kode_mk ASC");
$stmtMk->execute([$user['id']]);
$matkulDiampu = $stmtMk->fetchAll();

$daftarTA = $pdo->query("SELECT * FROM tahun_akademik ORDER BY tahun DESC, semester ASC")->fetchAll();

$mkId = (int) ($_GET['mk'] ?? 0);
$matkulDipilih = null;
$roster = [];
if ($mkId > 0) {
    foreach ($matkulDiampu as $mk) {
        if ((int) $mk['id'] === $mkId) {
            $matkulDipilih = $mk;
            break;
        }
    }
    if ($matkulDipilih) {
        $stmt = $pdo->prepare("
            SELECT m.id AS mahasiswa_id, m.nim, m.nama_lengkap, n.nilai_angka, n.nilai_huruf, n.bobot
            FROM krs
            JOIN mahasiswa m ON m.id = krs.mahasiswa_id
            LEFT JOIN nilai n ON n.mahasiswa_id = krs.mahasiswa_id AND n.mata_kuliah_id = krs.mata_kuliah_id AND n.tahun_akademik_id = krs.tahun_akademik_id
            WHERE krs.mata_kuliah_id = ? AND krs.tahun_akademik_id = ? AND krs.status = 'Disetujui'
            ORDER BY m.nama_lengkap ASC
        ");
        $stmt->execute([$mkId, $taId]);
        $roster = $stmt->fetchAll();
    }
}

layout_header('Isi Nilai', $user, 'nilai');
?>

<div class="card app-card mb-3">
  <div class="card-body">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-md-6">
        <label class="form-label small text-muted mb-1">Mata Kuliah</label>
        <select name="mk" class="form-select" onchange="this.form.submit()">
          <option value="">— Pilih mata kuliah —</option>
          <?php foreach ($matkulDiampu as $mk): ?>
          <option value="<?= $mk['id'] ?>" <?= $mkId === (int) $mk['id'] ? 'selected' : '' ?>><?= h($mk['kode_mk'] . ' — ' . $mk['nama_mk']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label small text-muted mb-1">Tahun Akademik</label>
        <select name="ta" class="form-select" onchange="this.form.submit()">
          <?php foreach ($daftarTA as $ta): ?>
          <option value="<?= $ta['id'] ?>" <?= $taId === (int) $ta['id'] ? 'selected' : '' ?>>
            <?= h($ta['tahun'] . ' - ' . $ta['semester']) ?><?= $ta['is_aktif'] ? ' (Aktif)' : '' ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>
  </div>
</div>

<?php if (!$matkulDiampu): ?>
  <div class="alert alert-info">Anda belum ditugaskan mengampu mata kuliah apa pun. Hubungi admin untuk penugasan.</div>
<?php elseif (!$matkulDipilih): ?>
  <div class="alert alert-secondary">Pilih mata kuliah di atas untuk mulai mengisi nilai.</div>
<?php else: ?>

  <div class="card app-card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span><i class="bi bi-pencil-square me-2"></i><?= h($matkulDipilih['kode_mk'] . ' — ' . $matkulDipilih['nama_mk']) ?></span>
      <span class="badge text-bg-secondary"><?= (int) $matkulDipilih['sks'] ?> SKS</span>
    </div>
    <form method="post">
      <?= siakad_csrf_field() ?>
      <input type="hidden" name="mk" value="<?= $matkulDipilih['id'] ?>">
      <input type="hidden" name="ta" value="<?= $taId ?>">
      <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr><th>NIM</th><th>Nama Mahasiswa</th><th style="width:140px">Nilai Angka</th><th style="width:100px" class="text-center">Huruf</th><th style="width:100px" class="text-center">Bobot</th></tr></thead>
          <tbody>
            <?php if (!$roster): ?>
              <tr><td colspan="5" class="text-center text-muted py-4">Belum ada mahasiswa yang KRS-nya disetujui untuk mata kuliah ini pada semester tersebut.</td></tr>
            <?php endif; ?>
            <?php foreach ($roster as $r): ?>
            <tr>
              <td class="font-monospace"><?= h($r['nim']) ?></td>
              <td><?= h($r['nama_lengkap']) ?></td>
              <td>
                <input type="number" name="nilai[<?= $r['mahasiswa_id'] ?>]" class="form-control form-control-sm nilai-input" min="0" max="100" step="0.01" value="<?= $r['nilai_angka'] !== null ? h((string) (float) $r['nilai_angka']) : '' ?>">
              </td>
              <td class="text-center"><span class="badge nilai-huruf-badge huruf-preview">-</span></td>
              <td class="text-center bobot-preview">-</td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        </div>
      </div>
      <?php if ($roster): ?>
      <div class="card-footer text-end">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan Semua Nilai</button>
      </div>
      <?php endif; ?>
    </form>
  </div>

<?php endif; ?>

<script>
// Pratinjau huruf & bobot secara langsung saat dosen mengetik (server tetap
// yang menghitung ulang & menyimpan nilai final saat form disubmit).
function skalaNilai(angka) {
  const skala = [
    [85, 'A', 4.0], [80, 'AB', 3.5], [75, 'B', 3.0], [70, 'BC', 2.5],
    [65, 'C', 2.0], [60, 'CD', 1.5], [55, 'D', 1.0], [0, 'E', 0.0],
  ];
  for (const [min, huruf, bobot] of skala) {
    if (angka >= min) return { huruf, bobot };
  }
  return { huruf: 'E', bobot: 0.0 };
}
function updatePreview(input) {
  const row = input.closest('tr');
  const hurufEl = row.querySelector('.huruf-preview');
  const bobotEl = row.querySelector('.bobot-preview');
  const val = parseFloat(input.value);
  if (isNaN(val)) {
    hurufEl.textContent = '-';
    bobotEl.textContent = '-';
    return;
  }
  const { huruf, bobot } = skalaNilai(Math.max(0, Math.min(100, val)));
  hurufEl.textContent = huruf;
  bobotEl.textContent = bobot.toFixed(2);
}
document.querySelectorAll('.nilai-input').forEach(inp => {
  updatePreview(inp);
  inp.addEventListener('input', () => updatePreview(inp));
});
</script>

<?php layout_footer(); ?>
