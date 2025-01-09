<?php
require_once '../backend/check_session.php';
require_once '../backend/database.php';

// Tambahkan tabel pengeluaran jika belum ada
$query = "CREATE TABLE IF NOT EXISTS pengeluaran (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tanggal DATE NOT NULL,
    kategori VARCHAR(50) NOT NULL,
    deskripsi TEXT NOT NULL,
    jumlah DECIMAL(10,2) NOT NULL,
    bukti_foto VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->exec($query);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            try {
                $tanggal = $_POST['tanggal'];
                $kategori = $_POST['kategori'];
                $deskripsi = $_POST['deskripsi'];
                $jumlah = $_POST['jumlah'];
                
                // Handle file upload
                $bukti_foto = null;
                if(isset($_FILES['bukti_foto']) && $_FILES['bukti_foto']['error'] == 0) {
                    $target_dir = "../uploads/pengeluaran/";
                    $ext = pathinfo($_FILES['bukti_foto']['name'], PATHINFO_EXTENSION);
                    $bukti_foto = uniqid() . '.' . $ext;
                    move_uploaded_file($_FILES['bukti_foto']['tmp_name'], $target_dir . $bukti_foto);
                }
                
                $stmt = $conn->prepare("INSERT INTO pengeluaran (tanggal, kategori, deskripsi, jumlah, bukti_foto) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$tanggal, $kategori, $deskripsi, $jumlah, $bukti_foto]);
                
                header("Location: pengeluaran.php?success=1");
                exit;
            } catch(PDOException $e) {
                $error = $e->getMessage();
            }
        } elseif ($_POST['action'] === 'edit') {
            try {
                $id = $_POST['id'];
                $tanggal = $_POST['tanggal'];
                $kategori = $_POST['kategori'];
                $deskripsi = $_POST['deskripsi'];
                $jumlah = $_POST['jumlah'];
                
                // Ambil info foto sebelum menghapus
                $stmt = $conn->prepare("SELECT bukti_foto FROM pengeluaran WHERE id = ?");
                $stmt->execute([$id]);
                $foto = $stmt->fetchColumn();
                
                // Hapus file foto jika ada
                if($foto && file_exists("../uploads/pengeluaran/".$foto)) {
                    unlink("../uploads/pengeluaran/".$foto);
                }
                
                // Hapus data dari database
                $stmt = $conn->prepare("DELETE FROM pengeluaran WHERE id = ?");
                $stmt->execute([$id]);
                
                $_SESSION['success'] = "Data pengeluaran berhasil dihapus";
                header("Location: pengeluaran.php");
                exit;
            } catch(PDOException $e) {
                $_SESSION['error'] = "Gagal menghapus data: " . $e->getMessage();
                header("Location: pengeluaran.php");
                exit;
            }
        } elseif ($_POST['action'] === 'delete') {
            try {
                $id = $_POST['id'];
                
                // Ambil info foto sebelum menghapus
                $stmt = $conn->prepare("SELECT bukti_foto FROM pengeluaran WHERE id = ?");
                $stmt->execute([$id]);
                $foto = $stmt->fetchColumn();
                
                // Hapus file foto jika ada
                if($foto && file_exists("../uploads/pengeluaran/".$foto)) {
                    unlink("../uploads/pengeluaran/".$foto);
                }
                
                // Hapus data dari database
                $stmt = $conn->prepare("DELETE FROM pengeluaran WHERE id = ?");
                $stmt->execute([$id]);
                
                $_SESSION['success'] = "Data pengeluaran berhasil dihapus";
                header("Location: pengeluaran.php");
                exit;
            } catch(PDOException $e) {
                $_SESSION['error'] = "Gagal menghapus data: " . $e->getMessage();
                header("Location: pengeluaran.php");
                exit;
            }
        }
    }
}

// Tambahkan notifikasi sukses/error jika ada
if (isset($_SESSION['success'])) {
    echo "<div class='fixed top-4 right-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded z-50' role='alert'>
            <p class='text-sm'>{$_SESSION['success']}</p>
          </div>";
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    echo "<div class='fixed top-4 right-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded z-50' role='alert'>
            <p class='text-sm'>{$_SESSION['error']}</p>
          </div>";
    unset($_SESSION['error']);
}

