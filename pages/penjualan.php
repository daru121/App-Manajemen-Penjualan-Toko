<?php
require_once '../backend/check_session.php';
require_once '../backend/database.php';
require_once '../vendor/tecnickcom/tcpdf/tcpdf.php';


// Tambahkan handler untuk cetak resi
if (isset($_GET['action']) && $_GET['action'] === 'print_receipt') {
    // Prevent any output
    ob_clean();
    
    $transaksi_id = $_GET['transaksi_id'];
    
    // Get transaction details
    $query = "SELECT t.*, p.nama as nama_pembeli, u.nama as nama_kasir 
             FROM transaksi t 
             JOIN pembeli p ON t.pembeli_id = p.id 
             JOIN users u ON t.user_id = u.id 
             WHERE t.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$transaksi_id]);
    $transaksi = $stmt->fetch();

    // Get items
    $query = "SELECT dt.*, b.nama_barang 
             FROM detail_transaksi dt 
             JOIN barang b ON dt.barang_id = b.id 
             WHERE dt.transaksi_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$transaksi_id]);
    $items = $stmt->fetchAll();

    // Create PDF
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, array(80, 200), true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('PAksesories');
    $pdf->SetAuthor('PAksesories');
    $pdf->SetTitle('Receipt #' . $transaksi_id);
    
    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Set margins
    $pdf->SetMargins(5, 5, 5);
    $pdf->SetAutoPageBreak(true, 5);
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', '', 8);
    
    // Store Name - Center aligned
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 5, 'Jamu Air Mancur', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(0, 4, 'Jl. Soekarno Hatta, Kutai Kartanegara', 0, 1, 'C');
    $pdf->Cell(0, 4, 'Telp: 082154541854', 0, 1, 'C');
    
    // Line separator
    $pdf->Ln(1);
    $pdf->Cell(0, 0, str_repeat('=', 48), 0, 1, 'C');
    $pdf->Ln(1);
    
    
    $pdf->Cell(15, 4, 'Tanggal', 0, 0, 'L');
    $pdf->Cell(2, 4, ':', 0, 0, 'L');
    $pdf->Cell(0, 4, date('d/m/Y H:i', strtotime($transaksi['tanggal'])), 0, 1, 'L');
    
    $pdf->Cell(15, 4, 'Kasir', 0, 0, 'L');
    $pdf->Cell(2, 4, ':', 0, 0, 'L');
    $pdf->Cell(0, 4, $transaksi['nama_kasir'], 0, 1, 'L');

    $pdf->Ln(1);
    
    
    if ($transaksi['marketplace'] != 'offline') {
        $pdf->Cell(15, 4, 'Market', 0, 0, 'L');
        $pdf->Cell(2, 4, ':', 0, 0, 'L');
        $pdf->Cell(0, 4, ucfirst($transaksi['marketplace']), 0, 1, 'L');
        
        if ($transaksi['daerah']) {
            $pdf->Cell(15, 4, 'Provinsi', 0, 0, 'L');
            $pdf->Cell(2, 4, ':', 0, 0, 'L');
            $pdf->Cell(0, 4, $transaksi['daerah'], 0, 1, 'L');
        }
    }


    
    // Items header dengan garis bawah
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(35, 4, 'Item', 0, 0);
    $pdf->Cell(10, 4, 'Qty', 0, 0, 'C');
    $pdf->Cell(15, 4, '@Harga', 0, 0, 'R');
    $pdf->Cell(15, 4, 'Total', 0, 1, 'R');
    
    // Line separator
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(0, 0, str_repeat('-', 48), 0, 1, 'C');
    $pdf->Ln(1);
    
    // Items dengan format yang lebih rapi
    foreach ($items as $item) {
        // Nama item
        $pdf->MultiCell(35, 4, $item['nama_barang'], 0, 'L', false, 0);
        $pdf->Cell(10, 4, $item['jumlah'], 0, 0, 'C');
        $pdf->Cell(15, 4, number_format($item['harga'], 0, ',', '.'), 0, 0, 'R');
        $pdf->Cell(15, 4, number_format($item['jumlah'] * $item['harga'], 0, ',', '.'), 0, 1, 'R');
    }
    
    // Line separator
    $pdf->Ln(1);
    $pdf->Cell(0, 0, str_repeat('-', 48), 0, 1, 'C');
    $pdf->Ln(1);
    
    // Totals dengan format yang lebih rapi
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(45, 4, 'Total:', 0, 0, 'R');
    $pdf->Cell(30, 4, 'Rp ' . number_format($transaksi['total_harga'], 0, ',', '.'), 0, 1, 'R');
    
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(45, 4, 'Pembayaran:', 0, 0, 'R');
    $pdf->Cell(30, 4, 'Rp ' . number_format($transaksi['pembayaran'], 0, ',', '.'), 0, 1, 'R');
    
    $pdf->Cell(45, 4, 'Kembalian:', 0, 0, 'R');
    $pdf->Cell(30, 4, 'Rp ' . number_format($transaksi['kembalian'], 0, ',', '.'), 0, 1, 'R');
    
    // Line separator
    $pdf->Ln(2);
    $pdf->Cell(0, 0, str_repeat('=', 48), 0, 1, 'C');
    $pdf->Ln(2);
    
    // Thank you message dengan style yang lebih baik
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(0, 4, 'Terima kasih atas kunjungan Anda', 0, 1, 'C');
    $pdf->Cell(0, 4, 'Barang yang sudah dibeli', 0, 1, 'C');
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(0, 4, 'tidak dapat ditukar/dikembalikan', 0, 1, 'C');
    
    // Output PDF
    $pdf->Output('Receipt_TRX-' . str_pad($transaksi_id, 4, '0', STR_PAD_LEFT) . '.pdf', 'I');
    exit;
}

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
                $marketplace = $_POST['marketplace'] ?? 'offline';
                $daerah = $_POST['daerah'] ?? null;
                $kurir = $_POST['kurir'] ?? null; 
                $no_resi = $_POST['no_resi'] ?? null;
                $kembalian = $payment_amount - $total;
                
                if ($kembalian < 0) {
                    echo json_encode(['status' => 'error', 'message' => 'Pembayaran kurang']);
                    exit;
                }
                
                $conn->beginTransaction();
                
                // Insert pembeli
                $stmt = $conn->prepare("INSERT INTO pembeli (nama) VALUES (?)");
                $stmt->execute([$buyer_name]);
                $pembeli_id = $conn->lastInsertId();
                
                // Insert transaksi dengan data pengiriman
                $stmt = $conn->prepare("INSERT INTO transaksi (user_id, pembeli_id, total_harga, pembayaran, kembalian, marketplace, daerah, kurir, no_resi, status_pengiriman) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
                $stmt->execute([
                    $_SESSION['user_id'], 
                    $pembeli_id, 
                    $total, 
                    $payment_amount, 
                    $kembalian,
                    $marketplace,
                    $daerah,
                    $kurir,
                    $no_resi
                ]);
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

        case 'process_transaction':
            try {
                $conn->beginTransaction();

                $nama_pembeli = $_POST['pembeli'];
                $marketplace = $_POST['marketplace'];
                $total_harga = $_POST['total_harga'];
                $pembayaran = $_POST['pembayaran'];
                $kembalian = $_POST['kembalian'];
                $daerah = null;

                // Set daerah jika marketplace online
                if (in_array($marketplace, ['shopee', 'tokopedia', 'tiktok'])) {
                    $daerah = $_POST['daerah'];
                    if (empty($daerah)) {
                        throw new Exception("Provinsi pembeli harus diisi untuk marketplace online!");
                    }
                }

                // Cek apakah pembeli sudah ada
                $stmt = $conn->prepare("SELECT id FROM pembeli WHERE nama = ?");
                $stmt->execute([$nama_pembeli]);
                $pembeli = $stmt->fetch();
                
                // Jika pembeli belum ada, tambahkan pembeli baru
                if (!$pembeli) {
                    $stmt = $conn->prepare("INSERT INTO pembeli (nama) VALUES (?)");
                    $stmt->execute([$nama_pembeli]);
                    $pembeli_id = $conn->lastInsertId();
                } else {
                    $pembeli_id = $pembeli['id'];
                }

                // Insert ke tabel transaksi
                $query = "INSERT INTO transaksi (user_id, pembeli_id, total_harga, pembayaran, kembalian, marketplace, daerah, kurir, no_resi, status_pengiriman) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
                $stmt = $conn->prepare($query);
                $stmt->execute([
                    $_SESSION['user_id'],
                    $pembeli_id,
                    $total_harga,
                    $pembayaran,
                    $kembalian,
                    $marketplace,
                    $daerah ?? null,
                    $kurir ?? null,
                    $no_resi ?? null
                ]);

                $transaksi_id = $conn->lastInsertId();

                // Insert detail transaksi
                foreach ($_SESSION['cart'] as $item) {
                    $stmt = $conn->prepare("INSERT INTO detail_transaksi (transaksi_id, barang_id, jumlah, harga) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$transaksi_id, $item['id'], $item['jumlah'], $item['harga']]);
                    
                    // Update stok
                    $stmt = $conn->prepare("UPDATE stok SET jumlah = jumlah - ? WHERE barang_id = ?");
                    $stmt->execute([$item['jumlah'], $item['id']]);
                }

                $conn->commit();
                unset($_SESSION['cart']); // Kosongkan cart
                
                // Kirim response dengan transaksi_id
                echo json_encode([
                    'status' => 'success',
                    'transaksi_id' => $transaksi_id // Pastikan mengirim transaksi_id
                ]);
                
            } catch(Exception $e) {
                $conn->rollBack();
                echo json_encode([
                    'status' => 'error',
                    'message' => $e->getMessage()
                ]);
            }
            exit;
            break;

        // Tambahkan case baru di dalam switch untuk pencarian pembeli
        case 'search_buyers':
            try {
                $keyword = strtolower($_POST['keyword']);
                
                $query = "SELECT DISTINCT nama FROM pembeli 
                         WHERE LOWER(nama) LIKE :keyword 
                         ORDER BY nama ASC 
                         LIMIT 5";
                
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

$user_id = $_SESSION['user_id'];
$query = "SELECT nama FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$user_id]);
$kasir = $stmt->fetch();

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS Kasir - Jamu Air Mancur</title>
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

        /* CSS untuk responsif mobile */
        @media (max-width: 768px) {
            /* Layout utama */
            .lg\:pl-72 {
                padding-left: 0;
                padding-top: 1rem;
            }

            /* Header section */
            .bg-blue-600.rounded-3xl {
                margin: 0.5rem;
                border-radius: 1rem;
            }

            /* Grid layout */
            .grid.grid-cols-12 {
                display: flex;
                flex-direction: column;
                gap: 1rem;
                padding: 0.5rem;
            }

            /* Kasir section (keranjang) */
            .col-span-5 {
                display: none; /* Sembunyikan default */
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 60px; /* Tinggi tab navigation */
                background: white;
                z-index: 30;
                overflow-y: auto;
                padding: 1rem;
            }

            .col-span-5.active {
                display: block;
            }

            /* Kasir header */
            .flex.items-center.justify-between.mb-6 {
                padding: 0.5rem;
            }

            /* Cart items */
            .space-y-3.mb-6 {
                max-height: 60vh;
                overflow-y: auto;
                padding: 0.5rem;
            }

            /* Product section */
            .col-span-7 {
                display: none; /* Sembunyikan default */
                padding-bottom: 60px; /* Space untuk tab navigation */
            }

            .col-span-7.active {
                display: block;
            }

            /* Search bar */
            .relative.mb-6 {
                position: sticky;
                top: 0;
                z-index: 20;
                background: transparent;
                padding: 1rem;
                margin: -0.5rem -0.5rem 1rem -0.5rem;
                border-bottom: none;
                box-shadow: none;
            }

            /* Search input */
            .relative.mb-6 input {
                width: 100%;
                height: 44px;
                padding: 0.75rem 1rem 0.75rem 2.75rem;
                font-size: 1rem;
                border-radius: 1rem;
                border: 1px solid rgba(0, 0, 0, 0.1);
                background: #f8fafc;
                transition: all 0.3s ease;
            }

            .relative.mb-6 input:focus {
                background: white;
                border-color: #3b82f6;
                box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
            }

            /* Search icon */
            .relative.mb-6 svg {
                left: 1rem;
                width: 1.25rem;
                height: 1.25rem;
                color: #94a3b8;
            }

            /* Product grid */
            #productContainer {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 0.75rem;
                padding: 0 0.5rem;
            }

            /* Product card */
            .group.bg-gray-50\/50 {
                background: white;
                border: 1px solid rgba(0, 0, 0, 0.05);
                border-radius: 1rem;
                overflow: hidden;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
                transition: transform 0.2s ease, box-shadow 0.2s ease;
            }

            .group.bg-gray-50\/50:active {
                transform: scale(0.98);
            }

            /* Product image */
            .aspect-square {
                height: 160px;
                width: 100%;
                background: #f8fafc;
                position: relative;
            }

            .aspect-square img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                transition: transform 0.3s ease;
            }

            .group:active .aspect-square img {
                transform: scale(1.05);
            }

            /* Product info */
            .p-4 {
                padding: 0.875rem;
            }

            /* Product title */
            .font-medium.text-gray-800 {
                font-size: 0.875rem;
                line-height: 1.25rem;
                font-weight: 500;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
                margin-bottom: 0.25rem;
            }

            /* Product category */
            .text-sm.text-gray-500 {
                font-size: 0.75rem;
                color: #64748b;
                margin-bottom: 0.5rem;
            }

            /* Price and action */
            .flex.items-center.justify-between {
                align-items: center;
                margin-top: 0.5rem;
            }

            /* Price */
            .text-lg.font-semibold.text-blue-600 {
                font-size: 0.875rem;
                font-weight: 600;
            }

            /* Stock badge */
            .px-2.py-1.text-xs {
                font-size: 0.675rem;
                padding: 0.25rem 0.5rem;
                border-radius: 0.5rem;
                font-weight: 500;
            }

            /* Add to cart button */
            button.p-2 {
                width: 32px;
                height: 32px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 0.75rem;
                background: rgba(59, 130, 246, 0.1);
                transition: all 0.2s ease;
            }

            button.p-2:active {
                transform: scale(0.9);
                background: rgba(59, 130, 246, 0.2);
            }

            button.p-2 svg {
                width: 1.25rem;
                height: 1.25rem;
                color: #3b82f6;
            }
        }

        /* Extra small screens */
        @media (max-width: 480px) {
            #productContainer {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .aspect-square {
                height: 200px;
            }

            .p-4 {
                padding: 1rem;
            }

            .font-medium.text-gray-800 {
                font-size: 1rem;
            }

            .text-lg.font-semibold.text-blue-600 {
                font-size: 1rem;
            }
        }

        /* Tambahkan CSS untuk mengatur tampilan tab */
        @media (max-width: 768px) {
            /* Kasir section (keranjang) */
            .col-span-5 {
                display: none; /* Sembunyikan default */
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 60px; /* Tinggi tab navigation */
                background: white;
                z-index: 30;
                overflow-y: auto;
                padding: 1rem;
            }

            .col-span-5.active {
                display: block;
            }

            /* Product section */
            .col-span-7 {
                display: none; /* Sembunyikan default */
                padding-bottom: 60px; /* Space untuk tab navigation */
            }

            .col-span-7.active {
                display: block;
            }

            /* Tab navigation active state */
            .tab-button.active {
                color: #2563eb;
                border-bottom: 2px solid #2563eb;
            }

            .tab-button.active svg {
                color: #2563eb;
            }
        }

        /* Tambahkan CSS untuk badge */
        @media (max-width: 768px) {
            /* Badge styling */
            .cart-count {
                font-size: 0.75rem;
                min-width: 20px;
                height: 20px;
                padding: 0 4px;
                border: 2px solid white;
                box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.1);
            }
        }

        /* Tambahkan CSS untuk efek hover yang lebih baik */
        @media (max-width: 768px) {
            /* Delete button styling */
            button.text-red-500 {
                color: rgb(239, 68, 68) !important; /* Memastikan warna merah */
            }

            button.text-red-500 svg {
                color: rgb(239, 68, 68) !important; /* Memastikan icon juga merah */
            }

            button.text-red-500:active {
                background-color: rgba(239, 68, 68, 0.1);
                transform: scale(0.95);
            }
        }

        /* Update CSS untuk tab styling */
        @media (max-width: 768px) {
            /* Tab button default state (abu-abu) */
            .tab-button {
                color: #6B7280; /* text-gray-500 */
            }

            .tab-button svg {
                color: #6B7280;
            }

            /* Tab button active state (biru) */
            .tab-button.active {
                color: #2563EB !important; /* text-blue-600 */
                border-bottom: 2px solid #2563EB;
            }

            .tab-button.active svg {
                color: #2563EB !important;
            }
        }

        @media (max-width: 768px) {
            /* Container pagination */
            .flex.items-center.justify-between.border-t.border-gray-100.pt-4 {
                display: flex;
                flex-direction: column;
                gap: 0.5rem;
                margin-bottom: 0;
                padding-bottom: 0;
                border-bottom: none;
            }

            /* Info entries - Perbesar ukuran font */
            .flex.items-center.justify-between.border-t.border-gray-100.pt-4 > .text-sm.text-gray-500 {
                order: 2;
                text-align: center;
                width: 100%;
                margin-top: 0.25rem;
                font-size: 0.95rem; /* Ukuran font lebih besar */
                line-height: 1.5; /* Line height yang sesuai */
                color: #4B5563; /* Warna yang lebih gelap agar lebih mudah dibaca */
            }

            /* Product container */
            #productContainer {
                margin-bottom: 0;
                padding-bottom: 0;
            }

            /* Produk section container */
            .col-span-7.active {
                padding-bottom: 60px;
            }

            /* Hapus margin/padding berlebih */
            .col-span-7 > div:last-child {
                margin-bottom: 0;
                padding-bottom: 0;
            }
        }

        /* Previous/Next buttons disabled state */
        .pagination-nav.disabled {
            opacity: 0.5;
            pointer-events: none;
            background-color: #F3F4F6;
            border-color: #E5E7EB;
            color: #9CA3AF;
        }

        /* Hover effect hanya untuk tombol yang tidak disabled */
        .pagination-nav:not(.disabled):hover {
            background-color: #F3F4F6;
            border-color: #D1D5DB;
        }

        /* Styling untuk pagination di desktop */
        @media (min-width: 769px) {
            .pagination-nav {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                padding: 0.5rem 1rem;
                border: 1px solid #E5E7EB;
                border-radius: 0.5rem;
                font-size: 0.875rem;
                color: #374151;
                background: white;
                transition: all 0.2s;
            }

            .pagination-nav:hover:not(.disabled) {
                background: #F3F4F6;
                border-color: #D1D5DB;
            }

            .pagination-nav.disabled {
                opacity: 0.5;
                cursor: not-allowed;
                background: #F3F4F6;
            }

            .pagination-button {
                padding: 0.5rem 1rem;
                border: 1px solid #E5E7EB;
                border-radius: 0.5rem;
                font-size: 0.875rem;
                color: #374151;
                background: white;
                min-width: 2.5rem;
                text-align: center;
                transition: all 0.2s;
            }

            .pagination-button:hover:not(.active) {
                background: #F3F4F6;
                border-color: #D1D5DB;
            }

            .pagination-button.active {
                background: #2563EB;
                color: white;
                border-color: #2563EB;
            }
        }

        /* Styling untuk tombol pagination */
        .pagination-button {
            padding: 0.5rem 1rem;
            min-width: 2.5rem;
            font-size: 0.875rem;
            border-radius: 0.5rem;
            transition: all 0.2s;
        }

        /* Style khusus untuk tombol aktif */
        .pagination-button.active {
            background-color: #2563EB;
            color: white;
            border-color: #2563EB;
        }

        /* Hover effect untuk tombol tidak aktif */
        .pagination-button:not(.active):hover {
            background-color: #F3F4F6;
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
            <div class="bg-blue-600 rounded-3xl p-4 sm:p-6 md:p-8 text-white relative overflow-hidden">
                <!-- Background decorative elements -->
                <div class="absolute top-0 right-0 w-32 h-32 sm:w-64 sm:h-64 bg-white/10 rounded-full -translate-y-16 translate-x-16 blur-3xl"></div>
                <div class="absolute bottom-0 left-0 w-32 h-32 sm:w-64 sm:h-64 bg-blue-500/20 rounded-full translate-y-16 -translate-x-16 blur-3xl"></div>
                
                <div class="relative flex flex-col gap-4 sm:gap-6">
                    <!-- Header Title -->
                    <div>
                        <h1 class="text-xl sm:text-2xl md:text-3xl font-bold mb-1 sm:mb-2">Point of Sales</h1>
                        <p class="text-sm sm:text-base text-blue-100">Kelola transaksi penjualan dengan mudah</p>
                    </div>
                    
                    <!-- Stats Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                        <!-- Total Transaksi -->
                        <div class="bg-white/10 backdrop-blur-sm rounded-xl p-3 sm:p-4">
                            <div class="text-xs sm:text-sm text-blue-100 mb-1">Total Transaksi Hari Ini</div>
                            <div class="text-lg sm:text-xl font-bold">
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
                        
                        <!-- Total Pendapatan -->
                        <div class="bg-white/10 backdrop-blur-sm rounded-xl p-3 sm:p-4">
                            <div class="text-xs sm:text-sm text-blue-100 mb-1">Total Pendapatan Hari Ini</div>
                            <div class="text-lg sm:text-xl font-bold">
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
                            <!-- Ubah bagian header kasir -->
                            <div class="flex items-center justify-between mb-6">
                                <div class="flex items-center gap-2">
                                    <h2 class="text-xl font-semibold bg-gradient-to-r from-gray-800 to-gray-600 bg-clip-text text-transparent">
                                        Kasir : 
                                    </h2>
                                    <span class="text-gray-500 font-bold text-lg "><?= htmlspecialchars($kasir['nama']) ?></span>
                                </div>
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
                                                            class="p-2 text-red-500 hover:bg-red-50 rounded-lg transition-colors">
                                                        <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Payment Section -->
                                <div class="border-t border-gray-100 pt-6">
                                    <!-- Summary Section -->
                                    <div class="mb-6 space-y-4">
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
                                    </div>

                                    <!-- Form Pembayaran -->
                                    <div class="space-y-3">
                                        <!-- Input Nama Pembeli -->
                                        <div class="relative">
                                            <input type="text" 
                                                   id="buyerName"
                                                   placeholder="Nama Pembeli"
                                                   autocomplete="off"
                                                   class="w-full px-4 py-3 bg-gray-50/50 border border-gray-100 rounded-xl
                                                          focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20
                                                          transition-all duration-300 outline-none">
                                            <div id="buyerSuggestions" class="absolute z-10 w-full mt-1 bg-white border border-gray-200 rounded-xl shadow-lg hidden">
                                            </div>
                                        </div>

                                        <!-- Input Jumlah Pembayaran -->
                                        <div class="mb-4">
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                Jumlah Pembayaran
                                            </label>
                                            <input type="number" 
                                                   id="paymentAmount"
                                                   oninput="calculateChange()"
                                                   class="w-full px-4 py-3 bg-gray-50/50 border border-gray-100 rounded-xl
                                                          focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20
                                                          transition-all duration-300 outline-none">
                                        </div>

                                        <!-- Pilih Marketplace -->
                                        <div class="mb-4">
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                Marketplace
                                            </label>
                                            <select id="marketplace" 
                                                    class="w-full px-4 py-3 bg-gray-50/50 border border-gray-100 rounded-xl
                                                           focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20
                                                           transition-all duration-300 outline-none">
                                                <option value="">Pilih Marketplace</option>
                                                <option value="offline">Offline</option>
                                                <option value="shopee">Shopee</option>
                                                <option value="tokopedia">Tokopedia</option>
                                                <option value="tiktok">Tiktok Shop</option>
                                            </select>
                                        </div>

                                        <!-- Dropdown Daerah (hidden by default) -->
                                        <div id="daerahPembeli" class="mb-4 hidden">
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                Provinsi Pembeli
                                            </label>
                                            <select id="daerah" name="daerah" class="w-full px-4 py-3 bg-gray-50/50 border border-gray-100 rounded-xl
                                                                         focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20
                                                                         transition-all duration-300 outline-none">
                                                <option value="">Pilih Provinsi</option>
                                                <optgroup label="Pulau Jawa">
                                                    <option value="DKI Jakarta">DKI Jakarta</option>
                                                    <option value="Banten">Banten</option>
                                                    <option value="Jawa Barat">Jawa Barat</option>
                                                    <option value="Jawa Tengah">Jawa Tengah</option>
                                                    <option value="DI Yogyakarta">DI Yogyakarta</option>
                                                    <option value="Jawa Timur">Jawa Timur</option>
                                                </optgroup>
                                                <optgroup label="Pulau Sumatera">
                                                    <option value="Aceh">Aceh</option>
                                                    <option value="Sumatera Utara">Sumatera Utara</option>
                                                    <option value="Sumatera Barat">Sumatera Barat</option>
                                                    <option value="Riau">Riau</option>
                                                    <option value="Kepulauan Riau">Kepulauan Riau</option>
                                                    <option value="Jambi">Jambi</option>
                                                    <option value="Sumatera Selatan">Sumatera Selatan</option>
                                                    <option value="Kepulauan Bangka Belitung">Kepulauan Bangka Belitung</option>
                                                    <option value="Bengkulu">Bengkulu</option>
                                                    <option value="Lampung">Lampung</option>
                                                </optgroup>
                                                <optgroup label="Pulau Kalimantan">
                                                    <option value="Kalimantan Barat">Kalimantan Barat</option>
                                                    <option value="Kalimantan Tengah">Kalimantan Tengah</option>
                                                    <option value="Kalimantan Selatan">Kalimantan Selatan</option>
                                                    <option value="Kalimantan Utara">Kalimantan Utara</option>
                                                </optgroup>
                                                <optgroup label="Kota/Kabupaten Kalimantan Timur">
                                                    <option value="Kabupaten Berau">Kabupaten Berau</option>
                                                    <option value="Kabupaten Kutai Barat">Kabupaten Kutai Barat</option>
                                                    <option value="Kabupaten Kutai Kartanegara">Kabupaten Kutai Kartanegara</option>
                                                    <option value="Kabupaten Kutai Timur">Kabupaten Kutai Timur</option>
                                                    <option value="Mahakam Ulu">Mahakam Ulu</option>
                                                    <option value="Kabupaten Paser">Kabupaten Paser</option>
                                                    <option value="Kabupaten Penjaman Paser Utara">Kabupaten Penjaman Paser Utara</option>
                                                    <option value="Kabupaten Bulungan">Kabupaten Bulungan</option>
                                                    <option value="Kota Balikpapan">Kota Balikpapan</option>
                                                    <option value="Kota Samarinda">Kota Samarinda</option>
                                                    <option value="Kota Bontang">Kota Bontang</option>
                                                    <option value="Kota Tarakan">Kota Tarakan</option>  
                                                </optgroup>
                                                <optgroup label="Pulau Sulawesi">
                                                    <option value="Sulawesi Utara">Sulawesi Utara</option>
                                                    <option value="Gorontalo">Gorontalo</option>
                                                    <option value="Sulawesi Tengah">Sulawesi Tengah</option>
                                                    <option value="Sulawesi Barat">Sulawesi Barat</option>
                                                    <option value="Sulawesi Selatan">Sulawesi Selatan</option>
                                                    <option value="Sulawesi Tenggara">Sulawesi Tenggara</option>
                                                </optgroup>
                                                <optgroup label="Kepulauan Maluku & Papua">
                                                    <option value="Maluku">Maluku</option>
                                                    <option value="Maluku Utara">Maluku Utara</option>
                                                    <option value="Papua">Papua</option>
                                                    <option value="Papua Barat">Papua Barat</option>
                                                    <option value="Papua Selatan">Papua Selatan</option>
                                                    <option value="Papua Tengah">Papua Tengah</option>
                                                    <option value="Papua Pegunungan">Papua Pegunungan</option>
                                                </optgroup>
                                                <optgroup label="Kepulauan Nusa Tenggara & Bali">
                                                    <option value="Bali">Bali</option>
                                                    <option value="Nusa Tenggara Barat">Nusa Tenggara Barat</option>
                                                    <option value="Nusa Tenggara Timur">Nusa Tenggara Timur</option>
                                                </optgroup>
                                            </select>
                                        </div>

                                        <!-- Tambahkan di bagian form pembayaran -->
                                        <div id="pengirimanInfo" class="mb-4 hidden">
                                            <div class="space-y-4">
                                                <!-- Kurir Selection -->
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                                        Kurir Pengiriman
                                                    </label>
                                                    <select id="kurir" name="kurir" class="w-full px-4 py-3 bg-gray-50/50 border border-gray-100 rounded-xl
                                                                            focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20
                                                                            transition-all duration-300 outline-none">
                                                        <option value="">Pilih Kurir</option>
                                                            <option value="jne">JNE</option>
                                                            <option value="jnt">J&T</option>
                                                            <option value="sicepat">SiCepat</option>
                                                            <option value="anteraja">AnterAja</option>
                                                            <option value="jntcargo">J&T Cargo</option>
                                                            <option value="tiki">Tiki</option>
                                                            <option value="ninja">Ninja</option>
                                                            <option value="shopee express">Shopee Express</option>
                                                    </select>
                                                </div>

                                                <!-- Nomor Resi -->
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                                        Nomor Resi
                                                    </label>
                                                    <input type="text" id="noResi" name="no_resi" 
                                                           class="w-full px-4 py-3 bg-gray-50/50 border border-gray-100 rounded-xl
                                                                  focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20
                                                                  transition-all duration-300 outline-none"
                                                           placeholder="Masukkan nomor resi">
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Display Kembalian -->
                                        <div class="mb-4">
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                Kembalian
                                            </label>
                                            <div id="changeAmount" 
                                                 class="w-full px-4 py-3 bg-gray-50/50 border border-gray-100 rounded-xl font-medium">
                                                Rp 0
                                            </div>
                                        </div>

                                        <!-- Tombol Aksi -->
                                        <div class="flex gap-3">
                                            <button onclick="cancelTransaction()" 
                                                    class="flex-1 px-4 py-3 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 transition-colors">
                                                Batal
                                            </button>
                                            <button onclick="showConfirmModal()" 
                                                    class="flex-1 px-4 py-3 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition-colors">
                                                Bayar
                                            </button>
                                        </div>
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
                                
                                <!-- Search Input -->
                                <div class="relative mb-6">
                                    <input type="text" 
                                           id="searchInput"
                                           placeholder="Cari produk..."
                                           class="w-full px-4 py-3 pl-11 bg-gray-50/50 border border-gray-100 rounded-xl
                                                  focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20
                                                  transition-all duration-300 outline-none">
                                    <svg class="w-5 h-5 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" 
                                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                    </svg>
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

                            <!-- Update tampilan pagination -->
                            <div class="flex items-center justify-between border-t border-gray-100 pt-4">
                                <!-- Info entries di pojok kiri -->
                                <div class="text-sm text-gray-500">
                                    Showing <?= ($offset + 1) ?> to <?= min($offset + $itemsPerPage, $totalProducts) ?> of <?= $totalProducts ?> entries
                                </div>
                                
                                <!-- Pagination buttons di pojok kanan -->
                                <div class="flex items-center gap-2">
                                    <!-- Previous button -->
                                    <button class="pagination-nav <?= ($currentPage == 1) ? 'disabled' : '' ?>" 
                                            onclick="changePage('prev')" 
                                            <?= ($currentPage == 1) ? 'disabled' : '' ?>>
                                        Previous
                                    </button>
                                    
                                    <!-- Page numbers -->
                                    <?php
                                    $totalPages = ceil($totalProducts / $itemsPerPage);
                                    for ($i = 1; $i <= $totalPages; $i++):
                                    ?>
                                        <button class="pagination-button <?= $currentPage == $i ? 'active bg-blue-600 text-white border border-blue-600' : 'border border-gray-200 hover:bg-gray-50' ?>" 
                                                onclick="changePage(<?= $i ?>)">
                                            <?= $i ?>
                                        </button>
                                    <?php endfor; ?>

                                    <!-- Next button -->
                                    <button class="pagination-nav <?= ($currentPage == $totalPages) ? 'disabled' : '' ?>" 
                                            onclick="changePage('next')" 
                                            <?= ($currentPage == $totalPages) ? 'disabled' : '' ?>>
                                        Next
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Konfirmasi -->
    <div id="confirmationModal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
        <div class="relative min-h-screen flex items-center justify-center p-4">
            <div class="bg-white w-full max-w-md rounded-2xl p-6">
                <h3 class="text-lg font-semibold mb-4">Konfirmasi Pembayaran</h3>
                <p class="text-sm text-gray-600 mb-4">Periksa kembali detail transaksi</p>
                
                <div class="space-y-3 mb-6">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Nama Pembeli</span>
                        <span class="font-medium" id="confirm-buyer-name"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Total Items</span>
                        <span class="font-medium" id="confirm-total-items"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Total Belanja</span>
                        <span class="font-medium" id="confirm-total"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Pembayaran</span>
                        <span class="font-medium text-blue-600" id="confirm-payment"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Kembalian</span>
                        <span class="font-medium text-green-600" id="confirm-change"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Marketplace</span>
                        <span class="font-medium" id="confirm-marketplace"></span>
                    </div>
                    <div id="confirm-daerah-container" class="flex justify-between hidden">
                        <span class="text-gray-600">Provinsi</span>
                        <span class="font-medium" id="confirm-daerah"></span>
                    </div>
                </div>

                <div class="flex gap-3">
                    <button onclick="hideConfirmModal()" 
                            class="flex-1 px-4 py-2 text-gray-600 bg-gray-100 rounded-xl hover:bg-gray-200 transition-colors">
                        Batal
                    </button>
                    <button onclick="processPayment()" 
                            class="flex-1 px-4 py-2 text-white bg-blue-600 rounded-xl hover:bg-blue-700 transition-colors">
                        Konfirmasi Pembayaran
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Sukses Pembayaran -->
    <div id="successModal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
        <div class="relative min-h-screen flex items-center justify-center p-4">
            <div class="bg-white w-full max-w-md rounded-2xl p-6">
                <div class="text-center mb-6">
                    <div class="w-16 h-16 bg-green-100 rounded-full mx-auto mb-4 flex items-center justify-center">
                        <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800">Pembayaran Berhasil!</h3>
                </div>

                <div class="space-y-3 mb-6">
                    <div class="flex justify-between">
                        <span class="text-gray-600">No. Transaksi</span>
                        <span class="font-medium" id="success-transaction-id"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Nama Pembeli</span>
                        <span class="font-medium" id="success-buyer-name"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Total Belanja</span>
                        <span class="font-medium" id="success-total"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Pembayaran</span>
                        <span class="font-medium text-blue-600" id="success-payment"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Kembalian</span>
                        <span class="font-medium text-green-600" id="success-change"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Marketplace</span>
                        <span class="font-medium" id="success-marketplace"></span>
                    </div>
                    <div id="success-daerah-container" class="flex justify-between hidden">
                        <span class="text-gray-600">Provinsi</span>
                        <span class="font-medium" id="success-daerah"></span>
                    </div>
                    <div id="success-pengiriman-container" class="hidden">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-gray-600">Kurir:</span>
                            <span id="success-kurir" class="font-medium"></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">No. Resi:</span>
                            <span id="success-resi" class="font-medium"></span>
                        </div>
                    </div>
                </div>

                <!-- Tambahkan div untuk button group -->
                <div class="flex gap-3">
                    <!-- Button Cetak Resi -->
                    <button onclick="printReceipt()" 
                            class="flex-1 px-4 py-3 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition-colors">
                        <span class="flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                            </svg>
                            Cetak Resi
                        </span>
                    </button>
                    <!-- Button Selesai -->
                    <button onclick="window.location.reload()" 
                            class="flex-1 px-4 py-3 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 transition-colors">
                        Selesai
                    </button>
                </div>
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

    <!-- Tambahkan floating action button untuk mobile -->
    <div class="cart-fab lg:hidden">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
        </svg>
        <span class="text-sm font-medium cart-count">0</span>
    </div>

    <!-- Tambahkan tab navigation untuk mobile -->
    <div class="lg:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 z-40">
        <div class="flex justify-around">
            <!-- Tab Keranjang -->
            <button onclick="showTab('cart')" id="cartTab" 
                    class="tab-button flex-1 py-4 px-2 text-center text-sm font-medium">
                <div class="flex flex-col items-center">
                    <div class="relative">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        <span class="cart-count absolute -top-2 -right-2 bg-blue-600 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">0</span>
                    </div>
                    <span class="mt-1">Keranjang</span>
                </div>
            </button>

            <!-- Tab Produk dengan icon yang diupdate -->
            <button onclick="showTab('products')" id="productsTab" 
                    class="tab-button flex-1 py-4 px-2 text-center text-sm font-medium">
                <div class="flex flex-col items-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                    <span class="mt-1">Produk</span>
                </div>
            </button>
        </div>
    </div>

    <script>
    // Tambahkan fungsi untuk update cart counter
    function updateCartCounter() {
        const cartCountElements = document.querySelectorAll('.cart-count');
        const cartItems = document.querySelectorAll('.col-span-5 .space-y-3.mb-6 > div'); // Mengambil semua item di keranjang
        const uniqueItemCount = cartItems.length; // Menghitung jumlah item unik
        
        // Update semua element yang menampilkan jumlah item keranjang
        cartCountElements.forEach(element => {
            element.textContent = uniqueItemCount;
            
            // Sembunyikan badge jika keranjang kosong
            if (uniqueItemCount === 0) {
                element.classList.add('hidden');
            } else {
                element.classList.remove('hidden');
            }
        });
    }

    // Update fungsi addToCart
    function addToCart(productId) {
        fetch('penjualan.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=add_to_cart&product_id=${productId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                location.reload();
                setTimeout(() => {
                    updateCartCounter(); // Update counter setelah reload
                }, 100);
            }
        });
    }

    // Update fungsi removeFromCart
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
                setTimeout(() => {
                    updateCartCounter(); // Update counter setelah reload
                }, 100);
            }
        });
    }

    // Panggil updateCartCounter saat halaman dimuat
    window.addEventListener('load', () => {
        updateCartCounter();
        
        if (window.innerWidth <= 768) {
            const activeTab = sessionStorage.getItem('activeTab') || 'products';
            showTab(activeTab);
        }
    });

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

    // Update fungsi updateQuantity
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
                // Dapatkan tab yang aktif sebelum reload
                const isCartActive = document.querySelector('.col-span-5').classList.contains('active');
                
                // Reload halaman
                location.reload();
                
                // Set timeout untuk memastikan reload selesai
                setTimeout(() => {
                    // Jika sebelumnya di tab keranjang, kembalikan ke tab keranjang
                    if (isCartActive && window.innerWidth <= 768) {
                        showTab('cart');
                    }
                }, 100);
            }
        });
    }

    function calculateChange() {
        const total = <?= $grandTotal ?>;
        const pembayaran = parseFloat(document.getElementById('paymentAmount').value) || 0;
        const kembalian = pembayaran - total;
        
        // Update tampilan kembalian
        const changeDisplay = document.getElementById('changeAmount');
        
        if (!pembayaran) {
            // Jika belum ada input pembayaran
            changeDisplay.textContent = 'Rp 0';
            changeDisplay.classList.remove('text-green-600', 'text-red-600');
            changeDisplay.classList.add('text-gray-600');
        } else if (kembalian >= 0) {
            // Jika pembayaran cukup
            changeDisplay.textContent = `Rp ${kembalian.toLocaleString('id-ID')}`;
            changeDisplay.classList.remove('text-red-600', 'text-gray-600');
            changeDisplay.classList.add('text-green-600');
        } else {
            // Jika pembayaran kurang
            changeDisplay.textContent = `Rp ${Math.abs(kembalian).toLocaleString('id-ID')} (Kurang)`;
            changeDisplay.classList.remove('text-green-600', 'text-gray-600');
            changeDisplay.classList.add('text-red-600');
        }
    }

    function showConfirmModal() {
        const namaPembeli = document.getElementById('buyerName').value;
        const marketplace = document.getElementById('marketplace').value;
        const daerah = document.getElementById('daerah')?.value;
        const pembayaran = parseFloat(document.getElementById('paymentAmount').value) || 0;
        const total = <?= $grandTotal ?>;
        const kembalian = pembayaran - total;

        // Validasi input
        if (!namaPembeli) {
            showAlert('Nama pembeli harus diisi!');
            return;
        }
        if (!marketplace) {
            showAlert('Pilih marketplace terlebih dahulu!');
            return;
        }
        if (pembayaran < total) {
            showAlert('Pembayaran kurang dari total belanja!');
            return;
        }
        if (['shopee', 'tokopedia', 'tiktok'].includes(marketplace) && !daerah) {
            showAlert('Silakan pilih provinsi pembeli!');
            return;
        }

        // Update modal content
        document.getElementById('confirm-buyer-name').textContent = namaPembeli;
        document.getElementById('confirm-total-items').textContent = '<?= $totalItems ?> items';
        document.getElementById('confirm-total').textContent = `Rp ${total.toLocaleString('id-ID')}`;
        document.getElementById('confirm-payment').textContent = `Rp ${pembayaran.toLocaleString('id-ID')}`;
        document.getElementById('confirm-change').textContent = `Rp ${kembalian.toLocaleString('id-ID')}`;
        document.getElementById('confirm-marketplace').textContent = marketplace;

        // Handle daerah display
        const daerahContainer = document.getElementById('confirm-daerah-container');
        if (['shopee', 'tokopedia', 'tiktok'].includes(marketplace) && daerah) {
            document.getElementById('confirm-daerah').textContent = daerah;
            daerahContainer.classList.remove('hidden');
        } else {
            daerahContainer.classList.add('hidden');
        }

        // Show modal with animation
        const modal = document.getElementById('confirmationModal');
        modal.classList.remove('hidden');
    }

    function hideConfirmModal() {
        const modal = document.getElementById('confirmationModal');
        modal.classList.add('hidden');
    }

    function processPayment() {
        if (!validateForm()) {
            return;
        }

        const namaPembeli = document.getElementById('buyerName').value;
        const marketplace = document.getElementById('marketplace').value;
        const daerah = document.getElementById('daerah')?.value;
        const kurir = document.getElementById('kurir')?.value;
        const noResi = document.getElementById('noResi')?.value;
        const pembayaran = parseFloat(document.getElementById('paymentAmount').value);
        const total = <?= $grandTotal ?>;
        const kembalian = pembayaran - total;

        // Hide confirmation modal
        hideConfirmModal();

        // Prepare form data
        const formData = new FormData();
        formData.append('action', 'process_payment');
        formData.append('buyer_name', namaPembeli);
        formData.append('payment_amount', pembayaran);
        formData.append('total', total);
        formData.append('marketplace', marketplace);
        if (daerah) formData.append('daerah', daerah);
        if (kurir) formData.append('kurir', kurir);
        if (noResi) formData.append('no_resi', noResi);

        // Send to server
        fetch('penjualan.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                // Show success modal with transaction details
                showSuccessModal({
                    transaksi_id: data.transaksi_id,
                    pembeli: namaPembeli,
                    total: total,
                    pembayaran: pembayaran,
                    kembalian: kembalian,
                    marketplace: marketplace,
                    daerah: daerah,
                    kurir: kurir,
                    no_resi: noResi
                });
            } else {
                showAlert(data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Terjadi kesalahan saat memproses transaksi', 'error');
        });
    }

    function showSuccessModal(data) {
        // Format nomor transaksi dengan padding zeros
        const formattedId = String(data.transaksi_id).padStart(4, '0');
        
        // Update content modal sukses
        document.getElementById('success-transaction-id').textContent = `TRX-${formattedId}`;
        document.getElementById('success-buyer-name').textContent = data.pembeli;
        document.getElementById('success-total').textContent = `Rp ${data.total.toLocaleString('id-ID')}`;
        document.getElementById('success-payment').textContent = `Rp ${data.pembayaran.toLocaleString('id-ID')}`;
        document.getElementById('success-change').textContent = `Rp ${data.kembalian.toLocaleString('id-ID')}`;
        document.getElementById('success-marketplace').textContent = data.marketplace;

        // Handle daerah & pengiriman display
        const daerahContainer = document.getElementById('success-daerah-container');
        const pengirimanContainer = document.getElementById('success-pengiriman-container');
        
        if (['shopee', 'tokopedia', 'tiktok'].includes(data.marketplace)) {
            if (data.daerah) {
                document.getElementById('success-daerah').textContent = data.daerah;
                daerahContainer.classList.remove('hidden');
            }
            if (data.kurir) {
                document.getElementById('success-kurir').textContent = data.kurir;
                document.getElementById('success-resi').textContent = data.no_resi || '-';
                pengirimanContainer.classList.remove('hidden');
            }
        } else {
            daerahContainer.classList.add('hidden');
            pengirimanContainer.classList.add('hidden');
        }

        // Show success modal
        document.getElementById('successModal').classList.remove('hidden');
    }

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
                                class="px-6 py-2 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 transition-colors">
                            Tidak
                        </button>
                        <button onclick="confirmCancel(this.closest('.fixed'))" 
                                class="px-6 py-2 bg-red-500 text-white rounded-xl hover:bg-red-600 transition-colors">
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

    // Event listener untuk marketplace
    document.getElementById('marketplace').addEventListener('change', function() {
        const daerahPembeli = document.getElementById('daerahPembeli');
        const pengirimanInfo = document.getElementById('pengirimanInfo');
        const daerahSelect = document.getElementById('daerah');
        const kurirSelect = document.getElementById('kurir');
        const noResiInput = document.getElementById('noResi');
        
        if (['shopee', 'tokopedia', 'tiktok'].includes(this.value)) {
            // Tampilkan form daerah dan pengiriman
            daerahPembeli.classList.remove('hidden');
            pengirimanInfo.classList.remove('hidden');
            
            // Animasi smooth
            [daerahPembeli, pengirimanInfo].forEach(el => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(-10px)';
                
                setTimeout(() => {
                    el.style.transition = 'all 0.3s ease';
                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0)';
                }, 10);
            });
            
            // Reset pilihan
            daerahSelect.value = '';
            kurirSelect.value = '';
            noResiInput.value = '';
        } else {
            // Sembunyikan form dengan animasi
            [daerahPembeli, pengirimanInfo].forEach(el => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(-10px)';
            });
            
            setTimeout(() => {
                daerahPembeli.classList.add('hidden');
                pengirimanInfo.classList.add('hidden');
                // Reset values
                daerahSelect.value = '';
                kurirSelect.value = '';
                noResiInput.value = '';
            }, 300);
        }
    });

    // Tambahkan validasi untuk form pengiriman
    function validateForm() {
        const marketplace = document.getElementById('marketplace').value;
        const daerah = document.getElementById('daerah').value;
        const kurir = document.getElementById('kurir').value;
        
        if (['shopee', 'tokopedia', 'tiktok'].includes(marketplace)) {
            if (!daerah) {
                showAlert('Silakan pilih provinsi pembeli untuk marketplace online');
                return false;
            }
            if (!kurir) {
                showAlert('Silakan pilih kurir pengiriman');
                return false;
            }
        }
        return true;
    }

    // Event listener untuk input pembayaran
    document.getElementById('paymentAmount').addEventListener('input', calculateChange);

    // Inisialisasi tampilan kembalian saat halaman dimuat
    document.addEventListener('DOMContentLoaded', function() {
        const changeDisplay = document.getElementById('changeAmount');
        
        // Set tampilan awal ke 0
        changeDisplay.textContent = 'Rp 0';
        changeDisplay.classList.remove('text-green-600', 'text-red-600');
        changeDisplay.classList.add('text-gray-600');
    });

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
        const productContainer = document.querySelector('#productContainer');
        
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
            if (data.results && data.results.length > 0) {
                let productsHTML = '';
                data.results.forEach(product => {
                    const isOutOfStock = product.stok <= 0;
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
                                ${isOutOfStock ? 
                                    `<div class="absolute inset-0 bg-black/50 flex items-center justify-center">
                                        <span class="text-white font-medium">Stok Habis</span>
                                    </div>` : ''
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
                                        <p class="text-sm ${isOutOfStock ? 'text-red-500' : 'text-green-600'}">
                                            Stok: ${product.stok}
                                        </p>
                                    </div>
                                    <button onclick="addToCart(${product.id})" 
                                            class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                                            ${isOutOfStock ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : ''}>
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

    function printReceipt() {
        const transaksi_id = document.getElementById('success-transaction-id').textContent.replace('TRX-', '');
        window.open(`penjualan.php?action=print_receipt&transaksi_id=${transaksi_id}`, '_blank');
    }

    // Tambahkan fungsi untuk pencarian pembeli
    function searchBuyers(keyword) {
        if (!keyword) {
            document.getElementById('buyerSuggestions').classList.add('hidden');
            return;
        }

        fetch('penjualan.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=search_buyers&keyword=${encodeURIComponent(keyword)}`
        })
        .then(response => response.json())
        .then(data => {
            const suggestionsDiv = document.getElementById('buyerSuggestions');
            
            if (data.status === 'success' && data.results.length > 0) {
                let suggestionsHTML = '';
                data.results.forEach(buyer => {
                    suggestionsHTML += `
                        <div class="px-4 py-2 hover:bg-gray-50 cursor-pointer transition-colors"
                             onclick="selectBuyer('${buyer.nama}')">
                            ${buyer.nama}
                        </div>
                    `;
                });
                
                suggestionsDiv.innerHTML = suggestionsHTML;
                suggestionsDiv.classList.remove('hidden');
            } else {
                suggestionsDiv.classList.add('hidden');
            }
        });
    }

    function selectBuyer(nama) {
        document.getElementById('buyerName').value = nama;
        document.getElementById('buyerSuggestions').classList.add('hidden');
    }

    // Tambahkan event listener untuk input nama pembeli
    document.getElementById('buyerName').addEventListener('input', (e) => {
        const keyword = e.target.value.trim();
        searchBuyers(keyword);
    });

    // Sembunyikan saran saat klik di luar
    document.addEventListener('click', (e) => {
        const suggestionsDiv = document.getElementById('buyerSuggestions');
        const buyerInput = document.getElementById('buyerName');
        
        if (!buyerInput.contains(e.target) && !suggestionsDiv.contains(e.target)) {
            suggestionsDiv.classList.add('hidden');
        }
    });

    // Update fungsi showTab untuk menyimpan state tab
    function showTab(tabName) {
        const cartSection = document.querySelector('.col-span-5');
        const productsSection = document.querySelector('.col-span-7');
        const cartTab = document.getElementById('cartTab');
        const productsTab = document.getElementById('productsTab');

        // Reset semua tab dan section
        cartSection.classList.remove('active');
        productsSection.classList.remove('active');
        cartTab.classList.remove('active');
        productsTab.classList.remove('active');

        // Aktifkan tab dan section yang dipilih
        if (tabName === 'cart') {
            cartSection.classList.add('active');
            cartTab.classList.add('active');
            sessionStorage.setItem('activeTab', 'cart');
        } else {
            productsSection.classList.add('active');
            productsTab.classList.add('active');
            sessionStorage.setItem('activeTab', 'products');
        }
    }

    // Update event listener untuk load
    window.addEventListener('load', () => {
        if (window.innerWidth <= 768) {
            // Cek apakah ada state tab yang tersimpan
            const activeTab = sessionStorage.getItem('activeTab') || 'products';
            showTab(activeTab);
        }
    });

    function changePage(page) {
        // Cek jika tombol disabled, jangan lakukan apa-apa
        if (page === 'prev' && <?= $currentPage ?> === 1) return;
        if (page === 'next' && <?= $currentPage ?> === <?= $totalPages ?>) return;

        if (page === 'prev') {
            page = <?= max(1, $currentPage - 1) ?>;
        } else if (page === 'next') {
            page = <?= min($totalPages, $currentPage + 1) ?>;
        }
        
        const activeTab = sessionStorage.getItem('activeTab') || 'products';
        window.location.href = `penjualan.php?page=${page}`;
        
        setTimeout(() => {
            showTab(activeTab);
        }, 100);
    }
    </script>
</body>
</html>
?>
