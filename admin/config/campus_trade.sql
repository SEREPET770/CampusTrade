-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jun 30, 2026 at 04:07 AM
-- Server version: 8.0.30
-- PHP Version: 8.3.29

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `campus_trade`
--

-- --------------------------------------------------------

--
-- Table structure for table `gambar_produk`
--

CREATE TABLE `gambar_produk` (
  `id_gambar` int NOT NULL,
  `id_produk` int NOT NULL,
  `image_path` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `gambar_produk`
--

INSERT INTO `gambar_produk` (`id_gambar`, `id_produk`, `image_path`) VALUES
(8, 39, 'prod_6a3ea8175fc9e.jpg'),
(10, 41, 'prod_6a40f124de89d.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `kategori_barang`
--

CREATE TABLE `kategori_barang` (
  `id_kategori` int NOT NULL,
  `nama_kategori` varchar(100) NOT NULL,
  `deskripsi` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `kategori_barang`
--

INSERT INTO `kategori_barang` (`id_kategori`, `nama_kategori`, `deskripsi`) VALUES
(1, 'Elektronik', 'Perangkat elektronik seperti laptop, hp, kamera'),
(2, 'Fashion', 'Pakaian, sepatu, tas dan aksesoris'),
(3, 'Buku', 'Buku kuliah, novel, dan referensi'),
(4, 'Alat Tulis', 'Peralatan tulis dan stationery'),
(5, 'Olahraga', 'Peralatan dan perlengkapan olahraga');

-- --------------------------------------------------------

--
-- Table structure for table `log_verifikasi`
--

CREATE TABLE `log_verifikasi` (
  `id_verifikasi` int NOT NULL,
  `id_user` int NOT NULL,
  `verified_by` int NOT NULL,
  `status` enum('terverifikasi','ditolak') NOT NULL,
  `catatan` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lokasi`
--

CREATE TABLE `lokasi` (
  `id_lokasi` int NOT NULL,
  `nama_lokasi` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `lokasi`
--

INSERT INTO `lokasi` (`id_lokasi`, `nama_lokasi`) VALUES
(20, 'Institut Teknologi Adhi Tama Surabaya (ITATS)'),
(1, 'Institut Teknologi Sepuluh Nopember (ITS)'),
(2, 'Politeknik Elektronika Negeri Surabaya (PENS)'),
(3, 'Politeknik Perkapalan Negeri Surabaya (PPNS)'),
(13, 'UIN Sunan Ampel Surabaya'),
(14, 'Universitas 17 Agustus 1945 Surabaya (UNTAG)'),
(4, 'Universitas Airlangga (UNAIR) Kampus A'),
(5, 'Universitas Airlangga (UNAIR) Kampus B'),
(6, 'Universitas Airlangga (UNAIR) Kampus C'),
(12, 'Universitas Ciputra'),
(15, 'Universitas Dinamika (STIKOM Surabaya)'),
(17, 'Universitas Dr. Soetomo (UNITOMO)'),
(11, 'Universitas Kristen Petra'),
(19, 'Universitas Muhammadiyah Surabaya'),
(18, 'Universitas Nahdlatul Ulama Surabaya (UNUSA)'),
(16, 'Universitas Narotama'),
(7, 'Universitas Negeri Surabaya (UNESA) Ketintang'),
(8, 'Universitas Negeri Surabaya (UNESA) Lidah Wetan'),
(10, 'Universitas Surabaya (UBAYA)'),
(9, 'UPN Veteran Jawa Timur');

-- --------------------------------------------------------

--
-- Table structure for table `metode_pembayaran`
--

CREATE TABLE `metode_pembayaran` (
  `id_metode` int NOT NULL,
  `nama_metode` varchar(50) DEFAULT NULL,
  `jenis` enum('Bank','E-Wallet','QRIS') DEFAULT NULL,
  `provider` varchar(50) DEFAULT NULL,
  `nomor_tujuan` varchar(50) DEFAULT NULL,
  `nama_pemilik` varchar(100) DEFAULT NULL,
  `catatan` varchar(255) DEFAULT NULL,
  `qr_code` varchar(255) DEFAULT NULL,
  `status` tinyint(1) DEFAULT '1',
  `id_user` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `metode_pembayaran`
--

INSERT INTO `metode_pembayaran` (`id_metode`, `nama_metode`, `jenis`, `provider`, `nomor_tujuan`, `nama_pemilik`, `catatan`, `qr_code`, `status`, `id_user`) VALUES
(1, 'BCA', 'Bank', 'BCA', '1234567890', 'Dewi Lestari', 'Transfer sesuai nominal tagihan', NULL, 1, 9),
(2, 'DANA', 'E-Wallet', 'DANA', '089876543210', 'Dewi Lestari', 'Kirim bukti setelah transfer', NULL, 1, 9),
(3, 'OVO', 'E-Wallet', 'OVO', '089876543210', 'Dewi Lestari', NULL, NULL, 1, 9);

-- --------------------------------------------------------

--
-- Table structure for table `pembayaran`
--

CREATE TABLE `pembayaran` (
  `id_pembayaran` int NOT NULL,
  `id_transaksi` int NOT NULL,
  `id_metode` int NOT NULL,
  `nominal` decimal(12,2) NOT NULL,
  `bukti_pembayaran` varchar(255) DEFAULT NULL,
  `catatan_pembeli` text,
  `status_verifikasi` enum('belum_bayar','menunggu_verifikasi','diterima','ditolak') DEFAULT 'belum_bayar',
  `tanggal_upload` datetime DEFAULT NULL,
  `tanggal_verifikasi` datetime DEFAULT NULL,
  `catatan_admin` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `pembayaran`
--

INSERT INTO `pembayaran` (`id_pembayaran`, `id_transaksi`, `id_metode`, `nominal`, `bukti_pembayaran`, `catatan_pembeli`, `status_verifikasi`, `tanggal_upload`, `tanggal_verifikasi`, `catatan_admin`, `created_at`) VALUES
(10, 9, 2, '1000000.00', 'BYR_9_1782674773.png', '', 'diterima', '2026-06-29 02:26:13', '2026-06-29 02:53:19', '', '2026-06-28 19:26:13');

-- --------------------------------------------------------

--
-- Table structure for table `pembayaran_produk_backup`
--

CREATE TABLE `pembayaran_produk_backup` (
  `id_pembayaran` int NOT NULL,
  `id_produk` int NOT NULL,
  `nominal` decimal(10,2) DEFAULT '3000.00',
  `bukti_pembayaran` varchar(255) DEFAULT NULL,
  `status_pembayaran` enum('belum_bayar','menunggu_verifikasi','aktif','ditolak') NOT NULL DEFAULT 'belum_bayar',
  `tanggal_bayar` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `pembayaran_produk_backup`
--

INSERT INTO `pembayaran_produk_backup` (`id_pembayaran`, `id_produk`, `nominal`, `bukti_pembayaran`, `status_pembayaran`, `tanggal_bayar`) VALUES
(6, 39, '3000.00', NULL, 'belum_bayar', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `pembayaran_transaksi_backup`
--

CREATE TABLE `pembayaran_transaksi_backup` (
  `id_pembayaran` int NOT NULL,
  `id_transaksi` int NOT NULL,
  `metode_pembayaran` enum('Transfer Bank','QRIS') NOT NULL,
  `bukti_pembayaran` varchar(255) DEFAULT NULL,
  `status_verifikasi` enum('belum_upload','menunggu_verifikasi','lunas','ditolak') DEFAULT 'belum_upload',
  `catatan_admin` text,
  `tanggal_bayar` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pengaturan`
--

CREATE TABLE `pengaturan` (
  `id_pengaturan` int NOT NULL,
  `tarif_ongkir_per_km` decimal(10,2) NOT NULL,
  `biaya_admin` decimal(10,2) DEFAULT '0.00',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `pengaturan`
--

INSERT INTO `pengaturan` (`id_pengaturan`, `tarif_ongkir_per_km`, `biaya_admin`, `updated_at`) VALUES
(1, '2000.00', '0.00', '2026-06-27 12:55:44');

-- --------------------------------------------------------

--
-- Table structure for table `pengiriman`
--

CREATE TABLE `pengiriman` (
  `id_pengiriman` int NOT NULL,
  `id_transaksi` int NOT NULL,
  `nama_penerima` varchar(100) DEFAULT NULL,
  `no_hp` varchar(20) DEFAULT NULL,
  `alamat` text,
  `metode_pengiriman` enum('Ambil di Tempat','Kurir Kampus','Ekspedisi') DEFAULT NULL,
  `nomor_resi` varchar(100) DEFAULT NULL,
  `status_pengiriman` enum('Belum Dikirim','Sedang Dikirim','Sudah Diterima') DEFAULT 'Belum Dikirim',
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `jarak_km` decimal(10,2) DEFAULT NULL,
  `ongkir` decimal(10,2) DEFAULT NULL,
  `tanggal_kirim` datetime DEFAULT NULL,
  `tanggal_diterima` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `pengiriman`
--

INSERT INTO `pengiriman` (`id_pengiriman`, `id_transaksi`, `nama_penerima`, `no_hp`, `alamat`, `metode_pengiriman`, `nomor_resi`, `status_pengiriman`, `latitude`, `longitude`, `jarak_km`, `ongkir`, `tanggal_kirim`, `tanggal_diterima`) VALUES
(2, 6, 'Siti Rahayu', '087775707406', 'Wiyung', 'Kurir Kampus', NULL, 'Belum Dikirim', '-7.31296009', '112.70914577', '4.64', '9280.00', NULL, NULL),
(3, 7, 'Siti Rahayu', '087775707406', 'Wiyung', 'Ekspedisi', NULL, 'Belum Dikirim', '-7.31227102', '112.69698510', '0.00', '0.00', NULL, NULL),
(4, 8, 'Siti Rahayu', '087775707406', 'Wiyung', 'Kurir Kampus', NULL, 'Belum Dikirim', '-7.31231785', '112.69698730', '0.00', '0.00', NULL, NULL),
(5, 9, 'M. ABID ALFAUZAN', '085792448847', 'Wiyung', 'Ekspedisi', NULL, 'Belum Dikirim', '-7.31232379', '112.69705983', '0.00', '0.00', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `produk`
--

CREATE TABLE `produk` (
  `id_produk` int NOT NULL,
  `id_user` int NOT NULL,
  `id_kategori` int NOT NULL,
  `id_lokasi` int DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `nama_produk` varchar(150) NOT NULL,
  `deskripsi` text NOT NULL,
  `harga` decimal(12,2) NOT NULL,
  `ongkir` decimal(10,2) DEFAULT '0.00',
  `metode_pengiriman` enum('Ambil di Tempat','Kurir Kampus','Ekspedisi') DEFAULT 'Ambil di Tempat',
  `kondisi` enum('Baru','Bekas') NOT NULL,
  `alasan_jual` text,
  `status_produk` enum('menunggu','tersedia','dipesan','terjual','ditolak') DEFAULT 'menunggu',
  `status_aktif` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `kelebihan` text,
  `kekurangan` text,
  `brand` varchar(100) DEFAULT NULL,
  `penulis_penerbit` varchar(255) DEFAULT NULL,
  `status_tayang` enum('belum_bayar','aktif') DEFAULT 'belum_bayar'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `produk`
--

INSERT INTO `produk` (`id_produk`, `id_user`, `id_kategori`, `id_lokasi`, `latitude`, `longitude`, `nama_produk`, `deskripsi`, `harga`, `ongkir`, `metode_pengiriman`, `kondisi`, `alasan_jual`, `status_produk`, `status_aktif`, `created_at`, `kelebihan`, `kekurangan`, `brand`, `penulis_penerbit`, `status_tayang`) VALUES
(39, 9, 2, 18, '-7.28790000', '112.74280000', 'Baju Mclaren', 'Ukuran : XL', '100000.00', '0.00', 'Ambil di Tempat', 'Bekas', 'Jarang digunakan', 'dipesan', 1, '2026-06-26 16:25:59', NULL, NULL, 'Puma', NULL, 'belum_bayar'),
(41, 9, 5, 18, NULL, NULL, 'Miniatur mobil McLaren', 'Masih mulus', '1000000.00', '0.00', 'Ambil di Tempat', 'Baru', 'Jarang digunakan', 'dipesan', 1, '2026-06-28 10:02:12', NULL, NULL, 'McLaren', NULL, 'belum_bayar');

-- --------------------------------------------------------

--
-- Table structure for table `tarif_ongkir`
--

CREATE TABLE `tarif_ongkir` (
  `id_tarif` int NOT NULL,
  `tarif_per_km` decimal(10,2) NOT NULL,
  `status_aktif` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transaksi`
--

CREATE TABLE `transaksi` (
  `id_transaksi` int NOT NULL,
  `id_produk` int NOT NULL,
  `id_penjual` int NOT NULL,
  `id_pembeli` int NOT NULL,
  `harga_produk` decimal(12,2) NOT NULL,
  `ongkir` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_bayar` decimal(12,2) NOT NULL,
  `status_transaksi` enum('menunggu_pembayaran','menunggu_verifikasi','diproses','dikirim','selesai','dibatalkan') DEFAULT 'menunggu_pembayaran',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `kode_invoice` varchar(30) DEFAULT NULL,
  `jarak_km` decimal(10,2) DEFAULT NULL,
  `metode_pembayaran` enum('Transfer Bank','DANA','OVO','GoPay','ShopeePay') DEFAULT NULL,
  `status_pembayaran` enum('belum_bayar','menunggu_konfirmasi','dibayar','ditolak') DEFAULT 'belum_bayar'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `transaksi`
--

INSERT INTO `transaksi` (`id_transaksi`, `id_produk`, `id_penjual`, `id_pembeli`, `harga_produk`, `ongkir`, `total_bayar`, `status_transaksi`, `created_at`, `kode_invoice`, `jarak_km`, `metode_pembayaran`, `status_pembayaran`) VALUES
(6, 39, 9, 7, '100000.00', '9280.00', '109280.00', 'menunggu_pembayaran', '2026-06-27 13:42:07', 'INV202606270001', '4.64', 'DANA', 'belum_bayar'),
(7, 39, 9, 7, '100000.00', '0.00', '100000.00', 'menunggu_pembayaran', '2026-06-28 10:29:25', 'CT-20260628-593506', '0.00', NULL, 'belum_bayar'),
(8, 39, 9, 7, '100000.00', '0.00', '100000.00', 'menunggu_pembayaran', '2026-06-28 10:46:16', 'CT-20260628-87C77E', '0.00', NULL, 'belum_bayar'),
(9, 41, 9, 12, '1000000.00', '0.00', '1000000.00', 'diproses', '2026-06-28 18:44:47', 'CT-20260629-FA021E', '0.00', NULL, 'menunggu_konfirmasi');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id_user` int NOT NULL,
  `nama` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nim` varchar(20) NOT NULL,
  `no_whatsapp` varchar(20) NOT NULL,
  `alamat` text,
  `kota` varchar(100) DEFAULT NULL,
  `kode_pos` varchar(10) DEFAULT NULL,
  `foto_ktm` varchar(255) DEFAULT NULL,
  `role` enum('admin','user') DEFAULT 'user',
  `status_verifikasi` enum('menunggu','terverifikasi','ditolak') DEFAULT 'menunggu',
  `status_aktif` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `terakhir_online` datetime DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id_user`, `nama`, `email`, `password`, `nim`, `no_whatsapp`, `alamat`, `kota`, `kode_pos`, `foto_ktm`, `role`, `status_verifikasi`, `status_aktif`, `created_at`, `terakhir_online`, `latitude`, `longitude`) VALUES
(4, 'M. ABID ALFAUZAN', 'serepetsingkong7@gmail.com', '$2y$10$ApjZ.M/iKqSyEVixGR4.7O0UyWAJnGfmSmc8aUM00U/UbTMoHpV8e', '3130024026', '085792448847', NULL, NULL, NULL, 'ktm_6a205311ec321.jpg', 'admin', 'terverifikasi', 1, '2026-06-03 16:15:13', NULL, NULL, NULL),
(7, 'Siti Rahayu', 'siti@gmail.com', 'user123', '2021003', '087775707406', NULL, NULL, NULL, 'ktm_003.jpg', 'user', 'terverifikasi', 1, '2026-06-06 05:31:55', '2026-06-28 21:13:28', NULL, NULL),
(9, 'Dewi Lestari', 'dewi@gmail.com', 'user123', '2021005', '085792448847', NULL, NULL, NULL, 'ktm_005.jpg', 'user', 'terverifikasi', 1, '2026-06-06 05:31:55', '2026-06-30 10:59:06', NULL, NULL),
(11, 'ABID', 'serepetsingkong6@gmail.com', 'Abid1234?', '3130024020', '085692438846', NULL, NULL, NULL, 'ktm_6a24300282894.jpg', 'admin', 'terverifikasi', 1, '2026-06-06 14:34:42', '2026-06-30 06:32:06', NULL, NULL),
(12, 'M. ABID ALFAUZAN', '3130024027@student.unusa.ac.id', '12345678', '3130024027', '085792448847', NULL, NULL, NULL, 'ktm_6a3de92b272ea.jpg', 'user', 'terverifikasi', 1, '2026-06-26 02:51:23', '2026-06-30 10:56:21', NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `gambar_produk`
--
ALTER TABLE `gambar_produk`
  ADD PRIMARY KEY (`id_gambar`),
  ADD KEY `fk_gambar_produk` (`id_produk`);

--
-- Indexes for table `kategori_barang`
--
ALTER TABLE `kategori_barang`
  ADD PRIMARY KEY (`id_kategori`);

--
-- Indexes for table `log_verifikasi`
--
ALTER TABLE `log_verifikasi`
  ADD PRIMARY KEY (`id_verifikasi`),
  ADD KEY `fk_verifikasi_user` (`id_user`),
  ADD KEY `fk_verifikasi_admin` (`verified_by`);

--
-- Indexes for table `lokasi`
--
ALTER TABLE `lokasi`
  ADD PRIMARY KEY (`id_lokasi`),
  ADD UNIQUE KEY `nama_lokasi` (`nama_lokasi`);

--
-- Indexes for table `metode_pembayaran`
--
ALTER TABLE `metode_pembayaran`
  ADD PRIMARY KEY (`id_metode`);

--
-- Indexes for table `pembayaran`
--
ALTER TABLE `pembayaran`
  ADD PRIMARY KEY (`id_pembayaran`),
  ADD KEY `fk_pembayaran_transaksi` (`id_transaksi`),
  ADD KEY `fk_pembayaran_metode` (`id_metode`);

--
-- Indexes for table `pembayaran_produk_backup`
--
ALTER TABLE `pembayaran_produk_backup`
  ADD PRIMARY KEY (`id_pembayaran`),
  ADD KEY `id_produk` (`id_produk`);

--
-- Indexes for table `pembayaran_transaksi_backup`
--
ALTER TABLE `pembayaran_transaksi_backup`
  ADD PRIMARY KEY (`id_pembayaran`),
  ADD KEY `id_transaksi` (`id_transaksi`);

--
-- Indexes for table `pengaturan`
--
ALTER TABLE `pengaturan`
  ADD PRIMARY KEY (`id_pengaturan`);

--
-- Indexes for table `pengiriman`
--
ALTER TABLE `pengiriman`
  ADD PRIMARY KEY (`id_pengiriman`),
  ADD KEY `id_transaksi` (`id_transaksi`);

--
-- Indexes for table `produk`
--
ALTER TABLE `produk`
  ADD PRIMARY KEY (`id_produk`),
  ADD KEY `fk_produk_user` (`id_user`),
  ADD KEY `fk_produk_kategori` (`id_kategori`),
  ADD KEY `fk_produk_lokasi` (`id_lokasi`);

--
-- Indexes for table `tarif_ongkir`
--
ALTER TABLE `tarif_ongkir`
  ADD PRIMARY KEY (`id_tarif`);

--
-- Indexes for table `transaksi`
--
ALTER TABLE `transaksi`
  ADD PRIMARY KEY (`id_transaksi`),
  ADD UNIQUE KEY `kode_invoice` (`kode_invoice`),
  ADD KEY `id_produk` (`id_produk`),
  ADD KEY `id_penjual` (`id_penjual`),
  ADD KEY `id_pembeli` (`id_pembeli`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id_user`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `nim` (`nim`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `gambar_produk`
--
ALTER TABLE `gambar_produk`
  MODIFY `id_gambar` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `kategori_barang`
--
ALTER TABLE `kategori_barang`
  MODIFY `id_kategori` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `log_verifikasi`
--
ALTER TABLE `log_verifikasi`
  MODIFY `id_verifikasi` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lokasi`
--
ALTER TABLE `lokasi`
  MODIFY `id_lokasi` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `metode_pembayaran`
--
ALTER TABLE `metode_pembayaran`
  MODIFY `id_metode` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `pembayaran`
--
ALTER TABLE `pembayaran`
  MODIFY `id_pembayaran` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `pembayaran_produk_backup`
--
ALTER TABLE `pembayaran_produk_backup`
  MODIFY `id_pembayaran` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `pembayaran_transaksi_backup`
--
ALTER TABLE `pembayaran_transaksi_backup`
  MODIFY `id_pembayaran` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pengaturan`
--
ALTER TABLE `pengaturan`
  MODIFY `id_pengaturan` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `pengiriman`
--
ALTER TABLE `pengiriman`
  MODIFY `id_pengiriman` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `produk`
--
ALTER TABLE `produk`
  MODIFY `id_produk` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `tarif_ongkir`
--
ALTER TABLE `tarif_ongkir`
  MODIFY `id_tarif` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transaksi`
--
ALTER TABLE `transaksi`
  MODIFY `id_transaksi` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id_user` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `gambar_produk`
--
ALTER TABLE `gambar_produk`
  ADD CONSTRAINT `fk_gambar_produk` FOREIGN KEY (`id_produk`) REFERENCES `produk` (`id_produk`) ON DELETE CASCADE;

--
-- Constraints for table `log_verifikasi`
--
ALTER TABLE `log_verifikasi`
  ADD CONSTRAINT `fk_verifikasi_admin` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id_user`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_verifikasi_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON DELETE CASCADE;

--
-- Constraints for table `pembayaran`
--
ALTER TABLE `pembayaran`
  ADD CONSTRAINT `fk_pembayaran_metode` FOREIGN KEY (`id_metode`) REFERENCES `metode_pembayaran` (`id_metode`),
  ADD CONSTRAINT `fk_pembayaran_transaksi` FOREIGN KEY (`id_transaksi`) REFERENCES `transaksi` (`id_transaksi`) ON DELETE CASCADE;

--
-- Constraints for table `pembayaran_produk_backup`
--
ALTER TABLE `pembayaran_produk_backup`
  ADD CONSTRAINT `pembayaran_produk_backup_ibfk_1` FOREIGN KEY (`id_produk`) REFERENCES `produk` (`id_produk`) ON DELETE CASCADE;

--
-- Constraints for table `pembayaran_transaksi_backup`
--
ALTER TABLE `pembayaran_transaksi_backup`
  ADD CONSTRAINT `pembayaran_transaksi_backup_ibfk_1` FOREIGN KEY (`id_transaksi`) REFERENCES `transaksi` (`id_transaksi`);

--
-- Constraints for table `pengiriman`
--
ALTER TABLE `pengiriman`
  ADD CONSTRAINT `pengiriman_ibfk_1` FOREIGN KEY (`id_transaksi`) REFERENCES `transaksi` (`id_transaksi`);

--
-- Constraints for table `produk`
--
ALTER TABLE `produk`
  ADD CONSTRAINT `fk_produk_kategori` FOREIGN KEY (`id_kategori`) REFERENCES `kategori_barang` (`id_kategori`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_produk_lokasi` FOREIGN KEY (`id_lokasi`) REFERENCES `lokasi` (`id_lokasi`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_produk_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON DELETE CASCADE;

--
-- Constraints for table `transaksi`
--
ALTER TABLE `transaksi`
  ADD CONSTRAINT `transaksi_ibfk_1` FOREIGN KEY (`id_produk`) REFERENCES `produk` (`id_produk`),
  ADD CONSTRAINT `transaksi_ibfk_2` FOREIGN KEY (`id_penjual`) REFERENCES `users` (`id_user`),
  ADD CONSTRAINT `transaksi_ibfk_3` FOREIGN KEY (`id_pembeli`) REFERENCES `users` (`id_user`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
