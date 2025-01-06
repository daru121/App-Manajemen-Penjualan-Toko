<?php
session_start();
require_once '../backend/database.php';

// Fungsi untuk mengambil semua data marketplace
function getMarketplaceData() {
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

// Hitung total penjualan dan profit hari ini
$queryToday = "SELECT 
    SUM(total_harga) as total_penjualan,
    SUM(total_harga - (
        SELECT SUM(dt.jumlah * b.harga_modal)
        FROM detail_transaksi dt
        JOIN barang b ON dt.barang_id = b.id
        WHERE dt.transaksi_id = transaksi.id
    )) as total_profit,
    COUNT(*) as total_transaksi
    FROM transaksi 
    WHERE DATE(tanggal) = CURDATE()";
$stmt = $conn->query($queryToday);
$todayStats = $stmt->fetch();

$totalPenjualan = $todayStats['total_penjualan'] ?? 0;
$totalProfit = $todayStats['total_profit'] ?? 0;
$marginProfit = $totalPenjualan > 0 ? ($totalProfit / $totalPenjualan) * 100 : 0;

// Tambahkan query untuk mendapatkan data kemarin
$queryYesterday = "SELECT 
    SUM(total_harga) as total_penjualan,
    SUM(total_harga - (
        SELECT SUM(dt.jumlah * b.harga_modal)
        FROM detail_transaksi dt
        JOIN barang b ON dt.barang_id = b.id
        WHERE dt.transaksi_id = transaksi.id
    )) as total_profit,
    COUNT(*) as total_transaksi
    FROM transaksi 
    WHERE DATE(tanggal) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
$stmt = $conn->query($queryYesterday);
$yesterdayStats = $stmt->fetch();

// Hitung persentase perubahan
$penjualanKemarin = $yesterdayStats['total_penjualan'] ?? 1; // Hindari pembagian dengan 0
$profitKemarin = $yesterdayStats['total_profit'] ?? 1;
$transaksiKemarin = $yesterdayStats['total_transaksi'] ?? 1;

$persenPenjualan = (($totalPenjualan - $penjualanKemarin) / $penjualanKemarin) * 100;
$persenProfit = (($totalProfit - $profitKemarin) / $profitKemarin) * 100;

// Query untuk total produk
$queryTotalProduk = "SELECT COUNT(*) as total FROM barang";
$stmt = $conn->query($queryTotalProduk);
$totalProduk = $stmt->fetch()['total'];

// Tambahkan fungsi untuk mengambil data berdasarkan range
function getSalesData($days) {
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

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - PAksesoris</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Tambahkan Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .dashboard-card {
            transition: all 0.3s ease;
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include '../components/sidebar.php'; ?>
    <?php include '../components/navbar.php'; ?>
    
    <div class="ml-64 pt-16 min-h-screen bg-gray-100">
        <div class="p-8">
            <!-- Header Section -->
            <div class="mb-8 bg-gradient-to-br from-indigo-600 via-blue-500 to-blue-400 rounded-3xl p-10 text-white shadow-2xl relative overflow-hidden">
                <!-- Add decorative elements -->
                <div class="absolute top-0 right-0 w-64 h-64 bg-white/10 rounded-full -translate-y-32 translate-x-32 blur-3xl"></div>
                <div class="absolute bottom-0 left-0 w-64 h-64 bg-blue-500/20 rounded-full translate-y-32 -translate-x-32 blur-3xl"></div>
                
                <div class="relative">
                    <h1 class="text-4xl font-bold mb-3">Dashboard</h1>
                    <p class="text-blue-100 text-lg">Overview penjualan dan kinerja toko hari ini</p>
                </div>
            </div>

            <!-- Statistik Cards dengan desain yang lebih modern -->
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
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
                                <!-- Modern Money/Sales Icon -->
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                        </div>

                        <!-- Amount -->
                        <div class="mb-6">
                            <h3 class="text-2xl font-bold text-gray-800">Rp <?= number_format($totalPenjualan, 0, ',', '.') ?></h3>
                            <div class="h-1 w-12 bg-blue-500 rounded-full mt-2"></div>
                        </div>

                        <!-- Footer -->
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
                                    <path d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                        </div>

                        <!-- Amount -->
                        <div class="mb-6">
                            <h3 class="text-2xl font-bold text-gray-800"><?= $todayStats['total_transaksi'] ?? 0 ?></h3>
                            <div class="h-1 w-12 bg-emerald-500 rounded-full mt-2"></div>
                        </div>

                        <!-- Footer -->
                        <div class="flex items-center mt-auto">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-sm font-medium <?= ($todayStats['total_transaksi'] ?? 0) >= $transaksiKemarin ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' ?>">
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                          d="<?= ($todayStats['total_transaksi'] ?? 0) >= $transaksiKemarin ? 'M13 7h8m0 0v8m0-8l-8 8-4 4-6-6' : 'M13 17h8m0 0V9m0 8l-8-8-4 4-6-6' ?>">
                                    </path>
                                </svg>
                                <?= number_format(abs((($todayStats['total_transaksi'] ?? 0) - $transaksiKemarin) / $transaksiKemarin * 100), 1) ?>%
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
                                    <path d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                        </div>

                        <!-- Amount -->
                        <div class="mb-6">
                            <h3 class="text-2xl font-bold text-gray-800">Rp <?= number_format($totalProfit, 0, ',', '.') ?></h3>
                            <div class="h-1 w-12 bg-violet-500 rounded-full mt-2"></div>
                        </div>

                        <!-- Footer -->
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
                                    <path d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" stroke-linecap="round" stroke-linejoin="round"/>
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
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Line Chart Container -->
                <div class="bg-white rounded-2xl p-6 shadow-sm">
                    <div class="flex justify-between items-center mb-8">
                        <div>
                            <h2 class="text-xl font-semibold text-gray-800">Trend Penjualan & Profit</h2>
                            <p class="text-sm text-gray-500 mt-1">Analisis performa bisnis</p>
                        </div>
                        <div class="flex items-center gap-4">
                            <!-- Filter Period -->
                            <select id="periodSelect" class="text-sm border rounded-xl px-4 py-2 bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="7">7 Hari Terakhir</option>
                                <option value="30">30 Hari Terakhir</option>
                            </select>
                            <!-- Legend Indicators with click function -->
                            <div class="flex items-center gap-4">
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

                    <!-- Chart Container with Loading State -->
                    <div class="relative">
                        <div id="chartLoading" class="absolute inset-0 bg-white/80 backdrop-blur-sm flex items-center justify-center z-10 hidden">
                            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
                        </div>
                        <canvas id="salesChart" height="300"></canvas>
                    </div>
                </div>

                <!-- Products Container -->
                <div class="bg-white rounded-2xl p-6 shadow-sm">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h2 class="text-xl font-semibold text-gray-800">5 Produk Terlaris</h2>
                            <p class="text-sm text-gray-500 mt-1">Berdasarkan jumlah penjualan</p>
                        </div>
                        <a href="informasi.php?tab=produk-terlaris" class="text-blue-500 hover:text-blue-600 text-sm font-medium flex items-center gap-1 transition-colors">
                            Lihat Semua
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Donut Chart -->
                        <div class="relative">
                            <canvas id="productsChart" class="max-w-full h-[300px]"></canvas>
                            <!-- Center Stats -->
                            <div class="absolute inset-0 flex items-center justify-center flex-col">
                                <span class="text-3xl font-bold text-gray-800"><?= array_sum(array_column($topProducts, 'total_sold')) ?></span>
                                <span class="text-sm text-gray-500">Total Terjual</span>
                            </div>
                        </div>

                        <!-- Products List -->
                        <div class="flex flex-col justify-center space-y-4">
                            <?php foreach ($topProducts as $index => $product): 
                                $colors = [
                                    'bg-blue-500', 'bg-emerald-500', 'bg-cyan-500', 
                                    'bg-orange-500', 'bg-violet-500'
                                ];
                                $lightColors = [
                                    'bg-blue-100 text-blue-700', 'bg-emerald-100 text-emerald-700', 
                                    'bg-cyan-100 text-cyan-700', 'bg-orange-100 text-orange-700', 
                                    'bg-violet-100 text-violet-700'
                                ];
                            ?>
                            <div class="flex items-center p-3 rounded-xl hover:bg-gray-50 transition-colors">
                                <div class="flex items-center gap-3 flex-1">
                                    <div class="w-8 h-8 rounded-lg <?= $colors[$index] ?> flex items-center justify-center text-white font-medium">
                                        <?= $index + 1 ?>
                                    </div>
                                    <div>
                                        <h3 class="font-medium text-gray-800"><?= $product['product_name'] ?></h3>
                                        <p class="text-sm text-gray-500"><?= $product['total_sold'] ?> unit terjual</p>
                                    </div>
                                </div>
                                <div class="px-3 py-1 rounded-full text-sm <?= $lightColors[$index] ?>">
                                    <?= number_format(($product['total_sold'] / array_sum(array_column($topProducts, 'total_sold'))) * 100, 1) ?>%
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Grid Container untuk Marketplace dan Daerah -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Ringkasan Penjualan Section -->
                <div class="w-full">
                    <div class="bg-white/80 backdrop-blur-xl rounded-2xl p-6 shadow-sm border border-gray-100">
                        <div class="flex justify-between items-center mb-6">
                            <div>
                                <h2 class="text-xl font-semibold text-gray-800">Ringkasan Penjualan</h2>
                                <p class="text-sm text-gray-500 mt-1">Total penjualan dari semua marketplace</p>
                            </div>
                        </div>

                        <!-- Marketplace Stats Summary -->
                        <div class="grid grid-cols-2 gap-4 mb-6">
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
                                            <?php if(strtolower($data['marketplace']) === 'shopee'): ?>
                                                <img src="../img/shopee.png" alt="Shopee" class="w-7 h-7 object-contain">
                                            <?php elseif(strtolower($data['marketplace']) === 'tokopedia'): ?>
                                                <img src="../img/tokopedia.png" alt="Tokopedia" class="w-7 h-7 object-contain">
                                            <?php elseif(strtolower($data['marketplace']) === 'tiktok'): ?>
                                                <img src="../img/tiktok.png" alt="Tiktok" class="w-7 h-7 object-contain">
                                            <?php else: ?>
                                                <svg class="w-7 h-7 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .415.336.75.75.75z"/>
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

                <!-- Section Top 3 Performing Regions -->
                <div class="w-full">
                    <div class="bg-white/80 backdrop-blur-xl rounded-2xl p-6 shadow-sm border border-gray-100">
                        <div class="flex justify-between items-center mb-4">
                            <div>
                                <h2 class="text-xl font-semibold text-gray-800">Top 3 Performing Regions</h2>
                                <p class="text-sm text-gray-500 mt-1">Daerah dengan performa penjualan tertinggi</p>
                            </div>
                            <a href="informasi.php?tab=daerah" class="text-blue-500 hover:text-blue-600 text-sm font-medium flex items-center gap-1 transition-colors">
                                Lihat Semua
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>
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
                                    GROUP BY daerah 
                                    ORDER BY total_pendapatan DESC 
                                    LIMIT 3");
                                    
                                    $queryDaerah->execute();
                                    $daerahData = $queryDaerah->fetchAll(PDO::FETCH_ASSOC);

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
                                        const regionData = {
                                            labels: <?= json_encode(array_column($daerahData, 'daerah')) ?>,
                                            datasets: [{
                                                data: <?= json_encode(array_column($daerahData, 'total_pendapatan')) ?>,
                                                backgroundColor: ['#3B82F6', '#6366F1', '#8B5CF6'],
                                                borderRadius: 6,
                                                maxBarThickness: 40
                                            }]
                                        };

                                        new Chart(regionCtx, {
                                            type: 'bar',
                                            data: regionData,
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
                                    });
                                    </script>
                                <?php
                                } catch (PDOException $e) {
                                    echo "<div class='text-center text-red-500'>Terjadi kesalahan: " . $e->getMessage() . "</div>";
                                }
                                ?>
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
                                    display: false,
                                    drawBorder: false
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
            const period = e.target.value;
            const data = period === '7' ? salesData7Days : salesData30Days;
            createChart(data);
        });

        // Update donut chart configuration
        const productsData = <?= json_encode($topProducts) ?>;
        const ctx = document.getElementById('productsChart').getContext('2d');
        
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: productsData.map(item => item.product_name),
                datasets: [{
                    data: productsData.map(item => item.total_sold),
                    backgroundColor: [
                        'rgb(99, 102, 241)',
                        'rgb(16, 185, 129)',
                        'rgb(6, 182, 212)',
                        'rgb(249, 115, 22)',
                        'rgb(139, 92, 246)'
                    ],
                    borderColor: 'white',
                    borderWidth: 2,
                    hoverOffset: 4,
                    hoverBorderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '75%',
                radius: '90%',
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
                                const value = context.raw;
                                const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return `${context.label}: ${value} unit (${percentage}%)`;
                            }
                        }
                    }
                },
                animation: {
                    animateScale: true,
                    animateRotate: true,
                    duration: 2000
                },
                hover: {
                    mode: 'nearest',
                    intersect: true
                }
            }
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
    </script>
    <!-- Tambahkan style untuk animasi smooth -->
    <style>
        .chart-container {
            transition: all 0.3s ease;
        }
        
        .chart-container:hover {
            transform: translateY(-5px);
        }

        canvas {
            transition: all 0.3s ease;
        }
        
        select, button {
            transition: all 0.2s ease;
        }
        
        select:hover, button:hover {
            transform: translateY(-2px);
        }

        /* Enhance scrollbar design */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: #c5c5c5;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        /* Add smooth transitions */
        .dashboard-card, .chart-container {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .dashboard-card:hover, .chart-container:hover {
            transform: translateY(-5px);
        }

        /* Enhanced focus states */
        select:focus, button:focus {
            outline: none;
            ring: 2px;
            ring-color: rgba(59, 130, 246, 0.5);
        }

        /* Add glass morphism effect */
        .glass-morphism {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }

        /* Enhance card animations */
        .group:hover .animate-pulse {
            animation: pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        @keyframes pulse {
            0%, 100% {
                opacity: 0.5;
            }
            50% {
                opacity: 0.25;
            }
        }

        /* Shine effect */
        .group:hover .shine {
            animation: shine 1.5s forwards;
        }

        @keyframes shine {
            0% {
                transform: translateX(-100%) translateY(-100%) rotate(45deg);
            }
            100% {
                transform: translateX(100%) translateY(100%) rotate(45deg);
            }
        }

        /* Smooth hover transitions */
        .group {
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .group:hover {
            transform: translateY(-5px);
        }

        /* Enhanced shadows */
        .enhanced-shadow {
            box-shadow: 
                0 10px 15px -3px rgba(0, 0, 0, 0.05),
                0 4px 6px -2px rgba(0, 0, 0, 0.025),
                0 0 0 1px rgba(0, 0, 0, 0.025);
        }

        .enhanced-shadow:hover {
            box-shadow: 
                0 20px 25px -5px rgba(0, 0, 0, 0.1),
                0 10px 10px -5px rgba(0, 0, 0, 0.04),
                0 0 0 1px rgba(0, 0, 0, 0.025);
        }

        /* Enhanced animations */
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        .group:hover {
            animation: float 3s ease-in-out infinite;
        }

        /* Enhanced shine effect */
        .shine {
            position: absolute;
            top: 0;
            left: -100%;
            width: 50%;
            height: 100%;
            background: linear-gradient(
                120deg,
                transparent,
                rgba(255,255,255,0.6),
                transparent
            );
            animation: shine-effect 2s linear infinite;
        }

        @keyframes shine-effect {
            0% { left: -100%; }
            100% { left: 200%; }
        }

        /* Smooth scale on hover */
        .scale-hover {
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .scale-hover:hover {
            transform: scale(1.05);
        }

        /* Enhanced glass effect */
        .glass {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        /* Gradient text */
        .gradient-text {
            background: linear-gradient(to right, #4F46E5, #7C3AED);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Tambahkan style untuk icon animation */
        .icon-container {
            position: relative;
            overflow: hidden;
        }
        
        .icon-container svg {
            transition: all 0.3s ease;
        }
        
        .icon-container:hover svg {
            transform: scale(1.1);
        }
        
        /* Shine effect for icons */
        .icon-container::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(
                45deg,
                transparent 0%,
                rgba(255,255,255,0.1) 50%,
                transparent 100%
            );
            transform: rotate(45deg);
            transition: all 0.3s ease;
        }
        
        .icon-container:hover::after {
            animation: shine 0.5s forwards;
        }
        
        @keyframes shine {
            0% {
                transform: translateX(-100%) rotate(45deg);
            }
            100% {
                transform: translateX(100%) rotate(45deg);
            }
        }

        /* Tambahkan style untuk legend yang bisa diklik */
        .cursor-pointer {
            cursor: pointer;
            user-select: none;
        }

        .cursor-pointer:hover {
            opacity: 0.8;
        }

        .opacity-50 {
            opacity: 0.5;
        }

        /* Gradient text colors */
        .gradient-text-blue {
            background: linear-gradient(to right, #3B82F6, #60A5FA);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .gradient-text-indigo {
            background: linear-gradient(to right, #6366F1, #818CF8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .gradient-text-violet {
            background: linear-gradient(to right, #8B5CF6, #A78BFA);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Enhanced glass effect */
        .glass {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
    </style>
</body>
</html>

