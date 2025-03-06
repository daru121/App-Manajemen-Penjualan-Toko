<?php
require_once '../backend/check_session.php';
require_once '../backend/database.php';

// Set timezone di awal file
date_default_timezone_set('Asia/Makassar'); // Set timezone ke WITA

// Update schema jika diperlukan
try {
    // Ubah status_pengiriman terlebih dahulu
    $sql1 = "ALTER TABLE transaksi 
             MODIFY COLUMN status_pengiriman 
             ENUM('pending', 'dikirim', 'selesai', 'dibatalkan') 
             DEFAULT 'pending'";
    $conn->exec($sql1);
    
    // Cek apakah kolom cancellation_reason sudah ada
    $sql2 = "SHOW COLUMNS FROM transaksi LIKE 'cancellation_reason'";
    $stmt = $conn->query($sql2);
    
    if ($stmt->rowCount() == 0) {
        // Jika belum ada, tambahkan kolom baru
        $sql3 = "ALTER TABLE transaksi 
                ADD COLUMN cancellation_reason 
                ENUM('dikembalikan ke penjual', 'barang hilang') NULL 
                AFTER status_pengiriman";
        $conn->exec($sql3);
    }
} catch(PDOException $e) {
    // Lanjutkan eksekusi meskipun ada error
}

// API Key BinderByte
$apiKey = "af5179b7271645d3e26a3cae146be63ce778f9e8ad6652d8b37b712aa65878e7"; // Ganti dengan API key BinderByte Anda

