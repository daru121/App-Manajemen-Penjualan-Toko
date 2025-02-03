<?php
session_start();
require_once '../backend/check_session.php';
require_once '../backend/database.php';

// Set timezone di awal file
date_default_timezone_set('Asia/Makassar'); // Set timezone ke WITA

// Tambahkan ini untuk menangkap pesan dari redirect
if (isset($_SESSION['alert'])) {
    $alertMessage = $_SESSION['alert']['message'];
    unset($_SESSION['alert']);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $nama_supplier = $_POST['nama_supplier'];
            $alamat = $_POST['alamat'];
            $telepon = $_POST['telepon'];
            $email = $_POST['email'];
            
            $stmt = $conn->prepare("INSERT INTO supplier (nama_supplier, alamat, telepon, email) VALUES (?, ?, ?, ?)");
            $stmt->execute([$nama_supplier, $alamat, $telepon, $email]);
            
            $_SESSION['alert'] = [
                'message' => 'Supplier berhasil ditambahkan!'
            ];
        } elseif ($_POST['action'] === 'edit') {
            $id = $_POST['supplier_id'];
            $nama_supplier = $_POST['nama_supplier'];
            $alamat = $_POST['alamat'];
            $telepon = $_POST['telepon'];
            $email = $_POST['email'];
            
            $stmt = $conn->prepare("UPDATE supplier SET nama_supplier = ?, alamat = ?, telepon = ?, email = ? WHERE id = ?");
            $stmt->execute([$nama_supplier, $alamat, $telepon, $email, $id]);
            
            $_SESSION['alert'] = [
                'message' => 'Supplier berhasil diperbarui!'
            ];
        } elseif ($_POST['action'] === 'delete') {
            $id = $_POST['supplier_id'];
            $stmt = $conn->prepare("DELETE FROM supplier WHERE id = ?");
            $stmt->execute([$id]);

            $_SESSION['alert'] = [
                'message' => 'Supplier berhasil dihapus!'
            ];
        }
        header("Location: supplier.php");
        exit;
    }
}

// Query untuk mengambil semua supplier
$query = "SELECT * FROM supplier ORDER BY nama_supplier ASC";
$stmt = $conn->query($query);
$suppliers = $stmt->fetchAll();

$itemsPerPage = 5; // Jumlah item per halaman
$currentPage = 1; // Halaman default
$totalItems = count($suppliers); // Total semua supplier
$totalPages = ceil($totalItems / $itemsPerPage); // Total halaman

