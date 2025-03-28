<?php
session_start();
require_once '../backend/check_session.php';
require_once '../backend/database.php';

// Fungsi untuk mengambil semua data marketplace
function getMarketplaceData()
{
    global $conn;

    $query = "SELECT 
            marketplace,
            COUNT(*) as total_orders,
            COUNT(DISTINCT pembeli_id) as total_customers,
            COALESCE(SUM(total_harga), 0) as total_revenue,
            COALESCE(ROUND(AVG(total_harga), 2), 0) as avg_order_value
        FROM transaksi 
        WHERE marketplace IS NOT NULL
        GROUP BY marketplace 
        ORDER BY total_revenue DESC";

    $stmt = $conn->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll();
}

// Tambahkan fungsi untuk mengambil data region berdasarkan periode
function getRegionData($days)
{
    global $conn;
    $query = "SELECT 
        t.daerah,
        COUNT(*) as total_transaksi,
        SUM(t.total_harga) as total_pendapatan,
        AVG(t.total_harga) as rata_rata
    FROM transaksi t
    WHERE t.daerah IS NOT NULL 
        AND t.tanggal >= DATE_SUB(CURRENT_DATE(), INTERVAL ? DAY)
        AND t.tanggal <= CURRENT_DATE()
    GROUP BY t.daerah 
    ORDER BY total_pendapatan DESC 
    LIMIT 3";

    $stmt = $conn->prepare($query);
    $stmt->execute([$days]);
    return $stmt->fetchAll();
}

// Ambil semua data marketplace
$marketplaceData = getMarketplaceData();

// Hitung total untuk card rangkuman
$totalRevenue = array_sum(array_column($marketplaceData, 'total_revenue'));
$totalOrders = array_sum(array_column($marketplaceData, 'total_orders'));
$avgOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

// Endpoint AJAX untuk update data
if (isset($_POST['action']) && $_POST['action'] === 'updateMarketplace') {
    header('Content-Type: application/json');
    $days = isset($_POST['days']) ? (int)$_POST['days'] : 7;
    $data = getMarketplaceData($days);
    echo json_encode([
        'marketplaceData' => $data,
        'summary' => [
            'totalRevenue' => array_sum(array_column($data, 'total_revenue')),
            'totalOrders' => array_sum(array_column($data, 'total_orders')),
            'avgOrderValue' => array_sum(array_column($data, 'total_revenue')) / array_sum(array_column($data, 'total_orders'))
        ]
    ]);
    exit;
}

