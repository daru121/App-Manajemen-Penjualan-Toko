<?php
require_once '../backend/check_session.php';
require_once '../backend/database.php';

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
                    if (!file_exists($target_dir)) {
                        mkdir($target_dir, 0777, true);
                    }
                    $ext = pathinfo($_FILES['bukti_foto']['name'], PATHINFO_EXTENSION);
                    $bukti_foto = uniqid() . '.' . $ext;
                    move_uploaded_file($_FILES['bukti_foto']['tmp_name'], $target_dir . $bukti_foto);
                }
                
                $stmt = $conn->prepare("INSERT INTO pengeluaran (tanggal, kategori, deskripsi, jumlah, bukti_foto) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$tanggal, $kategori, $deskripsi, $jumlah, $bukti_foto]);
                
                $_SESSION['alert'] = [
                    'type' => 'success',
                    'message' => 'Data pengeluaran berhasil ditambahkan!'
                ];
                header("Location: pengeluaran.php");
                exit;
            } catch(PDOException $e) {
                $_SESSION['alert'] = [
                    'type' => 'error',
                    'message' => 'Gagal menambah data: ' . $e->getMessage()
                ];
                header("Location: pengeluaran.php");
                exit;
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
                
                $_SESSION['alert'] = [
                    'type' => 'success',
                    'message' => 'Data pengeluaran berhasil diperbarui!'
                ];
                header("Location: pengeluaran.php");
                exit;
            } catch(PDOException $e) {
                $_SESSION['alert'] = [
                    'type' => 'error',
                    'message' => 'Gagal memperbarui data: ' . $e->getMessage()
                ];
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
                
                $_SESSION['alert'] = [
                    'type' => 'success',
                    'message' => 'Data pengeluaran berhasil dihapus!'
                ];
                header("Location: pengeluaran.php");
                exit;
            } catch(PDOException $e) {
                $_SESSION['alert'] = [
                    'type' => 'error',
                    'message' => 'Gagal menghapus data: ' . $e->getMessage()
                ];
                header("Location: pengeluaran.php");
                exit;
            }
        }
    }
}

