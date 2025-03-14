<?php
session_start();
require_once '../backend/check_session.php';
require_once '../backend/database.php';

// Set timezone di awal file
date_default_timezone_set('Asia/Makassar'); // Set timezone ke WITA


// Query untuk mengambil semua produk dengan kategori dan stok
$query = "SELECT b.*, k.nama_kategori, s.nama_supplier, s.id as supplier_id, COALESCE(st.jumlah, 0) as stok 
          FROM barang b 
          LEFT JOIN kategori k ON b.kategori_id = k.id 
          LEFT JOIN supplier s ON b.supplier_id = s.id
          LEFT JOIN stok st ON b.id = st.barang_id 
          ORDER BY b.nama_barang ASC";
$stmt = $conn->query($query);
$products = $stmt->fetchAll();

// Query untuk mengambil kategori untuk dropdown
$queryKategori = "SELECT * FROM kategori ORDER BY nama_kategori ASC";
$stmtKategori = $conn->query($queryKategori);
$categories = $stmtKategori->fetchAll();

// Query untuk mengambil supplier untuk dropdown
$querySupplier = "SELECT * FROM supplier ORDER BY nama_supplier ASC";
$stmtSupplier = $conn->query($querySupplier);
$suppliers = $stmtSupplier->fetchAll();

