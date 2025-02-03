<?php
require_once '../backend/check_session.php';
require_once '../backend/database.php';

// Di bagian awal file, setelah require
$filter_bulan = $_GET['bulan'] ?? date('Y-m');

// Get riwayat slip gaji dengan bonus target
$query = "SELECT 
    sg.*,
    u.nama,
    COALESCE(
        (SELECT SUM(
            CASE 
                WHEN tp.jenis_target = 'produk' THEN (
                    SELECT SUM(tpr.insentif_per_unit * tpr.jumlah_target)
                    FROM target_produk tpr
                    WHERE tpr.target_id = tp.id
                )
                WHEN tp.jenis_target = 'omset' AND (
                    SELECT SUM(t.total_harga)
                    FROM transaksi t
                    WHERE t.user_id = tp.user_id
                    AND DATE(t.tanggal) BETWEEN tp.periode_mulai AND tp.periode_selesai
                ) >= tp.target_nominal 
                THEN (tp.target_nominal * tp.insentif_persen / 100)
                ELSE 0
            END
        )
        FROM target_penjualan tp 
        WHERE tp.user_id = sg.user_id 
        AND tp.status = 'Selesai'
        AND DATE_FORMAT(tp.periode_selesai, '%Y-%m') = DATE_FORMAT(sg.tanggal, '%Y-%m')),
        0
    ) as bonus_target
FROM slip_gaji sg
JOIN users u ON sg.user_id = u.id
WHERE DATE_FORMAT(sg.tanggal, '%Y-%m') = ?
ORDER BY sg.tanggal DESC";

$stmt = $conn->prepare($query);
$stmt->execute([$filter_bulan]);
$riwayat = $stmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle delete
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $slip_id = $_POST['id'];
        $stmt = $conn->prepare("DELETE FROM slip_gaji WHERE id = ?");
        if ($stmt->execute([$slip_id])) {
            $_SESSION['success'] = "Data slip gaji berhasil dihapus!";
        } else {
            $_SESSION['error'] = "Gagal menghapus data slip gaji!";
        }
        header("Location: slip_gaji.php" . (isset($_GET['bulan']) ? "?bulan=" . $_GET['bulan'] : ""));
        exit;
    }
    
    // Handle input gaji
    if (!isset($_POST['action'])) {
        if (isset($_POST['karyawan_id'], $_POST['tanggal'], $_POST['gaji_pokok'])) {
            $user_id = $_POST['karyawan_id'];
            $tanggal = $_POST['tanggal'];
            $gaji_pokok = $_POST['gaji_pokok'];
            $bonus = $_POST['bonus'] ?? 0;
            $potongan = $_POST['potongan'] ?? 0;
            $keterangan_potongan = $_POST['keterangan_potongan'] ?? '';
            
            // Get bonus target untuk periode ini
            $queryBonus = "SELECT 
                COALESCE(
                    SUM(
                        CASE 
                            WHEN tp.jenis_target = 'produk' THEN (
                                SELECT SUM(tpr.insentif_per_unit * tpr.jumlah_target)
                                FROM target_produk tpr
                                WHERE tpr.target_id = tp.id
                            )
                            WHEN tp.jenis_target = 'omset' AND (
                                SELECT SUM(t.total_harga)
                                FROM transaksi t
                                WHERE t.user_id = tp.user_id
                                AND DATE(t.tanggal) BETWEEN tp.periode_mulai AND tp.periode_selesai
                            ) >= tp.target_nominal 
                            THEN (tp.target_nominal * tp.insentif_persen / 100)
                            ELSE 0
                        END
                    ),
                    0
                ) as bonus_target
            FROM target_penjualan tp 
            WHERE tp.user_id = ? 
            AND tp.status = 'Selesai'
            AND DATE_FORMAT(tp.periode_selesai, '%Y-%m') = DATE_FORMAT(?, '%Y-%m')";
            
            $stmt = $conn->prepare($queryBonus);
            $stmt->execute([$user_id, $tanggal]);
            $bonus_target = $stmt->fetch()['bonus_target'];
            
            // Total bonus = bonus input + bonus target
            $total_bonus = $bonus + $bonus_target;
            $total_gaji = $gaji_pokok + $total_bonus - $potongan;
            
            $query = "INSERT INTO slip_gaji (user_id, tanggal, gaji_pokok, bonus, potongan, keterangan_potongan, total_gaji) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($query);
            if ($stmt->execute([
                $user_id, 
                $tanggal, 
                $gaji_pokok, 
                $total_bonus, 
                $potongan,
                $keterangan_potongan,
                $total_gaji
            ])) {
                $_SESSION['success'] = "Data gaji berhasil ditambahkan!";
            } else {
                $_SESSION['error'] = "Gagal menambahkan data gaji!";
            }
            header("Location: slip_gaji.php" . (isset($_GET['bulan']) ? "?bulan=" . $_GET['bulan'] : ""));
            exit;
        }
    }
}