// Tambahkan endpoint AJAX untuk update data region
if (isset($_POST['action']) && $_POST['action'] === 'updateRegion') {
    header('Content-Type: application/json');

    try {
        $query = "SELECT 
            t.daerah,
            COUNT(*) as total_transaksi,
            SUM(t.total_harga) as total_pendapatan,
            AVG(t.total_harga) as rata_rata
        FROM transaksi t
        WHERE t.daerah IS NOT NULL 
            AND DATE(t.tanggal) >= :start_date
            AND DATE(t.tanggal) <= :end_date
        GROUP BY t.daerah 
        ORDER BY total_pendapatan DESC 
        LIMIT 3";

        $stmt = $conn->prepare($query);
        
        if (isset($_POST['days'])) {
            $startDate = date('Y-m-d', strtotime("-" . ($_POST['days'] - 1) . " days"));
            $endDate = date('Y-m-d');
        } else {
            $startDate = $_POST['startDate'];
            $endDate = $_POST['endDate'];
        }

        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $data
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

// Query untuk mengambil data transaksi 7 hari terakhir
$query = "SELECT 
    dates.tanggal,
    COALESCE(SUM(transaksi.total_harga), 0) as penjualan,
    COALESCE(SUM(transaksi.total_harga - (
        SELECT SUM(dt.jumlah * b.harga_modal)
        FROM detail_transaksi dt
        JOIN barang b ON dt.barang_id = b.id
        WHERE dt.transaksi_id = transaksi.id
    )), 0) as profit
    FROM (
        SELECT CURDATE() - INTERVAL (a.a + (10 * b.a) + (100 * c.a)) DAY AS tanggal
        FROM (SELECT 0 AS a UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6) AS a
        CROSS JOIN (SELECT 0 AS a) AS b
        CROSS JOIN (SELECT 0 AS a) AS c
        WHERE CURDATE() - INTERVAL (a.a + (10 * b.a) + (100 * c.a)) DAY >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    ) as dates
    LEFT JOIN transaksi ON DATE(transaksi.tanggal) = dates.tanggal
    GROUP BY dates.tanggal
    ORDER BY dates.tanggal";
$stmt = $conn->query($query);
$salesData = $stmt->fetchAll();

// Query untuk produk terlaris
$queryTopProducts = "SELECT 
    b.nama_barang as product_name,
    SUM(dt.jumlah) as total_sold
    FROM detail_transaksi dt
    JOIN barang b ON dt.barang_id = b.id
    GROUP BY b.id
    ORDER BY total_sold DESC
    LIMIT 5";
$stmt = $conn->query($queryTopProducts);
$topProducts = $stmt->fetchAll();

// Perbaikan query untuk menghitung total penjualan dan profit hari ini dengan lebih akurat
$queryToday = "SELECT 
    COALESCE(SUM(t.total_harga), 0) as total_penjualan,
    COALESCE(SUM(t.total_harga - COALESCE((
        SELECT SUM(dt.jumlah * b.harga_modal)
        FROM detail_transaksi dt
        JOIN barang b ON dt.barang_id = b.id
        WHERE dt.transaksi_id = t.id
    ), 0)), 0) as total_profit,
    COUNT(t.id) as total_transaksi
FROM transaksi t
WHERE DATE_FORMAT(CONVERT_TZ(t.tanggal, @@session.time_zone, '+08:00'), '%Y-%m-%d') = CURDATE()";

// Query untuk data kemarin dengan perhitungan yang sama
$queryYesterday = "SELECT 
    COALESCE(SUM(t.total_harga), 0) as total_penjualan,
    COALESCE(SUM(t.total_harga - COALESCE((
        SELECT SUM(dt.jumlah * b.harga_modal)
        FROM detail_transaksi dt
        JOIN barang b ON dt.barang_id = b.id
        WHERE dt.transaksi_id = t.id
    ), 0)), 0) as total_profit,
    COUNT(t.id) as total_transaksi
FROM transaksi t
WHERE DATE_FORMAT(CONVERT_TZ(t.tanggal, @@session.time_zone, '+08:00'), '%Y-%m-%d') = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";

// Fungsi untuk menghitung persentase perubahan dengan lebih akurat
function calculatePercentageChange($current, $previous)
{
    if ($previous == 0) {
        if ($current == 0) {
            return 0; // Tidak ada perubahan
        }
        // Jika sebelumnya 0, hitung kenaikan sebenarnya
        return ($current > 0) ? (($current - $previous) / 1) * 100 : -100;
    }
    // Hitung persentase perubahan normal
    return (($current - $previous) / abs($previous)) * 100;
}

try {
    // Eksekusi query hari ini
    $stmt = $conn->query($queryToday);
    $todayStats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Eksekusi query kemarin
    $stmt = $conn->query($queryYesterday);
    $yesterdayStats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Inisialisasi variabel dengan nilai default jika null
    $totalPenjualan = floatval($todayStats['total_penjualan'] ?? 0);
    $totalProfit = floatval($todayStats['total_profit'] ?? 0);
    $totalTransaksi = intval($todayStats['total_transaksi'] ?? 0);

    $penjualanKemarin = floatval($yesterdayStats['total_penjualan'] ?? 0);
    $profitKemarin = floatval($yesterdayStats['total_profit'] ?? 0);
    $transaksiKemarin = intval($yesterdayStats['total_transaksi'] ?? 0);

    // Hitung persentase perubahan menggunakan fungsi baru
    $persenPenjualan = calculatePercentageChange($totalPenjualan, $penjualanKemarin);
    $persenProfit = calculatePercentageChange($totalProfit, $profitKemarin);
    $persenTransaksi = calculatePercentageChange($totalTransaksi, $transaksiKemarin);

    // Hitung margin profit
    $marginProfit = $totalPenjualan > 0 ? ($totalProfit / $totalPenjualan) * 100 : 0;
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());

    // Set nilai default jika terjadi error
    $totalPenjualan = 0;
    $totalProfit = 0;
    $totalTransaksi = 0;
    $persenPenjualan = 0;
    $persenProfit = 0;
    $persenTransaksi = 0;
    $marginProfit = 0;
}

// Pastikan data marketplace tidak kosong
if (empty($marketplaceData)) {
    $marketplaceData = [
        [
            'marketplace' => 'Default',
            'total_orders' => 0,
            'total_customers' => 0,
            'total_revenue' => 0,
            'avg_order_value' => 0
        ]
    ];
}

// Pastikan data region tidak kosong
if (empty($regionData7Days)) {
    $regionData7Days = [
        [
            'daerah' => 'Default',
            'total_transaksi' => 0,
            'total_pendapatan' => 0,
            'rata_rata' => 0
        ]
    ];
}

if (empty($regionData30Days)) {
    $regionData30Days = $regionData7Days;
}

// Pastikan data sales tidak kosong
if (empty($salesData7Days)) {
    $salesData7Days = [
        [
            'tanggal' => date('Y-m-d'),
            'penjualan' => 0,
            'profit' => 0
        ]
    ];
}

if (empty($salesData30Days)) {
    $salesData30Days = $salesData7Days;
}

// Tambahkan query untuk mendapatkan data kemarin
$queryYesterday = "SELECT 
    COALESCE(SUM(total_harga), 0) as total_penjualan,
    COALESCE(SUM(total_harga - (
        SELECT COALESCE(SUM(dt.jumlah * b.harga_modal), 0)
        FROM detail_transaksi dt
        JOIN barang b ON dt.barang_id = b.id
        WHERE dt.transaksi_id = transaksi.id
    )), 0) as total_profit,
    COUNT(*) as total_transaksi
    FROM transaksi 
    WHERE DATE(tanggal) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";

// Tambahkan query untuk total produk
$queryTotalProduk = "SELECT COUNT(*) as total FROM barang";
$stmt = $conn->query($queryTotalProduk);
$totalProduk = $stmt->fetch()['total'];

// Tambahkan fungsi untuk mengambil data berdasarkan range
function getSalesData($days)
{
    global $conn;
    $query = "SELECT 
        dates.tanggal,
        COALESCE(SUM(transaksi.total_harga), 0) as penjualan,
        COALESCE(SUM(transaksi.total_harga - (
            SELECT SUM(dt.jumlah * b.harga_modal)
            FROM detail_transaksi dt
            JOIN barang b ON dt.barang_id = b.id
            WHERE dt.transaksi_id = transaksi.id
        )), 0) as profit
        FROM (
            SELECT CURDATE() - INTERVAL (a.a + (10 * b.a) + (100 * c.a)) DAY AS tanggal
            FROM (SELECT 0 AS a UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) AS a
            CROSS JOIN (SELECT 0 AS a UNION ALL SELECT 1 UNION ALL SELECT 2) AS b
            CROSS JOIN (SELECT 0 AS a UNION ALL SELECT 1 UNION ALL SELECT 2) AS c
            WHERE CURDATE() - INTERVAL (a.a + (10 * b.a) + (100 * c.a)) DAY >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        ) as dates
        LEFT JOIN transaksi ON DATE(transaksi.tanggal) = dates.tanggal
        GROUP BY dates.tanggal
        ORDER BY dates.tanggal";

    $stmt = $conn->prepare($query);
    $stmt->execute([$days - 1]); // -1 karena interval dimulai dari 0
    return $stmt->fetchAll();
}

// Ambil data default (7 hari)
$salesData7Days = getSalesData(7);
$salesData30Days = getSalesData(30);

// Tambahkan query untuk marketplace analytics setelah query $salesData30Days
$queryMarketplace = "SELECT 
    marketplace,
    COUNT(*) as total_orders,
    COUNT(DISTINCT pembeli_id) as total_customers,
    SUM(total_harga) as total_revenue,
    ROUND(AVG(total_harga), 2) as avg_order_value
FROM transaksi 
WHERE marketplace IS NOT NULL
GROUP BY marketplace 
ORDER BY total_revenue DESC";

$stmtMarketplace = $conn->prepare($queryMarketplace);
$stmtMarketplace->execute();
$marketplaceData = $stmtMarketplace->fetchAll();

// Ambil data untuk kedua periode
$regionData7Days = getRegionData(7);
$regionData30Days = getRegionData(30);

// Tambahkan endpoint AJAX untuk update data sales trend
if (isset($_POST['action']) && $_POST['action'] === 'updateSalesTrend') {
    header('Content-Type: application/json');

    try {
        if (isset($_POST['startDate']) && isset($_POST['endDate'])) {
            // Query untuk custom period
            $query = "SELECT 
                dates.tanggal,
                COALESCE(SUM(transaksi.total_harga), 0) as penjualan,
                COALESCE(SUM(transaksi.total_harga - (
                    SELECT SUM(dt.jumlah * b.harga_modal)
                    FROM detail_transaksi dt
                    JOIN barang b ON dt.barang_id = b.id
                    WHERE dt.transaksi_id = transaksi.id
                )), 0) as profit
                FROM (
                    SELECT CURDATE() - INTERVAL (a.a + (10 * b.a) + (100 * c.a)) DAY AS tanggal
                    FROM (SELECT 0 AS a UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) AS a
                    CROSS JOIN (SELECT 0 AS a UNION ALL SELECT 1 UNION ALL SELECT 2) AS b
                    CROSS JOIN (SELECT 0 AS a UNION ALL SELECT 1 UNION ALL SELECT 2) AS c
                    WHERE CURDATE() - INTERVAL (a.a + (10 * b.a) + (100 * c.a)) DAY >= ?
                    AND CURDATE() - INTERVAL (a.a + (10 * b.a) + (100 * c.a)) DAY <= ?
                ) as dates
                LEFT JOIN transaksi ON DATE(transaksi.tanggal) = dates.tanggal
                GROUP BY dates.tanggal
                ORDER BY dates.tanggal";

            $stmt = $conn->prepare($query);
            $stmt->execute([$_POST['startDate'], $_POST['endDate']]);
        } else {
            // Query untuk periode default (7 atau 30 hari)
            $days = isset($_POST['days']) ? (int)$_POST['days'] : 7;
            $query = "SELECT 
                dates.tanggal,
                COALESCE(SUM(transaksi.total_harga), 0) as penjualan,
                COALESCE(SUM(transaksi.total_harga - (
                    SELECT SUM(dt.jumlah * b.harga_modal)
                    FROM detail_transaksi dt
                    JOIN barang b ON dt.barang_id = b.id
                    WHERE dt.transaksi_id = transaksi.id
                )), 0) as profit
                FROM (
                    SELECT CURDATE() - INTERVAL (a.a + (10 * b.a) + (100 * c.a)) DAY AS tanggal
                    FROM (SELECT 0 AS a UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) AS a
                    CROSS JOIN (SELECT 0 AS a UNION ALL SELECT 1 UNION ALL SELECT 2) AS b
                    CROSS JOIN (SELECT 0 AS a UNION ALL SELECT 1 UNION ALL SELECT 2) AS c
                    WHERE CURDATE() - INTERVAL (a.a + (10 * b.a) + (100 * c.a)) DAY >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                ) as dates
                LEFT JOIN transaksi ON DATE(transaksi.tanggal) = dates.tanggal
                GROUP BY dates.tanggal
                ORDER BY dates.tanggal";

            $stmt = $conn->prepare($query);
            $stmt->execute([$days]);
        }

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $data
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

// Tambahkan endpoint AJAX untuk update data produk terlaris
if (isset($_POST['action']) && $_POST['action'] === 'updateTopProducts') {
    header('Content-Type: application/json');

    try {
        if (isset($_POST['startDate']) && isset($_POST['endDate'])) {
            // Query untuk custom period
            $queryTopProducts = "SELECT 
                b.nama_barang as product_name,
                SUM(dt.jumlah) as total_sold
                FROM detail_transaksi dt
                JOIN barang b ON dt.barang_id = b.id
                JOIN transaksi t ON dt.transaksi_id = t.id
                WHERE DATE(t.tanggal) >= ? 
                AND DATE(t.tanggal) <= ?
                GROUP BY b.id
                ORDER BY total_sold DESC
                LIMIT 5";

            $stmt = $conn->prepare($queryTopProducts);
            $stmt->execute([$_POST['startDate'], $_POST['endDate']]);
        } else {
            // Query untuk periode default (7 atau 30 hari)
            $days = isset($_POST['days']) ? (int)$_POST['days'] : 7;
            $queryTopProducts = "SELECT 
                b.nama_barang as product_name,
                SUM(dt.jumlah) as total_sold
                FROM detail_transaksi dt
                JOIN barang b ON dt.barang_id = b.id
                JOIN transaksi t ON dt.transaksi_id = t.id
                WHERE t.tanggal >= DATE_SUB(CURRENT_DATE(), INTERVAL ? DAY)
                GROUP BY b.id
                ORDER BY total_sold DESC
                LIMIT 5";

            $stmt = $conn->prepare($queryTopProducts);
            $stmt->execute([$days]);
        }

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $data
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Dashboard - Jamu Air Mancur</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="../src/css/dashboard.css">
    <!-- Tambahkan Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .dashboard-card {
            transition: all 0.3s ease;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }f
    </style>
</head>

<body class="bg-gray-50">
    <?php include '../components/sidebar.php'; ?>
    <?php include '../components/navbar.php'; ?>

    <div class="ml-0 md:ml-64 pt-16 min-h-screen bg-gray-100">
        <div class="p-4 md:p-8">
            <!-- Header Section -->
            <div class="mb-6 md:mb-8 bg-gradient-to-br from-indigo-600 via-blue-500 to-blue-400 rounded-2xl md:rounded-3xl p-6 md:p-10 text-white shadow-2xl relative overflow-hidden">
                <!-- Add decorative elements -->
                <div class="absolute top-0 right-0 w-64 h-64 bg-white/10 rounded-full -translate-y-32 translate-x-32 blur-3xl"></div>
                <div class="absolute bottom-0 left-0 w-64 h-64 bg-blue-500/20 rounded-full translate-y-32 -translate-x-32 blur-3xl"></div>

                <div class="relative">
                    <h1 class="text-3xl md:text-4xl font-bold mb-2 md:mb-3">Dashboard</h1>
                    <p class="text-blue-100 text-base md:text-lg">Overview penjualan dan kinerja toko hari ini</p>
                </div>
            </div>

            <!-- Statistik Cards dengan desain yang lebih modern -->
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 md:gap-6 mb-6 md:mb-8">
                <!-- Card Penjualan -->
                <div class="bg-white rounded-2xl p-6 shadow-sm hover:shadow-md transition-all duration-300">
                    <div class="flex flex-col h-full">
                        <!-- Header -->
                        <div class="flex justify-between items-start mb-6">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">PENJUALAN HARI INI</p>
                                <div class="inline-flex items-center gap-2">
                                    <span class="text-xs px-2 py-1 bg-blue-50 text-blue-600 rounded-full font-medium">Real time</span>
                                </div>
                            </div>
                            <div class="p-3 bg-blue-500 text-white rounded-xl">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </div>
                        </div>

                        <!-- Amount -->
                        <div class="mb-6">
                            <h3 class="text-2xl font-bold text-gray-800">Rp <?= number_format($totalPenjualan, 0, ',', '.') ?></h3>
                            <div class="h-1 w-12 bg-blue-500 rounded-full mt-2"></div>
                        </div>

                        <!-- Footer dengan persentase yang diperbarui -->
                        <div class="flex items-center mt-auto">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-sm font-medium <?= $persenPenjualan >= 0 ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' ?>">
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="<?= $persenPenjualan >= 0 ? 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6' : 'M13 17h8m0 0V9m0 8l-8-8-4 4-6-6' ?>">
                                    </path>
                                </svg>
                                <?= number_format(abs($persenPenjualan), 1) ?>%
                            </span>
                            <span class="ml-2 text-sm text-gray-500">vs kemarin</span>
                        </div>
                    </div>
                </div>

                <!-- Card Transaksi -->
                <div class="bg-white rounded-2xl p-6 shadow-sm hover:shadow-md transition-all duration-300">
                    <div class="flex flex-col h-full">
                        <!-- Header -->
                        <div class="flex justify-between items-start mb-6">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">TRANSAKSI HARI INI</p>
                                <div class="inline-flex items-center gap-2">
                                    <span class="text-xs px-2 py-1 bg-emerald-50 text-emerald-600 rounded-full font-medium">Real time</span>
                                </div>
                            </div>
                            <div class="p-3 bg-emerald-500 text-white rounded-xl">
                                <!-- Modern Transaction Icon -->
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </div>
                        </div>

                        <!-- Amount -->
                        <div class="mb-6">
                            <h3 class="text-2xl font-bold text-gray-800"><?= $todayStats['total_transaksi'] ?? 0 ?></h3>
                            <div class="h-1 w-12 bg-emerald-500 rounded-full mt-2"></div>
                        </div>

                        <!-- Footer dengan persentase yang diperbarui -->
                        <div class="flex items-center mt-auto">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-sm font-medium <?= $persenTransaksi >= 0 ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' ?>">
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="<?= $persenTransaksi >= 0 ? 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6' : 'M13 17h8m0 0V9m0 8l-8-8-4 4-6-6' ?>">
                                    </path>
                                </svg>
                                <?= number_format(abs($persenTransaksi), 1) ?>%
                            </span>
                            <span class="ml-2 text-sm text-gray-500">vs kemarin</span>
                        </div>
                    </div>
                </div>

                <!-- Card Profit -->
                <div class="bg-white rounded-2xl p-6 shadow-sm hover:shadow-md transition-all duration-300">
                    <div class="flex flex-col h-full">
                        <!-- Header -->
                        <div class="flex justify-between items-start mb-6">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">PROFIT HARI INI</p>
                                <div class="inline-flex items-center gap-2">
                                    <span class="text-xs px-2 py-1 bg-violet-50 text-violet-600 rounded-full font-medium">Real time</span>
                                </div>
                            </div>
                            <div class="p-3 bg-violet-500 text-white rounded-xl">
                                <!-- Modern Profit/Chart Icon -->
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </div>
                        </div>

                        <!-- Amount -->
                        <div class="mb-6">
                            <h3 class="text-2xl font-bold text-gray-800">Rp <?= number_format($totalProfit, 0, ',', '.') ?></h3>
                            <div class="h-1 w-12 bg-violet-500 rounded-full mt-2"></div>
                        </div>

                        <!-- Footer dengan persentase yang diperbarui -->
                        <div class="flex items-center mt-auto">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-sm font-medium <?= $persenProfit >= 0 ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' ?>">
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="<?= $persenProfit >= 0 ? 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6' : 'M13 17h8m0 0V9m0 8l-8-8-4 4-6-6' ?>">
                                    </path>
                                </svg>
                                <?= number_format(abs($persenProfit), 1) ?>%
                            </span>
                            <span class="ml-2 text-sm text-gray-500">vs kemarin</span>
                        </div>
                    </div>
                </div>

                <!-- Card Total Produk -->
                <div class="bg-white rounded-2xl p-6 shadow-sm hover:shadow-md transition-all duration-300">
                    <div class="flex flex-col h-full">
                        <!-- Header -->
                        <div class="flex justify-between items-start mb-6">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">TOTAL PRODUK</p>
                                <div class="inline-flex items-center gap-2">
                                    <span class="text-xs px-2 py-1 bg-orange-50 text-orange-600 rounded-full font-medium">Katalog</span>
                                </div>

                            </div>
                            <div class="p-3 bg-orange-500 text-white rounded-xl">
                                <!-- Modern Product/Box Icon -->
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </div>
                        </div>

                        <!-- Amount -->
                        <div class="mb-6">
                            <h3 class="text-2xl font-bold text-gray-800"><?= $totalProduk ?></h3>
                            <div class="h-1 w-12 bg-orange-500 rounded-full mt-2"></div>
                        </div>

                        <!-- Footer -->
                        <div class="flex items-center mt-auto">
                            <span class="text-sm text-gray-500">Total Katalog</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Section dengan desain yang lebih modern -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 md:gap-6 mb-6">
                <!-- Line Chart Container -->
                <div class="bg-white rounded-2xl p-4 md:p-6 shadow-sm">
                    <div class="flex flex-col gap-4 mb-6 md:mb-8">
                        <div class="flex flex-col md:flex-row justify-between items-start md:items-center space-y-4 md:space-y-0">
                            <div>
                                <h2 class="text-lg md:text-xl font-semibold text-gray-800">Trend Penjualan & Profit</h2>
                                <p class="text-sm text-gray-500 mt-1">Analisis performa bisnis</p>
                            </div>
                            <div class="flex flex-col md:flex-row items-start md:items-center gap-4 md:gap-6 w-full md:w-auto">
                                <!-- Select Period -->
                                <div class="flex flex-col md:flex-row items-start md:items-center gap-2 md:gap-4 w-full md:w-auto">
                                    <select id="periodSelect" class="w-full md:w-auto text-sm border rounded-xl px-4 py-2 bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="7">7 Hari Terakhir</option>
                                        <option value="30">30 Hari Terakhir</option>
                                        <option value="custom">Pilih Periode</option>
                                    </select>
                                    
                                    <div id="salesDatePickerContainer" class="hidden flex flex-col md:flex-row items-start md:items-center gap-2 w-full md:w-auto">
                                        <input type="date" id="salesStartDate" class="w-full md:w-auto text-sm border rounded-xl px-4 py-2 bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <span class="text-gray-500">s/d</span>
                                        <input type="date" id="salesEndDate" class="w-full md:w-auto text-sm border rounded-xl px-4 py-2 bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </div>
                                </div>

                                <!-- Legend Indicators - scroll horizontally on mobile -->
                                <div class="flex items-center gap-4 overflow-x-auto pb-2 md:pb-0 w-full md:w-auto">
                                    <div class="flex items-center gap-2 cursor-pointer hover:opacity-75 transition-opacity" onclick="toggleDataset(0)">
                                        <div class="w-3 h-3 rounded-full bg-blue-500"></div>
                                        <span class="text-sm text-gray-600" id="legend-penjualan">Penjualan</span>
                                    </div>
                                    <div class="flex items-center gap-2 cursor-pointer hover:opacity-75 transition-opacity" onclick="toggleDataset(1)">
                                        <div class="w-3 h-3 rounded-full bg-emerald-500"></div>
                                        <span class="text-sm text-gray-600" id="legend-profit">Profit</span>
                                    </div>
                                    <div class="flex items-center gap-2 cursor-pointer hover:opacity-75 transition-opacity" onclick="toggleDataset(2)">
                                        <div class="w-3 h-3 rounded-full bg-orange-500"></div>
                                        <span class="text-sm text-gray-600" id="legend-margin">Margin</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Chart Container with Loading State -->
                    <div class="relative" style="z-index: 1;">
                        <div id="chartLoading" class="absolute inset-0 bg-white/80 backdrop-blur-sm flex items-center justify-center z-10 hidden">
                            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
                        </div>
                        <canvas id="salesChart" height="300"></canvas>
                    </div>
                </div>

                <!-- Products Container -->
                <div class="bg-white rounded-2xl p-4 md:p-6 shadow-sm">
                    <!-- Header Section with Select Option -->
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center space-y-4 md:space-y-0 mb-6">
                        <div class="flex justify-between items-center w-full">
                            <div>
                                <h2 class="text-lg md:text-xl font-semibold text-gray-800">5 Produk Terlaris</h2>
                                <p class="text-sm text-gray-500 mt-1">Berdasarkan jumlah penjualan</p>
                            </div>
                            <!-- Three Dots Menu Mobile -->
                            <a href="informasi.php?tab=produk-terlaris" class="p-2 hover:bg-gray-50/80 rounded-full transition-all duration-300 flex-shrink-0 block md:hidden">
                                <svg class="w-5 h-5 text-gray-600" viewBox="0 0 24 24">
                                    <circle cx="5" cy="12" r="2" fill="currentColor" />
                                    <circle cx="12" cy="12" r="2" fill="currentColor" />
                                    <circle cx="19" cy="12" r="2" fill="currentColor" />
                                </svg>
                            </a>
                        </div>
                        <div class="flex flex-row items-center gap-4 w-full md:w-auto">
                            <!-- Select Period -->
                            <div class="flex flex-col md:flex-row items-start md:items-center gap-2 w-full">
                                <select id="productPeriodSelect" class="w-full md:w-auto text-sm border rounded-xl px-4 py-2 bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="7">7 Hari Terakhir</option>
                                    <option value="30">30 Hari Terakhir</option>
                                    <option value="custom">Pilih Periode</option>
                                </select>

                                <!-- Date picker container -->
                                <div id="productDatePickerContainer" class="hidden flex flex-col md:flex-row items-start md:items-center gap-2 w-full md:w-auto">
                                    <input type="date" id="productStartDate" class="w-full md:w-auto text-sm border rounded-xl px-4 py-2 bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <span class="text-gray-500">s/d</span>
                                    <input type="date" id="productEndDate" class="w-full md:w-auto text-sm border rounded-xl px-4 py-2 bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                            </div>

                            <!-- Three Dots Menu Desktop -->
                            <a href="informasi.php?tab=produk-terlaris" class="p-2 hover:bg-gray-50/80 rounded-full transition-all duration-300 flex-shrink-0 hidden md:block">
                                <svg class="w-5 h-5 text-gray-600" viewBox="0 0 24 24">
                                    <circle cx="5" cy="12" r="2" fill="currentColor" />
                                    <circle cx="12" cy="12" r="2" fill="currentColor" />
                                    <circle cx="19" cy="12" r="2" fill="currentColor" />
                                </svg>
                            </a>
                        </div>
                    </div>

                    <!-- Chart Container with Fixed Height -->
                    <div class="grid grid-cols-1 gap-6">
                        <div class="relative flex flex-col md:flex-row" style="min-height: 300px;">
                            <!-- Chart Container -->
                            <div class="flex-1 relative mb-4 md:mb-0" style="min-height: 250px;">
                                <!-- Total Products Display -->
                                <div class="absolute left-1/2 top-1/2 transform -translate-x-1/2 -translate-y-1/2 text-center z-10 pointer-events-none">
                                    <span class="text-2xl md:text-3xl font-bold text-gray-800" id="totalProductsSold">0</span>
                                    <p class="text-xs md:text-sm text-gray-500">Total Terjual</p>
                                </div>
                                <!-- Donut Chart -->
                                <canvas id="productsChart"></canvas>
                            </div>
                            <!-- Legend Container -->
                            <div class="w-full md:w-64 md:flex-shrink-0">
                                <div id="customLegend" class="grid grid-cols-2 md:grid-cols-1 gap-2 md:space-y-2 md:pt-4"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Grid Container untuk Marketplace dan Daerah -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
                <!-- Marketplace Section -->
                <div class="w-full">
                    <div class="bg-white/80 backdrop-blur-xl rounded-2xl p-4 md:p-6 shadow-sm border border-gray-100 h-full">
                        <div class="flex flex-col md:flex-row justify-between items-start md:items-center space-y-4 md:space-y-0 mb-6">
                            <div class="flex justify-between items-center w-full">
                                <div>
                                    <h2 class="text-lg md:text-xl font-semibold text-gray-800">Penjualan Marketplace</h2>
                                    <p class="text-sm text-gray-500 mt-1">Total penjualan dari semua marketplace</p>
                                </div>
                                <!-- Three Dots Menu Mobile -->
                                <a href="informasi.php?tab=marketplace" class="p-2 hover:bg-gray-50/80 rounded-full transition-all duration-300 flex-shrink-0 block md:hidden">
                                    <svg class="w-5 h-5 text-gray-600" viewBox="0 0 24 24">
                                        <circle cx="5" cy="12" r="2" fill="currentColor" />
                                        <circle cx="12" cy="12" r="2" fill="currentColor" />
                                        <circle cx="19" cy="12" r="2" fill="currentColor" />
                                    </svg>
                                </a>
                            </div>
                            <!-- Three Dots Menu Desktop -->
                            <a href="informasi.php?tab=marketplace" class="p-2 hover:bg-gray-50/80 rounded-full transition-all duration-300 flex-shrink-0 hidden md:block">
                                <svg class="w-5 h-5 text-gray-600" viewBox="0 0 24 24">
                                    <circle cx="5" cy="12" r="2" fill="currentColor" />
                                    <circle cx="12" cy="12" r="2" fill="currentColor" />
                                    <circle cx="19" cy="12" r="2" fill="currentColor" />
                                </svg>
                            </a>
                        </div>

                        <!-- Marketplace Stats Summary - stack vertically on mobile -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                            <div class="bg-gradient-to-br from-blue-50 to-blue-100/50 p-4 rounded-xl">
                                <p class="text-sm text-gray-600">Total Pendapatan</p>
                                <h3 class="text-xl font-bold text-gray-800 mt-1">
                                    Rp <?= number_format(array_sum(array_column($marketplaceData, 'total_revenue')), 0, ',', '.') ?>
                                </h3>
                                <p class="text-xs text-gray-500 mt-1">Total <?= array_sum(array_column($marketplaceData, 'total_orders')) ?> Transaksi</p>
                            </div>
                            <div class="bg-gradient-to-br from-green-50 to-green-100/50 p-4 rounded-xl">
                                <p class="text-sm text-gray-600">Rata-rata Transaksi</p>
                                <h3 class="text-xl font-bold text-gray-800 mt-1">
                                    Rp <?= number_format(array_sum(array_column($marketplaceData, 'total_revenue')) / array_sum(array_column($marketplaceData, 'total_orders')), 0, ',', '.') ?>
                                </h3>
                                <p class="text-xs text-gray-500 mt-1">Nilai rata-rata per order</p>
                            </div>
                        </div>

                        <!-- Distribusi Penjualan per Marketplace -->
                        <div class="space-y-3">
                            <h3 class="text-sm font-medium text-gray-700 mb-3">Distribusi Penjualan</h3>
                            <?php foreach ($marketplaceData as $data):
                                $colors = [
                                    'shopee' => ['from-orange-500/20 to-orange-500/5 text-orange-600', 'text-orange-600'],
                                    'tokopedia' => ['from-green-500/20 to-green-500/5 text-green-600', 'text-green-600'],
                                    'tiktok' => ['from-gray-800/20 to-gray-800/5 text-gray-700', 'text-gray-700'],
                                    'offline' => ['from-blue-500/20 to-blue-500/5 text-blue-600', 'text-blue-600']
                                ];
                                $color = $colors[strtolower($data['marketplace'])] ?? ['from-blue-500/20 to-blue-500/5 text-blue-600', 'text-blue-600'];

                                // Hitung persentase dari total
                                $percentageOfTotal = ($data['total_revenue'] / array_sum(array_column($marketplaceData, 'total_revenue'))) * 100;
                            ?>
                                <div class="group relative overflow-hidden cursor-pointer"
                                    onclick="showMarketplaceDetail('<?= $data['marketplace'] ?>', <?= json_encode($data) ?>)">
                                    <div class="flex items-center justify-between p-4 rounded-xl bg-gradient-to-br <?= $color[0] ?> hover:scale-[1.02] transition-all duration-300">
                                        <div class="flex items-center gap-3">
                                            <div class="p-3 rounded-xl bg-white shadow-sm">
                                                <?php if (strtolower($data['marketplace']) === 'shopee'): ?>
                                                    <img src="../img/shopee.png" alt="Shopee" class="w-7 h-7 object-contain">
                                                <?php elseif (strtolower($data['marketplace']) === 'tokopedia'): ?>
                                                    <img src="../img/tokopedia.png" alt="Tokopedia" class="w-7 h-7 object-contain">
                                                <?php elseif (strtolower($data['marketplace']) === 'tiktok'): ?>
                                                    <img src="../img/tiktok.png" alt="Tiktok" class="w-7 h-7 object-contain">
                                                <?php else: ?>
                                                    <svg class="w-7 h-7 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .415.336.75.75.75z" />
                                                    </svg>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <h3 class="font-medium text-gray-800"><?= ucfirst($data['marketplace']) ?></h3>
                                                <div class="flex items-center gap-2 mt-0.5">
                                                    <span class="text-xs text-gray-500"><?= $data['total_orders'] ?> Transaksi</span>
                                                    <span class="text-xs font-medium <?= $color[1] ?>"><?= number_format($percentageOfTotal, 1) ?>% dari total</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-sm font-semibold text-gray-800">
                                                Rp <?= number_format($data['total_revenue'], 0, ',', '.') ?>
                                            </div>
                                            <div class="text-xs text-gray-500 mt-0.5">
                                                Rata-rata: Rp <?= number_format($data['avg_order_value'], 0, ',', '.') ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Progress bar -->
                                    <div class="h-1 w-full bg-gray-100 absolute bottom-0 left-0">
                                        <div class="h-full <?= str_replace(['from-', '/20'], ['bg-', ''], explode(' ', $color[0])[0]) ?>"
                                            style="width: <?= $percentageOfTotal ?>%">
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Top 3 Performing Regions Section -->
                <div class="w-full">
                    <div class="bg-white/80 backdrop-blur-xl rounded-2xl p-4 md:p-6 shadow-sm border border-gray-100 h-full">
                        <div class="flex flex-col md:flex-row justify-between items-start md:items-center space-y-4 md:space-y-0 mb-6">
                            <div class="flex justify-between items-center w-full">
                                <div>
                                    <h2 class="text-lg md:text-xl font-semibold text-gray-800">Top 3 Daerah Terlaris</h2>
                                    <p class="text-sm text-gray-500 mt-1">Daerah dengan performa penjualan tertinggi</p>
                                </div>
                                <!-- Three Dots Menu Mobile -->
                                <a href="informasi.php?tab=daerah" class="p-2 hover:bg-gray-50/80 rounded-full transition-all duration-300 flex-shrink-0 block md:hidden">
                                    <svg class="w-5 h-5 text-gray-600" viewBox="0 0 24 24">
                                        <circle cx="5" cy="12" r="2" fill="currentColor" />
                                        <circle cx="12" cy="12" r="2" fill="currentColor" />
                                        <circle cx="19" cy="12" r="2" fill="currentColor" />
                                    </svg>
                                </a>
                            </div>
                            <div class="flex flex-col md:flex-row items-start md:items-center gap-4 w-full md:w-auto">
                                <select id="regionPeriodSelect" class="w-full md:w-auto text-sm border rounded-xl px-4 py-2 bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="7">7 Hari Terakhir</option>
                                    <option value="30">30 Hari Terakhir</option>
                                    <option value="custom">Pilih Periode</option>
                                </select>

                                <!-- Date picker container -->
                                <div id="datePickerContainer" class="hidden flex flex-col md:flex-row items-start md:items-center gap-2 w-full md:w-auto">
                                    <input type="date" id="startDate" class="w-full md:w-auto text-sm border rounded-xl px-4 py-2 bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <span class="text-gray-500">s/d</span>
                                    <input type="date" id="endDate" class="w-full md:w-auto text-sm border rounded-xl px-4 py-2 bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </div>

                                <!-- Three Dots Menu Desktop -->
                                <a href="informasi.php?tab=daerah" class="p-2 hover:bg-gray-50/80 rounded-full transition-all duration-300 flex-shrink-0 hidden md:block">
                                    <svg class="w-5 h-5 text-gray-600" viewBox="0 0 24 24">
                                        <circle cx="5" cy="12" r="2" fill="currentColor" />
                                        <circle cx="12" cy="12" r="2" fill="currentColor" />
                                        <circle cx="19" cy="12" r="2" fill="currentColor" />
                                    </svg>
                                </a>
                            </div>
                        </div>

                        <!-- Chart Container -->
                        <div class="flex flex-col">
                            <!-- Bar Chart -->
                            <div class="w-full" style="height: 300px;">
                                <canvas id="regionBarChart"></canvas>
                            </div>

                            <!-- Region Info Cards -->
                            <div class="mt-4 space-y-3">
                                <?php
                                try {
                                    $queryDaerah = $conn->prepare("SELECT 
                                        daerah,
                                        COUNT(*) as total_transaksi,
                                        SUM(total_harga) as total_pendapatan,
                                        AVG(total_harga) as rata_rata
                                    FROM transaksi 
                                    WHERE daerah IS NOT NULL 
                                        AND tanggal >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
                                        AND tanggal <= NOW()
                                    GROUP BY daerah 
                                    ORDER BY total_pendapatan DESC 
                                    LIMIT 3");

                                    $queryDaerah->execute();
                                    $daerahData = $queryDaerah->fetchAll(PDO::FETCH_ASSOC);
                                } catch (PDOException $e) {
                                    $daerahData = [];
                                }

                                foreach ($daerahData as $index => $data): ?>
                                    <div class="bg-gray-50/80 rounded-xl p-3">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-2">
                                                <span class="text-xl">
                                                    <?= $index === 0 ? '🥇' : ($index === 1 ? '🥈' : '🥉') ?>
                                                </span>
                                                <h3 class="text-sm font-medium text-blue-600">
                                                    <?= htmlspecialchars($data['daerah']) ?>
                                                </h3>
                                            </div>
                                            <div class="text-right">
                                                <p class="text-base font-semibold text-gray-800">
                                                    Rp <?= number_format($data['total_pendapatan'], 0, ',', '.') ?>
                                                </p>
                                                <div class="flex items-center gap-2 text-xs text-gray-500">
                                                    <span><?= $data['total_transaksi'] ?> Transaksi</span>
                                                    <span>·</span>
                                                    <span>Rata-rata: Rp <?= number_format($data['rata_rata'], 0, ',', '.') ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>

                                <script>
                                    document.addEventListener('DOMContentLoaded', function() {
                                        const regionCtx = document.getElementById('regionBarChart').getContext('2d');
                                        let regionChart; // Declare chart variable in wider scope

                                        function initRegionChart(data) {
                                            if (regionChart) {
                                                regionChart.destroy();
                                            }

                                            regionChart = new Chart(regionCtx, {
                                                type: 'bar',
                                                data: {
                                                    labels: data.map(item => item.daerah),
                                                    datasets: [{
                                                        data: data.map(item => item.total_pendapatan),
                                                        backgroundColor: ['#00c83c', '#0187ff', '#8B5CF6'],
                                                        borderRadius: 30,
                                                        maxBarThickness: 200
                                                    }]
                                                },
                                                options: {
                                                    responsive: true,
                                                    maintainAspectRatio: false,
                                                    plugins: {
                                                        legend: {
                                                            display: false
                                                        },
                                                        tooltip: {
                                                            callbacks: {
                                                                label: function(context) {
                                                                    return 'Rp ' + context.raw.toLocaleString('id-ID');
                                                                }
                                                            }
                                                        }
                                                    },
                                                    scales: {
                                                        y: {
                                                            beginAtZero: true,
                                                            grid: {
                                                                display: true,
                                                                drawBorder: false,
                                                                color: 'rgba(0, 0, 0, 0.05)'
                                                            },
                                                            ticks: {
                                                                callback: function(value) {
                                                                    return 'Rp ' + value.toLocaleString('id-ID');
                                                                }
                                                            }
                                                        },
                                                        x: {
                                                            grid: {
                                                                display: false
                                                            }
                                                        }
                                                    }
                                                }
                                            });

                                            // Store chart instance globally
                                            window.regionChart = regionChart;
                                        }

                                        // Initialize with default data (7 days)
                                        initRegionChart(<?= json_encode($daerahData) ?>);

                                        // Handle period change
                                        document.getElementById('regionPeriodSelect').addEventListener('change', function(e) {
                                            const selectedValue = e.target.value;
                                            const datePickerContainer = document.getElementById('datePickerContainer');

                                            if (selectedValue === 'custom') {
                                                datePickerContainer.classList.remove('hidden');
                                                return;
                                            } else {
                                                datePickerContainer.classList.add('hidden');
                                                updateRegionData({
                                                    days: selectedValue
                                                });
                                            }
                                        });

                                        // Tambahkan event listener untuk date inputs
                                        ['startDate', 'endDate'].forEach(id => {
                                            document.getElementById(id).addEventListener('change', function() {
                                                const startDate = document.getElementById('startDate').value;
                                                const endDate = document.getElementById('endDate').value;

                                                if (startDate && endDate) {
                                                    updateRegionData({
                                                        startDate,
                                                        endDate
                                                    });
                                                }
                                            });
                                        });

                                        // Fungsi untuk update data region
                                        function updateRegionData(params) {
                                            // Show loading state
                                            const cards = document.querySelectorAll('.bg-gray-50\\/80.rounded-xl');
                                            const chart = document.getElementById('regionBarChart');

                                            cards.forEach(card => card.style.opacity = '0.5');
                                            if (chart) chart.style.opacity = '0.5';

                                            // Prepare form data
                                            const formData = new FormData();
                                            formData.append('action', 'updateRegion');

                                            // Add parameters based on type
                                            if (params.days) {
                                                formData.append('days', params.days);
                                            } else {
                                                formData.append('startDate', params.startDate);
                                                formData.append('endDate', params.endDate);
                                            }

                                            // Fetch updated data
                                            fetch('dashboard.php', {
                                                    method: 'POST',
                                                    body: formData
                                                })
                                                .then(response => response.json())
                                                .then(result => {
                                                    if (result.success) {
                                                        // Update cards dan chart seperti sebelumnya
                                                        const container = document.querySelector('.mt-4.space-y-3');
                                                        container.innerHTML = result.data.map((region, index) => `
                                                    <div class="bg-gray-50/80 rounded-xl p-3">
                                                        <div class="flex items-center justify-between">
                                                            <div class="flex items-center gap-2">
                                                                <span class="text-xl">
                                                                    ${index === 0 ? '🥇' : (index === 1 ? '🥈' : '🥉')}
                                                                </span>
                                                                <h3 class="text-sm font-medium text-blue-600">
                                                                    ${region.daerah}
                                                                </h3>
                                                            </div>
                                                            <div class="text-right">
                                                                <p class="text-base font-semibold text-gray-800">
                                                                    Rp ${Number(region.total_pendapatan).toLocaleString('id-ID')}
                                                                </p>
                                                                <div class="flex items-center gap-2 text-xs text-gray-500">
                                                                    <span>${region.total_transaksi} Transaksi</span>
                                                                    <span>·</span>
                                                                    <span>Rata-rata: Rp ${Number(region.rata_rata).toLocaleString('id-ID')}</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                `).join('');

                                                        // Update chart
                                                        initRegionChart(result.data);

                                                        // Remove loading state
                                                        cards.forEach(card => card.style.opacity = '1');
                                                        if (chart) chart.style.opacity = '1';
                                                    }
                                                })
                                                .catch(error => {
                                                    console.error('Error:', error);
                                                    cards.forEach(card => card.style.opacity = '1');
                                                    if (chart) chart.style.opacity = '1';
                                                });
                                        }
                                    });
                                </script>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Data untuk kedua periode
        const salesData7Days = <?= json_encode($salesData7Days) ?>;
        const salesData30Days = <?= json_encode($salesData30Days) ?>;
        let currentChart = null;

        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('id-ID', {
                day: 'numeric',
                month: 'short'
            });
        }

        function formatCurrency(value) {
            return new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(value);
        }

        function showLoading() {
            document.getElementById('chartLoading').classList.remove('hidden');
        }

        function hideLoading() {
            document.getElementById('chartLoading').classList.add('hidden');
        }

        function createChart(data) {
            showLoading();

            setTimeout(() => {
                if (currentChart) {
                    currentChart.destroy();
                }

                const labels = data.map(item => formatDate(item.tanggal));
                const salesValues = data.map(item => parseFloat(item.penjualan));
                const profitValues = data.map(item => parseFloat(item.profit));
                const marginProfitValues = data.map(item =>
                    item.penjualan > 0 ? ((item.profit / item.penjualan) * 100).toFixed(1) : 0
                );

                const ctx = document.getElementById('salesChart').getContext('2d');

                // Add gradient backgrounds
                const salesGradient = ctx.createLinearGradient(0, 0, 0, 400);
                salesGradient.addColorStop(0, 'rgba(99, 102, 241, 0.1)');
                salesGradient.addColorStop(1, 'rgba(99, 102, 241, 0.02)');

                const profitGradient = ctx.createLinearGradient(0, 0, 0, 400);
                profitGradient.addColorStop(0, 'rgba(16, 185, 129, 0.1)');
                profitGradient.addColorStop(1, 'rgba(16, 185, 129, 0.02)');

                currentChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Penjualan',
                            data: salesValues,
                            borderColor: 'rgb(99, 102, 241)',
                            backgroundColor: salesGradient,
                            tension: 0.4,
                            fill: true,
                            borderWidth: 2,
                            pointRadius: 4,
                            pointBackgroundColor: 'white',
                            pointBorderColor: 'rgb(99, 102, 241)',
                            pointBorderWidth: 2,
                            order: 2,
                            hidden: false
                        }, {
                            label: 'Profit',
                            data: profitValues,
                            borderColor: 'rgb(16, 185, 129)',
                            backgroundColor: profitGradient,
                            tension: 0.4,
                            fill: true,
                            borderWidth: 2,
                            pointRadius: 4,
                            pointBackgroundColor: 'white',
                            pointBorderColor: 'rgb(16, 185, 129)',
                            pointBorderWidth: 2,
                            order: 1,
                            hidden: false
                        }, {
                            label: 'Margin Profit (%)',
                            data: marginProfitValues,
                            borderColor: 'rgb(249, 115, 22)',
                            backgroundColor: 'transparent',
                            tension: 0.4,
                            borderWidth: 2,
                            pointRadius: 3,
                            yAxisID: 'percentage',
                            order: 0,
                            hidden: false
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            intersect: false,
                            mode: 'index'
                        },
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                backgroundColor: 'white',
                                titleColor: '#1F2937',
                                bodyColor: '#1F2937',
                                bodyFont: {
                                    family: "'Inter', sans-serif",
                                    size: 12
                                },
                                titleFont: {
                                    family: "'Inter', sans-serif",
                                    size: 13,
                                    weight: '600'
                                },
                                padding: 12,
                                borderColor: 'rgba(0,0,0,0.1)',
                                borderWidth: 1,
                                displayColors: true,
                                boxWidth: 8,
                                boxHeight: 8,
                                usePointStyle: true,
                                callbacks: {
                                    label: function(context) {
                                        let label = context.dataset.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        if (context.dataset.yAxisID === 'percentage') {
                                            label += context.parsed.y + '%';
                                        } else {
                                            label += formatCurrency(context.parsed.y);
                                        }
                                        return label;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                type: 'linear',
                                display: true,
                                position: 'left',
                                beginAtZero: true,
                                grid: {
                                    drawBorder: false,
                                    color: 'rgba(0, 0, 0, 0.05)',
                                    drawTicks: false
                                },
                                ticks: {
                                    font: {
                                        family: "'Inter', sans-serif",
                                        size: 11
                                    },
                                    padding: 8,
                                    callback: function(value) {
                                        return formatCurrency(value);
                                    }
                                },
                                border: {
                                    dash: [4, 4]
                                }
                            },
                            percentage: {
                                type: 'linear',
                                display: true,
                                position: 'right',
                                beginAtZero: true,
                                grid: {
                                    drawOnChartArea: false,
                                    drawTicks: false
                                },
                                ticks: {
                                    font: {
                                        family: "'Inter', sans-serif",
                                        size: 11
                                    },
                                    padding: 8,
                                    callback: function(value) {
                                        return value + '%';
                                    }
                                },
                                border: {
                                    dash: [4, 4]
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    font: {
                                        family: "'Inter', sans-serif",
                                        size: 11
                                    },
                                    padding: 8,
                                    maxRotation: 0
                                },
                                border: {
                                    dash: [4, 4]
                                }
                            }
                        },
                        animations: {
                            tension: {
                                duration: 1000,
                                easing: 'linear'
                            },
                            y: {
                                duration: 1000,
                                easing: 'linear'
                            }
                        }
                    }
                });

                hideLoading();
            }, 300);
        }

        // Initialize chart with 7 days data
        createChart(salesData7Days);

        // Handle period change with loading state
        document.getElementById('periodSelect').addEventListener('change', function(e) {
            const selectedValue = e.target.value;
            const datePickerContainer = document.getElementById('salesDatePickerContainer');

            if (selectedValue === 'custom') {
                datePickerContainer.classList.remove('hidden');
                return;
            } else {
                datePickerContainer.classList.add('hidden');
                updateSalesData({
                    days: selectedValue
                });
            }
        });

        // Tambahkan event listener untuk date inputs
        ['salesStartDate', 'salesEndDate'].forEach(id => {
            document.getElementById(id).addEventListener('change', function() {
                const startDate = document.getElementById('salesStartDate').value;
                const endDate = document.getElementById('salesEndDate').value;

                if (startDate && endDate) {
                    updateSalesData({
                        startDate,
                        endDate
                    });
                }
            });
        });

        // Fungsi untuk update data sales trend
        function updateSalesData(params) {
            showLoading();

            const formData = new FormData();
            formData.append('action', 'updateSalesTrend');

            if (params.days) {
                formData.append('days', params.days);
            } else {
                formData.append('startDate', params.startDate);
                formData.append('endDate', params.endDate);
            }

            fetch('dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    createChart(result.data);
                } else {
                    console.error('Error:', result.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
            })
            .finally(() => {
                hideLoading();
            });
        }

        // Deklarasi variabel global untuk chart
        let productsChart = null;

        // Function to update products chart
        function updateProductsChart(data) {
            let hiddenLabels = [];
            const totalSold = data.reduce((acc, item) => acc + parseInt(item.total_sold), 0);
            let currentTotal = totalSold;
            
            document.getElementById('totalProductsSold').textContent = currentTotal;

            if (productsChart) {
                productsChart.destroy();
            }

            const ctx = document.getElementById('productsChart').getContext('2d');
            
            productsChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: data.map(item => item.product_name),
                    datasets: [{
                        data: data.map(item => item.total_sold),
                        backgroundColor: [
                            'rgb(99, 102, 241)',  // Indigo
                            'rgb(16, 185, 129)',  // Emerald
                            'rgb(6, 182, 212)',   // Cyan
                            'rgb(249, 115, 22)',  // Orange
                            'rgb(139, 92, 246)'   // Purple
                        ],
                        borderColor: 'white',
                        borderWidth: 2,
                        hoverOffset: 4,
                        cutout: '75%',
                        radius: '85%'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: {
                        padding: {
                            top: 20,
                            bottom: 20,
                            left: 20,
                            right: 20
                        }
                    },
                    plugins: {
                        legend: {
                            display: false // Hide default legend
                        },
                        tooltip: {
                            enabled: true,
                            backgroundColor: 'white',
                            titleColor: '#1F2937',
                            bodyColor: '#1F2937',
                            bodyFont: {
                                family: "'Inter', sans-serif",
                                size: 12
                            },
                            padding: 12,
                            borderColor: 'rgba(0,0,0,0.1)',
                            borderWidth: 1,
                            displayColors: true,
                            callbacks: {
                                label: function(context) {
                                    const value = context.raw;
                                    const percentage = ((value / totalSold) * 100).toFixed(1);
                                    return `${context.label}: ${value} unit (${percentage}%)`;
                                }
                            }
                        }
                    },
                    animation: {
                        animateScale: true,
                        animateRotate: true,
                        duration: 800,
                        onComplete: function() {
                            // Create custom legend after chart is rendered
                            const legendContainer = document.getElementById('customLegend');
                            legendContainer.innerHTML = '';
                            
                            data.forEach((item, index) => {
                                const percentage = ((item.total_sold / totalSold) * 100).toFixed(1);
                                const legendItem = document.createElement('div');
                                legendItem.className = 'flex items-start gap-3 p-2.5 rounded-lg hover:bg-gray-50/80 cursor-pointer transition-all duration-200';
                                legendItem.innerHTML = `
                                    <div class="legend-indicator-container relative">
                                        <div class="indicator-dot w-3.5 h-3.5 rounded-full mt-1 flex items-center justify-center" 
                                             style="background-color: ${this.config.data.datasets[0].backgroundColor[index]}">
                                        </div>
                                    </div>
                                    <div class="flex-1">
                                        <div class="legend-text-container">
                                            <span class="text-sm font-medium text-gray-700">${item.product_name}</span>
                                        </div>
                                        <div class="legend-value-container">
                                            <span class="text-xs text-gray-500">${item.total_sold} unit (${percentage}%)</span>
                                        </div>
                                    </div>
                                `;
                                
                                // Add click handler for toggling visibility
                                legendItem.onclick = () => {
                                    const meta = productsChart.getDatasetMeta(0);
                                    const isCurrentlyHidden = meta.data[index].hidden;
                                    meta.data[index].hidden = !isCurrentlyHidden;
                                    
                                    // Update total
                                    if (!isCurrentlyHidden) {
                                        currentTotal -= parseInt(item.total_sold);
                                        legendItem.classList.add('inactive');
                                    } else {
                                        currentTotal += parseInt(item.total_sold);
                                        legendItem.classList.remove('inactive');
                                    }
                                    
                                    // Check if all items are hidden
                                    const allHidden = productsChart.data.datasets[0].data.every((_, i) => 
                                        productsChart.getDatasetMeta(0).data[i].hidden
                                    );
                                    
                                    // Update total display - show 0 if all items are hidden
                                    document.getElementById('totalProductsSold').textContent = allHidden ? '0' : currentTotal;
                                    
                                    productsChart.update();
                                };
                                
                                legendContainer.appendChild(legendItem);
                            });
                        }
                    }
                }
            });
        }

        // Function to update products data
        function updateProductsData(params) {
            const formData = new FormData();
            formData.append('action', 'updateTopProducts');

            if (params.days) {
                formData.append('days', params.days);
            } else {
                formData.append('startDate', params.startDate);
                formData.append('endDate', params.endDate);
            }

            fetch('dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    updateProductsChart(result.data);
                } else {
                    console.error('Error:', result.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }

        // Event listeners for product period changes
        document.addEventListener('DOMContentLoaded', function() {
            // Handle product period change
            document.getElementById('productPeriodSelect').addEventListener('change', function(e) {
                const selectedValue = e.target.value;
                const datePickerContainer = document.getElementById('productDatePickerContainer');

                if (selectedValue === 'custom') {
                    datePickerContainer.classList.remove('hidden');
                } else {
                    datePickerContainer.classList.add('hidden');
                    updateProductsData({
                        days: selectedValue
                    });
                }
            });

            // Handle product date picker changes
            ['productStartDate', 'productEndDate'].forEach(id => {
                document.getElementById(id).addEventListener('change', function() {
                    const startDate = document.getElementById('productStartDate').value;
                    const endDate = document.getElementById('productEndDate').value;

                    if (startDate && endDate) {
                        updateProductsData({
                            startDate,
                            endDate
                        });
                    }
                });
            });

            // Initialize with 7 days data
            updateProductsData({ days: 7 });
        });

        // Tambahkan style untuk container chart
        const chartContainer = document.querySelector('.h-80');
        chartContainer.style.position = 'relative';
        chartContainer.style.minHeight = '300px';

        // Tambahkan style untuk chart section
        const chartSection = document.querySelector('.bg-white/80.backdrop-blur-xl');
        if (chartSection) {
            chartSection.style.background = 'white';
            chartSection.style.borderRadius = '16px';
            chartSection.style.boxShadow = '0 1px 3px rgba(0,0,0,0.1)';
        }

        // Tambahkan fungsi untuk toggle dataset
        function toggleDataset(index) {
            const dataset = currentChart.data.datasets[index];
            dataset.hidden = !dataset.hidden;

            // Update legend style
            const legendIds = ['legend-penjualan', 'legend-profit', 'legend-margin'];
            const element = document.getElementById(legendIds[index]);
            if (dataset.hidden) {
                element.classList.add('opacity-50');
            } else {
                element.classList.remove('opacity-50');
            }

            currentChart.update();
        }

        // Marketplace Chart
        const marketplaceCtx = document.getElementById('marketplaceChart');
        const marketplaceData = <?= json_encode($marketplaceData) ?>;

        // Sort data by revenue
        marketplaceData.sort((a, b) => b.total_revenue - a.total_revenue);

        // Get highest and lowest
        const highestMarket = marketplaceData[0];
        const lowestMarket = marketplaceData[marketplaceData.length - 1];

        const marketplaceColors = {
            'shopee': 'rgb(238, 77, 45)',
            'tokopedia': 'rgb(42, 169, 71)',
            'tiktok': 'rgb(45, 45, 45)',
            'offline': 'rgb(99, 102, 241)'
        };

        let currentChartType = 'bar';
        let marketplaceChart = null;

        function toggleChartType(type) {
            if (currentChartType === type) return;

            currentChartType = type;

            // Update button states
            document.querySelectorAll('.chart-type-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            document.getElementById(`${type}ChartBtn`).classList.add('active');

            // Destroy existing chart
            if (marketplaceChart) {
                marketplaceChart.destroy();
            }

            // Create new chart with selected type
            marketplaceChart = new Chart(marketplaceCtx, {
                type: currentChartType,
                data: {
                    labels: marketplaceData.map(item => item.marketplace),
                    datasets: [{
                        data: marketplaceData.map(item => item.total_revenue),
                        backgroundColor: marketplaceData.map(item => {
                            if (item === highestMarket) {
                                return 'rgba(34, 197, 94, 0.8)';
                            } else if (item === lowestMarket) {
                                return 'rgba(239, 68, 68, 0.8)';
                            }
                            const color = marketplaceColors[item.marketplace.toLowerCase()] || 'rgb(99, 102, 241)';
                            return color.replace('rgb', 'rgba').replace(')', ', 0.3)');
                        }),
                        borderWidth: currentChartType === 'line' ? 2 : 0,
                        borderColor: marketplaceData.map(item => {
                            if (item === highestMarket) {
                                return 'rgb(34, 197, 94)';
                            } else if (item === lowestMarket) {
                                return 'rgb(239, 68, 68)';
                            }
                            return marketplaceColors[item.marketplace.toLowerCase()] || 'rgb(99, 102, 241)';
                        }),
                        borderRadius: currentChartType === 'bar' ? 4 : 0,
                        barThickness: currentChartType === 'bar' ? 12 : undefined,
                        tension: currentChartType === 'line' ? 0.4 : undefined,
                        fill: currentChartType === 'line' ? 'start' : undefined,
                        pointBackgroundColor: 'white',
                        pointBorderWidth: 2,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'white',
                            titleColor: '#1F2937',
                            bodyColor: '#1F2937',
                            bodyFont: {
                                size: 12
                            },
                            padding: 10,
                            borderColor: 'rgba(0,0,0,0.1)',
                            borderWidth: 1,
                            callbacks: {
                                title: function(tooltipItems) {
                                    const item = marketplaceData[tooltipItems[0].dataIndex];
                                    let title = item.marketplace;
                                    if (item === highestMarket) {
                                        title += ' (Tertinggi)';
                                    } else if (item === lowestMarket) {
                                        title += ' (Terendah)';
                                    }
                                    return title;
                                },
                                label: function(context) {
                                    const data = marketplaceData[context.dataIndex];
                                    return [
                                        `Revenue: Rp ${data.total_revenue.toLocaleString('id-ID')}`,
                                        `${data.total_orders} Transaksi · ${data.total_customers} Pelanggan`
                                    ];
                                }
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            display: true,
                            drawBorder: false,
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            font: {
                                size: 11
                            },
                            callback: function(value) {
                                return 'Rp ' + value.toLocaleString('id-ID');
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                size: 11
                            }
                        }
                    }
                },
                animation: {
                    duration: 2000
                }
            });
        }

        // Initialize chart with bar type
        toggleChartType('bar');

        // Tambahkan class untuk cards agar bisa diselect
        document.querySelectorAll('.bg-gray-50\\/80.backdrop-blur-sm.rounded-xl').forEach(card => {
            card.classList.add('region-card');
        });

        // Tambahkan fungsi untuk memperbarui tampilan region
        function updateRegionDisplay(data) {
            // Update cards
            const container = document.querySelector('.mt-4.space-y-3');
            container.innerHTML = data.map((region, index) => `
                <div class="bg-gray-50/80 rounded-xl p-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-xl">
                                ${index === 0 ? '🥇' : (index === 1 ? '🥈' : '🥉')}
                            </span>
                            <h3 class="text-sm font-medium text-blue-600">
                                ${region.daerah}
                            </h3>
                        </div>
                        <div class="text-right">
                            <p class="text-base font-semibold text-gray-800">
                                Rp ${Number(region.total_pendapatan).toLocaleString('id-ID')}
                            </p>
                            <div class="flex items-center gap-2 text-xs text-gray-500">
                                <span>${region.total_transaksi} Transaksi</span>
                                <span>·</span>
                                <span>Rata-rata: Rp ${Number(region.rata_rata).toLocaleString('id-ID')}</span>
                            </div>
                        </div>
                    </div>
                </div>
            `).join('');

            // Update chart
            if (window.regionChart) {
                window.regionChart.data.labels = data.map(item => item.daerah);
                window.regionChart.data.datasets[0].data = data.map(item => item.total_pendapatan);
                window.regionChart.update('none'); // Gunakan 'none' untuk update instan
            }
        }

        // Fungsi untuk mengambil data region
        function fetchRegionData(params) {
            // Show loading state
            const cards = document.querySelectorAll('.bg-gray-50\\/80.rounded-xl');
            const chart = document.getElementById('regionBarChart');
            cards.forEach(card => card.style.opacity = '0.5');
            if (chart) chart.style.opacity = '0.5';

            // Prepare form data
            const formData = new FormData();
            formData.append('action', 'updateRegion');

            // Add parameters based on type
            if (params.days) {
                const today = new Date();
                const startDate = new Date();
                startDate.setDate(today.getDate() - (params.days - 1)); // -1 karena hari ini dihitung
                
                formData.append('days', params.days);
                formData.append('startDate', startDate.toISOString().split('T')[0]);
                formData.append('endDate', today.toISOString().split('T')[0]);
            } else {
                formData.append('startDate', params.startDate);
                formData.append('endDate', params.endDate);
            }

            // Fetch updated data
            return fetch('dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    updateRegionDisplay(result.data);
                }
                return result;
            })
            .catch(error => {
                console.error('Error:', error);
            })
            .finally(() => {
                // Remove loading state
                cards.forEach(card => card.style.opacity = '1');
                if (chart) chart.style.opacity = '1';
            });
        }

        // Event listener untuk period select
        document.getElementById('regionPeriodSelect').addEventListener('change', function(e) {
            const selectedValue = e.target.value;
            const datePickerContainer = document.getElementById('datePickerContainer');

            if (selectedValue === 'custom') {
                datePickerContainer.classList.remove('hidden');
                return;
            }

            datePickerContainer.classList.add('hidden');
            fetchRegionData({ days: parseInt(selectedValue) });
        });

        // Event listener untuk date picker
        ['startDate', 'endDate'].forEach(id => {
            document.getElementById(id).addEventListener('change', function() {
                const startDate = document.getElementById('startDate').value;
                const endDate = document.getElementById('endDate').value;

                if (startDate && endDate) {
                    fetchRegionData({ startDate, endDate });
                }
            });
        });

        // Inisialisasi awal dengan 7 hari
        document.addEventListener('DOMContentLoaded', function() {
            fetchRegionData({ days: 7 });
        });
    </script>
    <!-- Tambahkan style untuk animasi smooth -->
    <style>

    </style>
</body>

</html>