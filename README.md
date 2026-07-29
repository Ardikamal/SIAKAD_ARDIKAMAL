# SIAKAD Sederhana

SIAKAD Sederhana adalah sistem informasi akademik berbasis PHP untuk Universitas Bale Bandung (UNIBBA). Aplikasi ini mendukung tiga peran utama: Admin, Dosen, dan Mahasiswa, dengan fitur pengelolaan KRS, penilaian mata kuliah, serta perhitungan IPS/IPK otomatis.

## Fitur Utama

- Login multi-peran: `admin`, `dosen`, `mahasiswa`
- Admin dapat mengelola: mahasiswa, dosen, mata kuliah, KRS, nilai, dan tahun akademik
- Dosen dapat mengisi/mengelola nilai untuk mata kuliah yang diampu
- Mahasiswa dapat melihat dashboard, KRS aktif, jadwal, nilai, dan IPK/IPS
- Perhitungan IPK dan IPS otomatis berdasarkan sks dan bobot nilai
- Autentikasi berbasis cookie token HMAC, tanpa session server-side
- Proteksi CSRF untuk form penting
- Dukungan deploy ke Vercel (front-end statis + PHP API)

## Struktur Proyek

- `index.html` — halaman masuk utama yang mengarahkan ke `/api/index.php`
- `api/` — endpoint PHP utama
  - `login.php`, `logout.php`, `index.php`
  - `_config/` — konfigurasi aplikasi dan koneksi database
  - `_helpers/` — fungsi bersama dan layout
  - `admin/`, `dosen/`, `mahasiswa/` — halaman dashboard dan panel peran
- `assets/` — assets statis seperti CSS, JS, dan gambar
- `database/schema.sql` — skema database lengkap dengan data demo

## Persyaratan

- PHP 8.1+ dengan ekstensi PDO MySQL
- MySQL / MariaDB
- Web server seperti XAMPP, Laragon, atau deploy ke Vercel dengan runtime PHP

## Instalasi Lokal

1. Impor database:

```bash
mysql -u root -p < database/schema.sql
```

2. Salin/letakkan proyek ke folder web server Anda (misal `htdocs` atau `www`).
3. Buka browser ke `http://localhost/<folder-proyek>/api/login.php` atau `http://localhost/<folder-proyek>/`.

## Konfigurasi Environment

Aplikasi membaca variabel environment untuk koneksi database dan secret aplikasi.

- `DB_HOST` — host database (default: `localhost`)
- `DB_PORT` — port database (default: `3306`)
- `DB_NAME` — nama database (default: `siakad`)
- `DB_USER` — user database (default: `root`)
- `DB_PASS` — password database (default: kosong)
- `DB_SSL` — `true` jika koneksi database harus SSL
- `APP_SECRET` — secret untuk menandatangani token login (ganti di produksi)

## Akun Demo

Akun demo disertakan di `database/schema.sql`:

- Admin
  - username: `admin`
  - password: `admin123`
- Dosen
  - NIP: `D001`
  - password: `dosen123`
- Mahasiswa
  - NIM: `301230023`
  - password: `mahasiswa123`

## Deploy ke Vercel

1. Pastikan Vercel mendukung runtime PHP di project Anda.
2. Set environment variables di Vercel Project Settings.
3. Pastikan database eksternal tersedia (Vercel tidak menyediakan MySQL bawaan).
4. Deploy seperti biasa.

> Catatan: `database/schema.sql` hanya untuk import sekali. Database tidak di-deploy bersama kode.

## Penggunaan

- Akses `api/login.php` untuk masuk.
- Setelah login, aplikasi akan mengarahkan ke dashboard sesuai peran.
- Admin dapat mengelola data master dan memeriksa pengajuan KRS.
- Dosen dapat mengisi nilai mahasiswa untuk mata kuliah yang diampu.
- Mahasiswa dapat melihat jadwal, status KRS, dan ringkasan IPK/IPS.

## Lisensi

Proyek ini dibuat untuk tugas kuliah oleh Ardi Kamal Karima (NIM 301230023). Gunakan sebagai referensi atau modifikasi sesuai kebutuhan akademik.

