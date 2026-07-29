<?php
/**
 * auth.php — autentikasi & manajemen "sesi" tanpa penyimpanan sisi server.
 *
 * Kenapa tidak pakai session_start() bawaan PHP?
 * Di hosting biasa (XAMPP/shared hosting) session_start() menyimpan file sesi
 * di disk server dan itu bekerja baik-baik saja. Tapi di Vercel (serverless),
 * tiap request berpotensi dieksekusi di container yang berbeda dan filesystem-nya
 * tidak persisten antar request — file sesi yang ditulis saat login bisa saja
 * "hilang" di request berikutnya, sehingga pengguna mendadak ter-logout.
 *
 * Solusinya: setelah login sukses, kita terbitkan sebuah token berisi data
 * pengguna (role, id, nama) yang ditandatangani dengan HMAC-SHA256 memakai
 * APP_SECRET, lalu disimpan di cookie HTTP-only. Setiap request berikutnya
 * cukup memverifikasi tanda tangan token ini — tidak perlu membaca state apa
 * pun dari server. Ini murni PHP bawaan (hash_hmac, tanpa library/framework).
 */
if (!defined('SIAKAD_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

const SIAKAD_ROLES = ['admin', 'dosen', 'mahasiswa'];

function siakad_hash_password(string $plain): string
{
    return password_hash($plain, PASSWORD_DEFAULT);
}

function siakad_verify_password(string $plain, string $hash): bool
{
    return password_verify($plain, $hash);
}

function base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string
{
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
}

/** Menerbitkan token bertanda tangan dari payload (role, id, identifier, nama). */
function siakad_issue_token(array $payload): string
{
    $payload['exp'] = time() + SESSION_TTL;
    $payload['csrf'] = bin2hex(random_bytes(16));

    $body = base64url_encode(json_encode($payload));
    $signature = base64url_encode(hash_hmac('sha256', $body, APP_SECRET, true));

    return $body . '.' . $signature;
}

/** Memverifikasi token; mengembalikan payload (array) jika valid & belum kedaluwarsa, null jika tidak. */
function siakad_verify_token(string $token): ?array
{
    $parts = explode('.', $token);
    if (count($parts) !== 2) {
        return null;
    }
    [$body, $signature] = $parts;

    $expected = base64url_encode(hash_hmac('sha256', $body, APP_SECRET, true));
    if (!hash_equals($expected, $signature)) {
        return null; // tanda tangan tidak cocok -> token dipalsukan/rusak
    }

    $payload = json_decode(base64url_decode($body), true);
    if (!is_array($payload) || !isset($payload['exp']) || $payload['exp'] < time()) {
        return null; // kedaluwarsa atau format tidak valid
    }

    return $payload;
}

/** Set cookie login setelah kredensial tervalidasi. */
function siakad_login(string $role, array $userRow, string $identifierField, string $namaField): void
{
    $payload = [
        'role'       => $role,
        'id'         => (int) $userRow['id'],
        'identifier' => $userRow[$identifierField],
        'nama'       => $userRow[$namaField],
    ];
    $token = siakad_issue_token($payload);

    setcookie(SIAKAD_COOKIE, $token, [
        'expires'  => time() + SESSION_TTL,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function siakad_logout(): void
{
    setcookie(SIAKAD_COOKIE, '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/** Mengambil data pengguna yang sedang login dari cookie, atau null jika belum login. */
function siakad_current_user(): ?array
{
    static $cached = false;
    static $user = null;
    if ($cached) {
        return $user;
    }
    $cached = true;

    if (empty($_COOKIE[SIAKAD_COOKIE])) {
        return null;
    }
    $user = siakad_verify_token($_COOKIE[SIAKAD_COOKIE]);
    return $user;
}

/**
 * Menjaga halaman agar hanya bisa diakses oleh role tertentu.
 * Redirect ke halaman login jika belum login, atau ke dashboard milik role
 * yang benar jika login tapi salah role (mencegah, misal, mahasiswa membuka
 * URL halaman admin secara langsung).
 */
function siakad_require_role(string $requiredRole): array
{
    $user = siakad_current_user();
    if ($user === null) {
        header('Location: /api/login.php?redirected=1');
        exit;
    }
    if ($user['role'] !== $requiredRole) {
        header('Location: ' . siakad_dashboard_url($user['role']));
        exit;
    }
    return $user;
}

function siakad_dashboard_url(string $role): string
{
    return match ($role) {
        'admin'     => '/api/admin/dashboard.php',
        'dosen'     => '/api/dosen/dashboard.php',
        'mahasiswa' => '/api/mahasiswa/dashboard.php',
        default     => '/api/login.php',
    };
}

/** Ambil (atau buat) token CSRF dari sesi berjalan, untuk disisipkan ke form. */
function siakad_csrf_token(): string
{
    $user = siakad_current_user();
    return $user['csrf'] ?? '';
}

function siakad_csrf_field(): string
{
    $token = htmlspecialchars(siakad_csrf_token(), ENT_QUOTES);
    return "<input type=\"hidden\" name=\"csrf\" value=\"{$token}\">";
}

/** Hentikan proses dengan pesan error singkat jika token CSRF pada form tidak cocok. */
function siakad_csrf_check(): void
{
    $expected = siakad_csrf_token();
    $sent = $_POST['csrf'] ?? '';
    if ($expected === '' || !hash_equals($expected, $sent)) {
        http_response_code(400);
        exit('Permintaan tidak valid (CSRF token tidak cocok). Silakan muat ulang halaman dan coba lagi.');
    }
}
