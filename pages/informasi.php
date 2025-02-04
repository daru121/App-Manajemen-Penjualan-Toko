<?php
require_once '../backend/check_session.php';
require_once '../backend/database.php';
// Set timezone di awal file
date_default_timezone_set('Asia/Makassar'); // Set timezone ke WITA

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
    } catch (PDOException $e) {
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
            t.marketplace,
            p.nama as buyer_name,
            u.nama as nama_kasir
        FROM transaksi t
        LEFT JOIN pembeli p ON t.pembeli_id = p.id
        LEFT JOIN users u ON t.user_id = u.id
        WHERE t.id = ?";

        $stmt = $conn->prepare($query);
        $stmt->execute([$id]);
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($transaction) {
            // Get items details
            $query = "SELECT 
                b.nama_barang,
                dt.jumlah,
                dt.harga,
                (dt.jumlah * dt.harga) as subtotal,
                ((dt.harga - b.harga_modal) * dt.jumlah) as profit
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
    } catch (PDOException $e) {
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
    } catch (PDOException $e) {
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
    } catch (PDOException $e) {
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
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// Tambahkan handler untuk get_transaction
if (isset($_GET['action']) && $_GET['action'] === 'get_transaction') {
    header('Content-Type: application/json');
    try {
        $id = $_GET['id'];
        $stmt = $conn->prepare("SELECT t.*, p.nama as nama_pembeli, u.nama as nama_kasir 
                               FROM transaksi t 
                               LEFT JOIN pembeli p ON t.pembeli_id = p.id 
                               LEFT JOIN users u ON t.user_id = u.id 
                               WHERE t.id = ?");
        $stmt->execute([$id]);
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode($transaction);
        exit;
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

// Tambahkan di bagian awal file
if (isset($_GET['action']) && $_GET['action'] === 'get_pembeli_detail') {
    header('Content-Type: application/json');
    try {
        $nama_pembeli = $_GET['nama'];

        $query = "SELECT 
                    t.id,
                    t.tanggal,
                    t.total_harga,
                    t.marketplace,
                    t.daerah,  -- Tambahkan kolom daerah
                    GROUP_CONCAT(CONCAT(b.nama_barang, ' (', dt.jumlah, ')') SEPARATOR ', ') as detail_produk
                 FROM transaksi t
                 JOIN detail_transaksi dt ON t.id = dt.transaksi_id
                 JOIN barang b ON dt.barang_id = b.id
                 JOIN pembeli p ON t.pembeli_id = p.id
                 WHERE p.nama = ?
                 GROUP BY t.id, t.tanggal, t.total_harga, t.marketplace, t.daerah
                 ORDER BY t.tanggal DESC";

        $stmt = $conn->prepare($query);
        $stmt->execute([$nama_pembeli]);
        $details = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'details' => $details
        ]);
        exit;
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
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
        t.marketplace,
        u.nama as nama_kasir
    FROM transaksi t
    LEFT JOIN pembeli p ON t.pembeli_id = p.id
    LEFT JOIN users u ON t.user_id = u.id
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
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    $transactions = [];
}

// Tambahkan setelah query daerah yang sudah ada
if (isset($_GET['tab']) && $_GET['tab'] === 'marketplace') {
    // Buat array marketplace yang tersedia
    $available_marketplaces = ['Shopee', 'Tokopedia', 'Tiktok', 'Offline'];

    // Buat temporary table untuk marketplace
    $conn->exec("CREATE TEMPORARY TABLE IF NOT EXISTS temp_marketplace (marketplace VARCHAR(50))");
    $insertStmt = $conn->prepare("INSERT INTO temp_marketplace (marketplace) VALUES (?)");
    foreach ($available_marketplaces as $mp) {
        $insertStmt->execute([$mp]);
    }

    // Query untuk mendapatkan data transaksi per marketplace
    $query = "SELECT 
        COALESCE(m.marketplace, 'Offline') as nama,
        COUNT(DISTINCT t.id) as total_transaksi,
        COALESCE(SUM(t.total_harga), 0) as total_pendapatan,
        COALESCE(SUM((dt.harga - b.harga_modal) * dt.jumlah), 0) as profit
    FROM temp_marketplace m
    LEFT JOIN transaksi t ON t.marketplace = m.marketplace
    LEFT JOIN detail_transaksi dt ON t.id = dt.transaksi_id
    LEFT JOIN barang b ON dt.barang_id = b.id
    GROUP BY m.marketplace
    ORDER BY total_transaksi DESC, m.marketplace ASC";

    $stmt = $conn->prepare($query);
    $stmt->execute();
    $marketplaces = $stmt->fetchAll();

    // Hapus temporary table
    $conn->exec("DROP TEMPORARY TABLE IF EXISTS temp_marketplace");
}

// Tambahkan handler untuk detail marketplace
if (isset($_GET['action']) && $_GET['action'] === 'get_marketplace_detail') {
    header('Content-Type: application/json');
    try {
        $marketplace = $_GET['marketplace'];

        $query = "SELECT 
            t.id,
            t.tanggal,
            t.total_harga,
            p.nama as nama_pembeli,
            GROUP_CONCAT(CONCAT(b.nama_barang, ' (', dt.jumlah, ')') SEPARATOR ', ') as detail_produk,
            SUM((dt.harga - b.harga_modal) * dt.jumlah) as profit
        FROM transaksi t
        LEFT JOIN pembeli p ON t.pembeli_id = p.id
        JOIN detail_transaksi dt ON t.id = dt.transaksi_id
        JOIN barang b ON dt.barang_id = b.id
        WHERE t.marketplace = ?
        GROUP BY t.id, t.tanggal, t.total_harga, p.nama
        ORDER BY t.tanggal DESC";

        $stmt = $conn->prepare($query);
        $stmt->execute([$marketplace]);
        $details = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'details' => $details
        ]);
        exit;
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
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
            align-items: center;
            justify-content: center;
            padding: 1rem;
            overflow-y: auto;
        }

        .modal.active {
            display: flex;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
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

        .scrollbar-thin {
            scrollbar-width: thin;
        }

        .scrollbar-thin::-webkit-scrollbar {
            width: 6px;
        }

        .scrollbar-thin::-webkit-scrollbar-track {
            background: #F9FAFB;
            border-radius: 3px;
        }

        .scrollbar-thin::-webkit-scrollbar-thumb {
            background: #E5E7EB;
            border-radius: 3px;
        }

        .scrollbar-thin::-webkit-scrollbar-thumb:hover {
            background: #D1D5DB;
        }

        .modal-content {
            max-height: 80vh;
            width: 100%;
            max-width: 900px;
            display: flex;
            flex-direction: column;
        }

        .modal-body {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            /* Sembunyikan scrollbar horizontal */
            padding-right: 6px;
            /* Tambahkan padding untuk scrollbar */
        }

        /* Custom scrollbar styling */
        .modal-body::-webkit-scrollbar {
            width: 6px;
            position: absolute;
            right: 0;
        }

        .modal-body::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }

        .modal-body::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }

        .modal-body::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        /* Add/update responsive styles */
        .tab-navigation {
            display: inline-flex;
            background: #F3F4F6;
            padding: 0.375rem;
            border-radius: 1rem;
            gap: 0.25rem;
            margin-left: 0; /* Tambahkan ini */
        }

        .tab-button {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.25rem;
            border-radius: 0.75rem;
            font-weight: 500;
            transition: all 0.3s;
            white-space: nowrap;
        }

        .tab-button svg {
            width: 1.25rem;
            height: 1.25rem;
        }

        .tab-button.active {
            background: white;
            color: #2563EB;
            box-shadow: 0 4px 6px -1px rgb(37 99 235 / 0.1);
            transform: scale(1.02);
        }

        /* Responsive styles */
        @media (max-width: 768px) {
            .tab-navigation {
                display: flex;
                overflow-x: auto;
                width: 100%;
                scrollbar-width: none;
                -webkit-overflow-scrolling: touch;
                padding: 0.5rem;
            }

            .tab-navigation::-webkit-scrollbar {
                display: none;
            }

            .tab-button {
                flex: 0 0 auto;
            }
        }

        /* Filter section responsive */
        @media (max-width: 768px) {
            .filter-section {
                flex-direction: column;
                gap: 1rem;
            }

            .date-range {
                width: 100%;
            }

            .search-section {
                width: 100%;
            }
        }

        /* Table responsive */
        .table-container {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        @media (max-width: 768px) {
            .table-container table {
                min-width: 800px;
            }
            
            .modal-content {
                width: 95%;
                margin: 1rem;
                max-height: 90vh;
            }

            .modal {
                padding: 0.5rem;
            }

            .delete-modal .bg-white {
                width: 90%;
                max-width: 400px;
                margin: 1rem auto;
            }

            .modal-body {
                padding-right: 0;
            }
        }

        /* Modal responsive styles */
        @media (max-width: 640px) {
            .modal-content {
                width: 100%;
                height: 100%;
                margin: 0;
                border-radius: 0;
            }

            .modal-body {
                padding: 1rem;
            }

            /* Make table header sticky */
            .modal-body thead {
                position: sticky;
                top: 0;
                background: white;
                z-index: 10;
            }

            /* Adjust cell spacing */
            .modal-body td,
            .modal-body th {
                padding: 12px;
                white-space: nowrap;
            }

            /* Add horizontal scroll indicator */
            .modal-body::after {
                content: '';
                position: absolute;
                right: 0;
                top: 0;
                bottom: 0;
                width: 24px;
                background: linear-gradient(to right, transparent, rgba(255,255,255,0.9));
                pointer-events: none;
            }
        }

        /* Style untuk modal yang bisa di-scroll horizontal */
        .modal-body .table-wrapper {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        /* Pastikan tabel memiliki minimal width */
        #productHistoryTable,
        #daerahDetailContent,
        #marketplaceDetailTable {
            min-width: 800px;
        }

        /* Tambahkan gradient indicator untuk scroll horizontal */
        .table-wrapper::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            width: 30px;
            background: linear-gradient(to right, transparent, rgba(255,255,255,0.9));
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.2s;
        }

        .table-wrapper.can-scroll::after {
            opacity: 1;
        }

        /* Perbaiki padding untuk konsistensi */
        .modal-body .table-wrapper table {
            border-collapse: collapse;
            width: 100%;
        }

        .modal-body .table-wrapper th,
        .modal-body .table-wrapper td {
            white-space: nowrap;
            padding: 12px 16px;
        }

        /* Tambahkan style untuk modal daerah */
        #daerahDetailModal .table-wrapper {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        #daerahDetailModal table {
            min-width: 800px;
        }

        /* Tambahkan gradient indicator untuk scroll horizontal */
        #daerahDetailModal .table-wrapper::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            width: 30px;
            background: linear-gradient(to right, transparent, rgba(255,255,255,0.9));
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.2s;
        }

        #daerahDetailModal .table-wrapper.can-scroll::after {
            opacity: 1;
        }

        /* Tambahkan style untuk filter tanggal responsif */
        @media (max-width: 640px) {
            .date-range {
                display: flex;
                flex-direction: column;
                gap: 0.5rem;
                width: 100%;
            }

            .date-range input[type="date"] {
                width: 100%;
            }

            .date-range span {
                display: none; /* Sembunyikan text "to" pada mobile */
            }

            .search-section {
                margin-top: 0.5rem;
            }

            .filter-section {
                flex-direction: column;
                gap: 0.5rem;
            }

            .filter-section > div {
                width: 100%;
            }
        }
    </style>
