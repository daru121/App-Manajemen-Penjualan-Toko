<?php
require_once '../backend/check_session.php';
require_once '../backend/database.php';

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['action'])) {
        switch ($data['action']) {
            case 'cancel_transaction':
                // Hapus session cart
                unset($_SESSION['cart']);
                echo json_encode(['status' => 'success']);
                exit;
                break;
            
            // ... kode case lainnya ...
        }
    }
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'add_to_cart':
            try {
                $barang_id = $_POST['barang_id'];
                
                // Get product details with stock check
                $query = "SELECT b.*, COALESCE(s.jumlah, 0) as stok 
                         FROM barang b 
                         LEFT JOIN stok s ON b.id = s.barang_id 
                         WHERE b.id = ?";
                $stmt = $conn->prepare($query);
                $stmt->execute([$barang_id]);
                $product = $stmt->fetch();
                
                if (!$product) {
                    echo json_encode(['status' => 'error', 'message' => 'Produk tidak ditemukan']);
                    exit;
                }
                
                if ($product['stok'] < 1) {
                    echo json_encode(['status' => 'error', 'message' => 'Stok tidak mencukupi']);
                    exit;
                }
                
                // Initialize cart if not exists
                if (!isset($_SESSION['cart'])) {
                    $_SESSION['cart'] = [];
                }
                
                // Check if product already in cart
                $found = false;
                foreach ($_SESSION['cart'] as &$item) {
                    if ($item['id'] == $barang_id) {
                        if ($item['jumlah'] + 1 > $product['stok']) {
                            echo json_encode(['status' => 'error', 'message' => 'Stok tidak mencukupi']);
                            exit;
                        }
                        $item['jumlah']++;
                        $found = true;
                        break;
                    }
                }
                
                // Add new item to cart
                if (!$found) {
                    $_SESSION['cart'][] = [
                        'id' => $product['id'],
                        'nama_barang' => $product['nama_barang'],
                        'harga' => $product['harga'],
                        'jumlah' => 1
                    ];
                }
                
                echo json_encode(['status' => 'success']);
                exit;
                
            } catch (Exception $e) {
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
                exit;
            }
            break;

        case 'update_cart':
            $index = $_POST['index'];
            $quantity = $_POST['jumlah'];
            
            if (isset($_SESSION['cart'][$index])) {
                // Check stock availability
                $barang_id = $_SESSION['cart'][$index]['id'];
                $stmt = $conn->prepare("SELECT COALESCE(SUM(jumlah), 0) as stok FROM stok WHERE barang_id = ?");
                $stmt->execute([$barang_id]);
                $stok = $stmt->fetch()['stok'];
                
                if ($quantity > $stok) {
                    echo json_encode(['status' => 'error', 'message' => 'Stok tidak mencukupi']);
                    exit;
                }
                
                $_SESSION['cart'][$index]['jumlah'] = $quantity;
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Item tidak ditemukan']);
            }
            exit;
            break;

        case 'remove_from_cart':
            $index = $_POST['index'];
            if (isset($_SESSION['cart'][$index])) {
                array_splice($_SESSION['cart'], $index, 1);
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Item tidak ditemukan']);
            }
            exit;
            break;

        case 'process_payment':
            try {
                $buyer_name = $_POST['buyer_name'];
                $payment_amount = $_POST['payment_amount'];
                $total = $_POST['total'];
                $marketplace = $_POST['marketplace'];
                $kembalian = $payment_amount - $total;
                
                if ($kembalian < 0) {
                    echo json_encode(['status' => 'error', 'message' => 'Pembayaran kurang']);
                    exit;
                }
                
                $conn->beginTransaction();
                
                // Insert pembeli terlebih dahulu
                $stmt = $conn->prepare("INSERT INTO pembeli (nama) VALUES (?)");
                $stmt->execute([$buyer_name]);
                $pembeli_id = $conn->lastInsertId();
                
                // Insert transaksi
                $stmt = $conn->prepare("INSERT INTO transaksi (user_id, pembeli_id, total_harga, pembayaran, kembalian, tanggal) 
                                        VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
                $stmt->execute([$_SESSION['user_id'], $pembeli_id, $total, $payment_amount, $kembalian]);
                $transaksi_id = $conn->lastInsertId();
                
                // Insert detail transaksi and update stock
                foreach ($_SESSION['cart'] as $item) {
                    // Insert detail
                    $stmt = $conn->prepare("INSERT INTO detail_transaksi (transaksi_id, barang_id, jumlah, harga) 
                                           VALUES (?, ?, ?, ?)");
                    $stmt->execute([$transaksi_id, $item['id'], $item['jumlah'], $item['harga']]);
                    
                    // Update stock
                    $stmt = $conn->prepare("UPDATE stok SET jumlah = jumlah - ? WHERE barang_id = ? AND jumlah >= ?");
                    $stmt->execute([$item['jumlah'], $item['id'], $item['jumlah']]);
                    
                    if ($stmt->rowCount() === 0) {
                        throw new Exception('Stok tidak mencukupi untuk ' . $item['nama_barang']);
                    }
                }
                
                $conn->commit();
                
                // Clear cart
                unset($_SESSION['cart']);
                
                echo json_encode([
                    'status' => 'success',
                    'transaksi_id' => $transaksi_id,
                    'kembalian' => $kembalian
                ]);
                
            } catch (Exception $e) {
                $conn->rollBack();
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            }
            exit;
            break;

        case 'search':
            try {
                $keyword = strtolower($_POST['keyword']);
                
                $query = "SELECT b.*, k.nama_kategori, COALESCE(s.jumlah, 0) as stok 
                         FROM barang b 
                         LEFT JOIN kategori k ON b.kategori_id = k.id 
                         LEFT JOIN stok s ON b.id = s.barang_id 
                         WHERE LOWER(b.nama_barang) LIKE :keyword 
                            OR LOWER(k.nama_kategori) LIKE :keyword 
                         ORDER BY b.nama_barang ASC";
                
                $stmt = $conn->prepare($query);
                $stmt->execute(['keyword' => "%{$keyword}%"]);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['status' => 'success', 'results' => $results]);
            } catch (Exception $e) {
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            }
            exit;
            break;
    }
}

