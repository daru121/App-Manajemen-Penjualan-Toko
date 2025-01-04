<?php
require_once '../backend/check_session.php';
require_once '../backend/database.php';

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
        
        header('Location: informasi.php?success=1');
        exit;
    } catch(PDOException $e) {
        $conn->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

try {
    // Get user data
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    // Get sales information with buyer details
    $query = "SELECT 
        t.id,
        p.nama as buyer_name,
        t.tanggal,
        t.total_harga,
        SUM(dt.jumlah * (dt.harga - b.harga_modal)) as profit
    FROM transaksi t
    LEFT JOIN pembeli p ON t.pembeli_id = p.id
    LEFT JOIN detail_transaksi dt ON t.id = dt.transaksi_id
    LEFT JOIN barang b ON dt.barang_id = b.id
    WHERE 1=1";
    
    $params = [];

    // Filter by date range
    if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
        $query .= " AND DATE(t.tanggal) BETWEEN :start_date AND :end_date";
        $params[':start_date'] = $_GET['start_date'];
        $params[':end_date'] = $_GET['end_date'];
    }

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
        <div class="p-8">
            <!-- Header Section dengan gradient modern -->
            <div class="bg-gradient-to-br from-indigo-600 via-blue-500 to-blue-400 rounded-3xl p-10 text-white shadow-2xl relative overflow-hidden">
                <!-- Decorative elements -->
                <div class="absolute top-0 right-0 w-96 h-96 bg-white/10 rounded-full -translate-y-32 translate-x-32 blur-3xl"></div>
                <div class="absolute bottom-0 left-0 w-96 h-96 bg-blue-500/20 rounded-full translate-y-32 -translate-x-32 blur-3xl"></div>
                
                <div class="relative flex justify-between items-center">
                    <div>
                        <h1 class="text-4xl font-bold mb-3">Informasi Penjualan</h1>
                        <p class="text-blue-100 text-lg">Kelola data informasi penjualan</p>
                    </div>
                </div>
            </div>

            <!-- Filter & Search Section -->
            <div class="mt-8 flex items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <input type="date" id="start_date" value="<?= $_GET['start_date'] ?? date('Y-m-d') ?>" 
                           class="px-4 py-2 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                    <span class="text-gray-500">to</span>
                    <input type="date" id="end_date" value="<?= $_GET['end_date'] ?? date('Y-m-d') ?>"
                           class="px-4 py-2 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                </div>
                <div class="flex items-center gap-2">
                    <input type="text" id="searchInput" placeholder="Cari nama pembeli..." value="<?= $_GET['search'] ?? '' ?>"
                           class="px-4 py-2 w-80 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                    <button onclick="applyFilter()" class="px-4 py-2 bg-blue-500 text-white rounded-xl hover:bg-blue-600 transition-colors">
                        Cari
                    </button>
                </div>
            </div>

            <!-- Table Section -->
            <div class="mt-6 bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-4 text-left text-sm font-medium text-gray-600">NO</th>
                            <th class="px-6 py-4 text-left text-sm font-medium text-gray-600">NAMA PEMBELI</th>
                            <th class="px-6 py-4 text-left text-sm font-medium text-gray-600">TANGGAL TRANSAKSI</th>
                            <th class="px-6 py-4 text-left text-sm font-medium text-gray-600">TOTAL PEMBELIAN</th>
                            <th class="px-6 py-4 text-left text-sm font-medium text-gray-600">PROFIT</th>
                            <th class="px-6 py-4 text-left text-sm font-medium text-gray-600">AKSI</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($transactions as $index => $transaction): ?>
                        <tr class="hover:bg-gray-50/50">
                            <td class="px-6 py-4 text-sm text-gray-600"><?= $index + 1 ?></td>
                            <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars($transaction['buyer_name']) ?></td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                <?= date('d/m/Y H:i', strtotime($transaction['tanggal'])) ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                Rp <?= number_format($transaction['total_harga'], 0, ',', '.') ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                Rp <?= number_format($transaction['profit'], 0, ',', '.') ?>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <div class="flex items-center gap-2">
                                    <button onclick="viewDetail(<?= $transaction['id'] ?>)" 
                                            class="p-2 text-blue-500 hover:bg-blue-50 rounded-lg transition-colors">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </button>
                                    <button onclick="showDeleteModal(<?= $transaction['id'] ?>)"
                                            class="p-2 text-red-500 hover:bg-red-50 rounded-lg transition-colors">
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
                </table>
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
                <!-- Informasi Transaksi -->
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
                
                <!-- Detail Barang -->
                <div class="mt-6">
                    <h4 class="text-lg font-medium text-gray-800 mb-4">Detail Barang</h4>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-sm font-medium text-gray-600">No</th>
                                    <th class="px-4 py-2 text-left text-sm font-medium text-gray-600">Nama Barang</th>
                                    <th class="px-4 py-2 text-left text-sm font-medium text-gray-600">Jumlah</th>
                                    <th class="px-4 py-2 text-left text-sm font-medium text-gray-600">Harga</th>
                                    <th class="px-4 py-2 text-left text-sm font-medium text-gray-600">Subtotal</th>
                                    <th class="px-4 py-2 text-left text-sm font-medium text-gray-600">Profit</th>
                                </tr>
                            </thead>
                            <tbody id="modalDetailItems" class="divide-y divide-gray-200"></tbody>
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
                                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                </div>
                <h3 class="text-lg font-medium text-center mb-4">Konfirmasi Hapus</h3>
                <p class="text-gray-500 text-center mb-6">Apakah Anda yakin ingin menghapus transaksi ini?</p>
                <form id="deleteForm" method="POST" class="flex justify-center gap-3">
                    <input type="hidden" name="delete_id" id="deleteId">
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
        async function viewDetail(id) {
            try {
                const response = await fetch(`informasi.php?action=get_detail&id=${id}`);
                const data = await response.json();
                
                if (data.success) {
                    // Populate modal with transaction data
                    document.getElementById('modalBuyerName').textContent = data.transaction.buyer_name;
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
            const search = document.getElementById('searchInput').value;
            
            window.location.href = `informasi.php?start_date=${startDate}&end_date=${endDate}&search=${encodeURIComponent(search)}`;
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
    </script>
</body>
</html> 