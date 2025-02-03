<?php
require_once '../backend/check_session.php';
require_once '../backend/database.php';
require_once('../vendor/tecnickcom/tcpdf/tcpdf.php');

try {
    // Get date range
    $start_date = $_GET['start_date'] ?? date('Y-m-d');
    $end_date = $_GET['end_date'] ?? date('Y-m-d');
    $view_type = $_GET['type'] ?? 'daily';
    
    if ($view_type === 'yearly') {
        // Query untuk laporan tahunan (menampilkan semua transaksi dalam tahun)
        $query = "SELECT 
            t.id,
            t.tanggal,
            t.total_harga,
            t.marketplace,
            p.nama as nama_pembeli,
            GROUP_CONCAT(
                CONCAT(
                    dt.jumlah,
                    'pcs ',
                    b.nama_barang
                )
                SEPARATOR '\n'
            ) as detail_pembelian,
            SUM((dt.harga - b.harga_modal) * dt.jumlah) as profit
        FROM transaksi t
        JOIN detail_transaksi dt ON t.id = dt.transaksi_id
        JOIN barang b ON dt.barang_id = b.id
        LEFT JOIN pembeli p ON t.pembeli_id = p.id
        WHERE YEAR(t.tanggal) = YEAR(?)
        GROUP BY t.id, t.tanggal, t.total_harga, t.marketplace, p.nama
        ORDER BY t.tanggal ASC";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([$start_date]);
    } else if ($view_type === 'monthly') {
        // Query untuk laporan bulanan (menampilkan semua transaksi dalam bulan)
        $query = "SELECT 
            t.id,
            t.tanggal,
            t.total_harga,
            t.marketplace,
            p.nama as nama_pembeli,
            GROUP_CONCAT(
                CONCAT(
                    dt.jumlah,
                    'pcs ',
                    b.nama_barang
                )
                SEPARATOR '\n'
            ) as detail_pembelian,
            SUM((dt.harga - b.harga_modal) * dt.jumlah) as profit
        FROM transaksi t
        JOIN detail_transaksi dt ON t.id = dt.transaksi_id
        JOIN barang b ON dt.barang_id = b.id
        LEFT JOIN pembeli p ON t.pembeli_id = p.id
        WHERE DATE_FORMAT(t.tanggal, '%Y-%m') = DATE_FORMAT(?, '%Y-%m')
        GROUP BY t.id, t.tanggal, t.total_harga, t.marketplace, p.nama
        ORDER BY t.tanggal ASC";

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
                    dt.jumlah,
                    'pcs ',
                    b.nama_barang
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

    $grand_total_penjualan = 0;
    $grand_total_profit = 0;

    if ($view_type === 'yearly') {
        foreach ($transactions as $row) {
            $grand_total_penjualan += $row['total_harga'];
            $grand_total_profit += $row['profit'];
        }
    } else {
        foreach ($transactions as $transaction) {
            $grand_total_penjualan += $transaction['total_harga'];
            $grand_total_profit += $transaction['profit'];
        }
    }

} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
    $transactions = [];
}