// Tambahkan fungsi untuk handle upload gambar
function uploadImage($file) {
    $target_dir = "../uploads/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $imageFileType = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $newFileName = uniqid() . '.' . $imageFileType;
    $target_file = $target_dir . $newFileName;
    
    // Check file size
    if ($file["size"] > 2000000) {
        throw new Exception("File terlalu besar. Maksimal 2MB");
    }
    
    // Allow certain file formats
    if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg") {
        throw new Exception("Hanya file JPG, JPEG & PNG yang diperbolehkan");
    }
    
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        return $newFileName;
    } else {
        throw new Exception("Gagal mengupload file");
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $nama_barang = $_POST['nama_barang'];
            $kategori_id = $_POST['kategori_id'];
            $harga_modal = $_POST['harga_modal'];
            $harga_jual = $_POST['harga_jual'];
            $stok = $_POST['stok'];

            try {
                $conn->beginTransaction();

                // Handle image upload
                $gambar = null;
                if(isset($_FILES['gambar']) && $_FILES['gambar']['error'] == 0) {
                    $gambar = uploadImage($_FILES['gambar']);
                }
                
                // Insert produk with image and supplier
                $stmt = $conn->prepare("INSERT INTO barang (nama_barang, gambar, kategori_id, supplier_id, harga_modal, harga) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$nama_barang, $gambar, $kategori_id, $_POST['supplier_id'], $harga_modal, $harga_jual]);
                $barang_id = $conn->lastInsertId();

                // Insert stok
                $stmt = $conn->prepare("INSERT INTO stok (barang_id, jumlah) VALUES (?, ?)");
                $stmt->execute([$barang_id, $stok]);

                $conn->commit();
                $_SESSION['success'] = "Produk berhasil ditambahkan!";
            } catch(Exception $e) {
                $conn->rollback();
                $_SESSION['error'] = "Gagal menambahkan produk: " . $e->getMessage();
            }
            
            header("Location: produk.php");
            exit;
        } elseif ($_POST['action'] === 'edit') {
            try {
                $conn->beginTransaction();

                $id = $_POST['barang_id'];
                $nama_barang = $_POST['nama_barang'];
                $kategori_id = $_POST['kategori_id'];
                $harga_modal = $_POST['harga_modal'];
                $harga_jual = $_POST['harga_jual'];
                $stok = $_POST['stok'];
                
                // Handle image upload for edit
                if(isset($_FILES['gambar']) && $_FILES['gambar']['error'] == 0) {
                    // Delete old image if exists
                    $stmt = $conn->prepare("SELECT gambar FROM barang WHERE id = ?");
                    $stmt->execute([$id]);
                    $old_image = $stmt->fetchColumn();
                    
                    if($old_image && file_exists("../uploads/".$old_image)) {
                        unlink("../uploads/".$old_image);
                    }
                    
                    $gambar = uploadImage($_FILES['gambar']);
                    
                    // Update with new image
                    $stmt = $conn->prepare("UPDATE barang SET nama_barang = ?, gambar = ?, kategori_id = ?, supplier_id = ?, harga_modal = ?, harga = ? WHERE id = ?");
                    $stmt->execute([$nama_barang, $gambar, $kategori_id, $_POST['supplier_id'], $harga_modal, $harga_jual, $id]);
                } else {
                    // Update without changing image
                    $stmt = $conn->prepare("UPDATE barang SET nama_barang = ?, kategori_id = ?, supplier_id = ?, harga_modal = ?, harga = ? WHERE id = ?");
                    $stmt->execute([$nama_barang, $kategori_id, $_POST['supplier_id'], $harga_modal, $harga_jual, $id]);
                }

                // Check if stok record exists
                $stmt = $conn->prepare("SELECT COUNT(*) FROM stok WHERE barang_id = ?");
                $stmt->execute([$id]);
                $stokExists = $stmt->fetchColumn() > 0;

                if ($stokExists) {
                    // Update stok if exists
                    $stmt = $conn->prepare("UPDATE stok SET jumlah = ? WHERE barang_id = ?");
                    $stmt->execute([$stok, $id]);
                } else {
                    // Insert new stok record if doesn't exist
                    $stmt = $conn->prepare("INSERT INTO stok (barang_id, jumlah) VALUES (?, ?)");
                    $stmt->execute([$id, $stok]);
                }

                $conn->commit();
                $_SESSION['success'] = "Produk berhasil diperbarui!";
            } catch(Exception $e) {
                $conn->rollback();
                $_SESSION['error'] = "Gagal memperbarui produk: " . $e->getMessage();
            }
            
            header("Location: produk.php");
            exit;
        } elseif ($_POST['action'] === 'delete') {
            $id = $_POST['barang_id'];
            
            try {
                $conn->beginTransaction();

                // Delete stok first (foreign key constraint)
                $stmt = $conn->prepare("DELETE FROM stok WHERE barang_id = ?");
                $stmt->execute([$id]);

                // Delete produk
                $stmt = $conn->prepare("DELETE FROM barang WHERE id = ?");
                $stmt->execute([$id]);

                $conn->commit();
                $_SESSION['success'] = "Produk berhasil dihapus!";
            } catch(Exception $e) {
                $conn->rollback();
                $_SESSION['error'] = "Gagal menghapus produk: " . $e->getMessage();
            }
            
            header("Location: produk.php");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Barang - Jamu Air Mancur</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <?php include '../components/sidebar.php'; ?>
    <?php include '../components/navbar.php'; ?>

    <div class="ml-0 sm:ml-64 pt-24 sm:pt-16 min-h-screen bg-gray-50/50">
        <div class="p-4 sm:p-8">
            <!-- Header Section - Responsif -->
            <div class="mb-6 sm:mb-8 bg-gradient-to-br from-indigo-600 via-blue-500 to-blue-400 rounded-xl sm:rounded-3xl p-6 sm:p-10 text-white shadow-2xl relative overflow-hidden">
                <div class="absolute top-0 right-0 w-64 h-64 bg-white/10 rounded-full -translate-y-32 translate-x-32 blur-3xl"></div>
                <div class="absolute bottom-0 left-0 w-64 h-64 bg-blue-500/20 rounded-full translate-y-32 -translate-x-32 blur-3xl"></div>
                
                <div class="relative flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <h1 class="text-2xl sm:text-4xl font-bold mb-2 sm:mb-3">Data Barang</h1>
                        <p class="text-blue-100 text-base sm:text-lg">Kelola data produk dan inventory toko Anda</p>
                    </div>
                    <button onclick="showAddModal()" 
                            class="w-full sm:w-auto px-4 sm:px-5 py-3 bg-white/10 hover:bg-white/20 text-white rounded-xl flex items-center justify-center sm:justify-start gap-3 transition-all duration-300 backdrop-blur-sm">
                        <div class="p-2 bg-white/10 rounded-lg">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                        </div>
                        <span class="font-medium">Tambah Data</span>
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

            <!-- Table Card - Responsif -->
            <div class="bg-white/60 backdrop-blur-2xl rounded-xl sm:rounded-3xl shadow-2xl border border-white/20">
                <!-- Filter Section - Responsif -->
                <div class="p-4 sm:p-8 border-b border-gray-100/80">
                    <div class="flex flex-col sm:flex-row gap-4">
                        <!-- Filter Stok - Scrollable pada mobile -->
                        <div class="flex gap-2 overflow-x-auto pb-2 sm:pb-0 -mx-4 sm:mx-0 px-4 sm:px-0 hide-scrollbar">
                            <button onclick="filterStock('all')" 
                                    class="flex-shrink-0 px-4 py-2 rounded-xl bg-white/80 backdrop-blur-xl border border-gray-200 hover:border-gray-300 hover:bg-white transition-all duration-300 group flex items-center gap-2 stock-filter active">
                                <div class="w-2 h-2 rounded-full bg-gradient-to-br from-gray-400 to-gray-500"></div>
                                <span class="text-sm font-medium text-gray-700">All</span>
                                <span class="text-xs text-gray-400">(<?= count($products) ?>)</span>
                            </button>
                            
                            <button onclick="filterStock('banyak')" 
                                    class="flex-shrink-0 px-4 py-2 rounded-xl bg-white/80 backdrop-blur-xl border border-gray-200 hover:border-gray-300 hover:bg-white transition-all duration-300 group flex items-center gap-2 stock-filter">
                                <div class="w-2 h-2 rounded-full bg-gradient-to-br from-emerald-400 to-green-500"></div>
                                <span class="text-sm font-medium text-gray-700">Stock Banyak</span>
                                <span class="text-xs text-gray-400">(<?= count(array_filter($products, fn($p) => $p['stok'] > 10)) ?>)</span>
                            </button>
                            
                            <button onclick="filterStock('sedikit')" 
                                    class="flex-shrink-0 px-4 py-2 rounded-xl bg-white/80 backdrop-blur-xl border border-gray-200 hover:border-gray-300 hover:bg-white transition-all duration-300 group flex items-center gap-2 stock-filter">
                                <div class="w-2 h-2 rounded-full bg-gradient-to-br from-orange-400 to-orange-500"></div>
                                <span class="text-sm font-medium text-gray-700">Stock Sedikit</span>
                                <span class="text-xs text-gray-400">(<?= count(array_filter($products, fn($p) => $p['stok'] > 0 && $p['stok'] <= 10)) ?>)</span>
                            </button>
                            
                            <button onclick="filterStock('habis')" 
                                    class="flex-shrink-0 px-4 py-2 rounded-xl bg-white/80 backdrop-blur-xl border border-gray-200 hover:border-gray-300 hover:bg-white transition-all duration-300 group flex items-center gap-2 stock-filter">
                                <div class="w-2 h-2 rounded-full bg-gradient-to-br from-red-400 to-red-500"></div>
                                <span class="text-sm font-medium text-gray-700">Stock Habis</span>
                                <span class="text-xs text-gray-400">(<?= count(array_filter($products, fn($p) => $p['stok'] == 0)) ?>)</span>
                            </button>
                        </div>

                        <!-- Search Box - Full width pada mobile -->
                        <div class="relative w-full sm:w-auto sm:ml-auto">
                            <input type="text" 
                                   id="searchInput"
                                   placeholder="Cari produk..." 
                                   oninput="searchTable()"
                                   class="w-full sm:w-72 pl-12 pr-4 py-2.5 bg-gray-50/50 border border-gray-200/50 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30">
                            <div class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Table Wrapper - Horizontal scroll pada mobile -->
                <div class="overflow-x-auto">
                    <div class="inline-block min-w-full align-middle">
                        <div class="overflow-hidden">
                            <table class="min-w-full divide-y divide-gray-100">
                                <thead>
                                    <tr class="bg-gray-50/50">
                                        <th class="whitespace-nowrap text-left text-xs font-semibold text-gray-500 uppercase tracking-wider py-5 px-6">No</th>
                                        <th class="whitespace-nowrap text-left text-xs font-semibold text-gray-500 uppercase tracking-wider py-5 px-6">Nama Barang</th>
                                        <th class="whitespace-nowrap text-left text-xs font-semibold text-gray-500 uppercase tracking-wider py-5 px-6">Kategori</th>
                                        <th class="whitespace-nowrap text-left text-xs font-semibold text-gray-500 uppercase tracking-wider py-5 px-6">Stok</th>
                                        <th class="whitespace-nowrap text-right text-xs font-semibold text-gray-500 uppercase tracking-wider py-5 px-6">Harga Modal</th>
                                        <th class="whitespace-nowrap text-right text-xs font-semibold text-gray-500 uppercase tracking-wider py-5 px-6">Harga Jual</th>
                                        <th class="whitespace-nowrap text-center text-xs font-semibold text-gray-500 uppercase tracking-wider py-5 px-6">Profit</th>
                                        <th class="whitespace-nowrap text-center text-xs font-semibold text-gray-500 uppercase tracking-wider py-5 px-6">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100/80" id="productTableBody">
                                    <?php foreach ($products as $index => $product): ?>
                                    <tr class="hover:bg-gray-50/50 transition-all duration-300">
                                        <td class="whitespace-nowrap py-4 px-6 text-sm text-gray-600"><?= $index + 1 ?></td>
                                        <td class="whitespace-nowrap py-4 px-6">
                                            <div class="flex items-center gap-3">
                                                <div class="w-12 h-12 rounded-lg overflow-hidden bg-gray-100">
                                                    <img src="<?= $product['gambar'] ? '../uploads/' . $product['gambar'] : '../img/no-image.jpg' ?>" 
                                                         alt="<?= htmlspecialchars($product['nama_barang']) ?>"
                                                         class="w-full h-full object-cover">
                                                </div>
                                                <span class="text-sm text-gray-800 font-medium">
                                                    <?= htmlspecialchars($product['nama_barang']) ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap py-4 px-6">
                                            <span class="px-3 py-1.5 bg-indigo-50 text-indigo-600 rounded-full text-xs font-medium">
                                                <?= htmlspecialchars($product['nama_kategori']) ?>
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap py-4 px-6">
                                            <span class="px-3 py-1.5 bg-<?= $product['stok'] > 0 ? 'green' : 'red' ?>-50 text-<?= $product['stok'] > 0 ? 'green' : 'red' ?>-600 rounded-full text-xs font-medium">
                                                <?= $product['stok'] ?> unit
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap py-4 px-6 text-right text-sm text-gray-600">
                                            Rp <?= number_format($product['harga_modal'], 0, ',', '.') ?>
                                        </td>
                                        <td class="whitespace-nowrap py-4 px-6 text-right text-sm text-gray-600">
                                            Rp <?= number_format($product['harga'], 0, ',', '.') ?>
                                        </td>
                                        <td class="whitespace-nowrap py-4 px-6 text-right">
                                            <span class="text-sm text-green-600 font-medium">
                                                Rp <?= number_format($product['harga'] - $product['harga_modal'], 0, ',', '.') ?>
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap py-4 px-6">
                                            <div class="flex items-center justify-center gap-3">
                                                <button onclick='showEditModal(<?= json_encode([
                                                    "id" => $product["id"],
                                                    "nama_barang" => $product["nama_barang"],
                                                    "kategori_id" => $product["kategori_id"],
                                                    "supplier_id" => $product["supplier_id"],
                                                    "harga_modal" => $product["harga_modal"],
                                                    "harga" => $product["harga"],
                                                    "stok" => $product["stok"],
                                                    "gambar" => $product["gambar"]
                                                ]) ?>)' 
                                                        class="p-2.5 text-blue-600 hover:bg-blue-50 rounded-lg transition-all duration-200">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                    </svg>
                                                </button>
                                                <button onclick="showDeleteModal(<?= $product['id'] ?>)" 
                                                        class="p-2 text-red-600 hover:bg-red-50 rounded-xl transition-all duration-200 hover:shadow-lg hover:shadow-red-100">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                    </svg>
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

                <!-- Pagination - Stack pada mobile -->
                <div class="p-4 sm:p-8 border-t border-gray-100/80">
                    <div class="flex flex-col-reverse sm:flex-row items-center justify-between gap-4">
                        <!-- Showing info -->
                        <div class="text-sm text-gray-600 w-full sm:w-auto text-center sm:text-left order-1 sm:order-1" id="showingInfo">
                            Showing 1 to <?= min(5, count($products)) ?> of <?= count($products) ?> entries
                        </div>
                        
                        <!-- Pagination buttons -->
                        <div class="flex items-center gap-2 w-full sm:w-auto justify-center sm:justify-end order-2 sm:order-2">
                            <button onclick="changePage('prev')" 
                                    id="prevButton"
                                    class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700 hover:bg-gray-50 rounded-xl disabled:opacity-50 disabled:cursor-not-allowed">
                                Previous
                            </button>
                            <div id="pageNumbers" class="flex items-center gap-1">
                                <!-- Page numbers will be inserted here by JavaScript -->
                            </div>
                            <button onclick="changePage('next')" 
                                    id="nextButton"
                                    class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700 hover:bg-gray-50 rounded-xl disabled:opacity-50 disabled:cursor-not-allowed">
                                Next
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Modal -->
    <div id="productModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl p-4 sm:p-8 w-full max-w-xl max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h3 id="modalTitle" class="text-2xl font-bold text-gray-800"></h3>
                <button onclick="closeProductModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <form id="productForm" method="POST" class="space-y-6" enctype="multipart/form-data">
                <input type="hidden" name="action" id="formAction">
                <input type="hidden" name="barang_id" id="barangId">

                <div class="space-y-2">
                    <label class="text-sm font-medium text-gray-700">Nama Barang</label>
                    <input type="text" name="nama_barang" id="namaBarang" required
                           class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30">
                </div>

                <div class="space-y-2">
                    <label class="text-sm font-medium text-gray-700">Gambar Produk</label>
                    <div class="flex items-center gap-4">
                        <div id="imagePreview" class="hidden w-24 h-24 rounded-xl overflow-hidden bg-gray-100">
                            <img id="previewImg" src="" alt="Preview" class="w-full h-full object-cover">
                        </div>
                        <div class="flex-1">
                            <input type="file" name="gambar" id="gambarInput" accept="image/*"
                                   class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30"
                                   onchange="previewImage(this)">
                            <p class="mt-1 text-xs text-gray-500">Format: JPG, PNG. Maksimal 2MB</p>
                        </div>
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="text-sm font-medium text-gray-700">Kategori</label>
                    <select name="kategori_id" id="kategoriId" required
                            class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30">
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['nama_kategori']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="space-y-2">
                    <label class="text-sm font-medium text-gray-700">Supplier</label>
                    <select name="supplier_id" id="supplierId" required
                            class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30">
                        <option value="">Pilih Supplier</option>
                        <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?= $supplier['id'] ?>"><?= htmlspecialchars($supplier['nama_supplier']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <label class="text-sm font-medium text-gray-700">Harga Modal</label>
                        <input type="number" name="harga_modal" id="hargaModal" required
                               class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30">
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium text-gray-700">Harga Jual</label>
                        <input type="number" name="harga_jual" id="hargaJual" required
                               class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30">
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="text-sm font-medium text-gray-700">Stok</label>
                    <input type="number" name="stok" id="stok" required
                           class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30">
                </div>

                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeProductModal()"
                            class="px-6 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 rounded-xl transition-all duration-200">
                        Batal
                    </button>
                    <button type="submit"
                            class="px-6 py-2.5 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-xl transition-all duration-200">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-sm mx-4">
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
                <p class="text-gray-500 text-center mb-6">Apakah Anda yakin ingin menghapus produk ini?</p>
                <form id="deleteForm" method="POST" class="flex justify-center gap-3">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="barang_id" id="deleteId">
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

    <script>
        function showAddModal() {
            document.getElementById('modalTitle').textContent = 'Tambah Produk';
            document.getElementById('formAction').value = 'add';
            document.getElementById('productForm').reset();
            document.getElementById('productModal').classList.remove('hidden');
        }

        function showEditModal(product) {
            document.getElementById('modalTitle').textContent = 'Edit Produk';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('barangId').value = product.id;
            document.getElementById('namaBarang').value = product.nama_barang;
            document.getElementById('kategoriId').value = product.kategori_id;
            document.getElementById('supplierId').value = product.supplier_id || '';
            
            // Kembalikan format bilangan seperti sebelumnya
            document.getElementById('hargaModal').value = product.harga_modal;
            document.getElementById('hargaJual').value = product.harga;
            document.getElementById('stok').value = product.stok;
            
            // Tampilkan gambar jika ada
            if (product.gambar) {
                document.getElementById('previewImg').src = '../uploads/' + product.gambar;
                document.getElementById('imagePreview').classList.remove('hidden');
            } else {
                document.getElementById('imagePreview').classList.add('hidden');
            }
            
            document.getElementById('productModal').classList.remove('hidden');
        }

        function closeProductModal() {
            document.getElementById('productModal').classList.add('hidden');
        }

        function showDeleteModal(id) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        // Close delete modal when clicking outside
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });

        let currentPage = 1;
        const itemsPerPage = 15; // Fixed to 15 items per page

        function changePage(action) {
            const totalPages = Math.ceil(getVisibleRows().length / itemsPerPage);
            
            if (action === 'prev' && currentPage > 1) {
                currentPage--;
            } else if (action === 'next' && currentPage < totalPages) {
                currentPage++;
            } else if (typeof action === 'number') {
                currentPage = action;
            }
            
            updateTable();
        }

        function getVisibleRows() {
            return Array.from(document.querySelectorAll('#productTableBody tr:not(.hidden)'));
        }

        function updateTable() {
            const rows = getVisibleRows();
            const totalItems = rows.length;
            const totalPages = Math.ceil(totalItems / itemsPerPage);
            
            // Update row visibility
            rows.forEach((row, index) => {
                const start = (currentPage - 1) * itemsPerPage;
                const end = start + itemsPerPage;
                row.style.display = (index >= start && index < end) ? '' : 'none';
            });

            // Update showing info
            const start = totalItems === 0 ? 0 : ((currentPage - 1) * itemsPerPage) + 1;
            const end = Math.min(currentPage * itemsPerPage, totalItems);
            document.getElementById('showingInfo').textContent = 
                `Showing ${start} to ${end} of ${totalItems} entries`;

            // Update pagination buttons
            document.getElementById('prevButton').disabled = currentPage === 1;
            document.getElementById('nextButton').disabled = currentPage === totalPages;

            // Update page numbers
            const pageNumbers = document.getElementById('pageNumbers');
            pageNumbers.innerHTML = '';
            
            for (let i = 1; i <= totalPages; i++) {
                const button = document.createElement('button');
                button.className = `px-3 py-1 text-sm rounded-lg ${currentPage === i ? 
                    'bg-blue-600 text-white' : 
                    'text-gray-500 hover:text-gray-700 hover:bg-gray-50'}`;
                button.textContent = i;
                button.onclick = () => changePage(i);
                pageNumbers.appendChild(button);
            }

            // Update row numbers
            let visibleIndex = ((currentPage - 1) * itemsPerPage) + 1;
            rows.forEach((row, index) => {
                if (row.style.display !== 'none') {
                    const numberCell = row.querySelector('td:first-child');
                    if (numberCell) numberCell.textContent = visibleIndex++;
                }
            });
        }

        // Initialize table on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateTable();
        });

        // Add search functionality
        function searchTable() {
            const searchText = document.getElementById('searchInput').value.toLowerCase();
            const rows = document.querySelectorAll('#productTableBody tr');
            let visibleCount = 0;

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const shouldShow = text.includes(searchText);
                row.classList.toggle('hidden', !shouldShow);
                if (shouldShow) visibleCount++;
            });

            currentPage = 1;
            updateTable();
        }

        // Tambahkan fungsi untuk preview gambar
        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            const previewImg = document.getElementById('previewImg');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    preview.classList.remove('hidden');
                }
                
                reader.readAsDataURL(input.files[0]);
            } else {
                previewImg.src = '';
                preview.classList.add('hidden');
            }
        }

        // JavaScript untuk filter
        function filterStock(type) {
            const rows = document.querySelectorAll('tbody tr');
            const buttons = document.querySelectorAll('.stock-filter');
            
            // Remove active class from all buttons
            buttons.forEach(btn => btn.classList.remove('active', 'bg-gray-50'));
            
            // Add active class to clicked button
            event.currentTarget.classList.add('active', 'bg-gray-50');
            
            rows.forEach(row => {
                const stok = parseInt(row.querySelector('td:nth-child(4)').textContent);
                
                switch(type) {
                    case 'all':
                        row.style.display = '';
                        break;
                    case 'banyak':
                        row.style.display = stok > 10 ? '' : 'none';
                        break;
                    case 'sedikit':
                        row.style.display = (stok > 0 && stok <= 10) ? '' : 'none';
                        break;
                    case 'habis':
                        row.style.display = stok === 0 ? '' : 'none';
                        break;
                }
            });
        }

        // Tambahkan fungsi closeAlert
        function closeAlert() {
            const alert = document.getElementById('alert');
            if (alert) {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                alert.style.transition = 'all 0.3s ease-in-out';
                setTimeout(() => alert.remove(), 300);
            }
        }
    </script>

    <style>
        .stock-filter.active {
            @apply bg-white border-gray-300 shadow-md;
            transform: translateY(-1px);
        }

        .stock-filter.active .rounded-full {
            transform: scale(1.2);
        }

        .stock-filter.active span:not(.rounded-lg) {
            @apply text-gray-900;
        }

        /* Tambahkan style untuk animasi alert */
        #alert {
            opacity: 1;
            transform: translateY(0);
            transition: all 0.3s ease-in-out;
        }

        /* Sembunyikan scrollbar tapi tetap bisa scroll */
        .hide-scrollbar {
            -ms-overflow-style: none;  /* IE and Edge */
            scrollbar-width: none;  /* Firefox */
        }
        .hide-scrollbar::-webkit-scrollbar {
            display: none; /* Chrome, Safari and Opera */
        }
    </style>
</body>
</html>