// Handle update status pengiriman via AJAX
if (isset($_POST['action']) && $_POST['action'] === 'update_status') {
    try {
        $transaksi_id = $_POST['transaksi_id'];
        $status = $_POST['status'];
        $reason = isset($_POST['reason']) ? $_POST['reason'] : null;
        
        if ($status === 'dibatalkan') {
            $stmt = $conn->prepare("UPDATE transaksi SET status_pengiriman = ?, cancellation_reason = ? WHERE id = ?");
            $stmt->execute([$status, $reason, $transaksi_id]);
        } else {
            $stmt = $conn->prepare("UPDATE transaksi SET status_pengiriman = ?, cancellation_reason = NULL WHERE id = ?");
            $stmt->execute([$status, $transaksi_id]);
        }
        
        echo json_encode(['success' => true]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Handle cek resi via AJAX
if (isset($_POST['action']) && $_POST['action'] === 'check_resi') {
    $courier = strtolower($_POST['courier']);
    $awb = $_POST['awb'];
    
    $url = "https://api.binderbyte.com/v1/track?api_key={$apiKey}&courier={$courier}&awb={$awb}";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    
    echo $response;
    exit;
}

// Query untuk mengambil data pengiriman dan barang
$query = "SELECT t.*, p.nama as nama_pembeli, 
          GROUP_CONCAT(DISTINCT CONCAT_WS('|', b.nama_barang, b.gambar, dt.jumlah) SEPARATOR '||') as barang_info
          FROM transaksi t 
          LEFT JOIN pembeli p ON t.pembeli_id = p.id 
          LEFT JOIN detail_transaksi dt ON t.id = dt.transaksi_id
          LEFT JOIN barang b ON dt.barang_id = b.id
          WHERE t.marketplace != 'offline' 
          AND t.no_resi IS NOT NULL 
          GROUP BY t.id
          ORDER BY t.tanggal DESC";
$stmt = $conn->query($query);
$pengiriman = $stmt->fetchAll();

// Hitung statistik
$total = count($pengiriman);
$pending = array_filter($pengiriman, fn($p) => $p['status_pengiriman'] === 'pending');
$dikirim = array_filter($pengiriman, fn($p) => $p['status_pengiriman'] === 'dikirim');
$selesai = array_filter($pengiriman, fn($p) => $p['status_pengiriman'] === 'selesai');
$dibatalkan = array_filter($pengiriman, fn($p) => $p['status_pengiriman'] === 'dibatalkan');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengiriman - Jamu Air Mancur</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../src/css/pengiriman.css">
</head>
<body class="bg-gray-50">
    <?php include '../components/sidebar.php'; ?>
    <?php include '../components/navbar.php'; ?>

    <div class="ml-0 sm:ml-64 p-4 sm:p-8 pt-20 sm:pt-24 min-h-screen bg-gray-50/50">
        <!-- Header dengan gradient yang sama seperti informasi.php -->
        <div class="mb-8 bg-gradient-to-br from-blue-600 to-blue-400 rounded-3xl p-8 text-white">
            <h1 class="text-3xl font-bold mb-2">Pengiriman</h1>
            <p class="text-blue-100">Kelola dan pantau status pengiriman</p>
        </div>

        <!-- Statistik Cards -->
        <div class="grid grid-cols-5 gap-6 mb-8">
            <!-- Total Pengiriman -->
            <div class="relative bg-gradient-to-br from-indigo-50 to-purple-100/50 backdrop-blur-xl rounded-3xl p-6 border border-indigo-500/10 shadow-[0_8px_30px_rgb(0,0,0,0.04)] hover:shadow-[0_8px_30px_rgb(0,0,0,0.08)] transition-all duration-300 group overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-br from-indigo-500/5 to-transparent"></div>
                <div class="absolute -right-6 -bottom-6 w-32 h-32 bg-indigo-500/10 rounded-full blur-2xl group-hover:scale-150 transition-transform duration-500"></div>
                
                <div class="relative flex items-center gap-4">
                    <div class="p-4 bg-indigo-500/10 rounded-2xl group-hover:scale-110 group-hover:bg-indigo-500/20 transition-all duration-300">
                        <svg class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-4xl font-bold mb-1 text-gray-800 group-hover:translate-x-1 transition-transform"><?= $total ?></p>
                        <p class="text-sm font-medium text-gray-500">Total Pengiriman</p>
                    </div>
                </div>
            </div>

            <!-- Pending -->
            <div class="relative bg-gradient-to-br from-orange-50 to-orange-100/50 backdrop-blur-xl rounded-3xl p-6 border border-orange-500/10 shadow-[0_8px_30px_rgb(0,0,0,0.04)] hover:shadow-[0_8px_30px_rgb(0,0,0,0.08)] transition-all duration-300 group overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-br from-orange-500/5 to-transparent"></div>
                <div class="absolute -right-6 -bottom-6 w-32 h-32 bg-orange-500/10 rounded-full blur-2xl group-hover:scale-150 transition-transform duration-500"></div>
                
                <div class="relative flex items-center gap-4">
                    <div class="p-4 bg-orange-500/10 rounded-2xl group-hover:scale-110 group-hover:bg-orange-500/20 transition-all duration-300">
                        <svg class="w-7 h-7 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-4xl font-bold mb-1 text-gray-800 group-hover:translate-x-1 transition-transform"><?= count($pending) ?></p>
                        <p class="text-sm font-medium text-gray-500">Pending</p>
                    </div>
                </div>
            </div>

            <!-- Dalam Pengiriman -->
            <div class="relative bg-gradient-to-br from-blue-50 to-blue-100/50 backdrop-blur-xl rounded-3xl p-6 border border-blue-500/10 shadow-[0_8px_30px_rgb(0,0,0,0.04)] hover:shadow-[0_8px_30px_rgb(0,0,0,0.08)] transition-all duration-300 group overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-br from-blue-500/5 to-transparent"></div>
                <div class="absolute -right-6 -bottom-6 w-32 h-32 bg-blue-500/10 rounded-full blur-2xl group-hover:scale-150 transition-transform duration-500"></div>
                
                <div class="relative flex items-center gap-4">
                    <div class="p-4 bg-blue-500/10 rounded-2xl group-hover:scale-110 group-hover:bg-blue-500/20 transition-all duration-300">
                        <svg class="w-7 h-7 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-4xl font-bold mb-1 text-gray-800 group-hover:translate-x-1 transition-transform"><?= count($dikirim) ?></p>
                        <p class="text-sm font-medium text-gray-500">Dalam Pengiriman</p>
                    </div>
                </div>
            </div>

            <!-- Selesai -->
            <div class="relative bg-gradient-to-br from-green-50 to-green-100/50 backdrop-blur-xl rounded-3xl p-6 border border-green-500/10 shadow-[0_8px_30px_rgb(0,0,0,0.04)] hover:shadow-[0_8px_30px_rgb(0,0,0,0.08)] transition-all duration-300 group overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-br from-green-500/5 to-transparent"></div>
                <div class="absolute -right-6 -bottom-6 w-32 h-32 bg-green-500/10 rounded-full blur-2xl group-hover:scale-150 transition-transform duration-500"></div>
                
                <div class="relative flex items-center gap-4">
                    <div class="p-4 bg-green-500/10 rounded-2xl group-hover:scale-110 group-hover:bg-green-500/20 transition-all duration-300">
                        <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-4xl font-bold mb-1 text-gray-800 group-hover:translate-x-1 transition-transform"><?= count($selesai) ?></p>
                        <p class="text-sm font-medium text-gray-500">Selesai</p>
                    </div>
                </div>
            </div>

            <!-- Dibatalkan -->
            <div class="relative bg-red-50 to-red-100/50 backdrop-blur-xl rounded-3xl p-6 border border-red-500/10 shadow-[0_8px_30px_rgb(0,0,0,0.04)] hover:shadow-[0_8px_30px_rgb(0,0,0,0.08)] transition-all duration-300 group overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-br from-red-500/5 to-transparent"></div>
                <div class="absolute -right-6 -bottom-6 w-32 h-32 bg-red-500/10 rounded-full blur-2xl group-hover:scale-150 transition-transform duration-500"></div>
                
                <div class="relative flex items-center gap-4">
                    <div class="p-4 bg-red-500/10 rounded-2xl group-hover:scale-110 group-hover:bg-red-500/20 transition-all duration-300">
                        <svg class="w-7 h-7 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-4xl font-bold mb-1 text-gray-800 group-hover:translate-x-1 transition-transform"><?= count($dibatalkan) ?></p>
                        <p class="text-sm font-medium text-gray-500">Dibatalkan</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter dan Search -->
        <div class="mb-6 flex flex-col sm:flex-row gap-4">
            <!-- Filter Controls -->
            <div class="flex flex-col sm:flex-row gap-2 sm:gap-4 w-full sm:w-auto">
                <select id="statusFilter" onchange="filterTable()" 
                        class="w-full px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition-all duration-200">
                    <option value="">Semua Status</option>
                    <option value="pending">Pending</option>
                    <option value="dikirim">Dikirim</option>
                    <option value="selesai">Selesai</option>
                    <option value="dibatalkan">Dibatalkan</option>
                </select>
                
                <select id="marketplaceFilter" onchange="filterTable()"
                        class="w-full px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition-all duration-200">
                    <option value="">Semua Marketplace</option>
                    <option value="shopee">Shopee</option>
                    <option value="tokopedia">Tokopedia</option>
                    <option value="tiktok">TikTok</option>
                </select>
            </div>

            <!-- Search Box -->
            <div class="relative w-full sm:w-72 sm:ml-auto">
                <input type="text" 
                       id="searchInput" 
                       onkeyup="filterTable()"
                       placeholder="Cari nomor resi atau pembeli..." 
                       class="w-full pl-10 pr-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                <div class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Table Card dengan tampilan yang lebih rapi -->
        <div class="bg-white rounded-3xl shadow-xl border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-50/50">
                            <th class="whitespace-nowrap text-left py-4 px-6 text-sm font-semibold text-gray-600" style="width: 12%">No. Transaksi</th>
                            <th class="whitespace-nowrap text-left py-4 px-6 text-sm font-semibold text-gray-600" style="width: 15%">Pembeli</th>
                            <th class="whitespace-nowrap text-left py-4 px-6 text-sm font-semibold text-gray-600" style="width: 20%">Barang</th>
                            <th class="whitespace-nowrap text-left py-4 px-6 text-sm font-semibold text-gray-600" style="width: 13%">Marketplace</th>
                            <th class="whitespace-nowrap text-left py-4 px-6 text-sm font-semibold text-gray-600" style="width: 8%">Kurir</th>
                            <th class="whitespace-nowrap text-left py-4 px-6 text-sm font-semibold text-gray-600" style="width: 12%">No. Resi</th>
                            <th class="whitespace-nowrap text-left py-4 px-6 text-sm font-semibold text-gray-600" style="width: 10%">Status</th>
                            <th class="whitespace-nowrap text-left py-4 px-6 text-sm font-semibold text-gray-600" style="width: 10%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($pengiriman as $item): ?>
                            <tr class="hover:bg-gray-50/50 transition-colors duration-200">
                                <td class="whitespace-nowrap py-4 px-6">
                                    <span class="text-sm font-medium text-gray-900">
                                        TRX-<?= str_pad($item['id'], 4, '0', STR_PAD_LEFT) ?>
                                    </span>
                                    <span class="block text-xs text-gray-500">
                                        <?= date('d M Y H:i', strtotime($item['tanggal'])) ?>
                                    </span>
                                </td>
                                <td class="whitespace-nowrap py-4 px-6">
                                    <span class="text-sm font-medium text-gray-900">
                                        <?= htmlspecialchars($item['nama_pembeli']) ?>
                                    </span>
                                    <span class="block text-xs text-gray-500">
                                        <?= htmlspecialchars($item['daerah']) ?>
                                    </span>
                                </td>
                                <td class="py-4 px-6">
                                    <div class="relative" x-data="{ open: false }">
                                        <?php 
                                        $barang_info = $item['barang_info'] ? explode('||', $item['barang_info']) : [];
                                        $first_item = !empty($barang_info[0]) ? explode('|', $barang_info[0]) : ['', '', ''];
                                        $total_items = count($barang_info);
                                        ?>
                                        
                                        <div class="flex items-center gap-3 cursor-pointer hover:bg-gray-50 p-2 rounded-xl transition-all duration-200" 
                                             @click="open = !open">
                                            <div class="w-16 h-16 rounded-xl border border-gray-200 overflow-hidden flex-shrink-0 bg-white">
                                                <?php 
                                                if (!empty($first_item[1]) && file_exists("../uploads/" . $first_item[1])) {
                                                    echo '<img src="../uploads/' . $first_item[1] . '" 
                                                              alt="' . htmlspecialchars($first_item[0]) . '"
                                                              class="w-full h-full object-cover">';
                                                } else {
                                                    echo '<div class="w-full h-full bg-gray-50 flex items-center justify-center">
                                                            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                                      d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                            </svg>
                                                          </div>';
                                                }
                                                ?>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium text-gray-900 truncate">
                                                    <?= htmlspecialchars($first_item[0]) ?>
                                                </p>
                                                <p class="text-xs text-gray-500">
                                                    Jumlah: <?= htmlspecialchars($first_item[2]) ?>
                                                </p>
                                                <?php if($total_items > 1): ?>
                                                    <span class="inline-flex items-center px-2 py-0.5 mt-1 rounded-full text-xs font-medium bg-blue-50 text-blue-600">
                                                        +<?= $total_items - 1 ?> item lainnya
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <?php if($total_items > 1): ?>
                                            <div class="absolute left-0 mt-2 w-80 bg-white rounded-2xl shadow-lg border border-gray-200/75 z-10 overflow-hidden"
                                                 x-show="open"
                                                 x-transition:enter="transition ease-out duration-200"
                                                 x-transition:enter-start="opacity-0 transform scale-95"
                                                 x-transition:enter-end="opacity-100 transform scale-100"
                                                 x-transition:leave="transition ease-in duration-75"
                                                 x-transition:leave-start="opacity-100 transform scale-100"
                                                 x-transition:leave-end="opacity-0 transform scale-95"
                                                 @click.away="open = false"
                                                 style="display: none;">
                                                <div class="py-2">
                                                    <div class="px-3 py-2 text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50/50 border-b border-gray-100">
                                                        Item Lainnya
                                                    </div>
                                                    <?php 
                                                    for($i = 1; $i < count($barang_info); $i++) {
                                                        $item_info = explode('|', $barang_info[$i]);
                                                        if(count($item_info) >= 3):
                                                    ?>
                                                        <div class="flex items-center gap-3 p-3 hover:bg-gray-50 transition-colors">
                                                            <div class="w-14 h-14 rounded-xl border border-gray-200 overflow-hidden flex-shrink-0 bg-white">
                                                                <?php 
                                                                if (!empty($item_info[1]) && file_exists("../uploads/" . $item_info[1])) {
                                                                    echo '<img src="../uploads/' . $item_info[1] . '" 
                                                                              alt="' . htmlspecialchars($item_info[0]) . '"
                                                                              class="w-full h-full object-cover">';
                                                                } else {
                                                                    echo '<div class="w-full h-full bg-gray-50 flex items-center justify-center">
                                                                            <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                                                      d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                                            </svg>
                                                                          </div>';
                                                                }
                                                                ?>
                                                            </div>
                                                            <div class="flex-1 min-w-0">
                                                                <p class="text-sm font-medium text-gray-900 truncate">
                                                                    <?= htmlspecialchars($item_info[0]) ?>
                                                                </p>
                                                                <p class="text-xs text-gray-500">
                                                                    Jumlah: <?= htmlspecialchars($item_info[2]) ?>
                                                                </p>
                                                            </div>
                                                        </div>
                                                    <?php 
                                                        endif;
                                                    } 
                                                    ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap py-4 px-6">
                                    <span class="px-3 py-1 rounded-full text-xs font-medium inline-flex items-center gap-1
                                        <?php
                                        switch($item['marketplace']) {
                                            case 'shopee':
                                                echo 'bg-orange-50 text-orange-600';
                                                break;
                                            case 'tokopedia':
                                                echo 'bg-green-50 text-green-600';
                                                break;
                                            case 'tiktok':
                                                echo 'bg-gray-50 text-gray-600';
                                                break;
                                        }
                                        ?>">
                                        <span class="w-1.5 h-1.5 rounded-full 
                                            <?php
                                            switch($item['marketplace']) {
                                                case 'shopee':
                                                    echo 'bg-orange-600';
                                                    break;
                                                case 'tokopedia':
                                                    echo 'bg-green-600';
                                                    break;
                                                case 'tiktok':
                                                    echo 'bg-gray-600';
                                                    break;
                                            }
                                            ?>">
                                        </span>
                                        <?= ucfirst($item['marketplace']) ?>
                                    </span>
                                </td>
                                <td class="whitespace-nowrap py-4 px-6 text-sm font-medium text-gray-900">
                                    <?= strtoupper($item['kurir']) ?>
                                </td>
                                <td class="whitespace-nowrap py-4 px-6">
                                    <span class="text-sm font-medium text-gray-900">
                                        <?= $item['no_resi'] ?>
                                    </span>
                                </td>
                                <td class="whitespace-nowrap py-4 px-6">
                                    <?php if($item['status_pengiriman'] === 'dibatalkan'): ?>
                                        <div class="inline-flex flex-col items-start">
                                            <span class="px-3 py-1 rounded-full text-xs font-medium bg-red-50 text-red-600 whitespace-nowrap">
                                                <?= ucfirst($item['status_pengiriman']) ?>
                                            </span>
                                            <?php if($item['cancellation_reason']): ?>
                                                <span class="text-xs text-red-500 mt-1">
                                                    <?= ucfirst($item['cancellation_reason']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="px-3 py-1 rounded-full text-xs font-medium
                                            <?php
                                            switch($item['status_pengiriman']) {
                                                case 'pending':
                                                    echo 'bg-orange-50 text-orange-600';
                                                    break;
                                                case 'dikirim':
                                                    echo 'bg-blue-50 text-blue-600';
                                                    break;
                                                case 'selesai':
                                                    echo 'bg-green-50 text-green-600';
                                                    break;
                                            }
                                            ?>">
                                            <?= ucfirst($item['status_pengiriman']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="whitespace-nowrap py-4 px-6">
                                    <div class="flex gap-2">
                                        <button onclick="checkResi('<?= $item['kurir'] ?>', '<?= $item['no_resi'] ?>')"
                                                class="px-3 py-1.5 text-sm text-blue-600 hover:bg-blue-50 rounded-lg transition-colors inline-flex items-center gap-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                            Lacak
                                        </button>
                                        <button onclick="updateStatus(<?= $item['id'] ?>, '<?= $item['status_pengiriman'] ?>')"
                                                class="px-3 py-1.5 text-sm text-orange-600 hover:bg-orange-50 rounded-lg transition-colors inline-flex items-center gap-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                            Update
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Lacak Resi yang lebih informatif -->
    <div id="trackingModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl shadow-xl max-w-2xl w-full">
                <div class="p-6 border-b border-gray-100 flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-800">Detail Pengiriman</h3>
                    <button onclick="closeTrackingModal()" class="text-gray-400 hover:text-gray-500">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="p-6">
                    <div id="trackingResult" class="space-y-4">
                        <!-- Hasil tracking akan ditampilkan di sini -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Update Status -->
    <div id="updateModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl shadow-xl max-w-md w-full">
                <div class="p-6 border-b border-gray-100">
                    <h3 class="text-lg font-semibold text-gray-800">Update Status Pengiriman</h3>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <select id="statusSelect" class="w-full px-4 py-2 border border-gray-200 rounded-xl">
                            <option value="pending">Pending</option>
                            <option value="dikirim">Dikirim</option>
                            <option value="selesai">Selesai</option>
                            <option value="dibatalkan">Dibatalkan</option>
                        </select>
                    </div>
                    
                    <div id="cancellationReasonDiv" class="hidden">
                        <select id="cancellationReason" class="w-full px-4 py-2 border border-gray-200 rounded-xl">
                            <option value="">Pilih Alasan Pembatalan</option>
                            <option value="dikembalikan ke penjual">Dikembalikan ke Penjual</option>
                            <option value="barang hilang">Barang Hilang</option>
                        </select>
                    </div>
                </div>
                <div class="p-6 border-t border-gray-100 flex justify-end gap-3">
                    <button onclick="closeUpdateModal()"
                            class="px-4 py-2 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 transition-colors">
                        Batal
                    </button>
                    <button onclick="saveStatus()"
                            class="px-4 py-2 bg-blue-500 text-white rounded-xl hover:bg-blue-600 transition-colors">
                        Simpan
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Script untuk filter dan search -->
    <script>
    function filterTable() {
        const statusFilter = document.getElementById('statusFilter').value.toLowerCase();
        const marketplaceFilter = document.getElementById('marketplaceFilter').value.toLowerCase();
        const searchInput = document.getElementById('searchInput').value.toLowerCase();
        const rows = document.querySelectorAll('tbody tr');

        rows.forEach(row => {
            const status = row.querySelector('td:nth-child(7)').textContent.toLowerCase();
            const marketplace = row.querySelector('td:nth-child(4)').textContent.toLowerCase();
            const resi = row.querySelector('td:nth-child(6)').textContent.toLowerCase();
            const pembeli = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
            const noTransaksi = row.querySelector('td:nth-child(1)').textContent.toLowerCase();

            const matchesStatus = !statusFilter || status.includes(statusFilter);
            const matchesMarketplace = !marketplaceFilter || marketplace.includes(marketplaceFilter);
            const matchesSearch = !searchInput || 
                                noTransaksi.includes(searchInput) ||
                                resi.includes(searchInput) || 
                                pembeli.includes(searchInput);

            if (matchesStatus && matchesMarketplace && matchesSearch) {
                row.classList.remove('hidden');
                row.style.display = '';
            } else {
                row.classList.add('hidden');
                row.style.display = 'none';
            }
        });

        const visibleRows = document.querySelectorAll('tbody tr:not(.hidden)');
        const noResultsMessage = document.getElementById('noResultsMessage') || createNoResultsMessage();
        
        if (visibleRows.length === 0) {
            noResultsMessage.style.display = '';
        } else {
            noResultsMessage.style.display = 'none';
        }
    }

    function createNoResultsMessage() {
        const tbody = document.querySelector('tbody');
        const tr = document.createElement('tr');
        tr.id = 'noResultsMessage';
        tr.innerHTML = `
            <td colspan="8" class="py-8 text-center text-gray-500">
                <div class="flex flex-col items-center">
                    <svg class="w-12 h-12 text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="text-gray-500">Tidak ada data yang sesuai dengan filter</span>
                </div>
            </td>
        `;
        tbody.appendChild(tr);
        return tr;
    }

    document.getElementById('statusFilter').addEventListener('change', filterTable);
    document.getElementById('marketplaceFilter').addEventListener('change', filterTable);
    document.getElementById('searchInput').addEventListener('input', filterTable);

    document.addEventListener('DOMContentLoaded', filterTable);

    let currentTransactionId = null;

    function checkResi(courier, awb) {
        const trackingModal = document.getElementById('trackingModal');
        const trackingResult = document.getElementById('trackingResult');
        
        trackingResult.innerHTML = '<div class="text-center">Memuat data...</div>';
        trackingModal.classList.remove('hidden');
        
        fetch('pengiriman.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=check_resi&courier=${courier}&awb=${awb}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 200) {
                const history = data.data.history.reverse();
                trackingResult.innerHTML = `
                    <div class="space-y-6">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-500">No. Resi</p>
                                <p class="font-medium">${data.data.summary.awb}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Kurir</p>
                                <p class="font-medium">${data.data.summary.courier}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Status</p>
                                <p class="font-medium">${data.data.summary.status}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Service</p>
                                <p class="font-medium">${data.data.summary.service}</p>
                            </div>
                        </div>
                        <div class="border-t border-gray-100 pt-4">
                            <p class="font-medium mb-4">Riwayat Pengiriman</p>
                            <div class="space-y-4">
                                ${history.map(item => `
                                    <div class="flex gap-4">
                                        <div class="w-32 shrink-0">
                                            <p class="text-sm text-gray-500">${item.date}</p>
                                        </div>
                                        <div>
                                            <p class="text-sm">${item.desc}</p>
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    </div>
                `;
            } else {
                trackingResult.innerHTML = `
                    <div class="text-center text-red-600">
                        Gagal memuat data: ${data.message}
                    </div>
                `;
            }
        })
        .catch(error => {
            trackingResult.innerHTML = `
                <div class="text-center text-red-600">
                    Terjadi kesalahan saat memuat data
                </div>
            `;
        });
    }

    function closeTrackingModal() {
        document.getElementById('trackingModal').classList.add('hidden');
    }

    function updateStatus(id, currentStatus) {
        currentTransactionId = id;
        const statusSelect = document.getElementById('statusSelect');
        statusSelect.value = currentStatus;
        document.getElementById('updateModal').classList.remove('hidden');
    }

    function closeUpdateModal() {
        document.getElementById('updateModal').classList.add('hidden');
        currentTransactionId = null;
    }

    function saveStatus() {
        const status = document.getElementById('statusSelect').value;
        const reason = status === 'dibatalkan' ? document.getElementById('cancellationReason').value : null;
        
        if (status === 'dibatalkan' && !reason) {
            alert('Silakan pilih alasan pembatalan');
            return;
        }
        
        fetch('pengiriman.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=update_status&transaksi_id=${currentTransactionId}&status=${status}&reason=${reason}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Gagal mengupdate status: ' + data.message);
            }
        })
        .catch(error => {
            alert('Terjadi kesalahan saat mengupdate status');
        });
    }

    document.getElementById('statusSelect').addEventListener('change', function() {
        const cancellationDiv = document.getElementById('cancellationReasonDiv');
        if (this.value === 'dibatalkan') {
            cancellationDiv.classList.remove('hidden');
        } else {
            cancellationDiv.classList.add('hidden');
        }
    });

    // Close modals when clicking outside
    document.querySelectorAll('.fixed').forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.add('hidden');
            }
        });
    });
    </script>
</body>
</html> 