if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    // Prevent any output
    ob_clean();
    
    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('PAksesories');
    $pdf->SetAuthor('PAksesories');
    $pdf->SetTitle('Laporan Penjualan');
    
    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Set margins
    $pdf->SetMargins(10, 15, 10); // Reduced left and right margins to fit more columns
    
    // Add a page
    $pdf->AddPage('L'); // Changed to Landscape orientation
    
    // Set font
    $pdf->SetFont('helvetica', '', 12);
    
    // Add logo and company name
    $pdf->Cell(0, 10, '', 0, 1);
    $pdf->SetFont('helvetica', 'B', 20);
    $pdf->Cell(0, 10, 'Laporan Penjualan Jamu Air Mancur', 0, 1, 'C');
    
    // Add period information
    $pdf->SetFont('helvetica', '', 12);
    if ($view_type === 'monthly') {
        $period = 'Periode: ' . date('F Y', strtotime($start_date));
    } elseif ($view_type === 'yearly') {
        $period = 'Periode: Tahun ' . date('Y', strtotime($start_date));
    } else {
        $period = 'Periode: ' . date('d F Y', strtotime($start_date));
    }
    $pdf->Cell(0, 10, $period, 0, 1, 'C');
    $pdf->Ln(10);
    
    // Add table header
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetFillColor(240, 240, 240);
    
    // Semua view menggunakan format yang sama
    $header = array('No', 'Tanggal', 'Pembeli', 'Marketplace', 'Detail Barang', 'Total', 'Profit');
    $w = array(15, 35, 40, 30, 90, 35, 35); // Adjusted widths
    
    foreach($header as $i => $col) {
        $pdf->Cell($w[$i], 10, $col, 1, 0, 'C', true);
    }
    $pdf->Ln();
    
    // Add table data
    $pdf->SetFont('helvetica', '', 9);
    foreach($transactions as $i => $transaction) {
        // Calculate row height based on content
        $detail_lines = explode("\n", $transaction['detail_pembelian']);
        $max_height = max(count($detail_lines) * 5, 10);
        
        $pdf->Cell($w[0], $max_height, $i + 1, 1, 0, 'C');
        $pdf->Cell($w[1], $max_height, date('d/m/Y H:i', strtotime($transaction['tanggal'])), 1);
        $pdf->Cell($w[2], $max_height, $transaction['nama_pembeli'], 1);
        $pdf->Cell($w[3], $max_height, ucfirst($transaction['marketplace']), 1, 0, 'C');
        
        // Detail barang cell with multiline support
        $x = $pdf->GetX();
        $y = $pdf->GetY();

        // Create a cell with border first
        $pdf->Cell($w[4], $max_height, '', 1, 0);
        $pdf->SetXY($x, $y);

        // Group transactions by marketplace
        if ($i > 0 && $transactions[$i]['marketplace'] != $transactions[$i-1]['marketplace']) {
            $pdf->Line($x, $y, $x + $w[4], $y);
        }

        $pdf->MultiCell($w[4], 5, $transaction['detail_pembelian'], 0, 'L');
        $pdf->SetXY($x + $w[4], $y);
        
        $pdf->Cell($w[5], $max_height, 'Rp ' . number_format($transaction['total_harga'], 0, ',', '.'), 1, 0, 'R');
        $pdf->Cell($w[6], $max_height, 'Rp ' . number_format($transaction['profit'], 0, ',', '.'), 1, 0, 'R');
        $pdf->Ln();
    }
    
    // Total row
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(array_sum(array_slice($w, 0, 5)), 10, 'Total:', 1, 0, 'R');
    $pdf->Cell($w[5], 10, 'Rp ' . number_format($grand_total_penjualan, 0, ',', '.'), 1, 0, 'R');
    $pdf->Cell($w[6], 10, 'Rp ' . number_format($grand_total_profit, 0, ',', '.'), 1, 0, 'R');
    
    // Remove the signature section completely
    $pdf->Ln(20);
    
    // Pastikan tidak ada output sebelum PDF
    if (ob_get_length()) ob_clean();
    
    // Output PDF
    $pdf->Output('Laporan_Penjualan_' . date('Y-m-d') . '.pdf', 'I');
    exit;
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
    
    <div class="ml-0 md:ml-64 pt-16 min-h-screen bg-gray-50/50">
        <div class="p-4 md:p-8 space-y-4 md:space-y-6">
            <!-- Header Section -->
            <div class="bg-gradient-to-br from-blue-600 via-blue-500 to-blue-400 rounded-[30px] p-6 md:p-10 shadow-xl relative overflow-hidden">
                <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                    <div>
                        <h1 class="text-2xl md:text-3xl font-semibold text-white mb-2">Laporan Penjualan</h1>
                        <p class="text-blue-100/80">Lihat detail laporan penjualan Anda</p>
                    </div>
                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 w-full md:w-auto">
                        <?php if ($view_type === 'monthly'): ?>
                            <input type="month" id="selected_date" 
                                   value="<?= date('Y-m', strtotime($start_date)) ?>" 
                                   class="w-full sm:w-44 h-11 px-4 rounded-xl border border-white/30 focus:border-white/50 bg-white/20 text-white placeholder-white/60 focus:outline-none focus:ring-2 focus:ring-white/20">
                        <?php elseif ($view_type === 'yearly'): ?>
                            <input type="number" id="selected_date" 
                                   value="<?= date('Y', strtotime($start_date)) ?>" 
                                   min="2000" max="2099"
                                   class="w-full sm:w-44 h-11 px-4 rounded-xl border border-white/30 focus:border-white/50 bg-white/20 text-white placeholder-white/60 focus:outline-none focus:ring-2 focus:ring-white/20">
                        <?php else: ?>
                            <input type="date" id="selected_date" 
                                   value="<?= $start_date ?>" 
                                   class="w-full sm:w-44 h-11 px-4 rounded-xl border border-white/30 focus:border-white/50 bg-white/20 text-white placeholder-white/60 focus:outline-none focus:ring-2 focus:ring-white/20">
                        <?php endif; ?>

                        <!-- Buttons -->
                        <button onclick="applyFilter()" 
                                class="w-full sm:w-auto px-6 py-2.5 bg-white/10 hover:bg-white/20 text-white rounded-xl flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                            </svg>
                            <span>Filter</span>
                        </button>
                        
                        <button onclick="exportToPDF()" 
                                class="w-full sm:w-auto h-11 px-6 bg-white/10 hover:bg-white/20 text-white rounded-xl flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            <span>Export PDF</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tab Navigation -->
            <div class="overflow-x-auto -mx-4 md:mx-0 px-4 md:px-0">
                <div class="p-1.5 bg-gray-100/80 backdrop-blur-xl rounded-2xl flex gap-2 shadow-sm min-w-max">
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
            </div>

            <!-- Search dan Table Section -->
            <div class="bg-white rounded-[30px] overflow-hidden border border-gray-100 shadow-sm mx-4 md:mx-0">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[800px]">
                        <thead class="bg-gray-50/50 border-b border-gray-100">
                            <tr>
                                <th class="px-6 py-5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">No</th>
                                <th class="px-6 py-5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Tanggal</th>
                                <th class="px-6 py-5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Pembeli</th>
                                <th class="px-6 py-5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Marketplace</th>
                                <th class="px-6 py-5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Detail Barang</th>
                                <th class="px-6 py-5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Total</th>
                                <th class="px-6 py-5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Profit</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach ($transactions as $i => $transaction): ?>
                                <tr class="hover:bg-gray-50/50">
                                    <td class="px-6 py-4 text-sm text-gray-600">
                                        <?= $i + 1 ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600">
                                        <?= date('d/m/Y H:i', strtotime($transaction['tanggal'])) ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600">
                                        <?= htmlspecialchars($transaction['nama_pembeli']) ?>
                                    </td>
                                    <td class="px-6 py-4">
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
                        </tbody>
                        <!-- Footer dengan total -->
                        <tfoot class="bg-gray-50/50 border-t border-gray-100">
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-sm font-medium text-gray-800">
                                    Total Keseluruhan:
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-800 font-medium">
                                    Rp <?= number_format($grand_total_penjualan, 0, ',', '.') ?>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <span class="<?= $grand_total_profit >= 0 ? 'text-green-600' : 'text-red-600' ?> font-medium">
                                        Rp <?= number_format($grand_total_profit, 0, ',', '.') ?>
                                    </span>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
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
                // Format: YYYY-MM-01 untuk memastikan mengambil bulan yang tepat
                const [year, month] = selectedDate.split('-');
                window.location.href = `laporan.php?type=monthly&start_date=${year}-${month}-01`;
            } else if (currentTab.includes('tahunan')) {
                const yearDate = `${selectedDate}-01-01`;
                window.location.href = `laporan.php?type=yearly&start_date=${yearDate}`;
            } else {
                window.location.href = `laporan.php?start_date=${selectedDate}&end_date=${selectedDate}`;
            }
        }

        function exportToPDF() {
            const selectedDate = document.getElementById('selected_date').value;
            const currentTab = document.querySelector('button[class*="text-blue-600"]').id.toLowerCase();
            let url = 'laporan.php?';
            
            if (currentTab.includes('bulanan')) {
                url += `type=monthly&start_date=${selectedDate}-01&export=pdf`;
            } else if (currentTab.includes('tahunan')) {
                url += `type=yearly&start_date=${selectedDate}-01-01&export=pdf`;
            } else {
                url += `start_date=${selectedDate}&end_date=${selectedDate}&export=pdf`;
            }
            
            window.open(url, '_blank');
        }

        // Tambahkan script untuk filter search
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchText = e.target.value.toLowerCase();
            const tableRows = document.querySelectorAll('tbody tr');

            tableRows.forEach(row => {
                const namaPembeli = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
                if (namaPembeli.includes(searchText)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });

            // Update total saat melakukan pencarian
            updateTotal();
        });

        // Fungsi untuk mengupdate total
        function updateTotal() {
            let totalPenjualan = 0;
            let totalProfit = 0;
            const tableRows = document.querySelectorAll('tbody tr');

            tableRows.forEach(row => {
                if (row.style.display !== 'none') {
                    const penjualan = parseInt(row.querySelector('td:nth-child(6)').textContent.replace(/[^\d]/g, ''));
                    const profit = parseInt(row.querySelector('td:nth-child(7)').textContent.replace(/[^\d]/g, ''));
                    
                    totalPenjualan += penjualan;
                    totalProfit += profit;
                }
            });

            // Update tampilan total
            document.querySelector('tfoot td:nth-child(6)').textContent = 'Rp ' + totalPenjualan.toLocaleString('id-ID');
            document.querySelector('tfoot td:nth-child(7)').textContent = 'Rp ' + totalProfit.toLocaleString('id-ID');
        }
    </script>

    <style>
        @media (max-width: 768px) {
            /* Input styles for mobile */
            input[type="date"],
            input[type="month"],
            input[type="number"] {
                font-size: 16px !important;
                -webkit-appearance: none;
            }

            /* Table scroll */
            .overflow-x-auto {
                -webkit-overflow-scrolling: touch;
            }

            /* Hide scrollbar but keep functionality */
            .overflow-x-auto::-webkit-scrollbar {
                display: none;
            }

            /* Better spacing */
            .space-y-4 > * + * {
                margin-top: 1rem;
            }

            /* Touch targets */
            button, input {
                min-height: 44px;
            }
        }
    </style>
</body>
</html>

