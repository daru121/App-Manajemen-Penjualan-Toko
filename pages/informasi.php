<?php
require_once '../backend/check_session.php';
require_once '../backend/database.php';

// Tambahkan di bagian awal file setelah require statements
if (isset($_GET['action']) && $_GET['action'] === 'get_daerah_detail') {
    header('Content-Type: application/json');
    try {
        $daerah = $_GET['daerah'];
        
        $query = "SELECT 
            t.id,
            t.tanggal,
            t.total_harga,
            t.marketplace,
            p.nama as nama_pembeli
        FROM transaksi t
        LEFT JOIN pembeli p ON t.pembeli_id = p.id
        WHERE t.daerah = ?
        ORDER BY t.tanggal DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([$daerah]);
        $details = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'details' => $details
        ]);
        exit;
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Handle get transaction detail
if (isset($_GET['action']) && $_GET['action'] === 'get_detail' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    try {
        $id = $_GET['id'];
        
        // Get transaction details with buyer name
        $query = "SELECT 
            t.id,
            t.tanggal,
            t.total_harga,
            p.nama as buyer_name
        FROM transaksi t
        LEFT JOIN pembeli p ON t.pembeli_id = p.id
        WHERE t.id = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([$id]);
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($transaction) {
            // Get items details with profit calculation
            $query = "SELECT 
                b.nama_barang as nama_barang,
                dt.jumlah,
                dt.harga,
                (dt.jumlah * dt.harga) as subtotal,
                (dt.jumlah * (dt.harga - b.harga_modal)) as profit
            FROM detail_transaksi dt
            JOIN barang b ON dt.barang_id = b.id
            WHERE dt.transaksi_id = ?";
            
            $stmt = $conn->prepare($query);
            $stmt->execute([$id]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'transaction' => $transaction,
                'items' => $items
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Transaksi tidak ditemukan']);
        }
        exit;
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Handle delete transaction
if (isset($_POST['delete_id'])) {
    try {
        $id = $_POST['delete_id'];
        
        // Start transaction
        $conn->beginTransaction();
        
        // Delete detail transaksi first (foreign key constraint)
        $stmt = $conn->prepare("DELETE FROM detail_transaksi WHERE transaksi_id = ?");
        $stmt->execute([$id]);
        
        // Then delete the transaction
        $stmt = $conn->prepare("DELETE FROM transaksi WHERE id = ?");
        $stmt->execute([$id]);
        
        // Commit transaction
        $conn->commit();
        
        // Redirect back to the same page with all parameters
        $params = $_GET;
        $params['success'] = 1;
        $queryString = http_build_query($params);
        header("Location: informasi.php?$queryString");
        exit;
    } catch(PDOException $e) {
        $conn->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

// Handle get product history
if (isset($_GET['action']) && $_GET['action'] === 'get_product_history' && isset($_GET['product'])) {
    header('Content-Type: application/json');
    try {
        $product_name = $_GET['product'];
        
        $query = "SELECT 
            t.tanggal,
            p.nama as nama_pembeli,
            dt.jumlah,
            dt.harga
        FROM detail_transaksi dt
        JOIN barang b ON dt.barang_id = b.id
        JOIN transaksi t ON dt.transaksi_id = t.id
        LEFT JOIN pembeli p ON t.pembeli_id = p.id
        WHERE b.nama_barang = ?
        ORDER BY t.tanggal DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([$product_name]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'history' => $history
        ]);
        exit;
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Tambahkan handler untuk edit transaksi di bagian awal file
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_transaction') {
    header('Content-Type: application/json');
    try {
        $id = $_POST['transaction_id'];
        $tanggal = $_POST['tanggal'];
        $marketplace = $_POST['marketplace'];
        
        // Update transaksi
        $stmt = $conn->prepare("UPDATE transaksi SET tanggal = ?, marketplace = ? WHERE id = ?");
        $stmt->execute([$tanggal, $marketplace, $id]);
        
        echo json_encode(['status' => 'success']);
    } catch(PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// Tambahkan handler untuk get_transaction
if (isset($_GET['action']) && $_GET['action'] === 'get_transaction') {
    header('Content-Type: application/json');
    try {
        $id = $_GET['id'];
        $stmt = $conn->prepare("SELECT t.*, p.nama as nama_pembeli 
                               FROM transaksi t 
                               LEFT JOIN pembeli p ON t.pembeli_id = p.id 
                               WHERE t.id = ?");
        $stmt->execute([$id]);
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode($transaction);
        exit;
    } catch(PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

try {
    // Get user data
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    // Set default date range if not provided
    $today = date('Y-m-d');
    $start_date = $_GET['start_date'] ?? $today;
    $end_date = $_GET['end_date'] ?? $today;
    
    // Base query
    $query = "SELECT 
        t.id,
        p.nama as nama_pembeli,
        t.tanggal,
        t.total_harga,
        SUM(dt.jumlah * (dt.harga - b.harga_modal)) as profit,
        t.marketplace
    FROM transaksi t
    LEFT JOIN pembeli p ON t.pembeli_id = p.id
    LEFT JOIN detail_transaksi dt ON t.id = dt.transaksi_id
    LEFT JOIN barang b ON dt.barang_id = b.id
    WHERE 1=1";
    
    $params = [];

    // Filter by date range
    $query .= " AND DATE(t.tanggal) BETWEEN :start_date AND :end_date";
    $params[':start_date'] = $start_date;
    $params[':end_date'] = $end_date;

    // Filter by search term
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $query .= " AND p.nama LIKE :search";
        $params[':search'] = '%' . $_GET['search'] . '%';
    }

    $query .= " GROUP BY t.id ORDER BY t.tanggal DESC";

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
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
    <title>Informasi Penjualan - PAksesories</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }
        
        .modal.active {
            display: flex;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .delete-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .delete-modal.active {
            display: flex;
            animation: fadeIn 0.3s ease;
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include '../components/sidebar.php'; ?>
    <?php include '../components/navbar.php'; ?>
    
    <?php if (isset($_GET['success'])): ?>
    <div id="successAlert" class="fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50">
        Transaksi berhasil dihapus
    </div>
    <script>
        setTimeout(() => {
            document.getElementById('successAlert').style.display = 'none';
        }, 3000);
    </script>
    <?php endif; ?>
    
    <div class="ml-64 pt-16 min-h-screen bg-gray-50/50">
        <div class="p-8 space-y-6">
            <!-- Header Section -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-400 rounded-3xl p-12">
                <div>
                    <h1 class="text-3xl font-semibold text-white mb-2">Informasi Penjualan</h1>
                    <p class="text-blue-100/80">Kelola dan pantau data transaksi penjualan Anda</p>
                </div>
            </div>

            <!-- Tab Navigation -->
            <div class="p-1.5 bg-gray-100/80 backdrop-blur-xl rounded-2xl inline-flex gap-2 shadow-sm">
                <button id="btnTransaksi" onclick="showTab('transaksi')" 
                        class="flex items-center gap-2 px-6 py-3 rounded-xl font-medium transition-all duration-300
                               <?= !isset($_GET['tab']) || $_GET['tab'] === 'transaksi' ? 
                                   'bg-white text-blue-600 shadow-lg shadow-blue-500/10 scale-[1.02] ring-1 ring-black/5' : 
                                   'text-gray-500 hover:text-gray-600 hover:bg-white/50' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                    </svg>
                    Transaksi
                </button>
                <button id="btnProdukTerlaris" onclick="showTab('produk-terlaris')" 
                        class="flex items-center gap-2 px-6 py-3 rounded-xl font-medium transition-all duration-300
                               <?= isset($_GET['tab']) && $_GET['tab'] === 'produk-terlaris' ? 
                                   'bg-white text-blue-600 shadow-lg shadow-blue-500/10 scale-[1.02] ring-1 ring-black/5' : 
                                   'text-gray-500 hover:text-gray-600 hover:bg-white/50' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                    Produk Terjual
                </button>
                <button id="btnDaerah" onclick="showTab('daerah')" 
                        class="flex items-center gap-2 px-6 py-3 rounded-xl font-medium transition-all duration-300
                               <?= isset($_GET['tab']) && $_GET['tab'] === 'daerah' ? 
                                   'bg-white text-blue-600 shadow-lg shadow-blue-500/10 scale-[1.02] ring-1 ring-black/5' : 
                                   'text-gray-500 hover:text-gray-600 hover:bg-white/50' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Daerah
                </button>
            </div>

            <!-- Table Section -->
            <div class="bg-white rounded-2xl overflow-hidden border border-gray-100 shadow-sm">
                <?php if (!isset($_GET['tab']) || $_GET['tab'] === 'transaksi'): ?>
                <!-- Filter & Search Section untuk Tab Transaksi -->
                <div class="p-6 bg-white border-b border-gray-100 flex items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <input type="date" id="start_date" value="<?= $_GET['start_date'] ?? date('Y-m-d') ?>" 
                               class="px-4 py-2.5 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-gray-600">
                        <span class="text-gray-500 font-medium">to</span>
                        <input type="date" id="end_date" value="<?= $_GET['end_date'] ?? date('Y-m-d') ?>"
                               class="px-4 py-2.5 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-gray-600">
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="text" id="searchInput" placeholder="Cari nama pembeli..." value="<?= $_GET['search'] ?? '' ?>"
                               class="px-4 py-2.5 w-80 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-gray-600">
                        <button onclick="applyFilter()" 
                                class="px-6 py-2.5 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-xl 
                                       hover:from-blue-700 hover:to-blue-800 transition-all duration-300 font-medium
                                       shadow-lg shadow-blue-500/30 hover:shadow-blue-500/40">
                            Cari
                        </button>
                    </div>
                </div>

                <!-- Tabel Transaksi -->
                <table class="w-full">
                    <thead class="bg-gray-50/50 border-b border-gray-100">
                        <tr>
                            <th class="px-6 py-5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">NO</th>
                            <th class="px-6 py-5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">NAMA PEMBELI</th>
                            <th class="px-6 py-5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">TANGGAL TRANSAKSI</th>
                            <th class="px-6 py-5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">MARKETPLACE</th>
                            <th class="px-6 py-5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">TOTAL PEMBELIAN</th>
                            <th class="px-6 py-5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">PROFIT</th>
                            <th class="px-6 py-5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">AKSI</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($transactions as $index => $transaction): ?>
                        <tr class="hover:bg-gray-50/50">
                            <td class="px-6 py-4 text-sm text-gray-600"><?= $index + 1 ?></td>
                            <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars($transaction['nama_pembeli']) ?></td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                <?= date('d/m/Y H:i', strtotime($transaction['tanggal'])) ?>
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
                            <td class="px-6 py-4 text-sm text-gray-600">
                                Rp <?= number_format($transaction['total_harga'], 0, ',', '.') ?>
                            </td>
                            <td class="px-6 py-4">
                                <span class="<?= $transaction['profit'] >= 0 ? 'text-green-600' : 'text-red-600' ?> font-medium">
                                    Rp <?= number_format($transaction['profit'], 0, ',', '.') ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <button onclick="showDetail(<?= $transaction['id'] ?>)" 
                                            class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </button>
                                    <button onclick="showDeleteModal(<?= $transaction['id'] ?>)" 
                                            class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                    <button onclick="editTransaction(<?= $transaction['id'] ?>)" 
                                            class="p-2 text-yellow-600 hover:bg-yellow-50 rounded-lg transition-colors">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                  d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php elseif (isset($_GET['tab']) && $_GET['tab'] === 'produk-terlaris'): ?>
                    <!-- Tabel Produk Terlaris -->
                    <table class="w-full">
                        <thead class="bg-gray-50/50 border-b border-gray-100">
                            <tr>
                                <th class="px-6 py-5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">NO</th>
                                <th class="px-6 py-5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">NAMA PRODUK</th>
                                <th class="px-6 py-5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">TOTAL TERJUAL</th>
                                <th class="px-6 py-5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">TOTAL PENJUALAN</th>
                                <th class="px-6 py-5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">TOTAL PROFIT</th>
                                <th class="px-6 py-5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">AKSI</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php
                            // Query untuk produk terlaris dengan menambahkan kolom gambar
                            $query = "SELECT 
                                b.id,
                                b.nama_barang,
                                b.gambar,
                                COALESCE(SUM(dt.jumlah), 0) as total_terjual,
                                COALESCE(SUM(dt.jumlah * dt.harga), 0) as total_penjualan,
                                COALESCE(SUM((dt.harga - b.harga_modal) * dt.jumlah), 0) as total_profit,
                                COUNT(DISTINCT t.id) as total_transaksi
                            FROM barang b
                            LEFT JOIN detail_transaksi dt ON b.id = dt.barang_id
                            LEFT JOIN transaksi t ON dt.transaksi_id = t.id
                            GROUP BY b.id, b.nama_barang, b.gambar
                            ORDER BY total_terjual DESC";
                            
                            $stmt = $conn->prepare($query);
                            $stmt->execute();
                            $products = $stmt->fetchAll();
                            
                            foreach ($products as $index => $product):
                            ?>
                            <tr class="hover:bg-gray-50/50">
                                <td class="px-6 py-4 text-sm text-gray-600"><?= $index + 1 ?></td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-4">
                                        <!-- Image Container -->
                                        <div class="w-12 h-12 rounded-lg overflow-hidden bg-gray-100 border border-gray-200 flex-shrink-0">
                                            <?php if ($product['gambar']): ?>
                                                <img src="../uploads/<?= htmlspecialchars($product['gambar']) ?>" 
                                                     alt="<?= htmlspecialchars($product['nama_barang']) ?>"
                                                     class="w-full h-full object-cover">
                                            <?php else: ?>
                                                <div class="w-full h-full flex items-center justify-center bg-gray-50">
                                                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                              d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                    </svg>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <!-- Product Name and Transaction Count -->
                                        <div>
                                            <p class="text-sm font-medium text-gray-800">
                                                <?= htmlspecialchars($product['nama_barang']) ?>
                                            </p>
                                            <div class="text-xs text-gray-400 mt-0.5">
                                                <?= $product['total_transaksi'] ?: 'Belum ada' ?> transaksi
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-3 py-1 rounded-lg text-sm font-medium <?= $product['total_terjual'] > 0 ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600' ?>">
                                        <?= number_format($product['total_terjual']) ?> Unit
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    <?= $product['total_penjualan'] > 0 ? 'Rp ' . number_format($product['total_penjualan'], 0, ',', '.') : '-' ?>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="<?= $product['total_profit'] > 0 ? 'text-green-600' : ($product['total_profit'] < 0 ? 'text-red-600' : 'text-gray-400') ?> font-medium">
                                        <?= $product['total_profit'] != 0 ? 'Rp ' . number_format($product['total_profit'], 0, ',', '.') : '-' ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <button onclick="showProductHistory('<?= htmlspecialchars($product['nama_barang']) ?>')" 
                                            class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php elseif (isset($_GET['tab']) && $_GET['tab'] === 'daerah'): ?>
                    <!-- Tabel Daerah -->
                    <table class="w-full">
                        <thead class="bg-gray-50/50 border-b border-gray-100">
                            <tr>
                                <th class="px-6 py-5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">NO</th>
                                <th class="px-6 py-5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">DAERAH</th>
                                <th class="px-6 py-5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">TOTAL TRANSAKSI</th>
                                <th class="px-6 py-5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">TOTAL PENJUALAN</th>
                                <th class="px-6 py-5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">PROFIT</th>
                                <th class="px-6 py-5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">AKSI</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php
                            // Query untuk data daerah
                            $query = "SELECT 
                                t.daerah,
                                COUNT(DISTINCT t.id) as total_transaksi,
                                SUM(t.total_harga) as total_penjualan,
                                SUM(dt.jumlah * (dt.harga - b.harga_modal)) as profit
                            FROM transaksi t
                            JOIN detail_transaksi dt ON t.id = dt.transaksi_id
                            JOIN barang b ON dt.barang_id = b.id
                            WHERE t.daerah IS NOT NULL
                            GROUP BY t.daerah
                            ORDER BY total_transaksi DESC, total_penjualan DESC";
                            
                            $stmt = $conn->prepare($query);
                            $stmt->execute();
                            $daerahData = $stmt->fetchAll();
                            
                            foreach ($daerahData as $index => $data):
                            ?>
                            <tr class="hover:bg-gray-50/50">
                                <td class="px-6 py-4 text-sm text-gray-600"><?= $index + 1 ?></td>
                                <td class="px-6 py-4 text-sm text-gray-800 font-medium">
                                    <?= htmlspecialchars($data['daerah']) ?>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-3 py-1 rounded-lg text-sm font-medium bg-blue-100 text-blue-700">
                                        <?= number_format($data['total_transaksi']) ?> Transaksi
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    Rp <?= number_format($data['total_penjualan'], 0, ',', '.') ?>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="<?= $data['profit'] >= 0 ? 'text-green-600' : 'text-red-600' ?> font-medium">
                                        Rp <?= number_format($data['profit'], 0, ',', '.') ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <button onclick="showDaerahDetail('<?= htmlspecialchars($data['daerah']) ?>')" 
                                            class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Detail Modal -->
    <div id="detailModal" class="modal items-center justify-center">
        <div class="bg-white rounded-lg shadow-xl w-[700px] overflow-hidden">
            <!-- Modal Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">Detail Transaksi</h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-500">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            
            <!-- Modal Content -->
            <div class="p-6 space-y-6">
                <h4 class="font-medium text-gray-800">Informasi Transaksi</h4>
                <div class="space-y-4">
                    <div class="flex">
                        <span class="w-40 text-gray-600">Nama Pembeli</span>
                        <span class="text-gray-900">: </span>
                        <span class="ml-2 text-gray-900" id="modalBuyerName"></span>
                    </div>
                    <div class="flex">
                        <span class="w-40 text-gray-600">Tanggal</span>
                        <span class="text-gray-900">: </span>
                        <span class="ml-2 text-gray-900" id="modalDate"></span>
                    </div>
                    <div class="flex">
                        <span class="w-40 text-gray-600">Total Pembayaran</span>
                        <span class="text-gray-900">: </span>
                        <span class="ml-2 text-gray-900" id="modalTotal"></span>
                    </div>
                </div>
                
                <h4 class="font-medium text-gray-800 mt-6">Detail Barang</h4>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">No</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nama Barang</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Jumlah</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Harga</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Subtotal</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Profit</th>
                            </tr>
                        </thead>
                        <tbody id="modalDetailItems" class="divide-y divide-gray-200"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="delete-modal">
        <div class="bg-white rounded-lg shadow-xl w-[400px] overflow-hidden">
            <div class="p-6">
                <div class="flex items-center justify-center mb-6">
                    <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                </div>
                <h3 class="text-lg font-medium text-center mb-4">Konfirmasi Hapus</h3>
                <p class="text-gray-500 text-center mb-6">Apakah Anda yakin ingin menghapus transaksi ini?</p>
                <form id="deleteForm" method="POST" class="flex justify-center gap-3">
                    <input type="hidden" name="delete_id" id="deleteId">
                    <?php foreach ($_GET as $key => $value): ?>
                    <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
                    <?php endforeach; ?>
                    <button type="button" onclick="closeDeleteModal()" 
                            class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                        Batal
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors">
                        Hapus
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Product History Modal -->
    <div id="productHistoryModal" class="modal items-center justify-center">
        <div class="bg-white rounded-lg shadow-xl w-[700px] overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">Riwayat Penjualan Produk</h3>
                <button onclick="closeProductHistory()" class="text-gray-400 hover:text-gray-500">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            
            <div class="p-6 space-y-4">
                <h4 class="font-medium text-gray-800" id="productHistoryTitle"></h4>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">No</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Pembeli</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Jumlah</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Harga</th>
                            </tr>
                        </thead>
                        <tbody id="productHistoryItems" class="divide-y divide-gray-200"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Daerah Detail Modal -->
    <div id="daerahDetailModal" class="modal items-center justify-center">
        <div class="bg-white rounded-2xl shadow-xl w-[800px] overflow-hidden">
            <!-- Modal Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800" id="modalDaerahTitle">Detail Transaksi Daerah</h3>
                <button onclick="closeDaerahModal()" class="text-gray-400 hover:text-gray-500">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            
            <!-- Modal Content -->
            <div class="p-6">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-100">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500">NO</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500">NAMA PEMBELI</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500">TANGGAL</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500">TOTAL</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500">MARKETPLACE</th>
                            </tr>
                        </thead>
                        <tbody id="daerahDetailContent" class="divide-y divide-gray-100"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Edit Transaksi -->
    <div id="editTransactionModal" class="fixed inset-0 bg-black/50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-2xl w-full max-w-lg p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-800">Edit Transaksi</h3>
                <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <form id="editTransactionForm" class="space-y-4">
                <input type="hidden" id="editTransactionId" name="transaction_id">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Transaksi</label>
                    <input type="datetime-local" id="editTanggal" name="tanggal" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Marketplace</label>
                    <select id="editMarketplace" name="marketplace" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                        <option value="Offline">Offline</option>
                        <option value="Shopee">Shopee</option>
                        <option value="Tokopedia">Tokopedia</option>
                        <option value="Tiktok">Tiktok</option>
                    </select>
                </div>
                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" onclick="closeEditModal()" 
                            class="px-4 py-2 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                        Batal
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors">
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        async function showDetail(id) {
            try {
                const response = await fetch(`informasi.php?action=get_detail&id=${id}`);
                const data = await response.json();
                
                if (data.success) {
                    // Populate modal with transaction data
                    document.getElementById('modalBuyerName').textContent = data.transaction.buyer_name || '-';
                    document.getElementById('modalDate').textContent = new Date(data.transaction.tanggal)
                        .toLocaleString('id-ID', { 
                            day: '2-digit',
                            month: '2-digit',
                            year: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        });
                    document.getElementById('modalTotal').textContent = `Rp ${Number(data.transaction.total_harga).toLocaleString('id-ID')}`;
                    
                    // Populate items table
                    const tbody = document.getElementById('modalDetailItems');
                    tbody.innerHTML = data.items.map((item, index) => `
                        <tr>
                            <td class="px-4 py-2 text-sm text-gray-600">${index + 1}</td>
                            <td class="px-4 py-2 text-sm text-gray-600">${item.nama_barang}</td>
                            <td class="px-4 py-2 text-sm text-gray-600">${item.jumlah}</td>
                            <td class="px-4 py-2 text-sm text-gray-600">Rp ${Number(item.harga).toLocaleString('id-ID')}</td>
                            <td class="px-4 py-2 text-sm text-gray-600">Rp ${Number(item.subtotal).toLocaleString('id-ID')}</td>
                            <td class="px-4 py-2 text-sm text-gray-600">Rp ${Number(item.profit).toLocaleString('id-ID')}</td>
                        </tr>
                    `).join('');
                    
                    // Show modal
                    document.getElementById('detailModal').classList.add('active');
                } else {
                    alert(data.message || 'Gagal memuat detail transaksi');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat memuat detail transaksi');
            }
        }
        
        function closeModal() {
            document.getElementById('detailModal').classList.remove('active');
        }
        
        function applyFilter() {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            const searchTerm = document.getElementById('searchInput').value;
            
            // Get current URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            
            // Update parameters
            urlParams.set('start_date', startDate);
            urlParams.set('end_date', endDate);
            if (searchTerm) {
                urlParams.set('search', searchTerm);
            } else {
                urlParams.delete('search');
            }
            
            // Keep the current tab
            if (!urlParams.has('tab')) {
                urlParams.set('tab', 'transaksi');
            }
            
            // Redirect with all parameters
            window.location.href = `informasi.php?${urlParams.toString()}`;
        }

        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                applyFilter();
            }
        });

        // Close modal when clicking outside
        document.getElementById('detailModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        function showDeleteModal(id) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteModal').classList.add('active');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
        }

        // Close delete modal when clicking outside
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });

        function showTab(tab) {
            // Get current URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            
            // Update tab parameter
            urlParams.set('tab', tab);
            
            // Keep the date range and search parameters if they exist
            const startDate = document.getElementById('start_date')?.value;
            const endDate = document.getElementById('end_date')?.value;
            const searchTerm = document.getElementById('searchInput')?.value;
            
            if (startDate) urlParams.set('start_date', startDate);
            if (endDate) urlParams.set('end_date', endDate);
            if (searchTerm) urlParams.set('search', searchTerm);
            
            // Redirect with updated parameters
            window.location.href = `informasi.php?${urlParams.toString()}`;
        }

        async function showProductHistory(productName) {
            try {
                const response = await fetch(`informasi.php?action=get_product_history&product=${encodeURIComponent(productName)}`);
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('productHistoryTitle').textContent = `Riwayat Penjualan: ${productName}`;
                    
                    const tbody = document.getElementById('productHistoryItems');
                    tbody.innerHTML = data.history.map((item, index) => `
                        <tr>
                            <td class="px-4 py-2 text-sm text-gray-600">${index + 1}</td>
                            <td class="px-4 py-2 text-sm text-gray-600">
                                ${new Date(item.tanggal).toLocaleString('id-ID', {
                                    day: '2-digit',
                                    month: '2-digit',
                                    year: 'numeric',
                                    hour: '2-digit',
                                    minute: '2-digit'
                                })}
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-600">${item.nama_pembeli || '-'}</td>
                            <td class="px-4 py-2 text-sm text-gray-600">${item.jumlah} Unit</td>
                            <td class="px-4 py-2 text-sm text-gray-600">Rp ${Number(item.harga).toLocaleString('id-ID')}</td>
                        </tr>
                    `).join('');
                    
                    document.getElementById('productHistoryModal').classList.add('active');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat memuat riwayat produk');
            }
        }
        
        function closeProductHistory() {
            document.getElementById('productHistoryModal').classList.remove('active');
        }
        
        // Close product history modal when clicking outside
        document.getElementById('productHistoryModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeProductHistory();
            }
        });

        async function showDaerahDetail(daerah) {
            try {
                const response = await fetch(`informasi.php?action=get_daerah_detail&daerah=${encodeURIComponent(daerah)}`);
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('modalDaerahTitle').textContent = `Detail Transaksi: ${daerah}`;
                    
                    const tbody = document.getElementById('daerahDetailContent');
                    tbody.innerHTML = data.details.map((item, index) => `
                        <tr class="hover:bg-gray-50/50">
                            <td class="px-4 py-3 text-sm text-gray-600">${index + 1}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center">
                                        <span class="text-sm font-medium text-blue-600">
                                            ${item.nama_pembeli ? item.nama_pembeli.charAt(0).toUpperCase() : '?'}
                                        </span>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-800">${item.nama_pembeli || 'Tanpa Nama'}</p>
                                        <p class="text-xs text-gray-500">${item.marketplace}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                ${new Date(item.tanggal).toLocaleString('id-ID', {
                                    day: '2-digit',
                                    month: '2-digit',
                                    year: 'numeric',
                                    hour: '2-digit',
                                    minute: '2-digit'
                                })}
                            </td>
                            <td class="px-4 py-3 text-sm font-medium text-gray-800">
                                Rp ${Number(item.total_harga).toLocaleString('id-ID')}
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 rounded-lg text-xs font-medium ${getMarketplaceColor(item.marketplace)}">
                                    ${item.marketplace}
                                </span>
                            </td>
                        </tr>
                    `).join('');
                    
                    document.getElementById('daerahDetailModal').classList.add('active');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat memuat detail daerah');
            }
        }

        function getMarketplaceColor(marketplace) {
            switch(marketplace.toLowerCase()) {
                case 'shopee':
                    return 'bg-orange-100 text-orange-700';
                case 'tokopedia':
                    return 'bg-green-100 text-green-700';
                case 'tiktok':
                    return 'bg-gray-100 text-gray-700';
                default:
                    return 'bg-blue-100 text-blue-700';
            }
        }

        function closeDaerahModal() {
            document.getElementById('daerahDetailModal').classList.remove('active');
        }

        // Close modal when clicking outside
        document.getElementById('daerahDetailModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDaerahModal();
            }
        });

        function editTransaction(id) {
            fetch(`informasi.php?action=get_transaction&id=${id}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('editTransactionId').value = data.id;
                    
                    // Format tanggal untuk input datetime-local
                    const tanggal = new Date(data.tanggal);
                    const formattedDate = tanggal.toISOString().slice(0, 16);
                    document.getElementById('editTanggal').value = formattedDate;
                    
                    document.getElementById('editMarketplace').value = data.marketplace;
                    document.getElementById('editTransactionModal').classList.remove('hidden');
                });
        }

        function closeEditModal() {
            document.getElementById('editTransactionModal').classList.add('hidden');
        }

        document.getElementById('editTransactionForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'edit_transaction');
            
            fetch('informasi.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    closeEditModal();
                    location.reload();
                } else {
                    alert('Gagal mengupdate transaksi');
                }
            });
        });
    </script>
</body>
</html> 