// Tambahkan fungsi helper untuk filter
function getFilteredPengeluaran($conn, $kategori = '', $periode = '', $tanggal = '', $bulan = '', $tahun = '') {
    $params = [];
    $where = [];
    
    $query = "SELECT * FROM pengeluaran WHERE 1=1";
    
    // Filter kategori
    if (!empty($kategori)) {
        $where[] = "kategori = ?";
        $params[] = $kategori;
    }
    
    // Filter berdasarkan periode
    if (!empty($periode)) {
        switch ($periode) {
            case 'harian':
                if (!empty($tanggal)) {
                    $where[] = "DATE(tanggal) = ?";
                    $params[] = $tanggal;
                }
                break;
            case 'bulanan':
                if (!empty($bulan)) {
                    $where[] = "DATE_FORMAT(tanggal, '%Y-%m') = ?";
                    $params[] = $bulan;
                }
                break;
            case 'tahunan':
                if (!empty($tahun)) {
                    $where[] = "YEAR(tanggal) = ?";
                    $params[] = $tahun;
                }
                break;
        }
    }
    
    if (!empty($where)) {
        $query .= " AND " . implode(" AND ", $where);
    }
    
    $query .= " ORDER BY tanggal DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Handle AJAX request untuk filter
if (isset($_GET['ajax_filter'])) {
    header('Content-Type: application/json');
    try {
        $kategori = $_GET['kategori'] ?? '';
        $periode = $_GET['periode'] ?? '';
        $tanggal = $_GET['tanggal'] ?? '';
        $bulan = $_GET['bulan'] ?? '';
        $tahun = $_GET['tahun'] ?? '';
        
        $params = [];
        $where = [];
        
        $query = "SELECT * FROM pengeluaran WHERE 1=1";
        
        // Filter kategori
        if (!empty($kategori)) {
            $where[] = "kategori = ?";
            $params[] = $kategori;
        }
        
        // Filter berdasarkan periode
        if (!empty($periode)) {
            switch ($periode) {
                case 'harian':
                    if (!empty($tanggal)) {
                        $where[] = "DATE(tanggal) = ?";
                        $params[] = $tanggal;
                    }
                    break;
                case 'bulanan':
                    if (!empty($bulan)) {
                        $where[] = "DATE_FORMAT(tanggal, '%Y-%m') = ?";
                        $params[] = $bulan;
                    }
                    break;
                case 'tahunan':
                    if (!empty($tahun)) {
                        $where[] = "YEAR(tanggal) = ?";
                        $params[] = $tahun;
                    }
                    break;
            }
        }
        
        if (!empty($where)) {
            $query .= " AND " . implode(" AND ", $where);
        }
        
        $query .= " ORDER BY tanggal DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $filtered_data = $stmt->fetchAll();
        
        // Hitung total
        $total = array_sum(array_column($filtered_data, 'jumlah'));
        
        echo json_encode([
            'success' => true,
            'data' => $filtered_data,
            'total' => $total
        ]);
        exit;
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Get all pengeluaran with default filter for today's date
$today = date('Y-m-d');
$query = "SELECT * FROM pengeluaran WHERE tanggal = ? ORDER BY tanggal DESC";
$stmt = $conn->prepare($query);
$stmt->execute([$today]);
$pengeluaran = $stmt->fetchAll();

// Get total pengeluaran for today
$queryTotal = "SELECT SUM(jumlah) as total FROM pengeluaran WHERE tanggal = ?";
$stmtTotal = $conn->prepare($queryTotal);
$stmtTotal->execute([$today]);
$totalHariIni = $stmtTotal->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Add this function to handle AJAX requests for statistics
if (isset($_GET['start']) && isset($_GET['end'])) {
    try {
        $start_date = $_GET['start'];
        $end_date = $_GET['end'];

        // Get summary per category
        $query = "SELECT 
                    kategori,
                    COUNT(*) as jumlah_transaksi,
                    SUM(jumlah) as total_pengeluaran
                  FROM pengeluaran 
                  WHERE tanggal BETWEEN ? AND ?
                  GROUP BY kategori";
                  
        $stmt = $conn->prepare($query);
        $stmt->execute([$start_date, $end_date]);
        $summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate total
        $total = array_sum(array_column($summary, 'total_pengeluaran'));

        // Calculate percentages and prepare data for chart
        $labels = [];
        $values = [];
        
        foreach ($summary as &$item) {
            $labels[] = $item['kategori'];
            $values[] = $item['total_pengeluaran'];
            $item['percentage'] = $total > 0 ? ($item['total_pengeluaran'] / $total) * 100 : 0;
        }

        header('Content-Type: application/json');
        echo json_encode([
            'labels' => $labels,
            'values' => $values,
            'summary' => $summary,
            'period' => [
                'start' => $start_date,
                'end' => $end_date
            ]
        ]);
        exit;
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

function getCategoryBadgeClass($kategori) {
    return [
        'bg' => 'bg-blue-100',
        'text' => 'text-blue-700'
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengeluaran - Jamu Air Mancur</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-50">
    <?php include '../components/sidebar.php'; ?>
    <?php include '../components/navbar.php'; ?>

    <div class="ml-0 md:ml-64 p-4 md:p-8 pt-20 md:pt-24">
        <!-- Header -->
        <div class="mb-6 md:mb-8 bg-gradient-to-br from-indigo-600 via-blue-500 to-blue-400 rounded-2xl md:rounded-3xl p-6 md:p-10 text-white shadow-2xl relative overflow-hidden">
            <!-- Decorative elements -->
            <div class="absolute top-0 right-0 w-96 h-96 bg-white/10 rounded-full -translate-y-32 translate-x-32 blur-3xl"></div>
            <div class="absolute bottom-0 left-0 w-96 h-96 bg-blue-500/20 rounded-full translate-y-32 -translate-x-32 blur-3xl"></div>
            
            <div class="relative flex flex-col sm:flex-row justify-between sm:items-center gap-4">
                <div>
                    <h1 class="text-2xl sm:text-4xl font-bold mb-2 sm:mb-3">Pengeluaran</h1>
                    <p class="text-blue-100 text-base sm:text-lg">Kelola data pengeluaran operasional</p>
                </div>
                <button onclick="showAddModal()" 
                        class="w-full sm:w-auto px-4 sm:px-5 py-3 bg-white/10 hover:bg-white/20 text-white rounded-xl flex items-center justify-center sm:justify-start gap-3 transition-all duration-300 backdrop-blur-sm">
                    <div class="p-2 bg-white/10 rounded-lg">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                    </div>
                    <span class="font-medium">Tambah Pengeluaran</span>
                </button>
            </div>
        </div>

        <!-- Alert -->
        <?php if (isset($_SESSION['alert'])): ?>
            <div id="alert" class="mb-6 bg-[#F0FDF4] border-l-4 border-[#16A34A] p-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-[#16A34A]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-[#15803D]">
                                <?= $_SESSION['alert']['message'] ?>
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
            <?php unset($_SESSION['alert']); ?>
        <?php endif; ?>

        <!-- Tab Navigation -->
        <div class="p-1.5 bg-gray-100/80 backdrop-blur-xl rounded-xl md:rounded-2xl flex flex-col md:flex-row gap-2 shadow-sm mb-4 md:mb-6">
            <button onclick="switchTab('data')" class="flex items-center gap-2 px-6 py-3 rounded-xl font-medium transition-all duration-300
                    <?= !isset($_GET['tab']) || $_GET['tab'] === 'data' ? 
                        'bg-white text-blue-600 shadow-lg shadow-blue-500/10 scale-[1.02] ring-1 ring-black/5' : 
                        'text-gray-500 hover:text-gray-600 hover:bg-white/50' ?>">
                <svg class="w-4 md:w-5 h-4 md:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                        d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                </svg>
                Data Pengeluaran
            </button>
            <button onclick="switchTab('statistik')" class="flex items-center gap-2 px-6 py-3 rounded-xl font-medium transition-all duration-300
                    <?= isset($_GET['tab']) && $_GET['tab'] === 'statistik' ? 
                        'bg-white text-blue-600 shadow-lg shadow-blue-500/10 scale-[1.02] ring-1 ring-black/5' : 
                        'text-gray-500 hover:text-gray-600 hover:bg-white/50' ?>">
                <svg class="w-4 md:w-5 h-4 md:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                Statistik
            </button>
        </div>

        <!-- Tab Content -->
        <div id="dataTab">
            <!-- Filter -->
            <div class="mb-4 md:mb-6 bg-white rounded-xl md:rounded-3xl shadow-sm border border-gray-100 p-4 md:p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4">
                    <!-- Filter Kategori -->
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Kategori</label>
                        <select id="filterKategori" onchange="applyFilters()" 
                                class="w-full px-4 py-2 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                            <option value="">Semua Kategori</option>
                            <option value="Listrik">Listrik</option>
                            <option value="Gaji">Gaji</option>
                            <option value="Internet">Internet</option>
                            <option value="Maintenance">Maintenance</option>
                            <option value="Peralatan">Peralatan</option>
                            <option value="Sewa">Sewa</option>
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
                    <div id="dateInputs" class="flex-1 min-w-[200px]">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal</label>
                        <input type="date" id="filterTanggal" value="<?= $today ?>" onchange="applyFilters()"
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
                        <input type="number" 
                               id="filterTahun" 
                               min="2000" 
                               max="2099" 
                               value="<?= date('Y') ?>"
                               class="w-full px-4 py-2 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
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
            <div class="bg-white rounded-xl md:rounded-3xl shadow-sm border border-gray-100 overflow-x-auto">
                <div class="p-4 md:p-6">
                    <table id="pengeluaranTable" class="w-full min-w-[800px]">
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
                                    <?php
                                    $badgeClass = getCategoryBadgeClass($item['kategori']);
                                    ?>
                                    <div class="inline-flex items-center py-1 px-3 rounded-full text-sm font-medium
                                        <?= $badgeClass['bg'] ?> <?= $badgeClass['text'] ?>">
                                        <?= $item['kategori'] ?>
                                    </div>
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
                                    Rp <?= number_format($totalHariIni, 0, ',', '.') ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div id="statistikTab" class="hidden">
            <!-- Filter Periode -->
            <div class="mb-6 bg-white rounded-3xl shadow-sm border border-gray-100 p-6">
                <div class="flex flex-wrap gap-4">
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Periode</label>
                        <select id="statistikPeriode" onchange="updateStatistik()" 
                                class="w-full px-4 py-2 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                            <option value="bulanan">Bulanan</option>
                            <option value="mingguan">Mingguan</option>
                            <option value="tahunan">Tahunan</option>
                            <option value="custom">Periode Tertentu</option>
                        </select>
                    </div>

                    <!-- Periode Mingguan -->
                    <div id="weekRange" class="hidden flex-1 min-w-[200px]">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Pilih Tanggal Awal</label>
                        <input type="date" id="weekStartDate" 
                               onchange="updateWeekRange(this.value)"
                               class="w-full px-4 py-2 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                        <div class="mt-2">
                            <span class="text-sm text-gray-600">Rentang: </span>
                            <span class="text-sm font-medium text-gray-800" id="weekRangeText"></span>
                        </div>
                    </div>

                    <!-- Periode Bulanan -->
                    <div id="monthPicker" class="hidden flex-1 min-w-[200px]">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Pilih Bulan</label>
                        <input type="month" id="monthInput" 
                               value="<?= date('Y-m') ?>"
                               onchange="updateStatistik()"
                               class="w-full px-4 py-2 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                    </div>

                    <!-- Periode Tahunan -->
                    <div id="yearPicker" class="hidden flex-1 min-w-[200px]">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Pilih Tahun</label>
                        <select id="yearInput" onchange="updateStatistik()"
                                class="w-full px-4 py-2 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                            <?php 
                            $currentYear = date('Y');
                            for($year = $currentYear; $year >= $currentYear - 5; $year--) {
                                $selected = $year == $currentYear ? 'selected' : '';
                                echo "<option value='$year' $selected>$year</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <!-- Custom date range (initially hidden) -->
                    <div id="customDateRange" class="hidden flex-1 min-w-[200px]">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Dari Tanggal</label>
                        <input type="date" id="startDate" onchange="updateStatistik()"
                               class="w-full px-4 py-2 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                    </div>

                    <div id="customDateRangeEnd" class="hidden flex-1 min-w-[200px]">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Sampai Tanggal</label>
                        <input type="date" id="endDate" onchange="updateStatistik()"
                               class="w-full px-4 py-2 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                    </div>
                </div>
            </div>

            <!-- Existing grid content -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Pie Chart Card -->
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Distribusi Pengeluaran per Kategori</h3>
                    <div class="relative" style="height: 450px;">
                        <canvas id="pieChart"></canvas>
                    </div>
                </div>

                <!-- Summary Card -->
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Ringkasan Pengeluaran</h3>
                    <div class="space-y-4">
                        <?php
                        // Get summary per category
                        $querySummary = "SELECT kategori, COUNT(*) as jumlah_transaksi, SUM(jumlah) as total_pengeluaran 
                                        FROM pengeluaran 
                                        WHERE tanggal = ? 
                                        GROUP BY kategori";
                        $stmtSummary = $conn->prepare($querySummary);
                        $stmtSummary->execute([$today]);
                        $summaryData = $stmtSummary->fetchAll();

                        foreach ($summaryData as $summary):
                            $percentage = ($summary['total_pengeluaran'] / $totalHariIni) * 100;
                        ?>
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600"><?= $summary['kategori'] ?></p>
                                <p class="text-lg font-semibold text-gray-800">
                                    Rp <?= number_format($summary['total_pengeluaran'], 0, ',', '.') ?>
                                </p>
                                <p class="text-xs text-gray-500"><?= $summary['jumlah_transaksi'] ?> transaksi</p>
                            </div>
                            <div class="text-right">
                                <span class="text-sm font-medium text-gray-600">
                                    <?= number_format($percentage, 1) ?>%
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Modal -->
    <div id="addModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-xl md:rounded-2xl w-[95%] md:w-full max-w-md mx-auto my-4 max-h-[90vh] overflow-y-auto">
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
                            <option value="Gaji">Gaji</option>
                            <option value="Internet">Internet</option>
                            <option value="Maintenance">Maintenance</option>
                            <option value="Peralatan">Peralatan</option>
                            <option value="Sewa">Sewa</option>
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

    <!-- Modal Detail -->
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
            
            // Tampilkan input yang sesuai
            switch(periode) {
                case 'harian':
                    dateInputs.classList.remove('hidden');
                    break;
                case 'bulanan':
                    monthInputs.classList.remove('hidden');
                    break;
                case 'tahunan':
                    yearInputs.classList.remove('hidden');
                    // Set tahun sekarang sebagai default jika belum ada value
                    if (!document.getElementById('filterTahun').value) {
                        document.getElementById('filterTahun').value = new Date().getFullYear();
                    }
                    break;
            }
        }

        function applyFilters() {
            const kategori = document.getElementById('filterKategori').value;
            const periode = document.getElementById('filterPeriode').value;
            const tanggal = document.getElementById('filterTanggal').value;
            const bulan = document.getElementById('filterBulan').value;
            const tahun = document.getElementById('filterTahun').value;
            
            // Build query string
            const params = new URLSearchParams({
                ajax_filter: 1,
                kategori: kategori,
                periode: periode,
                tanggal: tanggal,
                bulan: bulan,
                tahun: tahun
            });
            
            // Fetch filtered data
            fetch(`pengeluaran.php?${params}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateTable(data.data);
                        updateTotal(data.total);
                    } else {
                        console.error('Error:', data.message);
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        function updateTable(data) {
            const tbody = document.querySelector('table tbody');
            tbody.innerHTML = '';
            
            data.forEach((item, index) => {
                const row = document.createElement('tr');
                row.className = 'border-b border-gray-100 hover:bg-gray-50/50 transition-colors';
                
                const date = new Date(item.tanggal).toLocaleDateString('id-ID', {
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric'
                });
                
                row.innerHTML = `
                    <td class="py-4 px-6 text-sm text-gray-600">${date}</td>
                    <td class="py-4 px-6">
                        <span class="px-3 py-1 rounded-lg text-sm font-medium ${getCategoryColorClass(item.kategori)}">
                            ${item.kategori}
                        </span>
                    </td>
                    <td class="py-4 px-6 text-sm text-gray-600">${item.deskripsi}</td>
                    <td class="py-4 px-6 text-sm font-medium text-gray-900">
                        Rp ${Number(item.jumlah).toLocaleString('id-ID')}
                    </td>
                    <td class="py-4 px-6">
                        <div class="flex justify-end gap-2">
                            <button onclick="showDetail(${JSON.stringify(item).replace(/"/g, '&quot;')})" 
                                    class="p-2 text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                          d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </button>
                            <button onclick="showDeleteModal(${item.id})"
                                    class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </div>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        function updateTotal(total) {
            const totalElement = document.getElementById('totalPengeluaran');
            if (totalElement) {
                totalElement.textContent = `Rp ${Number(total).toLocaleString('id-ID')}`;
            }
        }

        function getCategoryColorClass(kategori) {
            const colors = {
                'Gaji': 'bg-blue-100 text-blue-700',
                'Internet': 'bg-green-100 text-green-700',
                'Listrik': 'bg-yellow-100 text-yellow-700',
                'Maintenance': 'bg-purple-100 text-purple-700',
                'Peralatan': 'bg-pink-100 text-pink-700',
                'Sewa': 'bg-indigo-100 text-indigo-700',
                'Lainnya': 'bg-gray-100 text-gray-700'
            };
            return colors[kategori] || colors['Lainnya'];
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

        function closeAlert() {
            const alert = document.getElementById('alert');
            if (alert) {
                alert.style.opacity = '0';
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 300);
            }
        }

        // Initialize filters on page load
        document.addEventListener('DOMContentLoaded', function() {
            applyFilters();
        });

        // Tab switching function
        function switchTab(tabName) {
            const tabs = document.querySelectorAll('button[onclick^="switchTab"]');
            const dataTab = document.getElementById('dataTab');
            const statistikTab = document.getElementById('statistikTab');

            // Update URL with tab parameter
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('tab', tabName);
            window.history.replaceState({}, '', `?${urlParams.toString()}`);

            tabs.forEach(tab => {
                const isSelected = tab.getAttribute('onclick').includes(tabName);
                tab.className = `flex items-center gap-2 px-6 py-3 rounded-xl font-medium transition-all duration-300 ${
                    isSelected 
                        ? 'bg-white text-blue-600 shadow-lg shadow-blue-500/10 scale-[1.02] ring-1 ring-black/5' 
                        : 'text-gray-500 hover:text-gray-600 hover:bg-white/50'
                }`;
            });

            if (tabName === 'data') {
                dataTab.classList.remove('hidden');
                statistikTab.classList.add('hidden');
            } else {
                dataTab.classList.add('hidden');
                statistikTab.classList.remove('hidden');
                document.getElementById('statistikPeriode').value = 'bulanan';
                document.getElementById('monthInput').value = new Date().toISOString().slice(0, 7);
                updateStatistik();
            }
        }

        // Initialize Pie Chart
        function initPieChart() {
            const ctx = document.getElementById('pieChart').getContext('2d');
            
            <?php
            // Prepare data for pie chart
            $labels = [];
            $data = [];
            $backgroundColor = [
                'rgba(59, 130, 246, 0.8)',   // Blue
                'rgba(16, 185, 129, 0.8)',   // Green
                'rgba(245, 158, 11, 0.8)',   // Orange
                'rgba(236, 72, 153, 0.8)',   // Pink
                'rgba(139, 92, 246, 0.8)',   // Purple
                'rgba(14, 165, 233, 0.8)',   // Sky Blue
                'rgba(234, 88, 12, 0.8)',    // Dark Orange
                'rgba(168, 85, 247, 0.8)',   // Violet
            ];
            
            foreach ($summaryData as $index => $summary) {
                $labels[] = $summary['kategori'];
                $data[] = $summary['total_pengeluaran'];
            }
            ?>

            new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: <?= json_encode($labels) ?>,
                    datasets: [{
                        data: <?= json_encode($data) ?>,
                        backgroundColor: <?= json_encode($backgroundColor) ?>,
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: {
                        padding: {
                            top: 20,
                            bottom: 40,
                            left: 20,
                            right: 20
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                font: { size: 12 },
                                boxWidth: 12
                            }
                        }
                    }
                }
            });
        }

        // Initialize default tab on page load
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const currentTab = urlParams.get('tab') || 'data';
            switchTab(currentTab);
        });

        function updateStatistik() {
            const periode = document.getElementById('statistikPeriode').value;
            const weekRange = document.getElementById('weekRange');
            const monthPicker = document.getElementById('monthPicker');
            const yearPicker = document.getElementById('yearPicker');
            const customDateRange = document.getElementById('customDateRange');
            const customDateRangeEnd = document.getElementById('customDateRangeEnd');
            
            // Hide all inputs first
            weekRange.classList.add('hidden');
            monthPicker.classList.add('hidden');
            yearPicker.classList.add('hidden');
            customDateRange.classList.add('hidden');
            customDateRangeEnd.classList.add('hidden');
            
            let startDate, endDate;
            const today = new Date();
            
            switch(periode) {
                case 'mingguan':
                    weekRange.classList.remove('hidden');
                    // Set default date to today and trigger the range update
                    const weekStartDate = document.getElementById('weekStartDate');
                    weekStartDate.value = today.toISOString().split('T')[0];
                    // Set max date to today
                    weekStartDate.max = today.toISOString().split('T')[0];
                    updateWeekRange(weekStartDate.value);
                    break;
                
                case 'bulanan':
                    monthPicker.classList.remove('hidden');
                    const selectedMonth = document.getElementById('monthInput').value;
                    const [year, month] = selectedMonth.split('-');
                    startDate = new Date(year, month - 1, 1);
                    endDate = new Date(year, month, 0);
                    break;
                
                case 'tahunan':
                    yearPicker.classList.remove('hidden');
                    const selectedYear = document.getElementById('yearInput').value;
                    startDate = new Date(selectedYear, 0, 1);
                    endDate = new Date(selectedYear, 11, 31);
                    break;
                
                case 'custom':
                    customDateRange.classList.remove('hidden');
                    customDateRangeEnd.classList.remove('hidden');
                    startDate = document.getElementById('startDate').value;
                    endDate = document.getElementById('endDate').value;
                    if (!startDate || !endDate) return;
                    break;
            }

            // Format dates for API
            const formatDateForAPI = (date) => {
                if (typeof date === 'string') return date;
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            };

            // Update fetch URL to use the same file
            fetch(`pengeluaran.php?start=${formatDateForAPI(startDate)}&end=${formatDateForAPI(endDate)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.summary.length === 0) {
                        // Update tampilan untuk Distribusi Pengeluaran
                        document.getElementById('pieChart').parentElement.innerHTML = 
                            '<div class="flex items-center justify-center" style="height: 300px;">' +
                            '<p class="text-gray-500 text-center">Tidak ada data untuk periode ini</p>' +
                            '</div>';
                        
                        // Update tampilan untuk Ringkasan Pengeluaran
                        document.querySelector('#statistikTab .space-y-4').innerHTML = 
                            '<div class="flex items-center justify-center" style="height: 300px;">' +
                            '<p class="text-gray-500 text-center">Tidak ada data untuk periode ini</p>' +
                            '</div>';
                    } else {
                        updatePieChart(data);
                        updateSummary(data);
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        function updatePieChart(data) {
            const ctx = document.getElementById('pieChart').getContext('2d');
            if (window.myPieChart) {
                window.myPieChart.destroy();
            }

            window.myPieChart = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: data.labels,
                    datasets: [{
                        data: data.values,
                        backgroundColor: [
                            'rgba(59, 130, 246, 0.8)',   // Blue
                            'rgba(16, 185, 129, 0.8)',   // Green
                            'rgba(245, 158, 11, 0.8)',   // Orange
                            'rgba(236, 72, 153, 0.8)',   // Pink
                            'rgba(139, 92, 246, 0.8)',   // Purple
                            'rgba(14, 165, 233, 0.8)',   // Sky Blue
                            'rgba(234, 88, 12, 0.8)',    // Dark Orange
                            'rgba(168, 85, 247, 0.8)',   // Violet
                        ],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: {
                        padding: {
                            top: 20,
                            bottom: 40,
                            left: 20,
                            right: 20
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                font: { size: 12 },
                                boxWidth: 12
                            }
                        }
                    }
                }
            });
        }

        function updateSummary(data) {
            const summaryContainer = document.querySelector('#statistikTab .space-y-4');
            let html = '';
            
            // Pastikan data.summary ada dan merupakan array
            if (!data.summary || !Array.isArray(data.summary)) {
                html = `
                    <div class="flex items-center justify-center p-8">
                        <p class="text-gray-500 text-center">Tidak ada data untuk periode ini</p>
                    </div>
                `;
                summaryContainer.innerHTML = html;
                return;
            }
            
            // Hitung total keseluruhan dengan pengecekan nilai
            const totalPengeluaran = data.summary.reduce((acc, item) => {
                const amount = parseFloat(item.total_pengeluaran) || 0;
                return acc + amount;
            }, 0);
            
            const totalTransaksi = data.summary.reduce((acc, item) => {
                const count = parseInt(item.jumlah_transaksi) || 0;
                return acc + count;
            }, 0);
            
            // Header dengan total keseluruhan
            html += `
                <div class="mb-6 bg-gradient-to-br from-blue-50 to-indigo-50 rounded-2xl p-4">
                    <p class="text-sm font-medium text-gray-600 mb-1">Total Pengeluaran</p>
                    <p class="text-2xl font-bold text-blue-600">
                        ${totalPengeluaran > 0 ? 
                            `Rp ${new Intl.NumberFormat('id-ID').format(totalPengeluaran)}` : 
                            'Rp 0'}
                    </p>
                    <p class="text-xs text-gray-500 mt-1">
                        ${totalTransaksi} transaksi
                    </p>
                </div>
            `;

            // Grid untuk kategori-kategori
            html += '<div class="grid grid-cols-1 md:grid-cols-2 gap-4">';
            
            const colors = {
                'Gaji': 'blue',
                'Internet': 'green',
                'Listrik': 'orange',
                'Maintenance': 'purple',
                'Peralatan': 'sky',
                'Sewa': 'pink',
                'Lainnya': 'indigo'
            };

            data.summary.forEach(item => {
                const color = colors[item.kategori] || 'gray';
                const amount = parseFloat(item.total_pengeluaran) || 0;
                const percentage = totalPengeluaran > 0 ? (amount / totalPengeluaran * 100) : 0;
                
                html += `
                    <div class="bg-white rounded-xl p-4 border border-${color}-100 hover:border-${color}-200 transition-all duration-300">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <div class="w-2 h-2 rounded-full bg-${color}-500"></div>
                                    <p class="text-sm font-medium text-gray-600">${item.kategori}</p>
                                </div>
                                <p class="text-lg font-semibold text-${color}-600 mt-1">
                                    Rp ${new Intl.NumberFormat('id-ID').format(amount)}
                                </p>
                                <div class="flex items-center gap-3 mt-2">
                                    <p class="text-xs text-gray-500">
                                        <span class="font-medium">${parseInt(item.jumlah_transaksi) || 0}</span> transaksi
                                    </p>
                                    <p class="text-xs font-medium text-${color}-600">
                                        ${percentage.toFixed(1)}%
                                    </p>
                                </div>
                            </div>
                            <div class="w-16 h-16 flex items-center justify-center rounded-lg bg-${color}-50">
                                ${getCategoryIcon(item.kategori, color)}
                            </div>
                        </div>
                        <div class="mt-3 bg-gray-100 rounded-full h-1.5">
                            <div class="bg-${color}-500 h-1.5 rounded-full" style="width: ${percentage}%"></div>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            summaryContainer.innerHTML = html;
        }

        // Tambahkan fungsi untuk mendapatkan icon kategori
        function getCategoryIcon(category, color) {
            const icons = {
                'Gaji': `<svg class="w-6 h-6 text-${color}-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>`,
                'Internet': `<svg class="w-6 h-6 text-${color}-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"/>
                            </svg>`,
                'Listrik': `<svg class="w-6 h-6 text-${color}-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>`,
                'Maintenance': `<svg class="w-6 h-6 text-${color}-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>`,
                'Peralatan': `<svg class="w-6 h-6 text-${color}-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
                            </svg>`,
                'Sewa': `<svg class="w-6 h-6 text-${color}-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>`,
                'Lainnya': `<svg class="w-6 h-6 text-${color}-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z"/>
                                </svg>`
            };
            
            return icons[category] || icons['Lainnya'];
        }

        function updateWeekRange(startDateStr) {
            const startDate = new Date(startDateStr);
            const endDate = new Date(startDate);
            endDate.setDate(startDate.getDate() + 6); // Add 6 days to make it 7 days total
            
            // Format dates for display
            const formatDate = date => date.toLocaleDateString('id-ID', { 
                day: 'numeric', 
                month: 'long', 
                year: 'numeric' 
            });

            document.getElementById('weekRangeText').textContent = 
                `${formatDate(startDate)} - ${formatDate(endDate)}`;

            // Trigger statistik update
            updateStatistikWithDates(startDate, endDate);
        }

        function updateStatistikWithDates(startDate, endDate) {
            const formatDateForAPI = (date) => {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            };

            // Update fetch URL to use the same file
            fetch(`pengeluaran.php?start=${formatDateForAPI(startDate)}&end=${formatDateForAPI(endDate)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.summary.length === 0) {
                        // Update tampilan untuk Distribusi Pengeluaran
                        document.getElementById('pieChart').parentElement.innerHTML = 
                            '<div class="flex items-center justify-center" style="height: 300px;">' +
                            '<p class="text-gray-500 text-center">Tidak ada data untuk periode ini</p>' +
                            '</div>';
                        
                        // Update tampilan untuk Ringkasan Pengeluaran
                        document.querySelector('#statistikTab .space-y-4').innerHTML = 
                            '<div class="flex items-center justify-center" style="height: 300px;">' +
                            '<p class="text-gray-500 text-center">Tidak ada data untuk periode ini</p>' +
                            '</div>';
                    } else {
                        updatePieChart(data);
                        updateSummary(data);
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        // Add event listener to prevent selecting more than 7 days
        document.getElementById('weekStartDate').addEventListener('change', function(e) {
            const selectedDate = new Date(this.value);
            const today = new Date();
            const maxDate = new Date(selectedDate);
            maxDate.setDate(maxDate.getDate() + 6);

            // If the end date would be after today, adjust start date
            if (maxDate > today) {
                const adjustedStart = new Date(today);
                adjustedStart.setDate(today.getDate() - 6);
                this.value = adjustedStart.toISOString().split('T')[0];
            }

            updateWeekRange(this.value);
        });

        // Fungsi untuk menginisialisasi filter
        function initializeFilters() {
            // Set default filter ke hari ini
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('filterPeriode').value = 'harian';
            document.getElementById('filterTanggal').value = today;
            document.getElementById('filterKategori').value = '';
            
            // Tampilkan input tanggal dan sembunyikan yang lain
            toggleDateInputs();
            
            // Terapkan filter
            applyFilters();
        }

        // Fungsi untuk reset filter
        function resetFilters() {
            // Reset ke default (hari ini)
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('filterKategori').value = '';
            document.getElementById('filterPeriode').value = 'harian';
            document.getElementById('filterTanggal').value = today;
            document.getElementById('filterBulan').value = '';
            document.getElementById('filterTahun').value = '';
            
            // Update tampilan input
            toggleDateInputs();
            
            // Terapkan filter
            applyFilters();
        }

        // Initialize saat halaman dimuat
        document.addEventListener('DOMContentLoaded', function() {
            initializeFilters();
            
            // Tambahkan event listener untuk perubahan filter
            document.getElementById('filterKategori').addEventListener('change', applyFilters);
            document.getElementById('filterPeriode').addEventListener('change', function() {
                toggleDateInputs();
                applyFilters();
            });
            document.getElementById('filterTanggal').addEventListener('change', applyFilters);
            document.getElementById('filterBulan').addEventListener('change', applyFilters);
            document.getElementById('filterTahun').addEventListener('change', applyFilters);
        });
    </script>

    <style>
    /* Style untuk animasi tab */
    .transition-all {
        transition-property: all;
        transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
        transition-duration: 300ms;
    }

    .hover\:bg-white\/50:hover {
        background-color: rgb(255 255 255 / 0.5);
    }

    /* Animasi untuk shadow */
    .shadow-sm {
        box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
    }

    @media (max-width: 768px) {
        .table-responsive {
            margin: 0 -1rem;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        .action-button {
            padding: 0.5rem;
        }
        
        .modal-content {
            padding: 1rem;
        }
        
        .filter-section {
            flex-direction: column;
        }
        
        .filter-item {
            width: 100%;
        }
    }

    /* Better touch interactions */
    @media (hover: none) {
        .table-responsive {
            -webkit-overflow-scrolling: touch;
        }
        
        select, input[type="date"] {
            font-size: 16px; /* Prevent zoom on iOS */
        }
    }

    /* Smooth scrolling */
    .smooth-scroll {
        scroll-behavior: smooth;
    }
    </style>
</body>
</html> 