</head>

<body class="bg-gray-50">
    <?php include '../components/sidebar.php'; ?>
    <?php include '../components/navbar.php'; ?>

    <div class="ml-0 md:ml-64 pt-16 min-h-screen bg-gray-50/50">
        <div class="p-4 md:p-8 space-y-6">
            <!-- Header Section -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-400 rounded-3xl p-6 md:p-12">
                <div>
                    <h1 class="text-2xl md:text-3xl font-semibold text-white mb-2">Informasi Penjualan</h1>
                    <p class="text-blue-100/80">Kelola dan pantau data transaksi penjualan Anda</p>
                </div>
            </div>

            <!-- Success Alert -->
            <?php if (isset($_GET['success'])): ?>
            <div id="alert" class="mb-4">
                <div class="bg-[#F0FDF4] border-l-4 border-[#16A34A] p-4 shadow-lg rounded-lg">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-[#16A34A]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-[#15803D]">
                                    <?= $_GET['message'] ?? 'Transaksi berhasil dihapus' ?>
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
            </div>
            <?php endif; ?>

            <!-- Tab Navigation -->
            <div class="w-full mb-6">
                <div class="tab-navigation">
                    <button id="btnTransaksi" onclick="showTab('transaksi')"
                        class="tab-button px-6 py-3 rounded-xl font-medium transition-all duration-300
                        <?= !isset($_GET['tab']) || $_GET['tab'] === 'transaksi' ?
                            'bg-white text-blue-600 shadow-lg shadow-blue-500/10 scale-[1.02] ring-1 ring-black/5' :
                            'text-gray-500 hover:text-gray-600 hover:bg-white/50' ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                        <span>Transaksi</span>
                    </button>
                    <button id="btnProdukTerlaris" onclick="showTab('produk-terlaris')"
                        class="tab-button px-6 py-3 rounded-xl font-medium transition-all duration-300
                        <?= isset($_GET['tab']) && $_GET['tab'] === 'produk-terlaris' ?
                            'bg-white text-blue-600 shadow-lg shadow-blue-500/10 scale-[1.02] ring-1 ring-black/5' :
                            'text-gray-500 hover:text-gray-600 hover:bg-white/50' ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                        Produk Terjual
                    </button>
                    <button id="btnDaerah" onclick="showTab('daerah')"
                        class="tab-button px-6 py-3 rounded-xl font-medium transition-all duration-300
                        <?= isset($_GET['tab']) && $_GET['tab'] === 'daerah' ?
                            'bg-white text-blue-600 shadow-lg shadow-blue-500/10 scale-[1.02] ring-1 ring-black/5' :
                            'text-gray-500 hover:text-gray-600 hover:bg-white/50' ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Daerah
                    </button>
                    <button onclick="showTab('marketplace')"
                        class="tab-button px-6 py-3 rounded-xl font-medium transition-all duration-300
                        <?= isset($_GET['tab']) && $_GET['tab'] === 'marketplace' ?
                            'bg-white text-blue-600 shadow-lg shadow-blue-500/10 scale-[1.02] ring-1 ring-black/5' :
                            'text-gray-500 hover:text-gray-600 hover:bg-white/50' ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                        Marketplace
                    </button>
                    <button onclick="showTab('pembeli')"
                        class="tab-button px-6 py-3 rounded-xl font-medium transition-all duration-300
                        <?= isset($_GET['tab']) && $_GET['tab'] === 'pembeli' ?
                            'bg-white text-blue-600 shadow-lg shadow-blue-500/10 scale-[1.02] ring-1 ring-black/5' :
                            'text-gray-500 hover:text-gray-600 hover:bg-white/50' ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        Pembeli
                    </button>
                </div>
            </div>

            <!-- Table Section -->
            <div class="bg-white rounded-2xl overflow-hidden border border-gray-100 shadow-sm">
                <?php if (!isset($_GET['tab']) || $_GET['tab'] === 'transaksi'): ?>
                    <!-- Filter & Search Section untuk Tab Transaksi -->
                    <div class="p-4 md:p-6 bg-white border-b border-gray-100">
                        <div class="filter-section flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                            <div class="date-range flex flex-col sm:flex-row items-start sm:items-center gap-2 sm:gap-4 w-full md:w-auto">
                                <div class="w-full sm:w-auto">
                                    <label class="block sm:hidden text-sm text-gray-600 mb-1">Dari tanggal</label>
                                    <input type="date" id="start_date" value="<?= $_GET['start_date'] ?? date('Y-m-d') ?>"
                                        class="w-full sm:w-auto px-4 py-2.5 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-gray-600">
                                </div>
                                <span class="hidden sm:inline text-gray-500 font-medium">to</span>
                                <div class="w-full sm:w-auto">
                                    <label class="block sm:hidden text-sm text-gray-600 mb-1">Sampai tanggal</label>
                                    <input type="date" id="end_date" value="<?= $_GET['end_date'] ?? date('Y-m-d') ?>"
                                        class="w-full sm:w-auto px-4 py-2.5 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-gray-600">
                                </div>
                            </div>
                            <div class="search-section flex items-center gap-2 w-full md:w-auto">
                                <input type="text" id="searchInput" placeholder="Cari nama pembeli..." value="<?= $_GET['search'] ?? '' ?>"
                                    class="w-full md:w-80 px-4 py-2.5 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-gray-600">
                                <button onclick="applyFilter()"
                                        class="px-6 py-2.5 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-xl 
                                               hover:from-blue-700 hover:to-blue-800 transition-all duration-300 font-medium
                                               shadow-lg shadow-blue-500/30 hover:shadow-blue-500/40">
                                    Cari
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Tabel Transaksi -->
                    <div class="table-container">
                        <table class="w-full">
                            <thead class="bg-gray-50/50 border-b border-gray-100">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">NO</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">NAMA PEMBELI</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">KASIR</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">TANGGAL TRANSAKSI</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">MARKETPLACE</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">TOTAL PEMBELIAN</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">PROFIT</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">AKSI</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach ($transactions as $index => $transaction): ?>
                                    <tr class="hover:bg-gray-50/50">
                                        <td class="px-6 py-4 text-sm text-gray-600"><?= $index + 1 ?></td>
                                        <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars($transaction['nama_pembeli']) ?></td>
                                        <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars($transaction['nama_kasir'] ?? '-') ?></td>
                                        <td class="px-6 py-4 text-sm text-gray-600">
                                            <?php 
                                                $tanggal = new DateTime($transaction['tanggal']);
                                                echo $tanggal->format('d/m/Y H:i') . ' WITA'; 
                                            ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="px-3 py-1 rounded-lg text-sm font-medium
                                        <?php
                                        switch (strtolower($transaction['marketplace'])) {
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
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                    </svg>
                                                </button>
                                                <button onclick="showDeleteModal(<?= $transaction['id'] ?>)"
                                                    class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                                <button onclick="editTransaction(<?= $transaction['id'] ?>)"
                                                    class="p-2 text-yellow-600 hover:bg-yellow-50 rounded-lg transition-colors">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php elseif (isset($_GET['tab']) && $_GET['tab'] === 'produk-terlaris'): ?>
                    <!-- Tabel Produk Terlaris -->
                    <div class="table-container">
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
                                                                    d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
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
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php elseif (isset($_GET['tab']) && $_GET['tab'] === 'daerah'): ?>
                    <!-- Tabel Daerah -->
                    <div class="table-container">
                        <table class="w-full">
                            <thead class="bg-gray-50/50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">NO</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">DAERAH</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">TOTAL TRANSAKSI</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">TOTAL PENDAPATAN</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PROFIT</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">AKSI</th>
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
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php elseif (isset($_GET['tab']) && $_GET['tab'] === 'marketplace'): ?>
                    <div class="bg-white rounded-2xl overflow-hidden border border-gray-100 shadow-sm">
                        <div class="overflow-x-auto">
                            <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50/50">
                                <th class="text-left py-4 px-6 text-sm font-medium text-gray-600">NO</th>
                                <th class="text-left py-4 px-6 text-sm font-medium text-gray-600">MARKETPLACE</th>
                                <th class="text-left py-4 px-6 text-sm font-medium text-gray-600">TOTAL TRANSAKSI</th>
                                <th class="text-left py-4 px-6 text-sm font-medium text-gray-600">TOTAL PENDAPATAN</th>
                                <th class="text-left py-4 px-6 text-sm font-medium text-gray-600">PROFIT</th>
                                <th class="text-left py-4 px-6 text-sm font-medium text-gray-600">AKSI</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($marketplaces as $index => $marketplace): ?>
                                <tr class="border-b border-gray-100 hover:bg-gray-50/50 transition-colors">
                                    <td class="py-4 px-6 text-sm text-gray-600"><?= $index + 1 ?></td>
                                    <td class="py-4 px-6">
                                        <span class="px-3 py-1 rounded-lg text-sm font-medium 
                                            <?php
                                            switch (strtolower($marketplace['nama'])) {
                                                case 'shopee':
                                                    echo 'bg-orange-50 text-orange-600';
                                                    break;
                                                case 'tokopedia':
                                                    echo 'bg-green-50 text-green-600';
                                                    break;
                                                case 'tiktok':
                                                    echo 'bg-gray-50 text-gray-600';
                                                    break;
                                                default:
                                                    echo 'bg-blue-50 text-blue-600';
                                            }
                                            ?>">
                                            <?= ucfirst(htmlspecialchars($marketplace['nama'])) ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-6">
                                        <span class="px-3 py-1 rounded-lg text-sm font-medium bg-blue-50 text-blue-600">
                                            <?= $marketplace['total_transaksi'] ?> Transaksi
                                        </span>
                                    </td>
                                    <td class="py-4 px-6 text-sm text-gray-600">
                                        Rp <?= number_format($marketplace['total_pendapatan'], 0, ',', '.') ?>
                                    </td>
                                    <td class="py-4 px-6">
                                        <span class="text-sm font-medium <?= $marketplace['profit'] >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                                            Rp <?= number_format($marketplace['profit'], 0, ',', '.') ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-6">
                                        <button onclick="showMarketplaceDetail('<?= htmlspecialchars($marketplace['nama']) ?>')"
                                            class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                        </div>
                </div>

                <!-- Modal Detail Marketplace -->
                <div id="marketplaceDetailModal" class="modal">
                    <div class="modal-content bg-white rounded-2xl overflow-hidden shadow-xl">
                        <div class="sticky top-0 bg-white border-b border-gray-100 p-6 flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-gray-800" id="marketplaceTitle">Detail Marketplace</h3>
                                <button onclick="closeMarketplaceModal()" class="text-gray-400 hover:text-gray-500">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="p-6">
                                <div class="table-wrapper">
                                    <table class="w-full">
                                        <thead class="sticky top-0 bg-gray-50">
                                            <tr>
                                                <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">Tanggal</th>
                                                <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">Pembeli</th>
                                                <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">Produk</th>
                                                <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">Total</th>
                                                <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">Profit</th>
                                            </tr>
                                        </thead>
                                        <tbody id="marketplaceDetailTable"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php elseif (isset($_GET['tab']) && $_GET['tab'] === 'pembeli'): ?>
                    <div class="bg-white rounded-2xl overflow-hidden border border-gray-100 shadow-sm">
                    <!-- Search bar untuk pembeli -->
                        <div class="p-6 border-b border-gray-100">
                        <div class="flex items-center gap-4">
                            <div class="flex items-center gap-2 flex-1">
                                <div class="flex-1">
                                    <input type="text" 
                                           id="searchPembeli" 
                                           placeholder="Cari nama pembeli..."
                                           class="w-full px-4 py-2.5 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                                </div>
                                <button onclick="searchPembeli(document.getElementById('searchPembeli').value)"
                                        class="px-6 py-2.5 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-xl 
                                               hover:from-blue-700 hover:to-blue-800 transition-all duration-300 font-medium
                                               shadow-lg shadow-blue-500/30 hover:shadow-blue-500/40">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                        </svg>
                                        Cari
                                    </div>
                                </button>
                            </div>
                        </div>
                    </div>

                        <!-- Tabel Pembeli -->
                    <div class="table-container">
                            <table class="w-full">
                            <thead class="bg-gray-50/50">
                                <tr class="border-b border-gray-100">
                                    <th class="text-left py-4 px-6 text-sm font-medium text-gray-600">NO</th>
                                    <th class="text-left py-4 px-6 text-sm font-medium text-gray-600">NAMA PEMBELI</th>
                                    <th class="text-left py-4 px-6 text-sm font-medium text-gray-600">TOTAL TRANSAKSI</th>
                                    <th class="text-left py-4 px-6 text-sm font-medium text-gray-600">TOTAL PEMBELIAN</th>
                                    <th class="text-left py-4 px-6 text-sm font-medium text-gray-600">TERAKHIR BELI</th>
                                    <th class="text-center py-4 px-6 text-sm font-medium text-gray-600">AKSI</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Query untuk mendapatkan data pembeli unik dengan statistik
                                $query = "SELECT 
                                            p.nama,
                                            COUNT(t.id) as total_transaksi,
                                            SUM(t.total_harga) as total_pembelian,
                                            MAX(t.tanggal) as terakhir_beli
                                         FROM pembeli p
                                         LEFT JOIN transaksi t ON p.id = t.pembeli_id
                                         GROUP BY p.nama
                                         ORDER BY total_transaksi DESC, total_pembelian DESC";
                                
                                $stmt = $conn->prepare($query);
                                $stmt->execute();
                                $pembelis = $stmt->fetchAll();

                                foreach ($pembelis as $index => $pembeli):
                                ?>
                                    <tr class="border-b border-gray-50 hover:bg-gray-50/50">
                                        <td class="py-4 px-6 text-sm text-gray-600"><?= $index + 1 ?></td>
                                        <td class="py-4 px-6 text-sm font-medium text-gray-800">
                                            <?= htmlspecialchars($pembeli['nama']) ?>
                                        </td>
                                        <td class="py-4 px-6">
                                            <span class="px-3 py-1 rounded-lg text-sm font-medium bg-blue-100 text-blue-700">
                                                <?= number_format($pembeli['total_transaksi']) ?> Transaksi
                                            </span>
                                        </td>
                                        <td class="py-4 px-6 text-sm text-gray-600">
                                            Rp <?= number_format($pembeli['total_pembelian'], 0, ',', '.') ?>
                                        </td>
                                        <td class="py-4 px-6 text-sm text-gray-600">
                                            <?= date('d/m/Y H:i', strtotime($pembeli['terakhir_beli'])) ?>
                                        </td>
                                        <td class="py-4 px-6 text-center">
                                            <button onclick="showPembeliDetail('<?= htmlspecialchars($pembeli['nama']) ?>')"
                                                    class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                          d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                </svg>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Detail Modal -->
    <div id="detailModal" class="modal">
        <div class="modal-content bg-white rounded-2xl overflow-hidden shadow-xl">
            <!-- Header tetap -->
            <div class="sticky top-0 bg-white border-b border-gray-100 p-6 flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-800">Detail Transaksi</h3>
                <button onclick="closeDetailModal()" class="p-2 hover:bg-gray-100 rounded-lg transition-colors">
                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Body dengan scroll -->
            <div class="modal-body">
                <div class="p-6 space-y-6">
                    <h4 class="font-medium text-gray-800">Informasi Transaksi</h4>
                    <div class="space-y-4">
                        <div class="flex">
                            <span class="w-40 text-gray-600">Nama Pembeli</span>
                            <span class="text-gray-900" id="detailBuyerName">: </span>
                        </div>
                        <div class="flex">
                            <span class="w-40 text-gray-600">Tanggal</span>
                            <span class="text-gray-900" id="detailDate">: </span>
                        </div>
                        <div class="flex">
                            <span class="w-40 text-gray-600">Total</span>
                            <span class="text-gray-900" id="detailTotal">: </span>
                        </div>
                    </div>

                    <h4 class="font-medium text-gray-800 pt-4">Detail Produk</h4>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">Produk</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">Jumlah</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">Harga</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">Subtotal</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">Profit</th>
                                </tr>
                            </thead>
                            <tbody id="detailItems" class="divide-y divide-gray-100"></tbody>
                        </table>
                    </div>
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
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
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
    <div id="productHistoryModal" class="modal">
        <div class="modal-content bg-white rounded-2xl overflow-hidden shadow-xl">
            <!-- Header tetap -->
            <div class="sticky top-0 bg-white border-b border-gray-100 p-6 flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-800" id="productHistoryTitle">Riwayat Penjualan Produk</h3>
                <button onclick="closeProductHistory()" class="text-gray-400 hover:text-gray-500">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <!-- Body dengan scroll -->
            <div class="modal-body">
                <div class="p-6">
                    <div class="table-wrapper">
                        <table class="w-full">
                            <thead class="sticky top-0 bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">NO</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">TANGGAL</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">PEMBELI</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">JUMLAH</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">HARGA</th>
                                </tr>
                            </thead>
                            <tbody id="productHistoryTable"></tbody>
                        </table>
                    </div>
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
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Modal Content -->
            <div class="p-6">
                <div class="table-wrapper">
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
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
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
                    const transaction = data.transaction;
                    const items = data.items;

                    // Update modal content
                    document.getElementById('detailBuyerName').textContent = `: ${transaction.buyer_name || 'Tanpa Nama'}`;
                    // Format tanggal dan jam yang sudah dalam WITA dari server
                    const date = new Date(transaction.tanggal);
                    const formattedDate = `${date.getDate().toString().padStart(2, '0')}/${(date.getMonth() + 1).toString().padStart(2, '0')}/${date.getFullYear()}, ${date.getHours().toString().padStart(2, '0')}:${date.getMinutes().toString().padStart(2, '0')} WITA`;
                    document.getElementById('detailDate').textContent = `: ${formattedDate}`;
                    document.getElementById('detailTotal').textContent = `: Rp ${Number(transaction.total_harga).toLocaleString('id-ID')}`;

                    // Update items table
                    document.getElementById('detailItems').innerHTML = items.map(item => `
                        <tr class="hover:bg-gray-50/50">
                            <td class="px-4 py-3 text-sm text-gray-600">${item.nama_barang}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">${item.jumlah}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">Rp ${Number(item.harga).toLocaleString('id-ID')}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">Rp ${Number(item.subtotal).toLocaleString('id-ID')}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">Rp ${Number(item.profit).toLocaleString('id-ID')}</td>
                        </tr>
                    `).join('');

                    // Show modal
                    document.getElementById('detailModal').classList.add('active');
                    document.body.style.overflow = 'hidden';
                } else {
                    alert(data.message || 'Gagal memuat detail transaksi');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat memuat detail transaksi');
            }
        }

        function closeDetailModal() {
            const modal = document.getElementById('detailModal');
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }

        // Close modal when clicking outside
        document.getElementById('detailModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDetailModal();
            }
        });

        // Close modal when pressing Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && document.getElementById('detailModal').classList.contains('active')) {
                closeDetailModal();
            }
        });

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

            // Hapus parameter success dan message saat filter
            urlParams.delete('success');
            urlParams.delete('message');

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
            
            // Hapus parameter success dan message saat pindah tab
            urlParams.delete('success');
            urlParams.delete('message');

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

        async function showProductHistory(product) {
            try {
                const response = await fetch(`informasi.php?action=get_product_history&product=${encodeURIComponent(product)}`);
                const data = await response.json();

                if (data.success) {
                    document.getElementById('productHistoryTitle').textContent = `Riwayat Penjualan: ${product}`;

                    document.getElementById('productHistoryTable').innerHTML = data.history.map((item, index) => `
                        <tr class="border-b border-gray-100 hover:bg-gray-50/50 transition-colors">
                            <td class="px-4 py-3 text-sm text-gray-600">${index + 1}</td>
                            <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">
                                ${new Date(item.tanggal).toLocaleString('id-ID', {
                                    day: '2-digit',
                                    month: '2-digit',
                                    year: 'numeric',
                                    hour: '2-digit',
                                    minute: '2-digit'
                                })}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">${item.nama_pembeli || 'Tanpa Nama'}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">${item.jumlah} Unit</td>
                            <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">
                                Rp ${Number(item.harga).toLocaleString('id-ID')}
                            </td>
                        </tr>
                    `).join('');

                    document.getElementById('productHistoryModal').classList.add('active');
                    document.body.style.overflow = 'hidden';
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat memuat riwayat produk');
            }
        }

        function closeProductHistory() {
            document.getElementById('productHistoryModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        // Close modal when clicking outside
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
                                        <p class="text-sm font-medium text-gray-800">
                                            ${item.nama_pembeli || 'Tanpa Nama'}
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            ${item.marketplace}
                                        </p>
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
            switch (marketplace.toLowerCase()) {
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
                    const tanggal = new Date(data.tanggal + ' UTC');
                    tanggal.setHours(tanggal.getHours() + 8); // Konversi ke WITA
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
                    // Redirect dengan parameter success
                    const currentUrl = new URL(window.location.href);
                    currentUrl.searchParams.set('success', '1');
                    currentUrl.searchParams.set('message', 'Transaksi berhasil diperbarui');
                    window.location.href = currentUrl.toString();
                } else {
                    alert('Gagal mengupdate transaksi');
                }
            });
        });

        async function showMarketplaceDetail(marketplace) {
            try {
                const response = await fetch(`informasi.php?action=get_marketplace_detail&marketplace=${encodeURIComponent(marketplace)}`);
                const data = await response.json();

                if (data.success) {
                    document.getElementById('marketplaceTitle').textContent = `Detail ${marketplace}`;

                    document.getElementById('marketplaceDetailTable').innerHTML = data.details.map(item => `
                        <tr class="border-b border-gray-100 hover:bg-gray-50/50 transition-colors">
                            <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">
                                ${new Date(item.tanggal).toLocaleString('id-ID', {
                                    day: '2-digit',
                                    month: '2-digit',
                                    year: 'numeric',
                                    hour: '2-digit',
                                    minute: '2-digit'
                                })}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">${item.nama_pembeli || 'Tanpa Nama'}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">${item.detail_produk}</td>
                            <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">
                                Rp ${Number(item.total_harga).toLocaleString('id-ID')}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="text-sm font-medium ${Number(item.profit) >= 0 ? 'text-green-600' : 'text-red-600'}">
                                    Rp ${Number(item.profit).toLocaleString('id-ID')}
                                </span>
                            </td>
                        </tr>
                    `).join('');

                    document.getElementById('marketplaceDetailModal').classList.add('active');

                    // Prevent body scrolling when modal is open
                    document.body.style.overflow = 'hidden';
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat memuat detail marketplace');
            }
        }

        function closeMarketplaceModal() {
            document.getElementById('marketplaceDetailModal').classList.remove('active');
            // Restore body scrolling
            document.body.style.overflow = '';
        }

        // Close modal when clicking outside
        document.getElementById('marketplaceDetailModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeMarketplaceModal();
            }
        });

        // Tambahkan fungsi JavaScript ini di bagian <script>
        function showPembeliDetail(nama) {
            fetch(`informasi.php?action=get_pembeli_detail&nama=${encodeURIComponent(nama)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const modal = document.getElementById('detailModal');
                        const modalContent = document.querySelector('.modal-content');
                        
                        // Update judul modal
                        const modalTitle = modalContent.querySelector('h3');
                        modalTitle.textContent = `Detail Riwayat Pembelian - ${nama}`;

                        // Update konten modal
                        const modalBody = modalContent.querySelector('.modal-body');
                        modalBody.innerHTML = `
                            <div class="p-6">
                                <div class="space-y-4">
                                    ${data.details.map((detail, index) => `
                                        <div class="p-4 bg-gray-50/50 rounded-xl hover:bg-gray-50 transition-all duration-200">
                                            <div class="flex items-center justify-between mb-3">
                                                <div class="flex items-center gap-3">
                                                    <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
                                                        <span class="text-blue-600 font-medium">#${index + 1}</span>
                                                    </div>
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-800">
                                                            ${new Date(detail.tanggal).toLocaleString('id-ID', {
                                                                day: '2-digit',
                                                                month: 'long',
                                                                year: 'numeric',
                                                                hour: '2-digit',
                                                                minute: '2-digit'
                                                            })}
                                                        </div>
                                                        <div class="text-xs text-gray-500">ID Transaksi: #${detail.id}</div>
                                                    </div>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    ${detail.daerah ? `
                                                        <span class="px-3 py-1 rounded-lg text-sm font-medium bg-purple-100 text-purple-700">
                                                            ${detail.daerah}
                                                        </span>
                                                    ` : ''}
                                                    <span class="px-3 py-1 rounded-lg text-sm font-medium ${getMarketplaceColor(detail.marketplace)}">
                                                        ${detail.marketplace}
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="pl-13">
                                                <div class="p-3 bg-white rounded-lg border border-gray-100">
                                                    <div class="text-sm text-gray-600 mb-2">Produk yang dibeli:</div>
                                                    <div class="text-sm font-medium text-gray-800">${detail.detail_produk}</div>
                                                </div>
                                                <div class="flex justify-between items-center mt-3">
                                                    <div class="text-sm text-gray-500">Total Pembelian</div>
                                                    <div class="text-lg font-semibold text-gray-800">
                                                        Rp ${Number(detail.total_harga).toLocaleString('id-ID')}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        `;

                        // Tampilkan modal
                        modal.classList.add('active');
                        document.body.style.overflow = 'hidden';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan saat memuat detail pembeli');
                });
        }

        // Fungsi untuk mendapatkan warna marketplace
        function getMarketplaceColor(marketplace) {
            switch (marketplace.toLowerCase()) {
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

        // Tambahkan event listener untuk menutup modal saat klik di luar
        document.getElementById('detailModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDetailModal();
            }
        });

        // Fungsi untuk menutup modal
        function closeDetailModal() {
            const modal = document.getElementById('detailModal');
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }

        // Tambahkan fungsi debounce untuk mengoptimalkan pencarian
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

        // Fungsi pencarian yang diperbarui (tanpa highlight)
        function searchPembeli(searchTerm) {
            const rows = document.querySelectorAll('table tbody tr');
            searchTerm = searchTerm.toLowerCase().trim();

            rows.forEach(row => {
                const namaPembeli = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
                const shouldShow = namaPembeli.includes(searchTerm);
                
                // Tampilkan/sembunyikan baris
                row.style.display = shouldShow ? '' : 'none';
            });
        }

        // Inisialisasi pencarian dengan debounce
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchPembeli');
            if (searchInput) {
                const debouncedSearch = debounce((e) => searchPembeli(e.target.value), 300);
                searchInput.addEventListener('input', debouncedSearch);
            }
        });

        // Tambahkan fungsi ini di bagian <script>
        function checkTableScroll() {
            const tableWrappers = document.querySelectorAll('.table-wrapper');
            tableWrappers.forEach(wrapper => {
                if (wrapper.scrollWidth > wrapper.clientWidth) {
                    wrapper.classList.add('can-scroll');
                } else {
                    wrapper.classList.remove('can-scroll');
                }
            });
        }

        // Panggil fungsi saat modal dibuka
        document.addEventListener('DOMContentLoaded', function() {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                modal.addEventListener('transitionend', checkTableScroll);
            });
            
            // Check scroll saat window diresize
            window.addEventListener('resize', checkTableScroll);
        });

        // Fungsi untuk menutup alert
        function closeAlert() {
            const alert = document.getElementById('alert');
            if (alert) {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                alert.style.transition = 'all 0.3s ease-in-out';
                setTimeout(() => alert.remove(), 300);
            }
        }

        // Tambahkan script untuk menghapus parameter success dari URL setelah alert muncul
        window.onload = function() {
            if (window.history.replaceState) {
                const url = new URL(window.location.href);
                url.searchParams.delete('success');
                url.searchParams.delete('message');
                window.history.replaceState({}, '', url);
            }
        };
    </script>
</body>

</html>