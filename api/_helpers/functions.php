<?php
/**
 * functions.php — logika akademik & fungsi bantu bersama.
 *
 * Skala nilai & rumus IPK di file ini adalah SATU-SATUNYA sumber kebenaran —
 * dipakai baik saat dosen mengisi nilai maupun saat mahasiswa melihat KHS/
 * transkrip, supaya tidak pernah ada dua tempat yang menghitung berbeda.
 */
if (!defined('SIAKAD_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

/** Skala nilai akademik Indonesia: angka (0-100) -> huruf + bobot (0.0-4.0). */
function nilai_ke_huruf_bobot(float $angka): array
{
    $skala = [
        ['min' => 85, 'huruf' => 'A',  'bobot' => 4.0],
        ['min' => 80, 'huruf' => 'AB', 'bobot' => 3.5],
        ['min' => 75, 'huruf' => 'B',  'bobot' => 3.0],
        ['min' => 70, 'huruf' => 'BC', 'bobot' => 2.5],
        ['min' => 65, 'huruf' => 'C',  'bobot' => 2.0],
        ['min' => 60, 'huruf' => 'CD', 'bobot' => 1.5],
        ['min' => 55, 'huruf' => 'D',  'bobot' => 1.0],
        ['min' => 0,  'huruf' => 'E',  'bobot' => 0.0],
    ];
    foreach ($skala as $s) {
        if ($angka >= $s['min']) {
            return ['huruf' => $s['huruf'], 'bobot' => $s['bobot']];
        }
    }
    return ['huruf' => 'E', 'bobot' => 0.0];
}

/**
 * IPK/IPS = Σ(bobot × sks) / Σ(sks) — rumus IPK standar perguruan tinggi Indonesia.
 * $rows: array asosiatif dengan key 'bobot' dan 'sks' (mis. hasil query JOIN nilai+mata_kuliah).
 */
function hitung_ipk(array $rows): array
{
    $totalSks = 0;
    $totalBobotSks = 0.0;
    foreach ($rows as $r) {
        $sks = (int) $r['sks'];
        $totalSks += $sks;
        $totalBobotSks += ((float) $r['bobot']) * $sks;
    }
    if ($totalSks === 0) {
        return ['ipk' => 0.0, 'total_sks' => 0];
    }
    return ['ipk' => round($totalBobotSks / $totalSks, 2), 'total_sks' => $totalSks];
}

/**
 * Batas maksimal SKS yang boleh diambil semester ini, berdasarkan IPS semester
 * sebelumnya — konvensi umum perguruan tinggi di Indonesia. null berarti belum
 * ada riwayat nilai (mahasiswa baru / belum pernah dinilai), diberi jatah standar 24 SKS.
 */
function batas_sks_dari_ips(?float $ips): int
{
    if ($ips === null) return 24;
    if ($ips >= 3.5) return 24;
    if ($ips >= 3.0) return 21;
    if ($ips >= 2.5) return 18;
    if ($ips >= 2.0) return 15;
    return 12;
}

/**
 * IPS semester paling akhir yang SUDAH punya nilai (selain tahun_akademik_id
 * yang sedang aktif) — dipakai untuk menentukan batas SKS semester berjalan.
 * Mengembalikan null jika mahasiswa belum punya riwayat nilai sama sekali
 * (mis. mahasiswa baru), sehingga batas_sks_dari_ips() akan memberi jatah
 * standar 24 SKS.
 */
function ips_semester_terakhir(PDO $pdo, int $mahasiswaId, int $tahunAktifId): ?float
{
    $stmt = $pdo->prepare("
        SELECT n.tahun_akademik_id
        FROM nilai n
        WHERE n.mahasiswa_id = ? AND n.tahun_akademik_id != ?
        ORDER BY n.tahun_akademik_id DESC LIMIT 1
    ");
    $stmt->execute([$mahasiswaId, $tahunAktifId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $stmt = $pdo->prepare("
        SELECT n.bobot, mk.sks
        FROM nilai n JOIN mata_kuliah mk ON mk.id = n.mata_kuliah_id
        WHERE n.mahasiswa_id = ? AND n.tahun_akademik_id = ?
    ");
    $stmt->execute([$mahasiswaId, $row['tahun_akademik_id']]);
    $hasil = hitung_ipk($stmt->fetchAll());
    return $hasil['ipk'];
}

function h(?string $text): string
{
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/** Redirect dengan pesan singkat dibawa lewat query string (tanpa perlu session server). */
function redirect_with_flash(string $url, string $type, string $message): void
{
    $sep = str_contains($url, '?') ? '&' : '?';
    redirect($url . $sep . 'flash_type=' . urlencode($type) . '&flash_msg=' . urlencode($message));
}

/** Render alert Bootstrap dari flash message di query string, jika ada. */
function render_flash(): string
{
    $type = $_GET['flash_type'] ?? null;
    $msg = $_GET['flash_msg'] ?? null;
    if (!$type || !$msg) {
        return '';
    }
    $cls = match ($type) {
        'success' => 'success',
        'error'   => 'danger',
        'warning' => 'warning',
        default   => 'info',
    };
    $icon = match ($type) {
        'success' => 'bi-check-circle-fill',
        'error'   => 'bi-x-circle-fill',
        'warning' => 'bi-exclamation-triangle-fill',
        default   => 'bi-info-circle-fill',
    };
    return '<div class="alert alert-' . $cls . ' alert-dismissible fade show d-flex align-items-center gap-2" role="alert">'
        . '<i class="bi ' . $icon . '"></i><div>' . h($msg) . '</div>'
        . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
        . '</div>';
}

function nama_hari_urut(): array
{
    return ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
}

function badge_status_krs(string $status): string
{
    $cls = match ($status) {
        'Disetujui' => 'success',
        'Ditolak'   => 'danger',
        default     => 'warning',
    };
    return '<span class="badge text-bg-' . $cls . '">' . h($status) . '</span>';
}

function badge_status_akademik(string $status): string
{
    $cls = match ($status) {
        'Aktif'    => 'success',
        'Cuti'     => 'warning',
        'Lulus'    => 'info',
        default    => 'secondary',
    };
    return '<span class="badge text-bg-' . $cls . '">' . h($status) . '</span>';
}
