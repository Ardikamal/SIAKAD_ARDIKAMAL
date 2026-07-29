-- ============================================================================
-- SIAKAD SEDERHANA — Skema Database + Data Awal (Seeder)
-- Program Studi S1 Teknik Informatika — Universitas Bale Bandung (UNIBBA)
-- Dibuat oleh: Ardi Kamal Karima (NIM 301230023, Kelas 6C)
--
-- Cara pakai:
--   mysql -u root -p < database/schema.sql
-- atau import lewat phpMyAdmin (menu Import).
--
-- File ini MANDIRI: membuat database, semua tabel, relasi, dan data contoh
-- sekaligus. Aman dijalankan ulang (DROP TABLE IF EXISTS di awal).
--
-- Akun demo (password di bawah ini adalah bcrypt ASLI, bukan placeholder):
--   Admin      -> username: admin        | password: admin123
--   Dosen      -> NIP     : D001         | password: dosen123
--   Dosen      -> NIP     : D002         | password: dosen123
--   Mahasiswa  -> NIM     : 301230023    | password: mahasiswa123
-- ============================================================================

CREATE DATABASE IF NOT EXISTS siakad CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE siakad;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS nilai;
DROP TABLE IF EXISTS krs;
DROP TABLE IF EXISTS mata_kuliah;
DROP TABLE IF EXISTS tahun_akademik;
DROP TABLE IF EXISTS mahasiswa;
DROP TABLE IF EXISTS dosen;
DROP TABLE IF EXISTS admin;

SET FOREIGN_KEY_CHECKS = 1;

