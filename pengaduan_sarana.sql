-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 17 Bulan Mei 2026 pada 06.37
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `pengaduan_sarana`
--

DELIMITER $$
--
-- Prosedur
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_lihat_semua_aspirasi` ()   BEGIN
    SELECT
        i.id_pelaporan,
        s.nama_siswa,
        k.ket_kategori,
        i.lokasi,
        i.ket,
        a.status
    FROM tb_input_aspirasi i
    JOIN tb_siswa s ON i.nis = s.nis
    JOIN tb_kategori k ON i.id_kategori = k.id_kategori
    LEFT JOIN tb_aspirasi a ON i.id_pelaporan = a.id_pelaporan;
END$$

--
-- Fungsi
--
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_total_aspirasi_by_status` (`p_status` VARCHAR(20)) RETURNS INT(11) DETERMINISTIC BEGIN
    DECLARE total INT;
    SELECT COUNT(*) INTO total
    FROM tb_aspirasi
    WHERE status = p_status;
    RETURN total;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `tb_admin`
--

CREATE TABLE `tb_admin` (
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `tb_admin`
--

INSERT INTO `tb_admin` (`username`, `password`) VALUES
('admin', '0192023a7bbd73250516f069df18b500');

-- --------------------------------------------------------

--
-- Struktur dari tabel `tb_aspirasi`
--

CREATE TABLE `tb_aspirasi` (
  `id_aspirasi` int(5) NOT NULL,
  `id_pelaporan` int(5) DEFAULT NULL,
  `status` enum('Menunggu','Proses','Selesai') DEFAULT 'Menunggu',
  `id_kategori` int(5) DEFAULT NULL,
  `feedback` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Trigger `tb_aspirasi`
--
DELIMITER $$
CREATE TRIGGER `trg_update_status_aspirasi` AFTER UPDATE ON `tb_aspirasi` FOR EACH ROW BEGIN
    IF OLD.status <> NEW.status THEN
        INSERT INTO tb_log_sistem (kejadian)
        VALUES (
            CONCAT('ID Aspirasi ', NEW.id_aspirasi, ' berubah menjadi: ', NEW.status)
        );
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `tb_input_aspirasi`
--

CREATE TABLE `tb_input_aspirasi` (
  `id_pelaporan` int(5) NOT NULL,
  `nis` int(10) DEFAULT NULL,
  `id_kategori` int(5) DEFAULT NULL,
  `lokasi` varchar(50) DEFAULT NULL,
  `ket` varchar(50) DEFAULT NULL,
  `tgl_pelaporan` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Trigger `tb_input_aspirasi`
--
DELIMITER $$
CREATE TRIGGER `trg_setelah_input_aspirasi` AFTER INSERT ON `tb_input_aspirasi` FOR EACH ROW BEGIN
    INSERT INTO tb_aspirasi (id_pelaporan, status, feedback)
    VALUES (NEW.id_pelaporan, 'Menunggu', 'Belum ada umpan balik');

    INSERT INTO tb_log_sistem (kejadian)
    VALUES (CONCAT('Pengaduan baru dari NIS: ', NEW.nis));
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `tb_kategori`
--

CREATE TABLE `tb_kategori` (
  `id_kategori` int(5) NOT NULL,
  `ket_kategori` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `tb_kategori`
--

INSERT INTO `tb_kategori` (`id_kategori`, `ket_kategori`) VALUES
(1, 'Fasilitas Kelas'),
(2, 'Fasilitas Laboratorium'),
(3, 'Fasilitas Olahraga'),
(4, 'Fasilitas Kantin'),
(5, 'Fasilitas Toilet'),
(6, 'Lainnya');

-- --------------------------------------------------------

--
-- Struktur dari tabel `tb_log_sistem`
--

CREATE TABLE `tb_log_sistem` (
  `id_log` int(11) NOT NULL,
  `kejadian` text DEFAULT NULL,
  `waktu` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `tb_siswa`
--

CREATE TABLE `tb_siswa` (
  `NIS` int(10) NOT NULL,
  `nama_siswa` varchar(100) NOT NULL,
  `kelas` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `tb_siswa`
--

INSERT INTO `tb_siswa` (`NIS`, `nama_siswa`, `kelas`) VALUES
(12345, 'Ahmad Rahman', 'X-A'),
(12346, 'Siti Nurhaliza', 'X-B'),
(12347, 'Budi Santoso', 'XI-A'),
(12348, 'Maya Sari', 'XI-B'),
(12349, 'Rizki Pratama', 'XII-A'),
(12350, 'Dewi Lestari', 'XII-B');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `tb_admin`
--
ALTER TABLE `tb_admin`
  ADD PRIMARY KEY (`username`);

--
-- Indeks untuk tabel `tb_aspirasi`
--
ALTER TABLE `tb_aspirasi`
  ADD PRIMARY KEY (`id_aspirasi`),
  ADD KEY `tb_aspirasi_ibfk_1` (`id_pelaporan`);

--
-- Indeks untuk tabel `tb_input_aspirasi`
--
ALTER TABLE `tb_input_aspirasi`
  ADD PRIMARY KEY (`id_pelaporan`),
  ADD KEY `nis` (`nis`),
  ADD KEY `id_kategori` (`id_kategori`);

--
-- Indeks untuk tabel `tb_kategori`
--
ALTER TABLE `tb_kategori`
  ADD PRIMARY KEY (`id_kategori`);

--
-- Indeks untuk tabel `tb_log_sistem`
--
ALTER TABLE `tb_log_sistem`
  ADD PRIMARY KEY (`id_log`);

--
-- Indeks untuk tabel `tb_siswa`
--
ALTER TABLE `tb_siswa`
  ADD PRIMARY KEY (`NIS`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `tb_aspirasi`
--
ALTER TABLE `tb_aspirasi`
  MODIFY `id_aspirasi` int(5) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `tb_input_aspirasi`
--
ALTER TABLE `tb_input_aspirasi`
  MODIFY `id_pelaporan` int(5) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `tb_kategori`
--
ALTER TABLE `tb_kategori`
  MODIFY `id_kategori` int(5) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `tb_log_sistem`
--
ALTER TABLE `tb_log_sistem`
  MODIFY `id_log` int(11) NOT NULL AUTO_INCREMENT;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `tb_aspirasi`
--
ALTER TABLE `tb_aspirasi`
  ADD CONSTRAINT `tb_aspirasi_ibfk_1` FOREIGN KEY (`id_pelaporan`) REFERENCES `tb_input_aspirasi` (`id_pelaporan`);

--
-- Ketidakleluasaan untuk tabel `tb_input_aspirasi`
--
ALTER TABLE `tb_input_aspirasi`
  ADD CONSTRAINT `tb_input_aspirasi_ibfk_1` FOREIGN KEY (`nis`) REFERENCES `tb_siswa` (`NIS`),
  ADD CONSTRAINT `tb_input_aspirasi_ibfk_2` FOREIGN KEY (`id_kategori`) REFERENCES `tb_kategori` (`id_kategori`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