// Initialize variables
$totalItems = 0;
$grandTotal = 0;
$products = [];

// Pagination setup
$itemsPerPage = 9;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($currentPage - 1) * $itemsPerPage;

try {
    // Get total products count first
    $countQuery = "SELECT COUNT(*) as total FROM barang";
    $countStmt = $conn->query($countQuery);
    $totalProducts = $countStmt->fetch()['total'];
    $totalPages = ceil($totalProducts / $itemsPerPage);

    // Get products with pagination
    $query = "SELECT b.*, k.nama_kategori, 
             COALESCE((SELECT SUM(jumlah) FROM stok WHERE barang_id = b.id), 0) as total_stok 
             FROM barang b 
             LEFT JOIN kategori k ON b.kategori_id = k.id 
             ORDER BY b.id 
             LIMIT ? OFFSET ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bindValue(1, $itemsPerPage, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate cart totals
    if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $item) {
            $totalItems += $item['jumlah'];
            $grandTotal += ($item['harga'] * $item['jumlah']);
        }
    }

} catch (PDOException $e) {
    error_log($e->getMessage());
    $products = [];
    $totalProducts = 0;
    $totalPages = 1;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS Kasir - PAksesories</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
    <style>
        /* Base modal styles */
        .modal-container {
            perspective: 1000px;
        }

        .modal-content {
            backface-visibility: hidden;
            transform-style: preserve-3d;
        }

        /* Enhanced Modal Animations */
        .modal-enter {
            animation: modalEnterGopay 0.6s cubic-bezier(0.33, 1, 0.68, 1);
        }

        .modal-leave {
            animation: modalLeaveGopay 0.5s cubic-bezier(0.33, 1, 0.68, 1);
        }

        @keyframes modalEnterGopay {
            0% {
                opacity: 0;
                transform: scale(0.94) translateY(30px);
            }
            40% {
                opacity: 1;
            }
            70% {
                transform: scale(1.02) translateY(-4px);
            }
            85% {
                transform: scale(0.99) translateY(2px);
            }
            100% {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        @keyframes modalLeaveGopay {
            0% {
                opacity: 1;
                transform: scale(1);
            }
            100% {
                opacity: 0;
                transform: scale(0.95) translateY(20px);
            }
        }

        /* Success Icon Animation */
        .success-icon {
            animation: successIconEnter 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
        }

        @keyframes successIconEnter {
            0% {
                opacity: 0;
                transform: scale(0.5);
            }
            50% {
                transform: scale(1.2);
            }
            70% {
                transform: scale(0.9);
            }
            100% {
                opacity: 1;
                transform: scale(1);
            }
        }

        /* Success Checkmark Animation */
        .checkmark-path {
            stroke-dasharray: 100;
            stroke-dashoffset: 100;
            animation: drawCheck 0.6s ease-in-out forwards 0.4s;
        }

        @keyframes drawCheck {
            from {
                stroke-dashoffset: 100;
            }
            to {
                stroke-dashoffset: 0;
            }
        }

        /* Detail Items Animation */
        .detail-item {
            opacity: 0;
            transform: translateY(10px);
            transition: opacity 0.3s ease, transform 0.3s ease;
        }

        .detail-item-enter {
            animation: detailItemEnter 0.5s cubic-bezier(0.33, 1, 0.68, 1) forwards;
        }

        @keyframes detailItemEnter {
            0% {
                opacity: 0;
                transform: translateY(10px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Improved Backdrop Animation */
        .backdrop-enter {
            animation: backdropEnter 0.4s ease-out forwards;
        }

        @keyframes backdropEnter {
            from {
                opacity: 0;
                backdrop-filter: blur(0px);
            }
            to {
                opacity: 1;
                backdrop-filter: blur(2px);
            }
        }

        /* Tambahkan transition untuk opacity */
        .modal-content {
            transition: opacity 0.3s ease;
        }

        /* Pastikan text visible */
        .detail-item span {
            opacity: 1 !important;
            color: inherit;
        }

        /* Custom Select Styling */
        select {
            background-image: none !important;
        }
        
        select option {
            padding: 0.5rem 1rem;
        }
        
        /* Hover effect untuk options */
        select option:hover,
        select option:focus {
            background-color: #EFF6FF;
            color: #2563EB;
        }

        .detail-item {
            transition: opacity 0.3s ease-in-out;
        }
        
        .detail-item-enter {
            animation: slideIn 0.5s ease-out forwards;
        }
        
        @keyframes slideIn {
            from {
                transform: translateY(10px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* Animasi untuk alert modal */
        #alertContent {
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        
        .scale-95 {
            transform: scale(0.95);
            opacity: 0;
        }
        
        .scale-100 {
            transform: scale(1);
            opacity: 1;
        }
        
        .opacity-0 {
            opacity: 0;
        }
        
        .opacity-100 {
            opacity: 1;
        }

        /* Tambahkan animasi bounce yang lebih lembut */
        @keyframes bounce-gentle {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-5px);
            }
        }

        .animate-bounce-gentle {
            animation: bounce-gentle 2s infinite ease-in-out;
        }

        /* Tambahkan efek glass morphism */
        .backdrop-blur-md {
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }
    </style>
</head>
<body class="bg-gray-50">
    <div id="backdrop" class="fixed inset-0 bg-black/40 backdrop-blur-[2px] z-40 hidden"></div>
    <?php include '../components/sidebar.php'; ?>
    <?php include '../components/navbar.php'; ?>

    <div class="lg:pl-72 pr-4 pt-20">
        <!-- Header Section -->
        <div class="max-w-7xl mx-auto mb-8">
            <div class="bg-blue-600 rounded-3xl px-8 py-6 text-white relative overflow-hidden">
                <!-- Decorative elements -->
                
                <div class="relative flex justify-between items-center">
                    <div>
                        <h1 class="text-2xl font-bold mb-1">Point of Sales</h1>
                        <p class="text-blue-100">Kelola transaksi penjualan dengan mudah</p>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="px-3 py-1.5 bg-white/10 backdrop-blur-sm rounded-lg">
                            <div class="text-sm text-blue-100">Total Transaksi Hari Ini</div>
                            <div class="text-xl font-bold">
                                <?php
                                $today = date('Y-m-d');
                                $query = "SELECT COUNT(*) as total FROM transaksi WHERE DATE(tanggal) = ?";
                                $stmt = $conn->prepare($query);
                                $stmt->execute([$today]);
                                $result = $stmt->fetch();
                                echo $result['total'];
                                ?>
                            </div>
                        </div>
                        <div class="px-3 py-1.5 bg-white/10 backdrop-blur-sm rounded-lg">
                            <div class="text-sm text-blue-100">Total Pendapatan Hari Ini</div>
                            <div class="text-xl font-bold">
                                <?php
                                $query = "SELECT SUM(total_harga) as total FROM transaksi WHERE DATE(tanggal) = ?";
                                $stmt = $conn->prepare($query);
                                $stmt->execute([$today]);
                                $result = $stmt->fetch();
                                echo 'Rp ' . number_format($result['total'] ?? 0, 0, ',', '.');
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Update the main content wrapper -->
        <div class="max-w-7xl mx-auto px-4">
            <div class="grid grid-cols-12 gap-8">
                <!-- Left Side - Kasir -->
                <div class="col-span-5">
                    <div class="bg-white/80 backdrop-blur-sm rounded-3xl shadow-lg border border-gray-100 sticky top-24">
                        <div class="p-6">
                            <div class="flex items-center justify-between mb-6">
                                <h2 class="text-xl font-semibold bg-gradient-to-r from-gray-800 to-gray-600 bg-clip-text text-transparent">
                                    Kasir
                                </h2>
                                <div class="px-3 py-1 bg-gray-50 rounded-xl text-sm text-gray-500">
                                    <?= date('d/m/Y') ?>
                                </div>
                            </div>

                            <!-- Cart Items dengan animasi -->
                            <?php if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])): ?>
                                <div class="flex flex-col items-center justify-center py-12">
                                    <div class="w-32 h-32 text-gray-200 mb-4 animate-pulse">
                                        <svg class="w-full h-full" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" 
                                                  d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                                        </svg>
                                    </div>
                                    <p class="text-gray-400 text-center">Keranjang kosong</p>
                                </div>
                            <?php else: ?>
                                <!-- Cart Items List dengan hover effect -->
                                <div class="space-y-3 mb-6">
                                    <?php foreach ($_SESSION['cart'] as $index => $item): ?>
                                        <div class="group p-4 bg-gray-50/50 rounded-2xl hover:bg-gray-50 transition-all duration-300">
                                            <div class="flex justify-between items-center">
                                                <div>
                                                    <h4 class="font-medium text-gray-800"><?= htmlspecialchars($item['nama_barang']) ?></h4>
                                                    <p class="text-sm text-gray-500">Rp <?= number_format($item['harga'], 0, ',', '.') ?></p>
                                                </div>
                                                <div class="flex items-center gap-3">
                                                    <div class="flex items-center bg-white rounded-xl border border-gray-100 overflow-hidden">
                                                        <button onclick="updateQuantity(<?= $index ?>, <?= $item['jumlah'] - 1 ?>)"
                                                                class="p-2 hover:bg-gray-50 text-gray-400 hover:text-gray-600 transition-colors">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/>
                                                            </svg>
                                                        </button>
                                                        <span class="w-8 text-center text-sm font-medium text-gray-600">
                                                            <?= $item['jumlah'] ?>
                                                        </span>
                                                        <button onclick="updateQuantity(<?= $index ?>, <?= $item['jumlah'] + 1 ?>)"
                                                                class="p-2 hover:bg-gray-50 text-gray-400 hover:text-gray-600 transition-colors">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6"/>
                                                            </svg>
                                                        </button>
                                                    </div>
                                                    <button onclick="removeFromCart(<?= $index ?>)"
                                                            class="p-2 text-red-400 hover:text-red-500 hover:bg-red-50 rounded-xl transition-colors">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" 
                                                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Payment Section -->
                                <div class="border-t border-gray-100 pt-6 space-y-4">
                                    <div class="flex justify-between items-center">
                                        <span class="text-gray-600">Total Items</span>
                                        <span class="font-medium text-gray-800"><?= $totalItems ?> items</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-gray-600">Total</span>
                                        <span class="text-lg font-semibold text-gray-800">
                                            Rp <?= number_format($grandTotal, 0, ',', '.') ?>
                                        </span>
                                    </div>
                                    
                                    <!-- Input Fields dengan animasi focus -->
                                    <div class="space-y-3">
                                        <input type="text" 
                                               id="buyerName"
                                               placeholder="Nama Pembeli"
                                               class="w-full px-4 py-3 bg-gray-50/50 border border-gray-100 rounded-xl
                                                      focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20
                                                      transition-all duration-300 outline-none">
                                              
                                        <div class="relative">
                                            <input type="number" 
                                                   id="paymentAmount"
                                                   placeholder="Jumlah Pembayaran"
                                                   class="w-full px-4 py-3 bg-gray-50/50 border border-gray-100 rounded-xl
                                                          focus:bg-white focus:border-green-500 focus:ring-2 focus:ring-green-500/20
                                                          transition-all duration-300 outline-none">
                                            <div class="absolute right-0 top-0 h-full flex items-center pr-4">
                                                <span class="text-sm font-medium text-gray-400">Rp</span>
                                            </div>
                                        </div>

                                        <!-- Marketplace Select -->
                                        <div class="relative">
                                            <select id="marketplace" 
                                                    class="w-full px-4 py-3 bg-gray-50/50 border border-gray-100 rounded-xl
                                                           appearance-none cursor-pointer
                                                           focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20
                                                           transition-all duration-300 outline-none">
                                                <option value="" selected disabled>Pilih Marketplace</option>
                                                <option value="offline" class="flex items-center">
                                                    offline
                                                </option>
                                                <option value="shopee" class="flex items-center">
                                                    shopee
                                                </option>
                                                <option value="tokopedia">
                                                    tokopedia
                                                </option>
                                                <option value="tiktok">
                                                    tiktok
                                                </option>
                                            </select>
                                            <div class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none">
                                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                                </svg>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Kembalian Display -->
                                    <div class="p-4 bg-gray-50/50 rounded-2xl">
                                        <div class="flex justify-between items-center">
                                            <span class="text-gray-600">Kembalian</span>
                                            <span id="changeAmount" class="text-lg font-semibold text-green-500">
                                                Rp 0
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Tombol Aksi -->
                                    <div class="grid grid-cols-2 gap-4">
                                        <button onclick="cancelTransaction()" 
                                                class="w-full px-6 py-3 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 
                                                       transition-all duration-200 hover:shadow-lg hover:shadow-gray-200/50">
                                            Batal
                                        </button>
                                        <button onclick="processPayment()" 
                                                class="w-full px-6 py-3 bg-blue-500 text-white rounded-xl hover:bg-blue-600 
                                                       transition-all duration-200 hover:shadow-lg hover:shadow-blue-500/50">
                                            Bayar
                                        </button>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Right Side - Products -->
                <div class="col-span-7">
                    <div class="bg-white/80 backdrop-blur-sm rounded-3xl shadow-lg border border-gray-100">
                        <div class="p-6">
                            <!-- Header with Search -->
                            <div class="flex items-center justify-between mb-6">
                                <h2 class="text-xl font-semibold bg-gradient-to-r from-gray-800 to-gray-600 bg-clip-text text-transparent">
                                    List Produk
                                </h2>
                                
                                <!-- Search Box -->
                                <div class="relative">
                                    <input type="text" 
                                           id="searchInput"
                                           placeholder="Cari produk..." 
                                           class="w-72 pl-12 pr-4 py-2.5 bg-gray-50/50 border border-gray-200 rounded-xl text-sm 
                                                  focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition-all">
                                    <div class="absolute left-4 top-2.5 text-gray-400">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Product Grid -->
                            <div id="productContainer" class="grid grid-cols-3 gap-6 mb-6">
                                <?php if (empty($products)): ?>
                                    <div class="col-span-3 text-center py-8">
                                        <p class="text-gray-500">Tidak ada produk tersedia</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($products as $product): ?>
                                        <div class="group bg-gray-50/50 rounded-2xl overflow-hidden hover:shadow-lg transition-all duration-300">
                                            <!-- Product Image -->
                                            <div class="aspect-square bg-gray-100 relative overflow-hidden">
                                                <?php if (!empty($product['gambar'])): ?>
                                                    <img src="../uploads/<?= htmlspecialchars($product['gambar']) ?>" 
                                                         alt="<?= htmlspecialchars($product['nama_barang']) ?>"
                                                         class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
                                                <?php else: ?>
                                                    <div class="w-full h-full flex items-center justify-center text-gray-400">
                                                        <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" 
                                                                  d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                        </svg>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Product Info -->
                                            <div class="p-4">
                                                <h3 class="font-medium text-gray-800">
                                                    <?= htmlspecialchars($product['nama_barang']) ?>
                                                </h3>
                                                <p class="text-sm text-gray-500 mb-3">
                                                    <?= htmlspecialchars($product['nama_kategori']) ?>
                                                </p>
                                                <div class="flex items-center justify-between">
                                                    <span class="text-sm font-medium text-gray-800">
                                                        Rp <?= number_format($product['harga'], 0, ',', '.') ?>
                                                    </span>
                                                    <div class="flex items-center gap-2">
                                                        <span class="px-2 py-1 text-xs font-medium rounded-lg
                                                                   <?= ($product['total_stok'] > 0) ? 
                                                                       'text-green-700 bg-green-100' : 
                                                                       'text-red-700 bg-red-100' ?>">
                                                            Stok: <?= (int)$product['total_stok'] ?>
                                                        </span>
                                                        <?php if ($product['total_stok'] > 0): ?>
                                                            <button onclick="addToCart(<?= $product['id'] ?>)"
                                                                    class="p-2 text-blue-500 hover:bg-blue-50 rounded-lg transition-colors">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                                          d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                                                </svg>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <!-- Pagination -->
                            <?php if ($totalPages > 1): ?>
                                <div class="flex items-center justify-between border-t border-gray-100 pt-4">
                                    <div class="text-sm text-gray-500">
                                        Showing <?= ($offset + 1) ?> to <?= min($offset + $itemsPerPage, $totalProducts) ?> of <?= $totalProducts ?> entries
                                    </div>
                                    
                                    <div class="flex items-center gap-2">
                                        <?php if ($currentPage > 1): ?>
                                            <a href="?page=<?= ($currentPage - 1) ?>" 
                                               class="px-3 py-1 bg-white border border-gray-200 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition-colors">
                                                Previous
                                            </a>
                                        <?php endif; ?>

                                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                            <a href="?page=<?= $i ?>" 
                                               class="px-3 py-1 <?= $i === $currentPage ? 
                                                        'bg-blue-600 text-white' : 
                                                        'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' ?> 
                                                      rounded-lg text-sm transition-colors">
                                                <?= $i ?>
                                            </a>
                                        <?php endfor; ?>

                                        <?php if ($currentPage < $totalPages): ?>
                                            <a href="?page=<?= ($currentPage + 1) ?>" 
                                               class="px-3 py-1 bg-white border border-gray-200 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition-colors">
                                                Next
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Konfirmasi -->
    <div id="confirmationModal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black/30 backdrop-blur-sm"></div>
        <div class="relative z-50 min-h-screen flex items-center justify-center p-4">
            <div class="bg-white rounded-3xl shadow-2xl p-6 w-[480px] mx-auto">
                <h3 class="text-2xl font-bold text-gray-800 mb-6">Konfirmasi Pembayaran</h3>
                <p class="text-gray-500 mb-6">Periksa kembali detail transaksi</p>

                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Nama Pembeli</span>
                        <span id="confirm-buyer-name" class="font-medium"></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Total Items</span>
                        <span id="confirm-total-items" class="font-medium"></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Total Belanja</span>
                        <span id="confirm-total" class="font-medium"></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Pembayaran</span>
                        <span id="confirm-payment" class="font-medium text-green-600"></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Kembalian</span>
                        <span id="confirm-change" class="font-medium text-blue-600"></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Marketplace</span>
                        <span id="confirm-marketplace" class="font-medium"></span>
                    </div>
                </div>

                <!-- Buttons -->
                <div class="flex gap-3 mt-6">
                    <button onclick="hideConfirmModal()" 
                            class="flex-1 px-6 py-3 bg-white border-2 border-gray-200 text-gray-700 rounded-xl hover:bg-gray-50 hover:border-gray-300 transition-all duration-200 font-medium">
                        Batal
                    </button>
                    <button onclick="executePayment()" 
                            class="flex-1 px-6 py-3 bg-blue-500 text-white rounded-xl hover:bg-blue-600 transform hover:scale-[1.02] transition-all duration-200 font-medium">
                        Konfirmasi Pembayaran
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Sukses -->
    <div id="successModal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black/30 backdrop-blur-sm"></div>
        <div class="relative z-50 min-h-screen flex items-center justify-center p-4">
            <div class="bg-white rounded-3xl shadow-2xl p-6 w-[480px] mx-auto">
                <div class="text-center mb-6">
                    <div class="w-20 h-20 bg-green-50 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-10 h-10 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800">Pembayaran Berhasil!</h3>
                    <p class="text-sm text-gray-500 mt-1" id="success-date"></p>
                </div>

                <div class="bg-gray-50 rounded-2xl p-4 space-y-3">
                    <div class="detail-item flex justify-between items-center">
                        <span class="text-gray-600">No. Transaksi</span>
                        <span id="success-transaction-id" class="font-medium"></span>
                    </div>
                    <div class="detail-item flex justify-between items-center">
                        <span class="text-gray-600">Nama Pembeli</span>
                        <span id="success-buyer-name" class="font-medium"></span>
                    </div>
                    <div class="detail-item flex justify-between items-center">
                        <span class="text-gray-600">Total Belanja</span>
                        <span id="success-total" class="font-medium"></span>
                    </div>
                    <div class="detail-item flex justify-between items-center">
                        <span class="text-gray-600">Pembayaran</span>
                        <span id="success-payment" class="font-medium text-green-600"></span>
                    </div>
                    <div class="detail-item flex justify-between items-center">
                        <span class="text-gray-600">Kembalian</span>
                        <span id="success-change" class="font-medium text-blue-600"></span>
                    </div>
                    <div class="detail-item flex justify-between items-center">
                        <span class="text-gray-600">Marketplace</span>
                        <span id="success-marketplace" class="font-medium"></span>
                    </div>
                </div>

                <button onclick="closeSuccessModal()" 
                        class="w-full px-6 py-3 bg-green-500 text-white rounded-xl mt-6
                               hover:bg-green-600 transition-all duration-200">
                    Selesai
                </button>
            </div>
        </div>
    </div>

    <!-- Alert Modal -->
    <div id="alertModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center">
        <div class="bg-white/80 backdrop-blur-md rounded-2xl shadow-2xl w-[400px] overflow-hidden transform transition-all scale-95 opacity-0" 
             id="alertContent">
            <div class="p-6">
                <div class="flex items-center justify-center mb-6">
                    <div class="w-16 h-16 rounded-full bg-gradient-to-br from-yellow-400 to-orange-500 
                                flex items-center justify-center shadow-lg shadow-yellow-500/30 
                                animate-bounce-gentle">
                        <svg class="w-8 h-8 text-white drop-shadow-md" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                             <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                   d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                         </svg>
                     </div>
                 </div>
                 <h3 class="text-xl font-semibold text-center mb-3 text-gray-800">Peringatan</h3>
                 <p class="text-gray-600 text-center mb-8 text-base leading-relaxed" id="alertMessage"></p>
                 <div class="flex justify-center px-4">
                     <button onclick="hideAlert()" 
                             class="w-full px-6 py-3 bg-gradient-to-r from-gray-600 to-gray-700 text-white rounded-xl
                                    hover:from-gray-700 hover:to-gray-800 transform hover:scale-[1.02] 
                                    transition-all duration-200 shadow-lg shadow-gray-500/25
                                    font-medium text-sm">
                         OK
                     </button>
                 </div>
             </div>
         </div>
     </div>

    <script>
    function addToCart(barangId) {
        fetch('penjualan.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=add_to_cart&barang_id=${barangId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                location.reload();
            } else {
                alert(data.message);
            }
        });
    }

    function updateQuantity(index, quantity) {
        if (quantity < 1) return;
        
        fetch('penjualan.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=update_cart&index=${index}&jumlah=${quantity}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                location.reload();
            }
        });
    }

    function removeFromCart(index) {
        fetch('penjualan.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=remove_from_cart&index=${index}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                location.reload();
            }
        });
    }

    function calculateChange() {
        const total = <?= $grandTotal ?>;
        const payment = parseFloat(document.getElementById('paymentAmount').value) || 0;
        const change = payment - total;
        
        document.getElementById('changeAmount').textContent = 
            `Rp ${Math.max(0, change).toLocaleString('id-ID')}`;
    }

    function processPayment() {
        const namaPembeli = document.getElementById('buyerName').value;
        const jumlahBayar = parseFloat(document.getElementById('paymentAmount').value) || 0;
        const marketplace = document.getElementById('marketplace').value.toLowerCase() || 'offline';
        const total = <?= $grandTotal ?>;

        if (!namaPembeli) {
            showAlert('Nama pembeli harus diisi!');
            return;
        }

        if (jumlahBayar < total) {
            showAlert('Jumlah pembayaran kurang!');
            return;
        }

        if (!marketplace) {
            showAlert('Pilih marketplace terlebih dahulu!');
            return;
        }

        // Update modal konfirmasi
        document.getElementById('confirm-buyer-name').textContent = namaPembeli;
        document.getElementById('confirm-total-items').textContent = '<?= $totalItems ?> items';
        document.getElementById('confirm-total').textContent = `Rp ${total.toLocaleString('id-ID')}`;
        document.getElementById('confirm-payment').textContent = `Rp ${jumlahBayar.toLocaleString('id-ID')}`;
        document.getElementById('confirm-change').textContent = `Rp ${(jumlahBayar - total).toLocaleString('id-ID')}`;
        document.getElementById('confirm-marketplace').textContent = marketplace.charAt(0).toUpperCase() + marketplace.slice(1);

        showConfirmModal();
    }

    function showConfirmModal() {
        const modal = document.getElementById('confirmationModal');
        const overlay = modal.querySelector('.absolute');
        const content = modal.querySelector('.bg-white');
        const backdrop = document.getElementById('backdrop');
        
        backdrop.classList.remove('hidden');
        backdrop.style.opacity = '0';
        
        requestAnimationFrame(() => {
            backdrop.style.opacity = '1';
            modal.classList.remove('hidden');
            overlay.classList.add('overlay-enter');
            content.classList.add('modal-enter');
        });
        
        document.body.style.overflow = 'hidden';
    }

    function hideConfirmModal() {
        const modal = document.getElementById('confirmationModal');
        const overlay = modal.querySelector('.absolute');
        const content = modal.querySelector('.bg-white');
        const backdrop = document.getElementById('backdrop');
        
        overlay.classList.add('overlay-leave');
        content.classList.add('modal-leave');
        backdrop.style.opacity = '0';
        
        setTimeout(() => {
            modal.classList.add('hidden');
            overlay.classList.remove('overlay-leave');
            content.classList.remove('modal-leave');
            backdrop.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }, 300);
    }

    function executePayment() {
        const namaPembeli = document.getElementById('buyerName').value;
        const jumlahBayar = parseFloat(document.getElementById('paymentAmount').value) || 0;
        const marketplace = document.getElementById('marketplace').value.toLowerCase() || 'offline';
        const total = <?= $grandTotal ?>;

        hideConfirmModal();

        fetch('penjualan.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=process_payment&buyer_name=${encodeURIComponent(namaPembeli)}&payment_amount=${jumlahBayar}&total=${total}&marketplace=${marketplace}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                // Update success modal content
                document.getElementById('success-date').textContent = new Date().toLocaleDateString('id-ID', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
                document.getElementById('success-transaction-id').textContent = `#${data.transaksi_id}`;
                document.getElementById('success-buyer-name').textContent = namaPembeli;
                document.getElementById('success-total').textContent = `Rp ${total.toLocaleString('id-ID')}`;
                document.getElementById('success-payment').textContent = `Rp ${jumlahBayar.toLocaleString('id-ID')}`;
                document.getElementById('success-change').textContent = `Rp ${data.kembalian.toLocaleString('id-ID')}`;
                document.getElementById('success-marketplace').textContent = marketplace.charAt(0).toUpperCase() + marketplace.slice(1);
                showSuccessModal();
            } else {
                alert(data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Terjadi kesalahan saat memproses pembayaran');
        });
    }

    function showSuccessModal() {
        const modal = document.getElementById('successModal');
        const detailItems = modal.querySelectorAll('.detail-item');
        
        // Tampilkan modal terlebih dahulu
        modal.classList.remove('hidden');
        
        // Reset opacity untuk animasi baru
        detailItems.forEach(item => {
            item.style.opacity = '0';
            item.classList.remove('detail-item-enter');
        });
        
        // Animate items dengan delay
        detailItems.forEach((item, index) => {
            setTimeout(() => {
                item.style.opacity = '1';
                item.classList.add('detail-item-enter');
            }, index * 100);
        });
    }

    function closeSuccessModal() {
        const modal = document.getElementById('successModal');
        const content = modal.querySelector('.bg-white');
        
        setTimeout(() => {
            modal.classList.add('hidden');
            location.reload();
        }, 300);
    }

    // Update event listener untuk close modal saat klik outside
    document.addEventListener('click', function(e) {
        const confirmationModal = document.getElementById('confirmationModal');
        const successModal = document.getElementById('successModal');
        const backdrop = document.getElementById('backdrop');
        
        if (e.target === confirmationModal || e.target.classList.contains('absolute')) {
            hideConfirmModal();
            backdrop.classList.add('hidden');
        }
        if (e.target === successModal || e.target.classList.contains('absolute')) {
            closeSuccessModal();
            backdrop.classList.add('hidden');
        }
    });

    // Auto calculate change when payment amount changes
    document.getElementById('paymentAmount')?.addEventListener('input', calculateChange);

    // Fungsi pencarian dengan debounce
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Fungsi untuk melakukan pencarian
    function searchProducts(e) {
        const keyword = e.target.value.trim();
        
        // Jika keyword kosong, tampilkan semua produk
        if (!keyword) {
            window.location.reload();
            return;
        }
        
        fetch('penjualan.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=search&keyword=${encodeURIComponent(keyword)}`
        })
        .then(response => response.json())
        .then(data => {
            // Target container produk yang benar
            const productContainer = document.querySelector('#productContainer');
            
            if (data.results && data.results.length > 0) {
                let productsHTML = '';
                data.results.forEach(product => {
                    productsHTML += `
                        <div class="product-item group bg-gray-50/50 rounded-2xl overflow-hidden hover:shadow-lg transition-all duration-300">
                            <div class="aspect-square bg-gray-100 relative overflow-hidden">
                                ${product.gambar ? 
                                    `<img src="../uploads/${product.gambar}" 
                                          alt="${product.nama_barang}"
                                          class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">` :
                                    `<div class="w-full h-full flex items-center justify-center text-gray-400">
                                        <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" 
                                                  d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                    </div>`
                                }
                            </div>
                            <div class="p-4">
                                <h3 class="font-medium text-gray-800">${product.nama_barang}</h3>
                                <p class="text-sm text-gray-500 mb-3">${product.nama_kategori}</p>
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-lg font-semibold text-blue-600">
                                            Rp ${parseInt(product.harga).toLocaleString('id-ID')}
                                        </p>
                                        <p class="text-sm text-green-600">Stok: ${product.stok}</p>
                                    </div>
                                    <button onclick="addToCart(${product.id}))" 
                                            class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                  d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                });
                
                productContainer.innerHTML = productsHTML;
            } else {
                productContainer.innerHTML = `
                    <div class="col-span-3 text-center py-8">
                        <p class="text-gray-500">Tidak ada produk yang sesuai dengan pencarian</p>
                    </div>
                `;
            }
        });
    }

    // Inisialisasi pencarian dengan debounce
    const debouncedSearch = debounce(searchProducts, 300);
    document.getElementById('searchInput').addEventListener('input', debouncedSearch);

    function showAlert(message) {
        document.getElementById('alertMessage').textContent = message;
        const alertModal = document.getElementById('alertModal');
        const alertContent = document.getElementById('alertContent');
        
        alertModal.classList.remove('hidden');
        // Trigger animation
        setTimeout(() => {
            alertContent.classList.remove('scale-95', 'opacity-0');
            alertContent.classList.add('scale-100', 'opacity-100');
        }, 10);
    }
    
    function hideAlert() {
        const alertModal = document.getElementById('alertModal');
        const alertContent = document.getElementById('alertContent');
        
        // Reverse animation
        alertContent.classList.add('scale-95', 'opacity-0');
        alertContent.classList.remove('scale-100', 'opacity-100');
        
        setTimeout(() => {
            alertModal.classList.add('hidden');
        }, 200);
    }

    // Fungsi untuk membatalkan transaksi
    function cancelTransaction() {
        showConfirmCancelModal();
    }

    // Tambahkan modal konfirmasi pembatalan
    function showConfirmCancelModal() {
        const message = 'Apakah Anda yakin ingin membatalkan transaksi ini? Semua item akan dihapus.';
        
        // Buat dan tampilkan modal konfirmasi custom
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center';
        modal.innerHTML = `
            <div class="bg-white/80 backdrop-blur-md rounded-2xl shadow-2xl w-[400px] overflow-hidden transform transition-all scale-95 opacity-0"
                 id="cancelConfirmContent">
                <div class="p-6">
                    <div class="flex items-center justify-center mb-6">
                        <div class="w-16 h-16 rounded-full bg-gradient-to-br from-red-400 to-red-500 
                                  flex items-center justify-center shadow-lg shadow-red-500/30">
                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </div>
                    </div>
                    <h3 class="text-xl font-semibold text-center mb-3 text-gray-800">Konfirmasi Pembatalan</h3>
                    <p class="text-gray-600 text-center mb-8">${message}</p>
                    <div class="flex justify-center gap-3">
                        <button onclick="hideCancelModal(this.closest('.fixed'))" 
                                class="px-6 py-2 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 transition-all duration-200">
                            Tidak
                        </button>
                        <button onclick="confirmCancel(this.closest('.fixed'))" 
                                class="px-6 py-2 bg-red-500 text-white rounded-xl hover:bg-red-600 transition-all duration-200">
                            Ya, Batalkan
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Animasi tampil
        setTimeout(() => {
            const content = modal.querySelector('#cancelConfirmContent');
            content.classList.remove('scale-95', 'opacity-0');
            content.classList.add('scale-100', 'opacity-100');
        }, 10);
    }
    
    function hideCancelModal(modal) {
        const content = modal.querySelector('#cancelConfirmContent');
        content.classList.add('scale-95', 'opacity-0');
        content.classList.remove('scale-100', 'opacity-100');
        
        setTimeout(() => {
            modal.remove();
        }, 200);
    }
    
    function confirmCancel(modal) {
        // Hapus session cart
        fetch('penjualan.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'cancel_transaction'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                // Redirect atau reload halaman
                window.location.reload();
            }
        });
    }
    </script>
</body>
</html>
?>

