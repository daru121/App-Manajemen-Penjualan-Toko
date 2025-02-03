-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 14 Jan 2025 pada 04.02
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
-- Database: `simplepos`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `barang`
--

CREATE TABLE `barang` (
  `id` int(11) NOT NULL,
  `nama_barang` varchar(100) NOT NULL,
  `kategori_id` int(11) DEFAULT NULL,
  `harga_modal` decimal(10,2) NOT NULL,
  `harga` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `gambar` varchar(255) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `barang`
--

INSERT INTO `barang` (`id`, `nama_barang`, `kategori_id`, `harga_modal`, `harga`, `created_at`, `gambar`, `supplier_id`) VALUES
(1, 'Car Holder', 1, 13719.00, 20000.00, '2025-01-06 04:44:39', '677b5fb711e69.jpeg', 1),
(2, '1 Paket strap hp', 1, 29677.00, 49000.00, '2025-01-06 05:59:29', '677b71419d719.jpeg', 2),
(3, '4 in 1 type c USB', 1, 19372.00, 32000.00, '2025-01-06 06:00:19', '677b717376cda.jpeg', 2),
(4, 'Action figure conan', 5, 20903.00, 32000.00, '2025-01-06 06:01:23', '677b71b387dec.jpeg', 2),
(5, 'Action figure jujutsu', 5, 9609.00, 20000.00, '2025-01-06 06:02:34', '677b71fa7448e.jpeg', NULL),
(6, 'Action Figure Haikyuu', 5, 16231.00, 20000.00, '2025-01-06 06:03:17', '677b7225430f4.jpeg', NULL),
(7, 'Action figure kimetsu', 5, 12046.00, 25000.00, '2025-01-06 06:05:30', '677b72aa02987.jpeg', 1),
(8, 'Action Figure One piece', 5, 26731.00, 9609.00, '2025-01-06 06:06:48', '677b72f8d3cd4.jpeg', NULL),
(9, 'Audio mic gaming', 5, 5816.00, 13000.00, '2025-01-06 06:07:19', '677b7317b0ac7.jpeg', NULL),
(10, 'Bluetooth selfie stick', 1, 15168.00, 25199.00, '2025-01-06 06:07:51', '677b7337eff31.jpeg', 1),
(11, 'Bracket full besi dudukan lampu', 6, 9609.00, 18900.00, '2025-01-06 06:09:00', '677b737c4baf8.jpeg', NULL),
(12, 'Car charger', 1, 9269.00, 19200.00, '2025-01-06 06:09:35', '677b739f4898a.jpeg', NULL),
(13, 'Cooling pad fan cooler', 1, 14100.00, 25100.00, '2025-01-06 06:10:25', '677b73d1ca78a.jpeg', NULL),
(14, 'Headset realme R50', 1, 3417.00, 9900.00, '2025-01-06 06:10:59', '677b73f3d060e.jpeg', 1),
(15, 'Fantech Headset CHIEF II', 3, 114920.00, 15900.00, '2025-01-06 06:11:44', '677b742084648.jpeg', 1),
(16, 'Mousepad logo Microsoft', 2, 2900.00, 20000.00, '2025-01-06 06:12:36', '677b74548097f.jpeg', 2),
(17, 'dfsadsadsa', 5, 10000.00, 1000.00, '2025-01-06 12:35:24', NULL, 2);

-- --------------------------------------------------------

--
-- Struktur dari tabel `detail_transaksi`
--

CREATE TABLE `detail_transaksi` (
  `id` int(11) NOT NULL,
  `transaksi_id` int(11) NOT NULL,
  `barang_id` int(11) NOT NULL,
  `jumlah` int(11) NOT NULL,
  `harga` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `detail_transaksi`
--

INSERT INTO `detail_transaksi` (`id`, `transaksi_id`, `barang_id`, `jumlah`, `harga`, `created_at`) VALUES
(11, 9, 5, 1, 20000.00, '2025-01-06 08:14:42'),
(31, 28, 1, 1, 20000.00, '2025-01-07 01:21:20'),
(32, 29, 1, 1, 20000.00, '2025-01-07 01:23:03'),
(33, 30, 1, 1, 20000.00, '2025-01-07 01:25:28'),
(41, 36, 1, 6, 20000.00, '2025-01-09 01:57:38'),
(44, 39, 1, 1, 20000.00, '2025-01-11 12:17:10'),
(45, 40, 1, 1, 20000.00, '2025-01-11 12:17:28'),
(46, 41, 6, 1, 20000.00, '2025-01-11 12:40:25'),
(47, 41, 7, 1, 25000.00, '2025-01-11 12:40:25'),
(48, 41, 4, 1, 32000.00, '2025-01-11 12:40:25'),
(49, 41, 3, 1, 32000.00, '2025-01-11 12:40:25'),
(50, 42, 10, 1, 25199.00, '2025-01-11 12:48:58'),
(51, 43, 13, 1, 25100.00, '2025-01-11 12:50:56'),
(52, 44, 13, 1, 25100.00, '2025-01-11 12:51:39'),
(53, 45, 14, 1, 9900.00, '2025-01-11 12:53:42'),
(54, 46, 10, 1, 25199.00, '2025-01-11 12:55:20'),
(55, 47, 12, 1, 19200.00, '2025-01-11 12:57:49'),
(56, 48, 10, 1, 25199.00, '2025-01-11 13:01:44'),
(57, 49, 5, 1, 20000.00, '2025-01-11 13:03:26'),
(58, 50, 6, 1, 20000.00, '2025-01-11 13:05:13'),
(59, 51, 1, 1, 20000.00, '2025-01-11 13:13:24'),
(60, 52, 2, 1, 49000.00, '2025-01-11 13:15:14'),
(61, 53, 2, 1, 49000.00, '2025-01-11 13:15:43'),
(62, 53, 3, 1, 32000.00, '2025-01-11 13:15:43'),
(63, 54, 1, 1, 20000.00, '2025-01-11 13:19:35'),
(64, 55, 1, 1, 20000.00, '2025-01-11 13:21:22'),
(65, 56, 1, 1, 20000.00, '2025-01-12 01:01:54'),
(66, 56, 2, 1, 49000.00, '2025-01-12 01:01:54'),
(67, 57, 1, 1, 20000.00, '2025-01-12 02:30:58'),
(68, 57, 2, 1, 49000.00, '2025-01-12 02:30:58'),
(69, 58, 5, 1, 20000.00, '2025-01-12 02:34:12'),
(70, 59, 8, 1, 9609.00, '2025-01-12 02:36:28'),
(71, 60, 7, 1, 25000.00, '2025-01-12 02:38:11'),
(72, 61, 6, 1, 20000.00, '2025-01-12 03:22:10'),
(73, 62, 2, 1, 49000.00, '2025-01-12 04:27:54'),
(74, 63, 1, 1, 20000.00, '2025-01-13 03:06:53'),
(75, 63, 3, 1, 32000.00, '2025-01-13 03:06:53'),
(76, 63, 2, 1, 49000.00, '2025-01-13 03:06:53');

-- --------------------------------------------------------

--
-- Struktur dari tabel `kategori`
--

CREATE TABLE `kategori` (
  `id` int(11) NOT NULL,
  `nama_kategori` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `kategori`
--

INSERT INTO `kategori` (`id`, `nama_kategori`, `created_at`) VALUES
(1, 'Aksesoris HP', '2025-01-06 04:23:01'),
(2, 'Aksesoris Laptop', '2025-01-06 04:23:01'),
(3, 'Aksesoris Gaming', '2025-01-06 04:23:01'),
(4, 'Aksesoris Fashion', '2025-01-06 04:23:01'),
(5, 'Aksesoris Action Figure', '2025-01-06 06:00:44'),
(6, 'Aksesoris Motor', '2025-01-06 06:08:33');

-- --------------------------------------------------------

--
-- Struktur dari tabel `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reference_id` int(11) DEFAULT NULL,
  `reference_type` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `notifications`
--

INSERT INTO `notifications` (`id`, `type`, `title`, `message`, `is_read`, `created_at`, `reference_id`, `reference_type`) VALUES
(67, 'low_stock', 'Stok Sangat Menipis!', 'Produk 4 in 1 type c USB hanya tersisa 1 unit. Segera lakukan restock!', 0, '2025-01-06 11:51:32', 3, 'product'),
(68, 'low_stock', 'Stok Sangat Menipis!', 'Produk Action figure conan hanya tersisa 1 unit. Segera lakukan restock!', 0, '2025-01-06 11:51:32', 4, 'product'),
(69, 'out_of_stock', '⚠️ Stok Habis!', 'Produk 1 Paket strap hp telah habis. Mohon segera lakukan restock.', 0, '2025-01-06 11:51:32', 2, 'product'),
(70, 'low_stock', 'Stok Menipis', 'Produk Action figure kimetsu hanya tersisa 3 unit. Harap segera tambah stok.', 0, '2025-01-05 11:51:45', 7, 'product'),
(71, 'out_of_stock', '⚠️ Stok Habis!', 'Produk dfsadsadsa telah habis. Mohon segera lakukan restock.', 0, '2025-01-06 12:35:24', 17, 'product'),
(72, 'low_stock', 'Stok Menipis', 'Produk Action figure kimetsu hanya tersisa 3 unit. Harap segera tambah stok.', 0, '2025-01-06 14:20:20', 7, 'product'),
(73, 'low_stock', 'Stok Sangat Menipis!', 'Produk 4 in 1 type c USB hanya tersisa 1 unit. Segera lakukan restock!', 0, '2025-01-08 06:22:58', 3, 'product'),
(74, 'low_stock', 'Stok Sangat Menipis!', 'Produk Action figure conan hanya tersisa 1 unit. Segera lakukan restock!', 0, '2025-01-08 06:22:58', 4, 'product'),
(75, 'low_stock', 'Stok Menipis', 'Produk Action figure kimetsu hanya tersisa 3 unit. Harap segera tambah stok.', 0, '2025-01-08 06:22:58', 7, 'product'),
(76, 'out_of_stock', '⚠️ Stok Habis!', 'Produk dfsadsadsa telah habis. Mohon segera lakukan restock.', 0, '2025-01-08 06:22:58', 17, 'product'),
(77, 'low_stock', 'Stok Sangat Menipis!', 'Produk 4 in 1 type c USB hanya tersisa 1 unit. Segera lakukan restock!', 0, '2025-01-11 00:22:05', 3, 'product'),
(78, 'low_stock', 'Stok Sangat Menipis!', 'Produk Action figure conan hanya tersisa 1 unit. Segera lakukan restock!', 0, '2025-01-11 00:22:05', 4, 'product'),
(79, 'low_stock', 'Stok Menipis', 'Produk Action figure kimetsu hanya tersisa 3 unit. Harap segera tambah stok.', 0, '2025-01-11 00:22:05', 7, 'product'),
(80, 'out_of_stock', '⚠️ Stok Habis!', 'Produk dfsadsadsa telah habis. Mohon segera lakukan restock.', 0, '2025-01-11 00:22:05', 17, 'product'),
(81, 'out_of_stock', '⚠️ Stok Habis!', 'Produk 4 in 1 type c USB telah habis. Mohon segera lakukan restock.', 0, '2025-01-11 12:40:25', 3, 'product'),
(82, 'out_of_stock', '⚠️ Stok Habis!', 'Produk Action figure conan telah habis. Mohon segera lakukan restock.', 0, '2025-01-11 12:40:25', 4, 'product'),
(83, 'low_stock', 'Stok Sangat Menipis!', 'Produk Action figure kimetsu hanya tersisa 2 unit. Segera lakukan restock!', 0, '2025-01-12 00:28:59', 7, 'product'),
(84, 'out_of_stock', '⚠️ Stok Habis!', 'Produk dfsadsadsa telah habis. Mohon segera lakukan restock.', 0, '2025-01-12 00:28:59', 17, 'product'),
(85, 'out_of_stock', '⚠️ Stok Habis!', 'Produk dfsadsadsa telah habis. Mohon segera lakukan restock.', 0, '2025-01-13 01:25:48', 17, 'product'),
(86, 'out_of_stock', '⚠️ Stok Habis!', 'Produk dfsadsadsa telah habis. Mohon segera lakukan restock.', 0, '2025-01-14 02:41:09', 17, 'product');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pembeli`
--

CREATE TABLE `pembeli` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pembeli`
--

INSERT INTO `pembeli` (`id`, `nama`, `created_at`) VALUES
(2, 'wsadad', '2025-01-06 04:50:39'),
(3, 'John Doe', '2025-01-06 04:55:39'),
(4, 'Jane Smith', '2025-01-06 04:55:39'),
(5, 'sadsad', '2025-01-06 04:56:07'),
(6, 'dsadsa', '2025-01-06 05:48:50'),
(7, 'panji', '2025-01-06 06:13:17'),
(8, 'dsa', '2025-01-06 06:13:58'),
(9, 'asdsad', '2025-01-06 08:14:42'),
(10, 'sdsadsad', '2025-01-06 08:14:55'),
(11, 'dsada', '2025-01-06 09:26:44'),
(12, 'daru', '2025-01-06 09:45:10'),
(13, 'sdsad', '2025-01-06 10:26:46'),
(14, 'dasdad', '2025-01-06 10:27:27'),
(15, 'asdasdasd', '2025-01-06 10:30:48'),
(16, 'sadasdsaddsa', '2025-01-06 10:31:00'),
(17, 'asdsadsad', '2025-01-06 10:31:21'),
(18, 'sadsadsad', '2025-01-06 10:31:41'),
(19, 'dsadsadad', '2025-01-06 10:32:00'),
(20, 'asdsasad', '2025-01-06 10:32:12'),
(21, 'asdasd', '2025-01-06 10:34:13'),
(22, 'sadasdad', '2025-01-06 11:49:05'),
(23, 'dsasadsad', '2025-01-06 14:34:14'),
(25, 'ADS', '2025-01-07 01:18:55'),
(26, 'SA', '2025-01-07 01:23:03'),
(27, 'SADDSA', '2025-01-07 01:25:28'),
(28, 'daruuu', '2025-01-09 01:04:27'),
(29, 'adsasdads', '2025-01-09 01:57:38'),
(30, 'da', '2025-01-11 12:50:56'),
(31, 'sad', '2025-01-11 12:51:39'),
(32, 'd', '2025-01-11 12:53:42'),
(33, 'das', '2025-01-11 12:57:49'),
(34, 'panjiii', '2025-01-11 13:13:24'),
(35, 'sadasd', '2025-01-11 13:15:14'),
(36, 'sadas2', '2025-01-11 13:19:35'),
(37, 'daru caraka', '2025-01-11 13:21:22'),
(38, 'dasdsa', '2025-01-12 01:01:54'),
(39, 'daru carakaaa', '2025-01-12 02:30:58'),
(40, 'daru craka', '2025-01-12 02:34:12'),
(41, 'dsadas', '2025-01-12 02:36:28'),
(42, 'yudha satria', '2025-01-12 02:38:11'),
(43, 'daru ganteng', '2025-01-12 03:22:10'),
(44, 'panji ganteng', '2025-01-12 04:27:54'),
(45, 'panjikusuma', '2025-01-13 03:06:53');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengeluaran`
--

CREATE TABLE `pengeluaran` (
  `id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `kategori` varchar(50) NOT NULL,
  `deskripsi` text NOT NULL,
  `jumlah` decimal(10,2) NOT NULL,
  `bukti_foto` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pengeluaran`
--

INSERT INTO `pengeluaran` (`id`, `tanggal`, `kategori`, `deskripsi`, `jumlah`, `bukti_foto`, `created_at`) VALUES
(4, '2025-01-08', 'Listrik', 'sdasda', 10000.00, '677e24945ecb0.jpg', '2025-01-08 07:09:08');

-- --------------------------------------------------------

--
-- Struktur dari tabel `stok`
--

CREATE TABLE `stok` (
  `id` int(11) NOT NULL,
  `barang_id` int(11) NOT NULL,
  `jumlah` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `stok`
--

INSERT INTO `stok` (`id`, `barang_id`, `jumlah`, `created_at`) VALUES
(1, 1, 68, '2025-01-06 04:44:39'),
(2, 2, 99, '2025-01-06 05:59:29'),
(3, 3, 98, '2025-01-06 06:00:19'),
(4, 4, 100, '2025-01-06 06:01:23'),
(5, 5, 97, '2025-01-06 06:02:34'),
(6, 6, 97, '2025-01-06 06:03:17'),
(7, 7, 100, '2025-01-06 06:05:30'),
(8, 8, 98, '2025-01-06 06:06:48'),
(9, 9, 99, '2025-01-06 06:07:19'),
(10, 10, 97, '2025-01-06 06:07:51'),
(11, 11, 149, '2025-01-06 06:09:00'),
(12, 12, 99, '2025-01-06 06:09:35'),
(13, 13, 147, '2025-01-06 06:10:25'),
(14, 14, 49, '2025-01-06 06:10:59'),
(15, 15, 150, '2025-01-06 06:11:44'),
(16, 16, 100, '2025-01-06 06:12:36'),
(17, 17, 0, '2025-01-06 12:35:24');

-- --------------------------------------------------------

--
-- Struktur dari tabel `supplier`
--

CREATE TABLE `supplier` (
  `id` int(11) NOT NULL,
  `nama_supplier` varchar(100) NOT NULL,
  `alamat` text DEFAULT NULL,
  `telepon` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `supplier`
--

INSERT INTO `supplier` (`id`, `nama_supplier`, `alamat`, `telepon`, `email`, `created_at`, `updated_at`) VALUES
(1, 'Toko Samarinda', 'JALAN SAMARINDA', '085247694758', 'dsa@gmail.com', '2025-01-06 12:12:47', '2025-01-06 12:12:47'),
(2, 'Toko bantech', 'dsakjsakdjk', '085247694758', 'dsadhasd@gmail.com', '2025-01-06 12:34:26', '2025-01-06 12:34:26'),
(3, 'sadsad', 'sadsad', 'sadsadasd', 'darucaraka8913@outook.com', '2025-01-06 13:05:23', '2025-01-06 13:05:23'),
(4, 'dsadsaad', 'sadsadsad', 'sadasdsad', 'darucaraka8913@outook.com', '2025-01-06 13:05:27', '2025-01-06 13:05:27'),
(5, 'asdsadsad', 'adsasdsad', 'dsasadsad', 'darucaraka8913@outook.com', '2025-01-06 13:05:32', '2025-01-06 13:05:32'),
(6, 'saddsasadas', 'sadsadsad', 'sadsdasda', 'darucaraka8913@outook.com', '2025-01-06 13:05:37', '2025-01-06 13:05:37'),
(7, 'asddsadsa', 'sadsadsad', 'sdsadadsads', 'darucaraka8913@outook.com', '2025-01-06 13:05:44', '2025-01-06 13:05:44'),
(8, 'sdasadsda', 'dsasda', 'dsasdasda', 'darucaraka8913@outook.com', '2025-01-06 13:06:21', '2025-01-06 13:06:21'),
(9, 'adsadssad', 'sadsadads', 'sadsadsda', 'darucaraka8913@outook.com', '2025-01-06 13:06:30', '2025-01-06 13:06:30'),
(10, 'sadsadad', 'ssadsdasda', '085247694758', 'darucaraka8913@outook.com', '2025-01-06 13:06:36', '2025-01-06 13:06:36');

-- --------------------------------------------------------

--
-- Struktur dari tabel `transaksi`
--

CREATE TABLE `transaksi` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `pembeli_id` int(11) DEFAULT NULL,
  `total_harga` decimal(10,2) NOT NULL,
  `pembayaran` decimal(10,2) NOT NULL,
  `kembalian` decimal(10,2) NOT NULL,
  `marketplace` enum('offline','shopee','tokopedia','tiktok') NOT NULL DEFAULT 'offline',
  `tanggal` timestamp NOT NULL DEFAULT current_timestamp(),
  `daerah` varchar(50) DEFAULT NULL,
  `no_resi` varchar(50) DEFAULT NULL,
  `status_pengiriman` enum('pending','dikirim','selesai','dibatalkan') DEFAULT 'pending',
  `cancellation_reason` enum('dikembalikan ke penjual','barang hilang') DEFAULT NULL,
  `kurir` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `transaksi`
--

INSERT INTO `transaksi` (`id`, `user_id`, `pembeli_id`, `total_harga`, `pembayaran`, `kembalian`, `marketplace`, `tanggal`, `daerah`, `no_resi`, `status_pengiriman`, `cancellation_reason`, `kurir`) VALUES
(9, 5, 9, 20000.00, 21000.00, 1000.00, 'tokopedia', '2025-01-01 00:14:00', 'Kabupaten Berau', NULL, 'pending', NULL, NULL),
(28, 4, 5, 20000.00, 21000.00, 1000.00, 'offline', '2025-01-07 01:21:20', NULL, NULL, 'pending', NULL, NULL),
(29, 4, 26, 20000.00, 21000.00, 1000.00, 'shopee', '2025-01-06 17:23:00', NULL, NULL, 'pending', NULL, NULL),
(30, 4, 27, 20000.00, 21000.00, 1000.00, 'offline', '2025-01-07 01:25:28', NULL, NULL, 'pending', NULL, NULL),
(36, 4, 29, 120000.00, 130000.00, 10000.00, 'tokopedia', '2024-12-13 17:57:00', 'Kepulauan Bangka Belitung', NULL, 'pending', NULL, NULL),
(39, 4, 8, 20000.00, 21000.00, 1000.00, 'tiktok', '2025-01-10 04:17:00', 'Kota Samarinda', NULL, 'pending', NULL, NULL),
(40, 4, 8, 20000.00, 21000.00, 1000.00, 'tokopedia', '2025-01-09 20:17:00', 'Kota Samarinda', NULL, 'pending', NULL, NULL),
(41, 4, 12, 109000.00, 110000.00, 1000.00, 'shopee', '2025-01-11 12:40:25', 'Kabupaten Kutai Kartanegara', NULL, 'pending', NULL, NULL),
(42, 4, 9, 25199.00, 26000.00, 801.00, 'offline', '2025-01-11 12:48:58', NULL, NULL, 'pending', NULL, NULL),
(43, 4, 30, 25100.00, 26000.00, 900.00, 'offline', '2025-01-11 12:50:56', NULL, NULL, 'pending', NULL, NULL),
(44, 4, 31, 25100.00, 26000.00, 900.00, 'shopee', '2025-01-11 12:51:39', 'Kepulauan Bangka Belitung', NULL, 'pending', NULL, NULL),
(45, 4, 32, 9900.00, 10000.00, 100.00, 'shopee', '2025-01-11 12:53:42', 'Kabupaten Kutai Kartanegara', NULL, 'pending', NULL, NULL),
(46, 4, 8, 25199.00, 26000.00, 801.00, 'offline', '2025-01-11 12:55:20', NULL, NULL, 'pending', NULL, NULL),
(47, 4, 33, 19200.00, 20000.00, 800.00, 'offline', '2025-01-11 12:57:49', NULL, NULL, 'pending', NULL, NULL),
(48, 4, 8, 25199.00, 26000.00, 801.00, 'offline', '2025-01-11 13:01:44', NULL, NULL, 'pending', NULL, NULL),
(49, 4, 7, 20000.00, 21000.00, 1000.00, 'offline', '2025-01-11 13:03:26', NULL, NULL, 'pending', NULL, NULL),
(50, 4, 7, 20000.00, 20000.00, 0.00, 'offline', '2025-01-11 13:05:13', NULL, NULL, 'pending', NULL, NULL),
(51, 4, 34, 20000.00, 21000.00, 1000.00, 'offline', '2025-01-11 13:13:24', NULL, NULL, 'pending', NULL, NULL),
(52, 4, 35, 49000.00, 50000.00, 1000.00, 'shopee', '2025-01-11 13:15:14', 'Kabupaten Kutai Kartanegara', NULL, 'pending', NULL, NULL),
(53, 4, 6, 81000.00, 82000.00, 1000.00, 'offline', '2025-01-11 13:15:43', NULL, NULL, 'pending', NULL, NULL),
(54, 4, 36, 20000.00, 21000.00, 1000.00, 'offline', '2025-01-11 13:19:35', NULL, NULL, 'pending', NULL, NULL),
(55, 4, 37, 20000.00, 21000.00, 1000.00, 'offline', '2025-01-11 13:21:22', NULL, NULL, 'pending', NULL, NULL),
(56, 4, 38, 69000.00, 70000.00, 1000.00, 'shopee', '2025-01-12 01:01:54', 'Kota Samarinda', NULL, 'pending', NULL, NULL),
(57, 4, 39, 69000.00, 70000.00, 1000.00, 'shopee', '2025-01-12 02:30:58', 'Kabupaten Kutai Kartanegara', NULL, 'pending', NULL, NULL),
(58, 4, 40, 20000.00, 20000.00, 0.00, 'tiktok', '2025-01-12 02:34:12', 'Kota Samarinda', 'JP3123882082', 'dikirim', NULL, 'jnt'),
(59, 4, 41, 9609.00, 10000.00, 391.00, 'shopee', '2025-01-12 02:36:28', 'Riau', NULL, 'pending', NULL, NULL),
(60, 4, 42, 25000.00, 25000.00, 0.00, 'shopee', '2025-01-12 02:38:11', 'Kota Samarinda', 'JP3123882082', 'dibatalkan', 'barang hilang', 'jnt'),
(61, 4, 43, 20000.00, 21000.00, 1000.00, 'shopee', '2025-01-12 03:22:10', 'Kabupaten Berau', 'JP7460718622', 'dikirim', NULL, 'jnt'),
(62, 4, 44, 49000.00, 50000.00, 1000.00, 'shopee', '2025-01-12 04:27:54', 'Kabupaten Kutai Kartanegara', 'JP7460718622', 'dikirim', NULL, 'jnt'),
(63, 4, 45, 101000.00, 102000.00, 1000.00, 'shopee', '2025-01-13 03:06:53', 'Kabupaten Kutai Kartanegara', 'JP7460718622', 'pending', NULL, 'jnt');

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Admin','Operator','Kasir') DEFAULT 'Operator',
  `status` enum('Aktif','Tidak Aktif') DEFAULT 'Aktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `avatar` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `nama`, `email`, `password`, `role`, `status`, `created_at`, `avatar`) VALUES
(1, 'Admin', 'admin@admin.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'Aktif', '2025-01-06 04:23:01', NULL),
(4, 'Daru Caraka', 'darucaraka43@gmail.com', '$2y$10$bIvxnAL3MC1pRSpEqwnmAebTDugHLMDQh2GaUkd6XUFSZKkz.mdni', 'Operator', 'Aktif', '2025-01-06 04:33:37', '678246b4dd3a1.png'),
(5, 'panji kusuma', 'panji12@gmail.com', '$2y$10$IM8rTCOrregUCf/6fScuTuOXg/RBVj17Ietv0UPi8MIPjpSt4JnbG', 'Operator', 'Aktif', '2025-01-06 05:03:30', NULL);

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `barang`
--
ALTER TABLE `barang`
  ADD PRIMARY KEY (`id`),
  ADD KEY `kategori_id` (`kategori_id`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indeks untuk tabel `detail_transaksi`
--
ALTER TABLE `detail_transaksi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transaksi_id` (`transaksi_id`),
  ADD KEY `barang_id` (`barang_id`);

--
-- Indeks untuk tabel `kategori`
--
ALTER TABLE `kategori`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `pembeli`
--
ALTER TABLE `pembeli`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `pengeluaran`
--
ALTER TABLE `pengeluaran`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `stok`
--
ALTER TABLE `stok`
  ADD PRIMARY KEY (`id`),
  ADD KEY `barang_id` (`barang_id`);

--
-- Indeks untuk tabel `supplier`
--
ALTER TABLE `supplier`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `pembeli_id` (`pembeli_id`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `barang`
--
ALTER TABLE `barang`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT untuk tabel `detail_transaksi`
--
ALTER TABLE `detail_transaksi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=77;

--
-- AUTO_INCREMENT untuk tabel `kategori`
--
ALTER TABLE `kategori`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=87;

--
-- AUTO_INCREMENT untuk tabel `pembeli`
--
ALTER TABLE `pembeli`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT untuk tabel `pengeluaran`
--
ALTER TABLE `pengeluaran`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `stok`
--
ALTER TABLE `stok`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT untuk tabel `supplier`
--
ALTER TABLE `supplier`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `barang`
--
ALTER TABLE `barang`
  ADD CONSTRAINT `barang_ibfk_1` FOREIGN KEY (`kategori_id`) REFERENCES `kategori` (`id`),
  ADD CONSTRAINT `barang_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `supplier` (`id`);

--
-- Ketidakleluasaan untuk tabel `detail_transaksi`
--
ALTER TABLE `detail_transaksi`
  ADD CONSTRAINT `detail_transaksi_ibfk_1` FOREIGN KEY (`transaksi_id`) REFERENCES `transaksi` (`id`),
  ADD CONSTRAINT `detail_transaksi_ibfk_2` FOREIGN KEY (`barang_id`) REFERENCES `barang` (`id`);

--
-- Ketidakleluasaan untuk tabel `stok`
--
ALTER TABLE `stok`
  ADD CONSTRAINT `stok_ibfk_1` FOREIGN KEY (`barang_id`) REFERENCES `barang` (`id`);

--
-- Ketidakleluasaan untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  ADD CONSTRAINT `transaksi_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `transaksi_ibfk_2` FOREIGN KEY (`pembeli_id`) REFERENCES `pembeli` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