// Get data karyawan untuk dropdown
$queryKaryawan = "SELECT id, nama, gaji FROM users WHERE role IN ('Operator', 'Kasir') AND status = 'Aktif'";
$stmt = $conn->prepare($queryKaryawan);
$stmt->execute();
$karyawan = $stmt->fetchAll();

// Query untuk mendapatkan karyawan yang belum gajian bulan ini
$queryBelumGajian = "
    SELECT u.* 
    FROM users u 
    LEFT JOIN slip_gaji sg ON u.id = sg.user_id 
        AND DATE_FORMAT(sg.tanggal, '%Y-%m') = ?
    WHERE u.role IN ('Operator', 'Kasir') 
        AND u.status = 'Aktif'
        AND sg.id IS NULL
    ORDER BY u.nama ASC";

$stmt = $conn->prepare($queryBelumGajian);
$stmt->execute([$filter_bulan]);
$karyawanBelumGajian = $stmt->fetchAll();

// Update bagian PHP untuk handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'delete') {
        $slip_id = $_POST['id'];
        $stmt = $conn->prepare("DELETE FROM slip_gaji WHERE id = ?");
        if ($stmt->execute([$slip_id])) {
            $_SESSION['success'] = "Data slip gaji berhasil dihapus!";
        } else {
            $_SESSION['error'] = "Gagal menghapus data slip gaji!";
        }
        header("Location: slip_gaji.php");
        exit;
    }
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Penggajian Karyawan - Jamu Air Mancur</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include '../components/sidebar.php'; ?>
    <?php include '../components/navbar.php'; ?>

    <!-- Main Content Container - Responsif -->
    <div class="ml-0 sm:ml-64 pt-24 sm:pt-16 min-h-screen bg-gray-50/50">
        <div class="p-6 sm:p-8">
            <!-- Header Section -->
            <div class="mb-6 sm:mb-8 bg-gradient-to-br from-indigo-600 via-blue-500 to-blue-400 rounded-3xl p-6 sm:p-10 text-white shadow-2xl relative overflow-hidden">
                <!-- Decorative elements -->
                <div class="absolute top-0 right-0 w-96 h-96 bg-white/10 rounded-full -translate-y-32 translate-x-32 blur-3xl"></div>
                <div class="absolute bottom-0 left-0 w-96 h-96 bg-blue-500/20 rounded-full translate-y-32 -translate-x-32 blur-3xl"></div>
                
                <div class="relative flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
                    <div>
                        <h1 class="text-4xl font-bold mb-2">Penggajian Karyawan</h1>
                        <p class="text-blue-100 text-lg">Kelola data gaji karyawan</p>
                    </div>
                    <button onclick="showModal()" 
                            class="w-full sm:w-auto px-5 py-3 bg-white/10 hover:bg-white/20 text-white rounded-xl flex items-center justify-center sm:justify-start gap-3 transition-all duration-300 backdrop-blur-sm">
                        <div class="p-2 bg-white/10 rounded-lg">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                        </div>
                        <span class="font-medium">Input Gaji</span>
                    </button>
                </div>
            </div>

            <!-- Success Alert -->
            <?php if (isset($_SESSION['success'])): ?>
                <div id="alert" class="bg-[#F0FDF4] border-l-4 border-[#16A34A] p-4 mb-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-[#16A34A]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-[#15803D]">
                                    <?= $_SESSION['success'] ?>
                                </p>
                            </div>
                        </div>
                        <button onclick="closeAlert()" class="ml-auto pl-3">
                            <svg class="h-5 w-5 text-[#16A34A]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <!-- Error Alert -->
            <?php if (isset($_SESSION['error'])): ?>
                <div id="alert" class="bg-[#FEF2F2] border-l-4 border-[#DC2626] p-4 mb-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-[#DC2626]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-[#991B1B]">
                                    <?= $_SESSION['error'] ?>
                                </p>
                            </div>
                        </div>
                        <button onclick="closeAlert()" class="ml-auto pl-3">
                            <svg class="h-5 w-5 text-[#DC2626]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

                <!-- Filter Section -->
            <div class="flex items-center gap-3 mb-6">
                    <div class="flex-1">
                        <input type="month" 
                            id="filter_tanggal" 
                            value="<?= isset($_GET['bulan']) ? $_GET['bulan'] : date('Y-m') ?>"
                            class="w-full px-4 py-2 border border-gray-200 rounded-xl bg-white">
                    </div>
                    <button onclick="filterData()" 
                            class="px-4 py-2 bg-gray-50 text-gray-700 rounded-xl border border-gray-200 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                        </svg>
                        Filter
                </button>
            </div>

            <!-- Alert Karyawan Belum Gajian -->
            <?php if (count($karyawanBelumGajian) > 0): ?>
                <div class="mb-6">
                    <div class="flex items-center gap-3 p-4 bg-red-50 rounded-xl">
                        <div class="flex-shrink-0">
                            <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                        </div>
                        <div class="flex-1 text-sm">
                            <span class="text-red-600"><?= count($karyawanBelumGajian) ?> Karyawan</span> 
                            <span class="text-gray-600">belum menerima gaji bulan <?= date('F Y', strtotime($filter_bulan)) ?></span>
                            <button onclick="showDetailKaryawan()" class="ml-1 text-blue-600">
                                detail
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Tabel Riwayat - Mobile View -->
            <div class="block sm:hidden">
                <?php foreach ($riwayat as $i => $r): ?>
                    <div class="bg-white rounded-xl p-4 mb-3 border border-gray-100">
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <div class="font-medium text-gray-900"><?= htmlspecialchars($r['nama']) ?></div>
                                <div class="text-sm text-gray-500"><?= date('d M Y', strtotime($r['tanggal'])) ?></div>
                            </div>
                            <div class="flex gap-2">
                                <button onclick="showDeleteAlert(<?= $r['id'] ?>)" 
                                        class="text-red-600 p-1 rounded-lg hover:bg-red-50">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                                <a href="cetak_slip_gaji.php?id=<?= $r['id'] ?>" 
                                   target="_blank"
                                   class="text-blue-600 p-1 rounded-lg hover:bg-blue-50">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                    </svg>
                                </a>
                            </div>
                        </div>
                        <div class="space-y-1 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Gaji Pokok</span>
                                <span>Rp <?= number_format($r['gaji_pokok'], 0, ',', '.') ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Bonus</span>
                                <span>Rp <?= number_format($r['bonus'] - $r['bonus_target'], 0, ',', '.') ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Bonus Target</span>
                                <span>Rp <?= number_format($r['bonus_target'], 0, ',', '.') ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Potongan</span>
                                <span class="text-red-600">- Rp <?= number_format($r['potongan'], 0, ',', '.') ?></span>
                            </div>
                            <?php if (!empty($r['keterangan_potongan'])): ?>
                                <div class="text-xs text-gray-500">
                                    Ket: <?= htmlspecialchars($r['keterangan_potongan']) ?>
                                </div>
                            <?php endif; ?>
                            <div class="pt-2 border-t">
                                <div class="flex justify-between font-medium">
                                    <span>Total Terima</span>
                                    <span class="text-blue-600">Rp <?= number_format($r['total_gaji'], 0, ',', '.') ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Tabel Riwayat - Desktop View -->
            <div class="hidden sm:block overflow-x-auto">
                <div class="relative overflow-x-auto shadow-md sm:rounded-lg">
                    <table class="w-full text-sm text-left text-gray-500">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3">NO</th>
                                <th scope="col" class="px-6 py-3">TANGGAL</th>
                                <th scope="col" class="px-6 py-3">NAMA</th>
                                <th scope="col" class="px-6 py-3">NOMINAL</th>
                                <th scope="col" class="px-6 py-3">BONUS</th>
                                <th scope="col" class="px-6 py-3">BONUS TARGET</th>
                                <th scope="col" class="px-6 py-3">POTONGAN</th>
                                <th scope="col" class="px-6 py-3">TERIMA</th>
                                <th scope="col" class="px-6 py-3">AKSI</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($riwayat as $i => $r): ?>
                                <tr class="bg-white border-b hover:bg-gray-50">
                                    <td class="px-6 py-4"><?= $i + 1 ?></td>
                                    <td class="px-6 py-4"><?= date('d M Y', strtotime($r['tanggal'])) ?></td>
                                    <td class="px-6 py-4"><?= htmlspecialchars($r['nama']) ?></td>
                                    <td class="px-6 py-4">Rp <?= number_format($r['gaji_pokok'], 0, ',', '.') ?></td>
                                    <td class="px-6 py-4">
                                        Rp <?= number_format($r['bonus'] - $r['bonus_target'], 0, ',', '.') ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        Rp <?= number_format($r['bonus_target'], 0, ',', '.') ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        Rp <?= number_format($r['potongan'], 0, ',', '.') ?> (-)
                                        <?php if (!empty($r['keterangan_potongan'])): ?>
                                            <span class="block text-xs text-gray-500 mt-1">
                                                Ket: <?= htmlspecialchars($r['keterangan_potongan']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4">Rp <?= number_format($r['total_gaji'], 0, ',', '.') ?></td>
                                    <td class="px-6 py-4 flex gap-2">
                                        <button onclick="showDeleteAlert(<?= $r['id'] ?>)" 
                                                class="text-red-600 hover:text-red-700 p-1 rounded-lg hover:bg-red-50">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                        <a href="cetak_slip_gaji.php?id=<?= $r['id'] ?>" 
                                           target="_blank"
                                           class="text-blue-600 hover:text-blue-700 p-1 rounded-lg hover:bg-blue-50">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                            </svg>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Modal Input Gaji -->
            <div id="modal" class="fixed inset-0 bg-gray-500 bg-opacity-75 hidden z-[60]">
                <div class="flex items-start justify-center min-h-screen pt-20 sm:pt-16 px-4 pb-4">
                    <!-- Update modal container for better mobile view -->
                    <div class="bg-white rounded-xl shadow-lg w-full max-w-lg max-h-[80vh] overflow-y-auto relative">
                        <!-- Sticky header -->
                        <div class="sticky top-0 bg-white border-b border-gray-200 rounded-t-xl z-10">
                            <div class="p-4">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-lg font-semibold text-gray-800">Input Gaji Karyawan</h3>
                                    <button onclick="hideModal()" class="text-gray-400 hover:text-gray-500">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <form method="POST" class="p-4">
                            <div class="space-y-4">
                                <!-- Karyawan & Tanggal in one row for larger screens -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Karyawan</label>
                                        <select name="karyawan_id" required 
                                            class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                            onchange="setGaji(this)">
                                            <option value="">Pilih Karyawan</option>
                                            <?php foreach ($karyawan as $k): ?>
                                                <option value="<?= $k['id'] ?>" data-gaji="<?= $k['gaji'] ?>">
                                                    <?= htmlspecialchars($k['nama']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal</label>
                                        <input type="date" name="tanggal" required 
                                            class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                            onchange="getBonusTarget()">
                                    </div>
                                </div>

                                <!-- Nominal Section -->
                                <div class="bg-gray-50 rounded-lg p-4 space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Gaji Pokok</label>
                                        <input type="text" name="gaji_pokok" required readonly
                                            class="w-full p-2 border rounded-lg bg-gray-100 cursor-not-allowed" 
                                            title="Gaji pokok diambil dari data karyawan">
                                        <span class="text-xs text-gray-500">*Sesuai data karyawan</span>
                                    </div>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Bonus</label>
                                            <input type="text" name="bonus" value="0" required 
                                                class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Bonus Target</label>
                                            <input type="text" name="bonus_target" value="0" readonly
                                                class="w-full p-2 border rounded-lg bg-gray-100 cursor-not-allowed">
                                            <span class="text-xs text-gray-500">*Otomatis dari target tercapai</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Potongan Section -->
                                <div class="bg-gray-50 rounded-lg p-4 space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Potongan</label>
                                        <input type="text" name="potongan" value="0" required 
                                            class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Keterangan Potongan</label>
                                        <textarea name="keterangan_potongan" 
                                            class="w-full p-2 border rounded-lg resize-none h-16 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                            placeholder="Masukkan keterangan potongan (opsional)"></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Total & Buttons -->
                            <div class="sticky bottom-0 bg-white border-t border-gray-200 p-4 rounded-b-xl">
                                <div class="space-y-4">
                                    <div class="bg-blue-50 p-4 rounded-lg">
                                        <div class="flex justify-between items-center">
                                            <span class="text-sm font-medium text-gray-700">Total Terima Bersih:</span>
                                            <span class="text-lg font-bold text-blue-600" id="total_terima">Rp 0</span>
                                        </div>
                                    </div>

                                    <div class="flex gap-3 justify-end">
                                        <button type="button" onclick="hideModal()" 
                                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                                            Batal
                                        </button>
                                        <button type="submit" 
                                            class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">
                                            Simpan
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Modal Detail Karyawan -->
            <div id="modalDetail" class="fixed inset-0 bg-black/50 z-[70] hidden">
                <div class="flex items-center justify-center min-h-screen p-4">
                    <div class="bg-white w-full max-w-md rounded-2xl shadow-lg opacity-0 scale-95 transform transition-all duration-300">
                        <div class="p-6 text-center space-y-6">
                            <div class="w-20 h-20 rounded-full bg-blue-50 flex items-center justify-center mx-auto">
                                <svg class="w-10 h-10 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                            </div>

                            <div class="space-y-2">
                                <h3 class="text-xl font-medium text-gray-900">Karyawan Belum Gajian</h3>
                                <p class="text-gray-500">Bulan <?= date('F Y', strtotime($filter_bulan)) ?></p>
                            </div>

                            <div class="bg-gray-50 rounded-xl p-4">
                                <div class="space-y-3">
                                    <?php foreach ($karyawanBelumGajian as $k): ?>
                                        <div class="flex items-center justify-between">
                                            <span class="text-sm text-gray-600"><?= htmlspecialchars($k['nama']) ?></span>
                                            <button onclick="inputGajiLangsung(<?= $k['id'] ?>, '<?= htmlspecialchars($k['nama']) ?>', <?= $k['gaji'] ?>)" 
                                                    class="px-3 py-1 text-xs font-medium text-blue-600 hover:bg-blue-50 rounded-lg transition-colors duration-200">
                                                Input Gaji
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="flex justify-center">
                                <button onclick="hideDetailKaryawan()" 
                                        class="px-5 py-2.5 rounded-xl text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors duration-200">
                                    Tutup
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tambahkan modal konfirmasi hapus -->
            <div id="deleteAlert" class="fixed inset-0 bg-black/50 z-[70] hidden">
                <div class="flex items-center justify-center min-h-screen p-4">
                    <div class="bg-white w-full max-w-md rounded-2xl shadow-lg">
                        <div class="p-6 text-center space-y-6">
                            <div class="w-20 h-20 rounded-full bg-red-50 flex items-center justify-center mx-auto">
                                <svg class="w-10 h-10 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>

                            <div class="space-y-2">
                                <h3 class="text-xl font-medium text-gray-900">Hapus Slip Gaji</h3>
                                <p class="text-gray-500">Apakah Anda yakin ingin menghapus slip gaji ini?</p>
                            </div>

                            <form id="deleteForm" method="POST" class="flex gap-3 justify-center">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" id="deleteId">

                                <button type="button" onclick="closeDeleteAlert()"
                                    class="px-5 py-2.5 rounded-xl text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors duration-200">
                                    Batal
                                </button>
                                <button type="submit"
                                    class="px-5 py-2.5 rounded-xl text-sm font-medium text-white bg-red-600 hover:bg-red-700 transition-colors duration-200">
                                    Hapus
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showModal() {
            document.getElementById('modal').classList.remove('hidden');
        }
        
        function hideModal() {
            document.getElementById('modal').classList.add('hidden');
        }
        
        function formatNumber(angka) {
            return new Intl.NumberFormat('id-ID').format(angka);
        }
        
        function unformatNumber(str) {
            return str.replace(/\D/g, '');
        }
        
        function setGaji(select) {
            const gaji = select.options[select.selectedIndex].dataset.gaji;
            document.querySelector('input[name="gaji_pokok"]').value = formatNumber(gaji || 0);
            getBonusTarget();
            hitungTotal();
        }
        
        function getBonusTarget() {
            const karyawanId = document.querySelector('select[name="karyawan_id"]').value;
            const tanggal = document.querySelector('input[name="tanggal"]').value;
            
            if (!karyawanId || !tanggal) return;
            
            fetch(`get_bonus_target.php?user_id=${karyawanId}&tanggal=${tanggal}`)
                .then(response => response.json())
                .then(data => {
                    document.querySelector('input[name="bonus_target"]').value = formatNumber(data.bonus_target || 0);
                    hitungTotal();
                })
                .catch(error => console.error('Error:', error));
        }

        function formatRupiah(angka) {
            return 'Rp ' + formatNumber(angka);
        }

        function hitungTotal() {
            const gaji_pokok = parseInt(unformatNumber(document.querySelector('input[name="gaji_pokok"]').value)) || 0;
            const bonus = parseInt(unformatNumber(document.querySelector('input[name="bonus"]').value)) || 0;
            const bonus_target = parseInt(unformatNumber(document.querySelector('input[name="bonus_target"]').value)) || 0;
            const potongan = parseInt(unformatNumber(document.querySelector('input[name="potongan"]').value)) || 0;

            const total = gaji_pokok + bonus + bonus_target - potongan;
            document.getElementById('total_terima').textContent = formatRupiah(total);
        }

        // Format input saat diketik
        ['bonus', 'potongan'].forEach(name => {
            document.querySelector(`input[name="${name}"]`).addEventListener('input', function(e) {
                let value = unformatNumber(this.value);
                this.value = formatNumber(value);
                hitungTotal();
            });
        });

        // Sebelum submit form, hapus format angka
        document.querySelector('form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            ['gaji_pokok', 'bonus', 'bonus_target', 'potongan'].forEach(name => {
                const input = document.querySelector(`input[name="${name}"]`);
                input.value = unformatNumber(input.value);
            });

            this.submit();
        });

        function filterData() {
            const bulan = document.getElementById('filter_tanggal').value;
            window.location.href = `slip_gaji.php?bulan=${bulan}`;
        }

        function inputGajiLangsung(id, nama, gaji) {
            // Set nilai di form modal
            document.querySelector('select[name="karyawan_id"]').value = id;
            document.querySelector('input[name="gaji_pokok"]').value = formatNumber(gaji);
            // Show modal
            hideDetailKaryawan();
            showModal();
        }

        function inputGajiMassal() {
            if (confirm('Input gaji untuk semua karyawan yang belum gajian?')) {
                // Implementasi input gaji massal
                // Bisa redirect ke halaman baru atau tampilkan modal khusus
                alert('Fitur input gaji massal akan segera hadir!');
            }
        }

        function showDetailKaryawan() {
            const modal = document.getElementById('modalDetail');
            modal.classList.remove('hidden');
            // Tambahkan delay kecil untuk animasi fade in
            setTimeout(() => {
                modal.querySelector('.bg-white').classList.add('scale-100', 'opacity-100');
            }, 50);
            document.body.style.overflow = 'hidden';
        }

        function hideDetailKaryawan() {
            const modal = document.getElementById('modalDetail');
            modal.querySelector('.bg-white').classList.remove('scale-100', 'opacity-100');
            setTimeout(() => {
                modal.classList.add('hidden');
                document.body.style.overflow = '';
            }, 200);
        }

        // Tambahkan style untuk animasi
        const style = document.createElement('style');
        style.textContent = `
            #modalDetail .bg-white {
                transform: scale(0.95);
                opacity: 0;
                transition: all 0.2s ease-out;
            }
            #modalDetail .bg-white.scale-100 {
                transform: scale(1);
                opacity: 1;
            }
        `;
        document.head.appendChild(style);

        function closeAlert() {
            document.getElementById('alert').style.display = 'none';
        }

        // Tambahkan fungsi untuk menampilkan alert hapus
        function showDeleteAlert(id) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteAlert').classList.remove('hidden');
        }

        // Fungsi untuk menutup alert hapus
        function closeDeleteAlert() {
            document.getElementById('deleteAlert').classList.add('hidden');
        }

        // Event listener untuk menutup alert saat klik di luar
        document.getElementById('deleteAlert').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteAlert();
            }
        });

        // Event listener untuk tombol escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !document.getElementById('deleteAlert').classList.contains('hidden')) {
                closeDeleteAlert();
            }
        });
    </script>
</body>
</html>
