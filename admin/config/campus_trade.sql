-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jun 02, 2026 at 10:56 PM
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

-- --------------------------------------------------------

--
-- Table structure for table `kategori_barang`
--

CREATE TABLE `kategori_barang` (
  `id_kategori` int NOT NULL,
  `nama_kategori` varchar(100) NOT NULL,
  `deskripsi` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
-- Table structure for table `produk`
--

CREATE TABLE `produk` (
  `id_produk` int NOT NULL,
  `id_user` int NOT NULL,
  `id_kategori` int NOT NULL,
  `nama_produk` varchar(150) NOT NULL,
  `deskripsi` text NOT NULL,
  `harga` decimal(12,2) NOT NULL,
  `kondisi` enum('Baru','Bekas') NOT NULL,
  `status_produk` enum('tersedia','terjual','ditolak') DEFAULT 'tersedia',
  `status_aktif` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
  `foto_ktm` varchar(255) DEFAULT NULL,
  `role` enum('admin','user') DEFAULT 'user',
  `status_verifikasi` enum('menunggu','terverifikasi','ditolak') DEFAULT 'menunggu',
  `status_aktif` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
-- Indexes for table `produk`
--
ALTER TABLE `produk`
  ADD PRIMARY KEY (`id_produk`),
  ADD KEY `fk_produk_user` (`id_user`),
  ADD KEY `fk_produk_kategori` (`id_kategori`);

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
  MODIFY `id_gambar` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `kategori_barang`
--
ALTER TABLE `kategori_barang`
  MODIFY `id_kategori` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `log_verifikasi`
--
ALTER TABLE `log_verifikasi`
  MODIFY `id_verifikasi` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `produk`
--
ALTER TABLE `produk`
  MODIFY `id_produk` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id_user` int NOT NULL AUTO_INCREMENT;

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
-- Constraints for table `produk`
--
ALTER TABLE `produk`
  ADD CONSTRAINT `fk_produk_kategori` FOREIGN KEY (`id_kategori`) REFERENCES `kategori_barang` (`id_kategori`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_produk_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
