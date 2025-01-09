<?php
session_start();
require_once '../backend/database.php';

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
            
        } elseif ($_POST['action'] === 'edit') {
            $id = $_POST['supplier_id'];
            $nama_supplier = $_POST['nama_supplier'];
            $alamat = $_POST['alamat'];
            $telepon = $_POST['telepon'];
            $email = $_POST['email'];
            
            $stmt = $conn->prepare("UPDATE supplier SET nama_supplier = ?, alamat = ?, telepon = ?, email = ? WHERE id = ?");
            $stmt->execute([$nama_supplier, $alamat, $telepon, $email, $id]);
            
        } elseif ($_POST['action'] === 'delete') {
            $id = $_POST['supplier_id'];
            $stmt = $conn->prepare("DELETE FROM supplier WHERE id = ?");
            $stmt->execute([$id]);
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
    } catch(PDOException $e) {
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
    } catch(PDOException $e) {
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
    } catch(PDOException $e) {
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
    <title>Supplier - PAksesories</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <?php include '../components/sidebar.php'; ?>
    <?php include '../components/navbar.php'; ?>

    <div class="ml-64 pt-16 min-h-screen bg-gray-50/50">
        <div class="p-8">
            <!-- Header Section dengan gradient modern -->
            <div class="mb-8 bg-gradient-to-br from-indigo-600 via-blue-500 to-blue-400 rounded-3xl p-10 text-white shadow-2xl relative overflow-hidden">
                <!-- Decorative elements -->
                <div class="absolute top-0 right-0 w-96 h-96 bg-white/10 rounded-full -translate-y-32 translate-x-32 blur-3xl"></div>
                <div class="absolute bottom-0 left-0 w-96 h-96 bg-blue-500/20 rounded-full translate-y-32 -translate-x-32 blur-3xl"></div>
                
                <div class="relative flex justify-between items-center">
                    <div>
                        <h1 class="text-4xl font-bold mb-3">Supplier</h1>
                        <p class="text-blue-100 text-lg">Kelola data supplier produk</p>
                    </div>
                    <button onclick="showAddModal()" 
                            class="px-5 py-3 bg-white/10 hover:bg-white/20 text-white rounded-xl flex items-center gap-3 transition-all duration-300 backdrop-blur-sm">
                        <div class="p-2 bg-white/10 rounded-lg">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                        </div>
                        <span class="font-medium">Tambah Supplier</span>
                    </button>
                </div>
            </div>

            <!-- Table Card dengan glass effect -->
            <div class="bg-white/70 backdrop-blur-xl rounded-3xl shadow-xl border border-gray-200/70">
                <!-- Table Header -->
                <div class="p-6 border-b border-gray-100">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <label class="text-sm font-medium text-gray-600">Menampilkan</label>
                            <div class="px-5 py-2.5 bg-gray-50/50 border border-gray-200/50 rounded-xl text-sm">
                                <span class="text-gray-600"><?= count($suppliers) ?> Supplier</span>
                            </div>
                        </div>

                        <div class="relative">
                            <input type="text" 
                                   id="searchInput"
                                   placeholder="Cari supplier..." 
                                   oninput="searchTable()"
                                   class="w-72 pl-12 pr-4 py-2.5 bg-gray-50/50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/50 transition-all duration-300">
                            <div class="absolute left-4 top-2.5 text-gray-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Table Content -->
                <div class="overflow-x-auto p-1">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-50/50">
                                <th class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider py-4 px-6">No</th>
                                <th class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider py-4 px-6">Nama Supplier</th>
                                <th class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider py-4 px-6">Alamat</th>
                                <th class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider py-4 px-6">Telepon</th>
                                <th class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider py-4 px-6">Email</th>
                                <th class="text-center text-xs font-semibold text-gray-500 uppercase tracking-wider py-4 px-6">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100" id="supplierTableBody">
                            <?php foreach ($suppliers as $index => $supplier): ?>
                            <tr class="hover:bg-gray-50/50 transition-colors duration-200" data-supplier-id="<?= $supplier['id'] ?>">
                                <td class="py-4 px-6 text-sm text-gray-600"><?= $index + 1 ?></td>
                                <td class="py-4 px-6 text-sm text-gray-800 font-medium"><?= htmlspecialchars($supplier['nama_supplier']) ?></td>
                                <td class="py-4 px-6 text-sm text-gray-600"><?= htmlspecialchars($supplier['alamat']) ?></td>
                                <td class="py-4 px-6 text-sm text-gray-600"><?= htmlspecialchars($supplier['telepon']) ?></td>
                                <td class="py-4 px-6 text-sm text-gray-600"><?= htmlspecialchars($supplier['email']) ?></td>
                                <td class="py-4 px-6">
                                    <div class="flex justify-end gap-2">
                                        <button onclick="showPreviewModal(<?= $supplier['id'] ?>)" 
                                                class="p-2 text-indigo-600 hover:bg-indigo-50 rounded-lg transition-all duration-200">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                        </button>
                                        <button onclick="showEditModal(<?= htmlspecialchars(json_encode($supplier)) ?>)" 
                                                class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-all duration-200">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                        </button>
                                        <button onclick="showDeleteModal(<?= $supplier['id'] ?>)" 
                                                class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-all duration-200">
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

                <!-- Pagination section -->
                <div class="p-6 border-t border-gray-100">
                    <!-- ... (sama seperti di kategori.php) ... -->
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

    <!-- Delete Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black/50 z-50 hidden">
        <!-- ... (sama seperti di kategori.php) ... -->
    </div>

    <!-- Preview Modal -->
    <div id="previewModal" class="fixed inset-0 bg-black/50 z-50 hidden">
        <div class="flex items-center justify-center min-h-screen p-4 overflow-hidden">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-4xl max-h-[80vh] flex flex-col">
                <div class="p-6 border-b border-gray-100 flex justify-between items-center">
                    <h3 class="text-xl font-semibold text-gray-800">Produk dari <span id="supplierName" class="text-blue-600"></span></h3>
                    <button onclick="closePreviewModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
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
                                      d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                            </svg>
                        </div>
                        <p class="text-gray-500">Tidak ada produk dari supplier ini</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // ... (fungsi-fungsi JavaScript untuk pagination, search, dll sama seperti di kategori.php) ...
        
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

        function searchSupplier(searchTerm) {
            fetch(`supplier.php?action=search&search=${searchTerm}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const tbody = document.getElementById('supplierTableBody');
                        
                        if (data.suppliers.length > 0) {
                            tbody.innerHTML = data.suppliers.map((supplier, index) => `
                                <tr class="hover:bg-gray-50/50 transition-colors duration-200" data-supplier-id="${supplier.id}">
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
                        } else {
                            tbody.innerHTML = `
                                <tr>
                                    <td colspan="6" class="py-8 text-center text-gray-500">
                                        Tidak ada supplier yang sesuai dengan pencarian
                                    </td>
                                </tr>
                            `;
                        }
                    }
                });
        }

        // Debounce function
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

        // Attach search event with debounce
        const searchInput = document.getElementById('searchInput');
        const debouncedSearch = debounce((e) => searchSupplier(e.target.value), 300);
        searchInput.addEventListener('input', debouncedSearch);
    </script>
</body>
</html> 