-- ----------------------------------------------------------------------------
-- admin — akun pengelola sistem
-- ----------------------------------------------------------------------------
CREATE TABLE admin (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  password      VARCHAR(255) NOT NULL,
  nama_lengkap  VARCHAR(150) NOT NULL,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- dosen — akun pengajar, mengisi nilai untuk mata kuliah yang diampu
-- ----------------------------------------------------------------------------
CREATE TABLE dosen (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  nip           VARCHAR(30)  NOT NULL UNIQUE,
  password      VARCHAR(255) NOT NULL,
  nama_lengkap  VARCHAR(150) NOT NULL,
  email         VARCHAR(150) NULL,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- mahasiswa — akun mahasiswa, mengisi KRS dan melihat nilai/IPK
-- ----------------------------------------------------------------------------
CREATE TABLE mahasiswa (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  nim             VARCHAR(20)  NOT NULL UNIQUE,
  password        VARCHAR(255) NOT NULL,
  nama_lengkap    VARCHAR(150) NOT NULL,
  prodi           VARCHAR(100) NOT NULL DEFAULT 'S1 Teknik Informatika',
  angkatan        INT          NOT NULL,
  status_akademik ENUM('Aktif','Cuti','Lulus','Drop Out') NOT NULL DEFAULT 'Aktif',
  created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_mahasiswa_angkatan (angkatan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- tahun_akademik — tahun ajaran + semester berjalan (hanya 1 baris aktif)
-- ----------------------------------------------------------------------------
CREATE TABLE tahun_akademik (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  tahun     VARCHAR(20) NOT NULL,                 -- contoh: "2025/2026"
  semester  ENUM('Ganjil','Genap') NOT NULL,
  is_aktif  TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_tahun_semester (tahun, semester)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- mata_kuliah — katalog mata kuliah + jadwal (disederhanakan: 1 jadwal/matkul)
-- ----------------------------------------------------------------------------
CREATE TABLE mata_kuliah (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  kode_mk     VARCHAR(20)  NOT NULL UNIQUE,
  nama_mk     VARCHAR(150) NOT NULL,
  sks         INT          NOT NULL,
  semester    INT          NOT NULL,               -- semester kurikulum (1-8)
  dosen_id    INT          NULL,
  hari        ENUM('Senin','Selasa','Rabu','Kamis','Jumat','Sabtu') NULL,
  jam_mulai   VARCHAR(5)   NULL,                    -- "08:00"
  jam_selesai VARCHAR(5)   NULL,                    -- "10:30"
  ruangan     VARCHAR(50)  NULL,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_matkul_dosen FOREIGN KEY (dosen_id) REFERENCES dosen(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- krs — pengajuan Kartu Rencana Studi mahasiswa per semester
-- ----------------------------------------------------------------------------
CREATE TABLE krs (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  mahasiswa_id       INT NOT NULL,
  mata_kuliah_id     INT NOT NULL,
  tahun_akademik_id  INT NOT NULL,
  status             ENUM('Diajukan','Disetujui','Ditolak') NOT NULL DEFAULT 'Diajukan',
  created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_krs_mhs FOREIGN KEY (mahasiswa_id) REFERENCES mahasiswa(id) ON DELETE CASCADE,
  CONSTRAINT fk_krs_mk  FOREIGN KEY (mata_kuliah_id) REFERENCES mata_kuliah(id) ON DELETE CASCADE,
  CONSTRAINT fk_krs_ta  FOREIGN KEY (tahun_akademik_id) REFERENCES tahun_akademik(id) ON DELETE CASCADE,
  UNIQUE KEY uq_krs (mahasiswa_id, mata_kuliah_id, tahun_akademik_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- nilai — nilai akhir per mahasiswa per mata kuliah per semester
-- ----------------------------------------------------------------------------
CREATE TABLE nilai (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  mahasiswa_id       INT NOT NULL,
  mata_kuliah_id     INT NOT NULL,
  tahun_akademik_id  INT NOT NULL,
  nilai_angka        DECIMAL(5,2) NOT NULL,   -- 0-100
  nilai_huruf        VARCHAR(2)   NOT NULL,   -- A, AB, B, BC, C, CD, D, E
  bobot              DECIMAL(3,2) NOT NULL,   -- 0.00-4.00
  created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_nilai_mhs FOREIGN KEY (mahasiswa_id) REFERENCES mahasiswa(id) ON DELETE CASCADE,
  CONSTRAINT fk_nilai_mk  FOREIGN KEY (mata_kuliah_id) REFERENCES mata_kuliah(id) ON DELETE CASCADE,
  CONSTRAINT fk_nilai_ta  FOREIGN KEY (tahun_akademik_id) REFERENCES tahun_akademik(id) ON DELETE CASCADE,
  UNIQUE KEY uq_nilai (mahasiswa_id, mata_kuliah_id, tahun_akademik_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- DATA AWAL (SEEDER)
-- ============================================================================

-- admin (password: admin123)
INSERT INTO admin (username, password, nama_lengkap) VALUES
('admin', '$2y$10$Yk/Nq99EFxdxNXot00ae6uNn33exEf4hz4RXLPS3h7xPDu2XEZzcG', 'Administrator SIAKAD');

-- dosen (password: dosen123)
INSERT INTO dosen (nip, password, nama_lengkap, email) VALUES
('D001', '$2y$10$WtzRAWDtBLc0ETqDdyHIXupVtRHu69T5KAhua58sgvGsMCV8hneuy', 'Budi Santoso, M.Kom.', 'budi.santoso@unibba.ac.id'),
('D002', '$2y$10$WtzRAWDtBLc0ETqDdyHIXupVtRHu69T5KAhua58sgvGsMCV8hneuy', 'Siti Aminah, M.T.', 'siti.aminah@unibba.ac.id'),
('D003', '$2y$10$WtzRAWDtBLc0ETqDdyHIXupVtRHu69T5KAhua58sgvGsMCV8hneuy', 'Rina Marlina, M.Kom.', 'rina.marlina@unibba.ac.id');

-- mahasiswa (password: mahasiswa123)
INSERT INTO mahasiswa (nim, password, nama_lengkap, prodi, angkatan, status_akademik) VALUES
('301230023', '$2y$10$OEZuyUH8MRPO8bPm9gKtUuJV2OFMuyFYVcNsG3Gh2ri93oOSqRGjW', 'Ardi Kamal Karima', 'S1 Teknik Informatika', 2023, 'Aktif'),
('301230045', '$2y$10$OEZuyUH8MRPO8bPm9gKtUuJV2OFMuyFYVcNsG3Gh2ri93oOSqRGjW', 'Muhammad Rizky', 'S1 Teknik Informatika', 2023, 'Aktif'),
('301230012', '$2y$10$OEZuyUH8MRPO8bPm9gKtUuJV2OFMuyFYVcNsG3Gh2ri93oOSqRGjW', 'Nabila Putri', 'S1 Teknik Informatika', 2023, 'Aktif');

-- tahun akademik: semester lalu (riwayat nilai) + semester aktif (berjalan)
INSERT INTO tahun_akademik (tahun, semester, is_aktif) VALUES
('2025/2026', 'Ganjil', 0),
('2025/2026', 'Genap', 1);

-- mata kuliah semester 5 (riwayat — sudah dinilai)
INSERT INTO mata_kuliah (kode_mk, nama_mk, sks, semester, dosen_id, hari, jam_mulai, jam_selesai, ruangan) VALUES
('KK501', 'Basis Data Lanjut', 3, 5, 1, 'Senin', '08:00', '10:30', 'Lab RPL 1'),
('KK502', 'Rekayasa Perangkat Lunak', 3, 5, 3, 'Selasa', '13:00', '15:30', 'R. 301');

-- mata kuliah semester 6 (berjalan — KRS sudah diambil, nilai belum diisi)
INSERT INTO mata_kuliah (kode_mk, nama_mk, sks, semester, dosen_id, hari, jam_mulai, jam_selesai, ruangan) VALUES
('KK601', 'Kriptografi', 3, 6, 1, 'Senin', '08:00', '10:30', 'Lab RPL 1'),
('KK602', 'Interpretasi dan Pengolahan Citra', 3, 6, 2, 'Selasa', '10:30', '13:00', 'Lab RPL 2'),
('KK603', 'Komputasi Paralel dan Sistem Terdistribusi', 3, 6, 1, 'Rabu', '08:00', '10:30', 'Lab RPL 1'),
('KK604', 'Kecerdasan Buatan', 3, 6, 2, 'Kamis', '13:00', '15:30', 'R. 302'),
('KK605', 'Pengembangan Aplikasi Mobile', 3, 6, 3, 'Jumat', '08:00', '10:30', 'Lab RPL 3');

-- KRS semester 5 milik Ardi Kamal Karima (mahasiswa_id=1) — sudah disetujui
INSERT INTO krs (mahasiswa_id, mata_kuliah_id, tahun_akademik_id, status) VALUES
(1, 1, 1, 'Disetujui'),
(1, 2, 1, 'Disetujui');

-- Nilai semester 5 milik Ardi Kamal Karima
INSERT INTO nilai (mahasiswa_id, mata_kuliah_id, tahun_akademik_id, nilai_angka, nilai_huruf, bobot) VALUES
(1, 1, 1, 88.00, 'A', 4.00),
(1, 2, 1, 82.00, 'AB', 3.50);

-- KRS semester 6 (aktif) milik Ardi Kamal Karima — diambil, menunggu/​sudah disetujui, belum dinilai
INSERT INTO krs (mahasiswa_id, mata_kuliah_id, tahun_akademik_id, status) VALUES
(1, 3, 2, 'Disetujui'),
(1, 4, 2, 'Disetujui'),
(1, 5, 2, 'Diajukan');

-- KRS semester 6 mahasiswa lain (biar tabel Isi Nilai dosen tidak cuma 1 baris)
INSERT INTO krs (mahasiswa_id, mata_kuliah_id, tahun_akademik_id, status) VALUES
(2, 3, 2, 'Disetujui'),
(2, 5, 2, 'Disetujui'),
(3, 4, 2, 'Disetujui'),
(3, 6, 2, 'Diajukan');
