<?php
/**
 * constants.php — konfigurasi inti aplikasi.
 *
 * File ini TIDAK boleh diakses langsung lewat browser (lihat guard di bawah).
 * SIAKAD_APP dipakai sebagai "kunci" — setiap file entry-point (login.php,
 * admin/dashboard.php, dst.) mendefinisikan konstanta ini SEBELUM meng-include
 * file lain di folder _config / _helpers.
 */
if (!defined('SIAKAD_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

date_default_timezone_set('Asia/Jakarta');

// Nama cookie sesi login. Kita TIDAK memakai session_start() bawaan PHP
// (yang menyimpan file sesi di disk server) karena di lingkungan serverless
// seperti Vercel, tiap request bisa "mendarat" di instance/container yang
// berbeda dan filesystem-nya sementara/tidak konsisten antar request. Solusinya
// dipakai token terenkripsi (HMAC-signed) yang disimpan di cookie HTTP-only,
// jadi tidak butuh penyimpanan sesi di server sama sekali. Lihat auth.php.
define('SIAKAD_COOKIE', 'siakad_auth');

// Kunci rahasia untuk menandatangani token login. WAJIB diganti lewat
// environment variable APP_SECRET saat deploy ke Vercel (Project Settings ->
// Environment Variables). Nilai default di bawah HANYA untuk memudahkan coba
// lokal di XAMPP/Laragon dan tidak aman dipakai di produksi.
define('APP_SECRET', getenv('APP_SECRET') ?: 'siakad-dev-secret-ganti-saat-deploy-6c-ardikamal');

// Lama sesi login (detik). 8 jam.
define('SESSION_TTL', 8 * 60 * 60);

// Batas SKS minimum & maksimum absolut (di luar aturan berbasis IPS)
define('SKS_MIN_DIAMBIL', 0);
define('SKS_TARGET_LULUS', 144);

// Identitas aplikasi & penulis — tampil di footer semua halaman
define('APP_NAME', 'SIAKAD Sederhana');
define('APP_SUBTITLE', 'Sistem Informasi Akademik');
define('KAMPUS_NAMA', 'Universitas Bale Bandung (UNIBBA)');
define('PENULIS_NAMA', 'Ardi Kamal Karima');
define('PENULIS_NIM', '301230023');
define('PENULIS_KELAS', '6C');
define('PENULIS_PRODI', 'S1 Teknik Informatika');
define('PENULIS_FAKULTAS', 'Fakultas Teknologi Informasi');
