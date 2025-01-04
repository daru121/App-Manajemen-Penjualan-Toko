<?php
session_start();
require_once '../backend/database.php';

// Query untuk mengambil semua produk dengan kategori dan stok
$query = "SELECT b.*, k.nama_kategori, COALESCE(s.jumlah, 0) as stok 
          FROM barang b 
          LEFT JOIN kategori k ON b.kategori_id = k.id 
          LEFT JOIN stok s ON b.id = s.barang_id 
          ORDER BY b.nama_barang ASC";
$stmt = $conn->query($query);
$products = $stmt->fetchAll();

// Query untuk mengambil kategori untuk dropdown
$queryKategori = "SELECT * FROM kategori ORDER BY nama_kategori ASC";
$stmtKategori = $conn->query($queryKategori);
$categories = $stmtKategori->fetchAll();

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
                
                // Insert produk with image
                $stmt = $conn->prepare("INSERT INTO barang (nama_barang, gambar, kategori_id, harga_modal, harga) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$nama_barang, $gambar, $kategori_id, $harga_modal, $harga_jual]);
                $barang_id = $conn->lastInsertId();

                // Insert stok
                $stmt = $conn->prepare("INSERT INTO stok (barang_id, jumlah) VALUES (?, ?)");
                $stmt->execute([$barang_id, $stok]);

                $conn->commit();
            } catch(Exception $e) {
                $conn->rollback();
                $_SESSION['error'] = $e->getMessage();
            }
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
                    $stmt = $conn->prepare("UPDATE barang SET nama_barang = ?, gambar = ?, kategori_id = ?, harga_modal = ?, harga = ? WHERE id = ?");
                    $stmt->execute([$nama_barang, $gambar, $kategori_id, $harga_modal, $harga_jual, $id]);
                } else {
                    // Update without changing image
                    $stmt = $conn->prepare("UPDATE barang SET nama_barang = ?, kategori_id = ?, harga_modal = ?, harga = ? WHERE id = ?");
                    $stmt->execute([$nama_barang, $kategori_id, $harga_modal, $harga_jual, $id]);
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
                
                // Set success message
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
            } catch(Exception $e) {
                $conn->rollback();
                throw $e;
            }
        }
        header("Location: produk.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Barang - PAksesories</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <?php include '../components/sidebar.php'; ?>
    <?php include '../components/navbar.php'; ?>

    <div class="ml-64 pt-16 min-h-screen bg-gray-50/50">
        <div class="p-8">
            <!-- Header Section -->
            <div class="mb-8 bg-gradient-to-br from-indigo-600 via-blue-500 to-blue-400 rounded-3xl p-10 text-white shadow-2xl relative overflow-hidden">
                <div class="absolute top-0 right-0 w-64 h-64 bg-white/10 rounded-full -translate-y-32 translate-x-32 blur-3xl"></div>
                <div class="absolute bottom-0 left-0 w-64 h-64 bg-blue-500/20 rounded-full translate-y-32 -translate-x-32 blur-3xl"></div>
                
                <div class="relative flex justify-between items-center">
                    <div>
                        <h1 class="text-4xl font-bold mb-3">Data Barang</h1>
                        <p class="text-blue-100 text-lg">Kelola data produk dan inventory toko Anda</p>
                    </div>
                    <button onclick="showAddModal()" 
                            class="px-5 py-3 bg-white/10 hover:bg-white/20 text-white rounded-xl flex items-center gap-3 transition-all duration-300 backdrop-blur-sm">
                        <div class="p-2 bg-white/10 rounded-lg">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                        </div>
                        <span class="font-medium">Tambah Data</span>
                    </button>
                </div>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div id="successAlert" class="mb-4 p-4 bg-green-50 border border-green-200 text-green-700 rounded-xl flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <?= $_SESSION['success'] ?>
                    </div>
                    <button onclick="this.parentElement.remove()" class="text-green-600 hover:text-green-700">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div id="errorAlert" class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <?= $_SESSION['error'] ?>
                    </div>
                    <button onclick="this.parentElement.remove()" class="text-red-600 hover:text-red-700">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <!-- Table Card -->
            <div class="bg-white/60 backdrop-blur-2xl rounded-3xl shadow-2xl border border-white/20">
                <div class="p-8 border-b border-gray-100/80">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <label class="text-sm font-medium text-gray-600">Menampilkan</label>
                            <div class="px-5 py-2.5 bg-gray-50/50 border border-gray-200/50 rounded-xl text-sm">
                                <span class="text-gray-600"><?= count($products) ?> Barang</span>
                            </div>
                        </div>

                        <div class="relative">
                            <input type="text" 
                                   id="searchInput"
                                   placeholder="Cari produk..." 
                                   oninput="searchTable()"
                                   class="w-80 pl-12 pr-4 py-2.5 bg-gray-50/50 border border-gray-200/50 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30 transition-all duration-300 hover:bg-gray-50/80">
                            <div class="absolute left-4 top-2.5 text-gray-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto p-2">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-50/50">
                                <th class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider py-5 px-6">No</th>
                                <th class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider py-5 px-6">Nama Barang</th>
                                <th class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider py-5 px-6">Kategori</th>
                                <th class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider py-5 px-6">Stok</th>
                                <th class="text-right text-xs font-semibold text-gray-500 uppercase tracking-wider py-5 px-6">Harga Modal</th>
                                <th class="text-right text-xs font-semibold text-gray-500 uppercase tracking-wider py-5 px-6">Harga Jual</th>
                                <th class="text-right text-xs font-semibold text-gray-500 uppercase tracking-wider py-5 px-6">Profit</th>
                                <th class="text-center text-xs font-semibold text-gray-500 uppercase tracking-wider py-5 px-6">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100/80" id="productTableBody">
                            <?php foreach ($products as $index => $product): ?>
                            <tr class="hover:bg-gray-50/50 transition-all duration-300">
                                <td class="py-4 px-6 text-sm text-gray-600"><?= $index + 1 ?></td>
                                <td class="py-4 px-6">
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
                                <td class="py-4 px-6">
                                    <span class="px-3 py-1.5 bg-indigo-50 text-indigo-600 rounded-full text-xs font-medium">
                                        <?= htmlspecialchars($product['nama_kategori']) ?>
                                    </span>
                                </td>
                                <td class="py-4 px-6">
                                    <span class="px-3 py-1.5 bg-<?= $product['stok'] > 0 ? 'green' : 'red' ?>-50 text-<?= $product['stok'] > 0 ? 'green' : 'red' ?>-600 rounded-full text-xs font-medium">
                                        <?= $product['stok'] ?> unit
                                    </span>
                                </td>
                                <td class="py-4 px-6 text-right text-sm text-gray-600">
                                    Rp <?= number_format($product['harga_modal'], 0, ',', '.') ?>
                                </td>
                                <td class="py-4 px-6 text-right text-sm text-gray-600">
                                    Rp <?= number_format($product['harga'], 0, ',', '.') ?>
                                </td>
                                <td class="py-4 px-6 text-right">
                                    <span class="text-sm text-green-600 font-medium">
                                        Rp <?= number_format($product['harga'] - $product['harga_modal'], 0, ',', '.') ?>
                                    </span>
                                </td>
                                <td class="py-4 px-6">
                                    <div class="flex items-center justify-center gap-3">
                                        <button onclick='showEditModal(<?= json_encode([
                                            "id" => $product["id"],
                                            "nama_barang" => $product["nama_barang"],
                                            "kategori_id" => $product["kategori_id"],
                                            "harga_modal" => $product["harga_modal"],
                                            "harga" => $product["harga"],
                                            "stok" => $product["stok"]
                                        ]) ?>)' 
                                                class="p-2 text-blue-600 hover:bg-blue-50 rounded-xl transition-all duration-200 hover:shadow-lg hover:shadow-blue-100">
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

                <!-- Pagination -->
                <div class="p-8 border-t border-gray-100/80">
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-gray-600" id="showingInfo">
                            Showing 1 to <?= min(5, count($products)) ?> of <?= count($products) ?> entries
                        </p>
                        <div class="flex items-center gap-2">
                            <button onclick="changePage('prev')" 
                                    id="prevButton"
                                    class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700 hover:bg-gray-50 rounded-xl disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200">
                                Previous
                            </button>
                            <div id="pageNumbers" class="flex items-center gap-1">
                                <!-- Page numbers will be inserted here by JavaScript -->
                            </div>
                            <button onclick="changePage('next')" 
                                    id="nextButton"
                                    class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700 hover:bg-gray-50 rounded-xl disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200">
                                Next
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Modal -->
    <div id="productModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-2xl p-8 w-full max-w-xl">
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
    <div id="deleteModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
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
    </script>
</body>
</html>
