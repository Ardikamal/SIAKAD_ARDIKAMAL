<?php
/**
 * database.php — koneksi PDO ke MySQL/MariaDB.
 *
 * Konfigurasi diambil dari environment variable, dengan nilai default yang
 * cocok untuk server lokal (XAMPP/Laragon: host localhost, user root, tanpa
 * password, database "siakad") supaya bisa langsung jalan tanpa setup apa pun
 * selain import database/schema.sql.
 *
 * Saat deploy ke Vercel, database TIDAK ikut ter-deploy (Vercel hanya menjalankan
 * kode PHP-nya, tidak menyediakan server MySQL). Sediakan MySQL eksternal
 * (lihat README.md bagian Deployment untuk beberapa pilihan gratis), lalu isi
 * environment variable berikut di Project Settings -> Environment Variables:
 *   DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, DB_SSL (opsional, "true" jika
 *   provider mewajibkan koneksi SSL, mis. TiDB Cloud/Aiven/PlanetScale).
 */
if (!defined('SIAKAD_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

function siakad_db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $host = getenv('DB_HOST') ?: 'localhost';
    $port = getenv('DB_PORT') ?: '3306';
    $name = getenv('DB_NAME') ?: 'siakad';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';
    $useSsl = filter_var(getenv('DB_SSL') ?: 'false', FILTER_VALIDATE_BOOLEAN);

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    if ($useSsl) {
        // Beberapa provider MySQL cloud (TiDB Cloud, Aiven, dll) mewajibkan TLS.
        $options[PDO::MYSQL_ATTR_SSL_CA] = getenv('DB_SSL_CA') ?: null;
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
    }

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $e) {
        http_response_code(500);
        // Pesan error disederhanakan (tidak menampilkan detail kredensial) demi keamanan.
        die(
            '<div style="font-family:sans-serif;max-width:640px;margin:60px auto;padding:24px;' .
            'border:1px solid #EF4444;border-radius:12px;background:#FEF2F2;color:#7f1d1d;">' .
            '<h2 style="margin-top:0;">Tidak bisa terhubung ke database</h2>' .
            '<p>Periksa environment variable <code>DB_HOST</code>, <code>DB_NAME</code>, ' .
            '<code>DB_USER</code>, <code>DB_PASS</code> (lihat README.md bagian Instalasi/Deployment).</p>' .
            '</div>'
        );
    }

    return $pdo;
}