// Get all pengeluaran
$query = "SELECT * FROM pengeluaran ORDER BY tanggal DESC";
$stmt = $conn->query($query);
$pengeluaran = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengeluaran - PAksesories</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <?php include '../components/sidebar.php'; ?>
    <?php include '../components/navbar.php'; ?>

    <div class="ml-64 p-8 pt-24">
        <!-- Header -->
        <div class="mb-8 bg-gradient-to-br from-indigo-600 via-blue-500 to-blue-400 rounded-3xl p-10 text-white shadow-2xl relative overflow-hidden">
            <div class="absolute top-0 right-0 w-96 h-96 bg-white/10 rounded-full -translate-y-32 translate-x-32 blur-3xl"></div>
            <div class="absolute bottom-0 left-0 w-96 h-96 bg-blue-500/20 rounded-full translate-y-32 -translate-x-32 blur-3xl"></div>
            
            <div class="relative flex justify-between items-center">
                <div>
                    <h1 class="text-4xl font-bold mb-3">Pengeluaran</h1>
                    <p class="text-blue-100 text-lg">Kelola data pengeluaran operasional</p>
                </div>
                <button onclick="showAddModal()" 
                        class="px-5 py-3 bg-white/10 hover:bg-white/20 text-white rounded-xl flex items-center gap-3 transition-all duration-300 backdrop-blur-sm">
                    <div class="p-2 bg-white/10 rounded-lg">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                    </div>
                    <span class="font-medium">Tambah Pengeluaran</span>
                </button>
            </div>
        </div>

        <!-- Filter -->
        <div class="mb-6 bg-white rounded-3xl shadow-sm border border-gray-100 p-6">
            <div class="flex flex-wrap gap-4">
                <!-- Filter Kategori -->
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Kategori</label>
                    <select id="filterKategori" onchange="applyFilters()" 
                            class="w-full px-4 py-2 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                        <option value="">Semua Kategori</option>
                        <option value="Listrik">Listrik</option>
                        <option value="Plastik">Plastik</option>
                        <option value="Internet">Internet</option>
                        <option value="Lainnya">Lainnya</option>
                    </select>
                </div>

                <!-- Filter Periode -->
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Periode</label>
                    <select id="filterPeriode" onchange="toggleDateInputs(); applyFilters()"
                            class="w-full px-4 py-2 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                        <option value="">Semua Waktu</option>
                        <option value="harian">Harian</option>
                        <option value="bulanan">Bulanan</option>
                        <option value="tahunan">Tahunan</option>
                    </select>
                </div>

                <!-- Input tanggal (awalnya hidden) -->
                <div id="dateInputs" class="hidden flex-1 min-w-[200px]">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal</label>
                    <input type="date" id="filterTanggal" onchange="applyFilters()"
                           class="w-full px-4 py-2 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                </div>

                <!-- Input bulan (awalnya hidden) -->
                <div id="monthInputs" class="hidden flex-1 min-w-[200px]">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Bulan</label>
                    <input type="month" id="filterBulan" onchange="applyFilters()"
                           class="w-full px-4 py-2 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                </div>

                <!-- Input tahun (awalnya hidden) -->
                <div id="yearInputs" class="hidden flex-1 min-w-[200px]">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tahun</label>
                    <select id="filterTahun" onchange="applyFilters()"
                            class="w-full px-4 py-2 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                        <option value="">Pilih Tahun</option>
                        <?php
                        $currentYear = date('Y');
                        for($year = $currentYear; $year >= $currentYear - 5; $year--) {
                            echo "<option value='$year'>$year</option>";
                        }
                        ?>
                    </select>
                </div>

                <!-- Reset Filter -->
                <div class="flex items-end">
                    <button onclick="resetFilters()" 
                            class="px-4 py-2 text-gray-500 hover:text-gray-700 hover:bg-gray-50 rounded-xl transition-colors">
                        Reset Filter
                    </button>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="bg-white rounded-3xl shadow-sm border border-gray-100">
            <div class="p-6">
                <table id="pengeluaranTable" class="w-full">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Tanggal</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Kategori</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Deskripsi</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Jumlah</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold text-gray-500 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($pengeluaran as $item): ?>
                        <tr class="hover:bg-gray-50/50">
                            <td class="px-6 py-4 text-sm text-gray-600" data-tanggal="<?= $item['tanggal'] ?>">
                                <?= date('d/m/Y', strtotime($item['tanggal'])) ?>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-3 py-1 text-xs font-medium rounded-full
                                    <?php
                                    switch($item['kategori']) {
                                        case 'Listrik':
                                            echo 'bg-yellow-100 text-yellow-700';
                                            break;
                                        case 'Plastik':
                                            echo 'bg-green-100 text-green-700';
                                            break;
                                        default:
                                            echo 'bg-blue-100 text-blue-700';
                                    }
                                    ?>">
                                    <?= $item['kategori'] ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600"><?= $item['deskripsi'] ?></td>
                            <td class="px-6 py-4 text-sm font-medium text-blue-600" data-jumlah="<?= $item['jumlah'] ?>">
                                Rp <?= number_format($item['jumlah'], 0, ',', '.') ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex justify-end gap-2">
                                    <!-- Preview Button -->
                                    <button onclick="showDetail(<?= htmlspecialchars(json_encode($item)) ?>)" 
                                            class="p-2 text-indigo-600 hover:bg-indigo-50 rounded-lg transition-all duration-200">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </button>
                                    <!-- Edit Button -->
                                    <button onclick="showEditModal(<?= htmlspecialchars(json_encode($item)) ?>)" 
                                            class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-all duration-200">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                  d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </button>
                                    <!-- Delete Button -->
                                    <button onclick="showDeleteModal(<?= $item['id'] ?>)" 
                                            class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-all duration-200">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="bg-gray-50">
                            <td colspan="3" class="px-6 py-4 text-sm font-medium text-gray-700">Total Pengeluaran:</td>
                            <td colspan="2" class="px-6 py-4 text-sm font-semibold text-blue-600" id="totalPengeluaran">
                                Rp 0
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Modal -->
    <div id="addModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl w-full max-w-md mx-4">
            <form action="" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                
                <div class="p-6 border-b border-gray-100">
                    <h3 class="text-lg font-semibold text-gray-800">Tambah Pengeluaran</h3>
                </div>

                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal</label>
                        <input type="date" name="tanggal" required
                               class="w-full px-4 py-2 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Kategori</label>
                        <select name="kategori" required
                                class="w-full px-4 py-2 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                            <option value="Listrik">Listrik</option>
                            <option value="Plastik">Plastik</option>
                            <option value="Internet">Internet</option>
                            <option value="Lainnya">Lainnya</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Deskripsi</label>
                        <textarea name="deskripsi" required
                                  class="w-full px-4 py-2 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Jumlah (Rp)</label>
                        <input type="number" name="jumlah" required
                               class="w-full px-4 py-2 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Bukti Foto</label>
                        <input type="file" name="bukti_foto" accept="image/*"
                               class="w-full px-4 py-2 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                    </div>
                </div>

                <div class="p-6 border-t border-gray-100 flex justify-end gap-4">
                    <button type="button" onclick="closeAddModal()"
                            class="px-4 py-2 text-gray-500 hover:text-gray-700">
                        Batal
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tambahkan Modal Detail setelah Add Modal -->
    <div id="detailModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl w-full max-w-lg mx-4">
            <div class="p-6 border-b border-gray-100 flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-800">Detail Pengeluaran</h3>
                <button onclick="closeDetailModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="p-6">
                <div class="space-y-6">
                    <!-- Info Pengeluaran -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-500 mb-1">Tanggal</p>
                            <p class="font-medium text-gray-800" id="detailTanggal"></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 mb-1">Kategori</p>
                            <p class="font-medium" id="detailKategori"></p>
                        </div>
                        <div class="col-span-2">
                            <p class="text-sm text-gray-500 mb-1">Deskripsi</p>
                            <p class="font-medium text-gray-800" id="detailDeskripsi"></p>
                        </div>
                        <div class="col-span-2">
                            <p class="text-sm text-gray-500 mb-1">Jumlah</p>
                            <p class="text-lg font-semibold text-blue-600" id="detailJumlah"></p>
                        </div>
                    </div>

                    <!-- Bukti Foto -->
                    <div>
                        <p class="text-sm text-gray-500 mb-2">Bukti Foto</p>
                        <div class="aspect-video rounded-xl overflow-hidden bg-gray-100">
                            <img id="detailFoto" src="" alt="Bukti Pengeluaran" 
                                 class="w-full h-full object-contain">
                            <div id="noFoto" class="hidden w-full h-full flex items-center justify-center text-gray-400">
                                <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" 
                                          d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tambahkan Modal Edit setelah Modal Detail -->
    <div id="editModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl w-full max-w-md mx-4">
            <form action="" method="POST" enctype="multipart/form-data" id="editForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editId">
                
                <div class="p-6 border-b border-gray-100 flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-800">Edit Pengeluaran</h3>
                    <button type="button" onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal</label>
                        <input type="date" name="tanggal" id="editTanggal" required
                               class="w-full px-4 py-2 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Kategori</label>
                        <select name="kategori" id="editKategori" required
                                class="w-full px-4 py-2 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                            <option value="Listrik">⚡Listrik</option>
                            <option value="Internet">🌐Internet</option>
                            <option value="Promosi">📢Promosi</option>
                            <option value="Perlengkapan packing">📦Perlengkapan packing</option>
                            <option value="Lainnya">Lainnya</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Deskripsi</label>
                        <textarea name="deskripsi" id="editDeskripsi" required
                                  class="w-full px-4 py-2 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Jumlah (Rp)</label>
                        <input type="number" name="jumlah" id="editJumlah" required
                               class="w-full px-4 py-2 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Bukti Foto Baru (Opsional)</label>
                        <input type="file" name="bukti_foto" accept="image/*"
                               class="w-full px-4 py-2 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                    </div>
                </div>

                <div class="p-6 border-t border-gray-100 flex justify-end gap-4">
                    <button type="button" onclick="closeEditModal()"
                            class="px-4 py-2 text-gray-500 hover:text-gray-700">
                        Batal
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tambahkan Modal Delete setelah Modal Edit -->
    <div id="deleteModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl w-full max-w-md mx-4">
            <form action="" method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteId">
                
                <div class="p-6 text-center">
                    <div class="w-20 h-20 rounded-full bg-red-50 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-10 h-10 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Konfirmasi Hapus</h3>
                    <p class="text-gray-500 mb-6">Apakah Anda yakin ingin menghapus data pengeluaran ini?</p>
                    
                    <div class="flex justify-center gap-4">
                        <button type="button" onclick="closeDeleteModal()"
                                class="px-4 py-2 text-gray-500 hover:text-gray-700">
                            Batal
                        </button>
                        <button type="submit"
                                class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600">
                            Hapus
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showAddModal() {
            document.getElementById('addModal').classList.remove('hidden');
            document.getElementById('addModal').classList.add('flex');
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.add('hidden');
            document.getElementById('addModal').classList.remove('flex');
        }

        function showDetail(item) {
            // Format tanggal
            const tanggal = new Date(item.tanggal).toLocaleDateString('id-ID', {
                day: '2-digit',
                month: 'long',
                year: 'numeric'
            });

            // Format jumlah uang
            const jumlah = new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR'
            }).format(item.jumlah);

            // Update konten modal
            document.getElementById('detailTanggal').textContent = tanggal;
            document.getElementById('detailKategori').textContent = item.kategori;
            document.getElementById('detailDeskripsi').textContent = item.deskripsi;
            document.getElementById('detailJumlah').textContent = jumlah;

            // Handle foto
            const fotoElement = document.getElementById('detailFoto');
            const noFotoElement = document.getElementById('noFoto');

            if (item.bukti_foto) {
                fotoElement.src = '../uploads/pengeluaran/' + item.bukti_foto;
                fotoElement.classList.remove('hidden');
                noFotoElement.classList.add('hidden');
            } else {
                fotoElement.classList.add('hidden');
                noFotoElement.classList.remove('hidden');
            }

            // Tampilkan modal
            document.getElementById('detailModal').classList.remove('hidden');
            document.getElementById('detailModal').classList.add('flex');
        }

        function closeDetailModal() {
            document.getElementById('detailModal').classList.add('hidden');
            document.getElementById('detailModal').classList.remove('flex');
        }

        // Tambahkan event listener untuk menutup modal saat klik di luar
        document.getElementById('detailModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDetailModal();
            }
        });

        function showEditModal(item) {
            document.getElementById('editId').value = item.id;
            document.getElementById('editTanggal').value = item.tanggal;
            document.getElementById('editKategori').value = item.kategori;
            document.getElementById('editDeskripsi').value = item.deskripsi;
            document.getElementById('editJumlah').value = item.jumlah;

            document.getElementById('editModal').classList.remove('hidden');
            document.getElementById('editModal').classList.add('flex');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
            document.getElementById('editModal').classList.remove('flex');
        }

        // Tambahkan event listener untuk menutup modal edit saat klik di luar
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });

        function toggleDateInputs() {
            const periode = document.getElementById('filterPeriode').value;
            const dateInputs = document.getElementById('dateInputs');
            const monthInputs = document.getElementById('monthInputs');
            const yearInputs = document.getElementById('yearInputs');

            // Sembunyikan semua input terlebih dahulu
            dateInputs.classList.add('hidden');
            monthInputs.classList.add('hidden');
            yearInputs.classList.add('hidden');

            // Tampilkan input sesuai periode yang dipilih
            switch(periode) {
                case 'harian':
                    dateInputs.classList.remove('hidden');
                    break;
                case 'bulanan':
                    monthInputs.classList.remove('hidden');
                    break;
                case 'tahunan':
                    yearInputs.classList.remove('hidden');
                    break;
            }
        }

        function applyFilters() {
            const rows = document.querySelectorAll('#pengeluaranTable tbody tr');
            const kategori = document.getElementById('filterKategori').value;
            const periode = document.getElementById('filterPeriode').value;
            const tanggal = document.getElementById('filterTanggal').value;
            const bulan = document.getElementById('filterBulan').value;
            const tahun = document.getElementById('filterTahun').value;

            rows.forEach(row => {
                let showRow = true;
                const rowKategori = row.querySelector('td:nth-child(2)').textContent.trim();
                const rowTanggal = row.querySelector('td:nth-child(1)').getAttribute('data-tanggal');

                // Filter kategori
                if (kategori && rowKategori !== kategori) {
                    showRow = false;
                }

                // Filter berdasarkan periode
                if (periode) {
                    switch(periode) {
                        case 'harian':
                            if (tanggal && rowTanggal !== tanggal) {
                                showRow = false;
                            }
                            break;
                        case 'bulanan':
                            if (bulan && !rowTanggal.startsWith(bulan)) {
                                showRow = false;
                            }
                            break;
                        case 'tahunan':
                            if (tahun && !rowTanggal.startsWith(tahun)) {
                                showRow = false;
                            }
                            break;
                    }
                }

                row.classList.toggle('hidden', !showRow);
            });

            updateTotalPengeluaran();
        }

        function resetFilters() {
            document.getElementById('filterKategori').value = '';
            document.getElementById('filterPeriode').value = '';
            document.getElementById('filterTanggal').value = '';
            document.getElementById('filterBulan').value = '';
            document.getElementById('filterTahun').value = '';
            
            const rows = document.querySelectorAll('#pengeluaranTable tbody tr');
            rows.forEach(row => row.classList.remove('hidden'));
            
            toggleDateInputs();
            updateTotalPengeluaran();
        }

        function updateTotalPengeluaran() {
            const visibleRows = document.querySelectorAll('#pengeluaranTable tbody tr:not(.hidden)');
            let total = 0;

            visibleRows.forEach(row => {
                const jumlah = parseInt(row.querySelector('td:nth-child(4)').getAttribute('data-jumlah'));
                total += jumlah;
            });

            document.getElementById('totalPengeluaran').textContent = 
                new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(total);
        }

        function showDeleteModal(id) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteModal').classList.remove('hidden');
            document.getElementById('deleteModal').classList.add('flex');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
            document.getElementById('deleteModal').classList.remove('flex');
        }

        // Tambahkan event listener untuk menutup modal delete saat klik di luar
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });
    </script>
</body>
</html> 