// Di bagian atas file, tambahkan handler untuk AJAX request
if (isset($_GET['action']) && $_GET['action'] === 'get_products') {
    header('Content-Type: application/json');
    $supplier_id = $_GET['supplier_id'];
    
    try {
        $query = "SELECT b.*, k.nama_kategori, COALESCE(s.jumlah, 0) as stok 
                 FROM barang b 
                 LEFT JOIN kategori k ON b.kategori_id = k.id 
                 LEFT JOIN stok s ON b.id = s.barang_id 
                 WHERE b.supplier_id = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([$supplier_id]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'status' => 'success',
            'products' => $products
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

// Di bagian atas file PHP, tambahkan handler baru untuk get_supplier_name
if (isset($_GET['action']) && $_GET['action'] === 'get_supplier_name') {
    header('Content-Type: application/json');
    $supplier_id = $_GET['supplier_id'];
    
    try {
        $query = "SELECT nama_supplier FROM supplier WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$supplier_id]);
        $supplier = $stmt->fetch();
        
        echo json_encode([
            'status' => 'success',
            'nama_supplier' => $supplier['nama_supplier']
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

// Di bagian atas file, tambahkan handler untuk search
if (isset($_GET['action']) && $_GET['action'] === 'search') {
    header('Content-Type: application/json');
    $search = $_GET['search'];
    
    try {
        $query = "SELECT * FROM supplier 
                  WHERE nama_supplier LIKE :search 
                  OR alamat LIKE :search 
                  OR telepon LIKE :search 
                  OR email LIKE :search 
                  ORDER BY nama_supplier ASC";
        
        $stmt = $conn->prepare($query);
        $stmt->execute(['search' => "%$search%"]);
        $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'status' => 'success',
            'suppliers' => $suppliers
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'status' => 'error',
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier - Jamu Air Mancur</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>

<body class="bg-gray-50">
    <?php include '../components/sidebar.php'; ?>
    <?php include '../components/navbar.php'; ?>

    <!-- Main Content Container - Responsif -->
    <div class="ml-0 sm:ml-64 pt-24 sm:pt-16 min-h-screen bg-gray-50/50">
        <div class="p-6 sm:p-8">
            <!-- Header Section -->
            <div class="mb-6 sm:mb-8 bg-gradient-to-br from-indigo-600 via-blue-500 to-blue-400 rounded-xl sm:rounded-3xl p-6 sm:p-10 text-white shadow-2xl relative overflow-hidden">
                <!-- Decorative elements -->
                <div class="absolute top-0 right-0 w-96 h-96 bg-white/10 rounded-full -translate-y-32 translate-x-32 blur-3xl"></div>
                <div class="absolute bottom-0 left-0 w-96 h-96 bg-blue-500/20 rounded-full translate-y-32 -translate-x-32 blur-3xl"></div>
                
                <div class="relative flex flex-col sm:flex-row justify-between sm:items-center gap-4">
                    <div>
                        <h1 class="text-2xl sm:text-4xl font-bold mb-2 sm:mb-3">Supplier</h1>
                        <p class="text-blue-100 text-base sm:text-lg">Kelola data supplier produk</p>
                    </div>
                    <button onclick="showAddModal()" 
                            class="w-full sm:w-auto px-4 sm:px-5 py-3 bg-white/10 hover:bg-white/20 text-white rounded-xl flex items-center justify-center sm:justify-start gap-3 transition-all duration-300 backdrop-blur-sm">
                        <div class="p-2 bg-white/10 rounded-lg">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                        </div>
                        <span class="font-medium">Tambah Supplier</span>
                    </button>
                </div>
            </div>

            <!-- Alert Message - Sesuai dengan kategori.php -->
            <?php if (isset($alertMessage)): ?>
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
                                    <?= $alertMessage ?>
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
            <?php endif; ?>

            <!-- Table Card - Responsif tapi Tetap Elegan -->
            <div class="bg-white/70 backdrop-blur-xl rounded-3xl shadow-xl border border-gray-200/70">
                <!-- Table Header - Stack di Mobile -->
                <div class="p-4 sm:p-6 border-b border-gray-100">
                    <div class="flex flex-col sm:flex-row gap-4 sm:gap-0 sm:items-center sm:justify-between">
                        <div class="w-full sm:w-auto flex items-center gap-3">
                            <label class="text-sm font-medium text-gray-600">Menampilkan</label>
                            <div class="w-full sm:w-auto px-5 py-2.5 bg-gray-50/50 border border-gray-200/50 rounded-xl text-sm">
                                <span class="text-gray-600"><?= count($suppliers) ?> Supplier</span>
                            </div>
                        </div>

                        <div class="relative w-full sm:w-auto">
                            <input type="text" 
                                   id="searchInput"
                                   placeholder="Cari supplier..." 
                                   oninput="searchTable()"
                                class="w-full sm:w-72 pl-12 pr-4 py-2.5 bg-gray-50/50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/50 transition-all duration-300">
                            <div class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Table Content -->
                <div class="relative">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="bg-gray-50/50">
                                    <th scope="col" class="w-[5%] px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">No</th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Nama Supplier</th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Alamat</th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Telepon</th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Email</th>
                                    <th scope="col" class="px-6 py-4 text-right text-xs font-semibold text-gray-500 uppercase">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100" id="supplierTableBody">
                                <?php foreach ($suppliers as $index => $supplier): ?>
                                    <tr class="hover:bg-gray-50/50 transition-colors duration-200">
                                        <td class="px-6 py-4 text-sm text-gray-600">
                                            <?= $index + 1 ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm font-medium text-gray-800">
                                            <?= htmlspecialchars($supplier['nama_supplier']) ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-600">
                                            <?= htmlspecialchars($supplier['alamat']) ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-600">
                                            <?= htmlspecialchars($supplier['telepon']) ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-600">
                                            <?= htmlspecialchars($supplier['email']) ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex justify-end gap-2">
                                                <button onclick="showPreviewModal(<?= $supplier['id'] ?>)" 
                                                        class="p-2.5 text-indigo-600 hover:bg-indigo-50 rounded-lg transition-all duration-200">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                    </svg>
                                                </button>
                                                <button onclick='showEditModal(<?= json_encode($supplier) ?>)' 
                                                        class="p-2.5 text-blue-600 hover:bg-blue-50 rounded-lg transition-all duration-200">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                    </svg>
                                                </button>
                                                <button onclick="showDeleteModal(<?= $supplier['id'] ?>)" 
                                                        class="p-2.5 text-red-600 hover:bg-red-50 rounded-lg transition-all duration-200">
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

                <!-- Pagination section - Outside of scroll area -->
                <div class="p-4 sm:p-6 border-t border-gray-100">
                    <div class="flex flex-col-reverse sm:flex-row items-center justify-between gap-4">
                        <!-- Showing info - Kiri di desktop -->
                        <div class="text-sm text-gray-700 w-full sm:w-auto text-center sm:text-left order-1 sm:order-1" id="showingInfo">
                            Showing 1 to 5 of <?= $totalItems ?> entries
                        </div>

                        <!-- Pagination buttons - Kanan di desktop -->
                        <div class="flex items-center gap-2 w-full sm:w-auto justify-center sm:justify-end order-2 sm:order-2">
                            <button id="prevButton" 
                                    onclick="changePage('prev')" 
                                    class="px-3 py-1 text-sm text-gray-500 hover:text-gray-700 disabled:opacity-50 disabled:cursor-not-allowed">
                                Previous
                            </button>

                            <div class="flex items-center gap-1" id="pageNumbers">
                                <!-- Page numbers will be inserted here by JavaScript -->
                            </div>

                            <button id="nextButton" 
                                    onclick="changePage('next')" 
                                    class="px-3 py-1 text-sm text-gray-500 hover:text-gray-700 disabled:opacity-50 disabled:cursor-not-allowed">
                                Next
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Modal -->
    <div id="supplierModal" class="fixed inset-0 bg-black/50 z-50 hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
                <div class="p-6 border-b border-gray-100">
                    <h3 class="text-xl font-semibold text-gray-800" id="modalTitle">Tambah Supplier</h3>
                </div>
                
                <form id="supplierForm" method="POST" class="p-6 space-y-4">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="supplier_id" id="supplierId">
                    
                    <div class="space-y-2">
                        <label class="text-sm font-medium text-gray-700">Nama Supplier</label>
                        <input type="text" name="nama_supplier" id="namaSupplier" required
                               class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30">
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-medium text-gray-700">Alamat</label>
                        <textarea name="alamat" id="alamat" required
                                  class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30"></textarea>
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-medium text-gray-700">Telepon</label>
                        <input type="tel" name="telepon" id="telepon" required
                               class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30">
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-medium text-gray-700">Email</label>
                        <input type="email" name="email" id="email" required
                               class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30">
                    </div>

                    <div class="flex justify-end gap-3">
                        <button type="button" onclick="closeModal()"
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
    </div>

    <!-- Delete Confirmation Alert -->
    <div id="deleteAlert" class="fixed inset-0 bg-black/50 z-[70] hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white w-full max-w-md rounded-2xl shadow-lg">
                <div class="p-6 text-center space-y-6">
                    <div class="w-20 h-20 rounded-full bg-red-50 flex items-center justify-center mx-auto">
                        <svg class="w-10 h-10 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>

                    <div class="space-y-2">
                        <h3 class="text-xl font-medium text-gray-900">Hapus Supplier</h3>
                        <p class="text-gray-500">Apakah Anda yakin ingin menghapus supplier ini?</p>
                    </div>

                    <form id="deleteForm" method="POST" class="flex gap-3 justify-center">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="supplier_id" id="deleteId">

                        <button type="button" onclick="closeDeleteAlert()"
                            class="px-5 py-2.5 rounded-xl text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors duration-200">
                            Batal
                        </button>
                        <button type="submit"
                            class="px-5 py-2.5 rounded-xl text-sm font-medium text-white bg-red-600 hover:bg-red-700 transition-colors duration-200">
                            Hapus
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div id="previewModal" class="fixed inset-0 bg-black/50 z-50 hidden">
        <div class="flex items-center justify-center min-h-screen p-4 overflow-hidden">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-4xl max-h-[80vh] flex flex-col">
                <div class="p-6 border-b border-gray-100 flex justify-between items-center">
                    <h3 class="text-xl font-semibold text-gray-800">Produk dari <span id="supplierName" class="text-blue-600"></span></h3>
                    <button onclick="closePreviewModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                
                <div class="p-6 overflow-y-auto">
                    <div id="productGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <!-- Products will be inserted here -->
                    </div>
                    
                    <div id="noProducts" class="hidden text-center py-8">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                    d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                            </svg>
                        </div>
                        <p class="text-gray-500">Tidak ada produk dari supplier ini</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* Responsive styles */
        @media (max-width: 640px) {
            /* Improve table readability on mobile */
            .table-wrapper {
                margin: 0 -1rem;
            }
            
            td, th {
                padding-top: 1rem;
                padding-bottom: 1rem;
            }
            
            /* Better touch targets */
            button {
                min-height: 44px;
                min-width: 44px;
            }
            
            /* Text truncation */
            .line-clamp-1 {
                display: -webkit-box;
                -webkit-line-clamp: 1;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }
            
            /* Improve spacing */
            .flex-col > * + * {
                margin-top: 0.25rem;
            }
            
            /* Better modal padding */
            .modal-content {
                padding: 1.5rem;
            }
            
            /* Improve form inputs */
            input, textarea {
                font-size: 16px !important;
                padding: 0.75rem 1rem;
            }
        }
    </style>

    <script>
        let currentPage = 1;
        let itemsPerPage = 15;

        function updateTable() {
            const rows = document.querySelectorAll('#supplierTableBody tr:not(.hidden)');
            const totalItems = rows.length;
            const totalPages = Math.ceil(totalItems / itemsPerPage);
            
            // Hide all rows first
            rows.forEach(row => row.style.display = 'none');
            
            // Show rows for current page
            const start = (currentPage - 1) * itemsPerPage;
            const end = start + itemsPerPage;
            
            for (let i = start; i < end && i < totalItems; i++) {
                rows[i].style.display = '';
            }
            
            // Update showing info
            document.getElementById('showingInfo').textContent = 
                `Showing ${totalItems === 0 ? 0 : start + 1} to ${Math.min(end, totalItems)} of ${totalItems} entries`;
            
            // Update pagination buttons
            document.getElementById('prevButton').disabled = currentPage === 1;
            document.getElementById('nextButton').disabled = currentPage === totalPages;
            
            // Update page numbers
            const pageNumbers = document.getElementById('pageNumbers');
            pageNumbers.innerHTML = '';
            
            for (let i = 1; i <= totalPages; i++) {
                const button = document.createElement('button');
                button.className = `px-3 py-1 text-sm rounded-md ${currentPage === i ? 
                    'bg-blue-600 text-white' : 
                    'text-gray-500 hover:text-gray-700'}`;
                button.textContent = i;
                button.onclick = () => changePage(i);
                pageNumbers.appendChild(button);
            }
            
            // Update row numbers
            let visibleIndex = 1;
            rows.forEach(row => {
                if (row.style.display !== 'none') {
                    const numberCell = row.getElementsByTagName('td')[0];
                    numberCell.textContent = visibleIndex++;
                }
            });
        }

        function changePage(action) {
            const rows = document.querySelectorAll('#supplierTableBody tr:not(.hidden)');
            const totalItems = rows.length;
            const totalPages = Math.ceil(totalItems / itemsPerPage);
            
            if (action === 'prev' && currentPage > 1) {
                currentPage--;
            } else if (action === 'next' && currentPage < totalPages) {
                currentPage++;
            } else if (typeof action === 'number') {
                currentPage = action;
            }
            
            updateTable();
        }

        // Update fungsi searchSupplier
        function searchSupplier(query) {
            fetch(`supplier.php?action=search&search=${query}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const tbody = document.getElementById('supplierTableBody');
                        if (data.suppliers.length > 0) {
                            tbody.innerHTML = data.suppliers.map((supplier, index) => `
                                <tr class="hover:bg-gray-50/50 transition-colors duration-200">
                                    <td class="py-4 px-6 text-sm text-gray-600">${index + 1}</td>
                                    <td class="py-4 px-6 text-sm text-gray-800 font-medium">${supplier.nama_supplier}</td>
                                    <td class="py-4 px-6 text-sm text-gray-600">${supplier.alamat}</td>
                                    <td class="py-4 px-6 text-sm text-gray-600">${supplier.telepon}</td>
                                    <td class="py-4 px-6 text-sm text-gray-600">${supplier.email}</td>
                                    <td class="py-4 px-6">
                                        <div class="flex justify-end gap-2">
                                            <button onclick="showPreviewModal(${supplier.id})" 
                                                    class="p-2 text-indigo-600 hover:bg-indigo-50 rounded-lg transition-all duration-200">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                </svg>
                                            </button>
                                            <button onclick='showEditModal(${JSON.stringify(supplier)})' 
                                                    class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-all duration-200">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                            </button>
                                            <button onclick="showDeleteModal(${supplier.id})" 
                                                    class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-all duration-200">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            `).join('');

                            // Reset ke halaman pertama
                            currentPage = 1;
                            
                            // Update tampilan tabel dan pagination
                            updateTable();
                        } else {
                            tbody.innerHTML = `
                                <tr>
                                    <td colspan="6" class="py-8 text-center text-gray-500">
                                        Tidak ada supplier yang sesuai dengan pencarian
                                    </td>
                                </tr>
                            `;
                            
                            // Reset info pagination jika tidak ada hasil
                            document.getElementById('showingInfo').textContent = 'Showing 0 to 0 of 0 entries';
                            document.getElementById('pageNumbers').innerHTML = '';
                            document.getElementById('prevButton').disabled = true;
                            document.getElementById('nextButton').disabled = true;
                        }
                    }
                });
        }

        // Tambahkan debounce untuk search
        const searchInput = document.getElementById('searchInput');
        let searchTimeout;

        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                searchSupplier(this.value);
            }, 300);
        });

        // Initialize table when page loads
        document.addEventListener('DOMContentLoaded', function() {
            updateTable();
        });
        
        function showAddModal() {
            document.getElementById('modalTitle').textContent = 'Tambah Supplier';
            document.getElementById('formAction').value = 'add';
            document.getElementById('supplierForm').reset();
            document.getElementById('supplierModal').classList.remove('hidden');
        }

        function showEditModal(supplier) {
            document.getElementById('modalTitle').textContent = 'Edit Supplier';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('supplierId').value = supplier.id;
            document.getElementById('namaSupplier').value = supplier.nama_supplier;
            document.getElementById('alamat').value = supplier.alamat;
            document.getElementById('telepon').value = supplier.telepon;
            document.getElementById('email').value = supplier.email;
            document.getElementById('supplierModal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('supplierModal').classList.add('hidden');
        }

        function showPreviewModal(supplierId) {
            const modal = document.getElementById('previewModal');
            const productGrid = document.getElementById('productGrid');
            const noProducts = document.getElementById('noProducts');
            const supplierName = document.getElementById('supplierName');
            
            // Show modal
            modal.classList.remove('hidden');
            
            // Fetch supplier name
            fetch(`supplier.php?action=get_supplier_name&supplier_id=${supplierId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        supplierName.textContent = data.nama_supplier;
                    }
                });
            
            // Fetch products
            fetch(`supplier.php?action=get_products&supplier_id=${supplierId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        if (data.products.length > 0) {
                            productGrid.innerHTML = data.products.map(product => `
                                <div class="bg-gray-50 rounded-xl p-4 hover:shadow-md transition-all duration-300">
                                    <div class="aspect-square rounded-lg overflow-hidden bg-white mb-4">
                                        ${product.gambar ? 
                                            `<img src="../uploads/${product.gambar}" 
                                                  alt="${product.nama_barang}"
                                                  class="w-full h-full object-cover">` :
                                            `<div class="w-full h-full flex items-center justify-center bg-gray-100">
                                                <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" 
                                                          d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                </svg>
                                            </div>`
                                        }
                                    </div>
                                    <h3 class="font-medium text-gray-800 mb-1">${product.nama_barang}</h3>
                                    <p class="text-sm text-gray-500 mb-2">${product.nama_kategori}</p>
                                </div>
                            `).join('');
                            
                            productGrid.classList.remove('hidden');
                            noProducts.classList.add('hidden');
                        } else {
                            productGrid.classList.add('hidden');
                            noProducts.classList.remove('hidden');
                        }
                    }
                });
        }

        function closePreviewModal() {
            document.getElementById('previewModal').classList.add('hidden');
        }

        // Close preview modal when clicking outside
        document.getElementById('previewModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closePreviewModal();
            }
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

        // Fungsi untuk menampilkan alert hapus
        function showDeleteAlert(id) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteAlert').classList.remove('hidden');
        }

        // Fungsi untuk menutup alert hapus
        function closeDeleteAlert() {
            document.getElementById('deleteAlert').classList.add('hidden');
        }

        // Event listener untuk menutup alert saat klik di luar
        document.getElementById('deleteAlert').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteAlert();
            }
        });

        // Event listener untuk tombol escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !document.getElementById('deleteAlert').classList.contains('hidden')) {
                closeDeleteAlert();
            }
        });

        // Update fungsi showDeleteModal menjadi showDeleteAlert
        function showDeleteModal(id) {
            showDeleteAlert(id);
        }
    </script>
</body>

</html> 