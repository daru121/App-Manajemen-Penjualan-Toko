<?php
require_once '../backend/check_session.php';
require_once '../backend/database.php';

try {
    // Get date range
    $start_date = $_GET['start_date'] ?? date('Y-m-d');
    $end_date = $_GET['end_date'] ?? date('Y-m-d');
    $view_type = $_GET['type'] ?? 'daily';
    
    if ($view_type === 'yearly') {
        // Query untuk laporan tahunan
        $query = "WITH yearly_stats AS (
            SELECT 
                YEAR(t.tanggal) as tahun,
                COUNT(DISTINCT t.id) as total_transaksi,
                SUM(t.total_harga) as total_penjualan,
                GROUP_CONCAT(DISTINCT t.marketplace) as marketplaces,
                SUM(CASE WHEN t.marketplace = 'offline' THEN 1 ELSE 0 END) as offline_count,
                SUM(CASE WHEN t.marketplace = 'shopee' THEN 1 ELSE 0 END) as shopee_count,
                SUM(CASE WHEN t.marketplace = 'tokopedia' THEN 1 ELSE 0 END) as tokopedia_count,
                SUM(CASE WHEN t.marketplace = 'tiktok' THEN 1 ELSE 0 END) as tiktok_count
            FROM transaksi t
            WHERE YEAR(t.tanggal) = YEAR(?)
            GROUP BY YEAR(t.tanggal)
        )
        SELECT 
            ys.*,
            SUM((dt.harga - b.harga_modal) * dt.jumlah) as total_profit
        FROM yearly_stats ys
        JOIN transaksi t ON YEAR(t.tanggal) = ys.tahun
        JOIN detail_transaksi dt ON t.id = dt.transaksi_id
        JOIN barang b ON dt.barang_id = b.id
        GROUP BY ys.tahun
        ORDER BY ys.tahun DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([$start_date]);
    } else if ($view_type === 'monthly') {
        // Query untuk laporan bulanan
        $query = "SELECT 
            DATE_FORMAT(t.tanggal, '%Y-%m') as bulan,
            DATE_FORMAT(t.tanggal, '%M %Y') as nama_bulan,
            COUNT(DISTINCT t.id) as total_transaksi,
            SUM(t.total_harga) as total_penjualan,
            SUM((dt.harga - b.harga_modal) * dt.jumlah) as total_profit,
            GROUP_CONCAT(DISTINCT t.marketplace) as marketplaces,
            SUM(CASE WHEN t.marketplace = 'offline' THEN 1 ELSE 0 END) as offline_count,
            SUM(CASE WHEN t.marketplace = 'shopee' THEN 1 ELSE 0 END) as shopee_count,
            SUM(CASE WHEN t.marketplace = 'tokopedia' THEN 1 ELSE 0 END) as tokopedia_count,
            SUM(CASE WHEN t.marketplace = 'tiktok' THEN 1 ELSE 0 END) as tiktok_count
        FROM transaksi t
        JOIN detail_transaksi dt ON t.id = dt.transaksi_id
        JOIN barang b ON dt.barang_id = b.id
        WHERE DATE_FORMAT(t.tanggal, '%Y-%m') = DATE_FORMAT(?, '%Y-%m')
        GROUP BY DATE_FORMAT(t.tanggal, '%Y-%m')
        ORDER BY bulan DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([$start_date]);
    } else {
        // Original daily query
        $query = "SELECT 
            t.id,
            t.tanggal,
            t.total_harga,
            t.marketplace,
            p.nama as nama_pembeli,
            GROUP_CONCAT(
                CONCAT(
                    b.nama_barang,
                    ', ',
                    dt.jumlah,
                    ' x Rp ',
                    FORMAT(dt.harga, 0),
                    ' = Rp ',
                    FORMAT(dt.jumlah * dt.harga, 0)
                )
                SEPARATOR '\n'
            ) as detail_pembelian,
            SUM((dt.harga - b.harga_modal) * dt.jumlah) as profit
        FROM transaksi t
        JOIN detail_transaksi dt ON t.id = dt.transaksi_id
        JOIN barang b ON dt.barang_id = b.id
        LEFT JOIN pembeli p ON t.pembeli_id = p.id
        WHERE DATE(t.tanggal) BETWEEN ? AND ?
        GROUP BY t.id, t.tanggal, t.total_harga, t.marketplace, p.nama
        ORDER BY t.tanggal DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([$start_date, $end_date]);
    }

    $transactions = $stmt->fetchAll();

} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
    $transactions = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - PAksesories</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <?php include '../components/sidebar.php'; ?>
    <?php include '../components/navbar.php'; ?>
    
    <div class="ml-64 pt-16 min-h-screen bg-gray-50/50">
        <div class="p-8 space-y-6">
            <!-- Header Section -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-400 rounded-3xl p-12">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-semibold text-white mb-2">Laporan Penjualan</h1>
                        <p class="text-blue-100/80">Lihat detail laporan penjualan Anda</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <?php if ($view_type === 'monthly'): ?>
                            <input type="month" id="selected_date" 
                                   value="<?= date('Y-m', strtotime($start_date)) ?>" 
                                   class="px-4 py-2.5 rounded-xl border border-white/20 focus:border-white/40 bg-white/10 text-white placeholder-white/60">
                        <?php elseif ($view_type === 'yearly'): ?>
                            <input type="number" id="selected_date" 
                                   value="<?= date('Y', strtotime($start_date)) ?>" 
                                   min="2000" max="2099"
                                   class="px-4 py-2.5 rounded-xl border border-white/20 focus:border-white/40 bg-white/10 text-white placeholder-white/60">
                        <?php else: ?>
                            <input type="date" id="selected_date" 
                                   value="<?= $start_date ?>" 
                                   class="px-4 py-2.5 rounded-xl border border-white/20 focus:border-white/40 bg-white/10 text-white placeholder-white/60">
                        <?php endif; ?>
                        <button onclick="applyFilter()" 
                                class="p-2.5 bg-white/10 text-white rounded-xl hover:bg-white/20 transition-all duration-200">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </button>
                        <button onclick="exportToExcel()" 
                                class="p-2.5 bg-white/10 text-white rounded-xl hover:bg-white/20 transition-all duration-200">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tab Navigation -->
            <div class="p-1.5 bg-gray-100/80 backdrop-blur-xl rounded-2xl inline-flex gap-2 shadow-sm">
                <button id="btnHarian" onclick="showTab('harian')" 
                        class="flex items-center gap-2 px-6 py-3 rounded-xl font-medium transition-all duration-300
                               <?= $view_type === 'daily' ? 
                                   'bg-white text-blue-600 shadow-lg shadow-blue-500/10 scale-[1.02] ring-1 ring-black/5' : 
                                   'text-gray-500 hover:text-gray-600 hover:bg-white/50' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    Laporan Harian
                </button>
                <button id="btnBulanan" onclick="showTab('bulanan')" 
                        class="flex items-center gap-2 px-6 py-3 rounded-xl font-medium transition-all duration-300
                               <?= $view_type === 'monthly' ? 
                                   'bg-white text-blue-600 shadow-lg shadow-blue-500/10 scale-[1.02] ring-1 ring-black/5' : 
                                   'text-gray-500 hover:text-gray-600 hover:bg-white/50' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    Laporan Bulanan
                </button>
                <button id="btnTahunan" onclick="showTab('tahunan')" 
                        class="flex items-center gap-2 px-6 py-3 rounded-xl font-medium transition-all duration-300
                               <?= $view_type === 'yearly' ? 
                                   'bg-white text-blue-600 shadow-lg shadow-blue-500/10 scale-[1.02] ring-1 ring-black/5' : 
                                   'text-gray-500 hover:text-gray-600 hover:bg-white/50' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Laporan Tahunan
                </button>
            </div>

            <!-- Table Section -->
            <div class="bg-white rounded-2xl overflow-hidden border border-gray-100 shadow-sm">
                <table class="w-full">
                    <?php if ($view_type === 'monthly'): ?>
                    <thead class="bg-gray-50/50 border-b border-gray-100">
                        <tr>
                            <th class="px-6 py-5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Bulan</th>
                            <th class="px-6 py-5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Transaksi</th>
                            <th class="px-6 py-5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Marketplace</th>
                            <th class="px-6 py-5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Penjualan</th>
                            <th class="px-6 py-5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Profit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php 
                        $grand_total_penjualan = 0;
                        $grand_total_profit = 0;
                        foreach ($transactions as $row): 
                            $grand_total_penjualan += $row['total_penjualan'];
                            $grand_total_profit += $row['total_profit'];
                            
                            // Convert marketplaces string to array
                            $marketplaces = array_unique(explode(',', $row['marketplaces']));
                        ?>
                            <tr class="hover:bg-gray-50/50">
                                <td class="px-6 py-4 text-sm text-gray-800 font-medium">
                                    <?= $row['nama_bulan'] ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    <?= number_format($row['total_transaksi']) ?> Transaksi
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-wrap gap-2">
                                        <?php foreach ($marketplaces as $marketplace): ?>
                                        <span class="px-3 py-1 rounded-lg text-sm font-medium relative group
                                            <?php 
                                                switch(strtolower($marketplace)) {
                                                    case 'shopee':
                                                        echo 'bg-orange-100 text-orange-700';
                                                        break;
                                                    case 'tokopedia':
                                                        echo 'bg-green-100 text-green-700';
                                                        break;
                                                    case 'tiktok':
                                                        echo 'bg-gray-100 text-gray-700';
                                                        break;
                                                    default:
                                                        echo 'bg-blue-100 text-blue-700';
                                                }
                                            ?>">
                                            <?= ucfirst(htmlspecialchars($marketplace)) ?>
                                            <!-- Individual Tooltip -->
                                            <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-2 
                                                        bg-gray-800 rounded-lg text-white text-xs whitespace-nowrap
                                                        opacity-0 invisible group-hover:opacity-100 group-hover:visible 
                                                        pointer-events-none transition-all duration-200 z-10">
                                                <?php
                                                $count_key = strtolower($marketplace) . '_count';
                                                echo $row[$count_key] . ' transaksi';
                                                ?>
                                                <div class="absolute -bottom-1 left-1/2 -translate-x-1/2 
                                                            w-2 h-2 bg-gray-800 transform rotate-45">
                                                </div>
                                            </div>
                                        </span>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-800 font-medium">
                                    Rp <?= number_format($row['total_penjualan'], 0, ',', '.') ?>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <span class="<?= $row['total_profit'] >= 0 ? 'text-green-600' : 'text-red-600' ?> font-medium">
                                        Rp <?= number_format($row['total_profit'], 0, ',', '.') ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <!-- Grand Total Row -->
                        <tr class="bg-gray-50/80">
                            <td colspan="3" class="px-6 py-4 text-sm text-gray-800 font-semibold text-right">
                                Total Keseluruhan:
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-800 font-semibold">
                                Rp <?= number_format($grand_total_penjualan, 0, ',', '.') ?>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <span class="<?= $grand_total_profit >= 0 ? 'text-green-600' : 'text-red-600' ?> font-semibold">
                                    Rp <?= number_format($grand_total_profit, 0, ',', '.') ?>
                                </span>
                            </td>
                        </tr>
                    </tbody>
                    <?php elseif ($view_type === 'yearly'): ?>
                    <thead class="bg-gray-50/50 border-b border-gray-100">
                        <tr>
                            <th class="px-6 py-5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Tahun</th>
                            <th class="px-6 py-5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Transaksi</th>
                            <th class="px-6 py-5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Marketplace</th>
                            <th class="px-6 py-5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Penjualan</th>
                            <th class="px-6 py-5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Profit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php 
                        $grand_total_penjualan = 0;
                        $grand_total_profit = 0;
                        foreach ($transactions as $row): 
                            $grand_total_penjualan += $row['total_penjualan'];
                            $grand_total_profit += $row['total_profit'];
                            
                            // Convert marketplaces string to array
                            $marketplaces = array_unique(explode(',', $row['marketplaces']));
                        ?>
                            <tr class="hover:bg-gray-50/50">
                                <td class="px-6 py-4 text-sm text-gray-800 font-medium">
                                    <?= $row['tahun'] ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    <?= number_format($row['total_transaksi']) ?> Transaksi
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-wrap gap-2">
                                        <?php foreach ($marketplaces as $marketplace): ?>
                                        <span class="px-3 py-1 rounded-lg text-sm font-medium relative group
                                            <?php 
                                                switch(strtolower($marketplace)) {
                                                    case 'shopee':
                                                        echo 'bg-orange-100 text-orange-700';
                                                        break;
                                                    case 'tokopedia':
                                                        echo 'bg-green-100 text-green-700';
                                                        break;
                                                    case 'tiktok':
                                                        echo 'bg-gray-100 text-gray-700';
                                                        break;
                                                    default:
                                                        echo 'bg-blue-100 text-blue-700';
                                                }
                                            ?>">
                                            <?= ucfirst(htmlspecialchars($marketplace)) ?>
                                            <!-- Individual Tooltip -->
                                            <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-2 
                                                        bg-gray-800 rounded-lg text-white text-xs whitespace-nowrap
                                                        opacity-0 invisible group-hover:opacity-100 group-hover:visible 
                                                        pointer-events-none transition-all duration-200 z-10">
                                                <?php
                                                $count_key = strtolower($marketplace) . '_count';
                                                echo $row[$count_key] . ' transaksi';
                                                ?>
                                                <div class="absolute -bottom-1 left-1/2 -translate-x-1/2 
                                                            w-2 h-2 bg-gray-800 transform rotate-45">
                                                </div>
                                            </div>
                                        </span>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-800 font-medium">
                                    Rp <?= number_format($row['total_penjualan'], 0, ',', '.') ?>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <span class="<?= $row['total_profit'] >= 0 ? 'text-green-600' : 'text-red-600' ?> font-medium">
                                        Rp <?= number_format($row['total_profit'], 0, ',', '.') ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <!-- Grand Total Row -->
                        <tr class="bg-gray-50/80">
                            <td colspan="3" class="px-6 py-4 text-sm text-gray-800 font-semibold text-right">
                                Total Keseluruhan:
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-800 font-semibold">
                                Rp <?= number_format($grand_total_penjualan, 0, ',', '.') ?>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <span class="<?= $grand_total_profit >= 0 ? 'text-green-600' : 'text-red-600' ?> font-semibold">
                                    Rp <?= number_format($grand_total_profit, 0, ',', '.') ?>
                                </span>
                            </td>
                        </tr>
                    </tbody>
                    <?php else: ?>
                    <thead class="bg-gray-50/50 border-b border-gray-100">
                        <tr>
                            <th class="px-6 py-5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">NO</th>
                            <th class="px-6 py-5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">TANGGAL</th>
                            <th class="px-6 py-5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">NAMA PEMBELI</th>
                            <th class="px-6 py-5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Marketplace</th>
                            <th class="px-6 py-5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">DETAIL PEMBELIAN</th>
                            <th class="px-6 py-5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">TOTAL</th>
                            <th class="px-6 py-5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">PROFIT</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php 
                        $total_penjualan = 0;
                        $total_profit = 0;
                        foreach ($transactions as $index => $transaction): 
                            $total_penjualan += $transaction['total_harga'];
                            $total_profit += $transaction['profit'];
                        ?>
                            <tr class="hover:bg-gray-50/50">
                                <td class="px-6 py-4 text-sm text-gray-600"><?= $index + 1 ?></td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    <?= date('d/m/Y H:i', strtotime($transaction['tanggal'])) ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    <?= htmlspecialchars($transaction['nama_pembeli']) ?>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <span class="px-3 py-1 rounded-lg text-sm font-medium
                                        <?php 
                                            switch(strtolower($transaction['marketplace'])) {
                                                case 'shopee':
                                                    echo 'bg-orange-100 text-orange-700';
                                                    break;
                                                case 'tokopedia':
                                                    echo 'bg-green-100 text-green-700';
                                                    break;
                                                case 'tiktok':
                                                    echo 'bg-gray-100 text-gray-700';
                                                    break;
                                                default:
                                                    echo 'bg-blue-100 text-blue-700';
                                            }
                                        ?>">
                                        <?= ucfirst(htmlspecialchars($transaction['marketplace'])) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600 whitespace-pre-line">
                                    <?= nl2br($transaction['detail_pembelian']) ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    Rp <?= number_format($transaction['total_harga'], 0, ',', '.') ?>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <span class="<?= $transaction['profit'] >= 0 ? 'text-green-600' : 'text-red-600' ?> font-medium">
                                        Rp <?= number_format($transaction['profit'], 0, ',', '.') ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <!-- Total Row -->
                        <tr class="bg-gray-50/80">
                            <td colspan="5" class="px-6 py-4 text-sm text-gray-800 font-semibold text-right">
                                Total:
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-800 font-semibold">
                                Rp <?= number_format($total_penjualan, 0, ',', '.') ?>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <span class="<?= $total_profit >= 0 ? 'text-green-600' : 'text-red-600' ?> font-semibold">
                                    Rp <?= number_format($total_profit, 0, ',', '.') ?>
                                </span>
                            </td>
                        </tr>
                    </tbody>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>

    <script>
        function showTab(tab) {
            // Update date range based on tab
            const today = new Date();
            let selectedDate = today;
            
            switch(tab) {
                case 'harian':
                    // Keep today's date
                    break;
                case 'bulanan':
                    // Format untuk input type="month"
                    document.getElementById('selected_date').value = 
                        `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}`;
                    window.location.href = `laporan.php?type=monthly&start_date=${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-01`;
                    return;
                case 'tahunan':
                    // Format untuk input type="year"
                    document.getElementById('selected_date').type = 'number';
                    document.getElementById('selected_date').value = today.getFullYear();
                    window.location.href = `laporan.php?type=yearly&start_date=${today.getFullYear()}-01-01`;
                    return;
                    break;
            }
            
            document.getElementById('selected_date').value = selectedDate.toISOString().split('T')[0];
            
            window.location.href = `laporan.php?start_date=${selectedDate.toISOString().split('T')[0]}&end_date=${selectedDate.toISOString().split('T')[0]}`;
        }

        function applyFilter() {
            const selectedDate = document.getElementById('selected_date').value;
            const currentTab = document.querySelector('button[class*="text-blue-600"]').id.toLowerCase();
            
            if (currentTab.includes('bulanan')) {
                window.location.href = `laporan.php?type=monthly&start_date=${selectedDate}-01`;
            } else if (currentTab.includes('tahunan')) {
                // Pastikan format tanggal untuk tahunan benar
                const yearDate = `${selectedDate}-01-01`;
                window.location.href = `laporan.php?type=yearly&start_date=${yearDate}`;
            } else {
                window.location.href = `laporan.php?start_date=${selectedDate}&end_date=${selectedDate}`;
            }
        }

        function exportToExcel() {
            const selectedDate = document.getElementById('selected_date').value;
            const currentTab = document.querySelector('button.bg-blue-600').id.toLowerCase();
            
            if (currentTab.includes('bulanan')) {
                window.location.href = `export_excel.php?type=monthly&start_date=${selectedDate}-01`;
            } else {
                window.location.href = `export_excel.php?start_date=${selectedDate}&end_date=${selectedDate}`;
            }
        }
    </script>
</body>
</html>
