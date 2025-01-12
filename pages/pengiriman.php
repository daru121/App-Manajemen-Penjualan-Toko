<?php
require_once '../backend/check_session.php';
require_once '../backend/database.php';

// API Key BinderByte
$apiKey = "af5179b7271645d3e26a3cae146be63ce778f9e8ad6652d8b37b712aa65878e7"; // Ganti dengan API key BinderByte Anda

// Handle update status pengiriman via AJAX
if (isset($_POST['action']) && $_POST['action'] === 'update_status') {
    try {
        $transaksi_id = $_POST['transaksi_id'];
        $status = $_POST['status'];
        
        $stmt = $conn->prepare("UPDATE transaksi SET status_pengiriman = ? WHERE id = ?");
        $stmt->execute([$status, $transaksi_id]);
        
        echo json_encode(['success' => true]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Handle cek resi via AJAX
if (isset($_POST['action']) && $_POST['action'] === 'check_resi') {
    $courier = strtolower($_POST['courier']);
    $awb = $_POST['awb'];
    
    $url = "https://api.binderbyte.com/v1/track?api_key={$apiKey}&courier={$courier}&awb={$awb}";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    
    echo $response;
    exit;
}

// Query untuk mengambil data pengiriman
$query = "SELECT t.*, p.nama as nama_pembeli 
          FROM transaksi t 
          LEFT JOIN pembeli p ON t.pembeli_id = p.id 
          WHERE t.marketplace != 'offline' 
          AND t.no_resi IS NOT NULL 
          ORDER BY t.tanggal DESC";
$stmt = $conn->query($query);
$pengiriman = $stmt->fetchAll();

// Hitung statistik
$total = count($pengiriman);
$pending = array_filter($pengiriman, fn($p) => $p['status_pengiriman'] === 'pending');
$dikirim = array_filter($pengiriman, fn($p) => $p['status_pengiriman'] === 'dikirim');
$selesai = array_filter($pengiriman, fn($p) => $p['status_pengiriman'] === 'selesai');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengiriman - PAksesories</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <?php include '../components/sidebar.php'; ?>
    <?php include '../components/navbar.php'; ?>

    <div class="ml-64 p-8 pt-24">
        <!-- Header dengan gradient yang sama seperti informasi.php -->
        <div class="mb-8 bg-gradient-to-br from-blue-600 to-blue-400 rounded-3xl p-8 text-white">
            <h1 class="text-3xl font-bold mb-2">Pengiriman</h1>
            <p class="text-blue-100">Kelola dan pantau status pengiriman</p>
        </div>

        <!-- Statistik Cards -->
        <div class="grid grid-cols-4 gap-6 mb-8">
            <!-- Total Pengiriman -->
            <div class="relative bg-gradient-to-br from-indigo-50 to-purple-100/50 backdrop-blur-xl rounded-3xl p-6 border border-indigo-500/10 shadow-[0_8px_30px_rgb(0,0,0,0.04)] hover:shadow-[0_8px_30px_rgb(0,0,0,0.08)] transition-all duration-300 group overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-br from-indigo-500/5 to-transparent"></div>
                <div class="absolute -right-6 -bottom-6 w-32 h-32 bg-indigo-500/10 rounded-full blur-2xl group-hover:scale-150 transition-transform duration-500"></div>
                
                <div class="relative flex items-center gap-4">
                    <div class="p-4 bg-indigo-500/10 rounded-2xl group-hover:scale-110 group-hover:bg-indigo-500/20 transition-all duration-300">
                        <svg class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-4xl font-bold mb-1 text-gray-800 group-hover:translate-x-1 transition-transform"><?= $total ?></p>
                        <p class="text-sm font-medium text-gray-500">Total Pengiriman</p>
                    </div>
                </div>
            </div>

            <!-- Pending -->
            <div class="relative bg-gradient-to-br from-orange-50 to-orange-100/50 backdrop-blur-xl rounded-3xl p-6 border border-orange-500/10 shadow-[0_8px_30px_rgb(0,0,0,0.04)] hover:shadow-[0_8px_30px_rgb(0,0,0,0.08)] transition-all duration-300 group overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-br from-orange-500/5 to-transparent"></div>
                <div class="absolute -right-6 -bottom-6 w-32 h-32 bg-orange-500/10 rounded-full blur-2xl group-hover:scale-150 transition-transform duration-500"></div>
                
                <div class="relative flex items-center gap-4">
                    <div class="p-4 bg-orange-500/10 rounded-2xl group-hover:scale-110 group-hover:bg-orange-500/20 transition-all duration-300">
                        <svg class="w-7 h-7 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-4xl font-bold mb-1 text-gray-800 group-hover:translate-x-1 transition-transform"><?= count($pending) ?></p>
                        <p class="text-sm font-medium text-gray-500">Pending</p>
                    </div>
                </div>
            </div>

            <!-- Dalam Pengiriman -->
            <div class="relative bg-gradient-to-br from-blue-50 to-blue-100/50 backdrop-blur-xl rounded-3xl p-6 border border-blue-500/10 shadow-[0_8px_30px_rgb(0,0,0,0.04)] hover:shadow-[0_8px_30px_rgb(0,0,0,0.08)] transition-all duration-300 group overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-br from-blue-500/5 to-transparent"></div>
                <div class="absolute -right-6 -bottom-6 w-32 h-32 bg-blue-500/10 rounded-full blur-2xl group-hover:scale-150 transition-transform duration-500"></div>
                
                <div class="relative flex items-center gap-4">
                    <div class="p-4 bg-blue-500/10 rounded-2xl group-hover:scale-110 group-hover:bg-blue-500/20 transition-all duration-300">
                        <svg class="w-7 h-7 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-4xl font-bold mb-1 text-gray-800 group-hover:translate-x-1 transition-transform"><?= count($dikirim) ?></p>
                        <p class="text-sm font-medium text-gray-500">Dalam Pengiriman</p>
                    </div>
                </div>
            </div>

            <!-- Selesai -->
            <div class="relative bg-gradient-to-br from-green-50 to-green-100/50 backdrop-blur-xl rounded-3xl p-6 border border-green-500/10 shadow-[0_8px_30px_rgb(0,0,0,0.04)] hover:shadow-[0_8px_30px_rgb(0,0,0,0.08)] transition-all duration-300 group overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-br from-green-500/5 to-transparent"></div>
                <div class="absolute -right-6 -bottom-6 w-32 h-32 bg-green-500/10 rounded-full blur-2xl group-hover:scale-150 transition-transform duration-500"></div>
                
                <div class="relative flex items-center gap-4">
                    <div class="p-4 bg-green-500/10 rounded-2xl group-hover:scale-110 group-hover:bg-green-500/20 transition-all duration-300">
                        <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-4xl font-bold mb-1 text-gray-800 group-hover:translate-x-1 transition-transform"><?= count($selesai) ?></p>
                        <p class="text-sm font-medium text-gray-500">Selesai</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter dan Search -->
        <div class="mb-6 flex justify-between items-center">
            <div class="flex gap-4">
                <select id="statusFilter" onchange="filterTable()" 
                        class="px-4 py-2 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                    <option value="">Semua Status</option>
                    <option value="pending">Pending</option>
                    <option value="dikirim">Dikirim</option>
                    <option value="selesai">Selesai</option>
                </select>
                
                <select id="marketplaceFilter" onchange="filterTable()"
                        class="px-4 py-2 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                    <option value="">Semua Marketplace</option>
                    <option value="shopee">Shopee</option>
                    <option value="tokopedia">Tokopedia</option>
                    <option value="tiktok">TikTok</option>
                </select>
            </div>

            <div class="relative">
                <input type="text" id="searchInput" onkeyup="filterTable()"
                       class="pl-10 pr-4 py-2 bg-white border border-gray-200 rounded-xl text-sm w-64
                              focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                       placeholder="Cari nomor resi atau pembeli...">
                <svg class="w-5 h-5 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
        </div>

        <!-- Table Card dengan animasi hover yang lebih baik -->
        <div class="bg-white rounded-3xl shadow-xl border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-50/50">
                            <th class="text-left py-4 px-6 text-sm font-semibold text-gray-600">No. Transaksi</th>
                            <th class="text-left py-4 px-6 text-sm font-semibold text-gray-600">Pembeli</th>
                            <th class="text-left py-4 px-6 text-sm font-semibold text-gray-600">Marketplace</th>
                            <th class="text-left py-4 px-6 text-sm font-semibold text-gray-600">Kurir</th>
                            <th class="text-left py-4 px-6 text-sm font-semibold text-gray-600">No. Resi</th>
                            <th class="text-left py-4 px-6 text-sm font-semibold text-gray-600">Status</th>
                            <th class="text-left py-4 px-6 text-sm font-semibold text-gray-600">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($pengiriman as $item): ?>
                            <tr class="hover:bg-gray-50/50 transition-colors duration-200">
                                <td class="py-4 px-6">
                                    <span class="text-sm font-medium text-gray-900">
                                        TRX-<?= str_pad($item['id'], 4, '0', STR_PAD_LEFT) ?>
                                    </span>
                                    <span class="block text-xs text-gray-500">
                                        <?= date('d M Y H:i', strtotime($item['tanggal'])) ?>
                                    </span>
                                </td>
                                <td class="py-4 px-6">
                                    <span class="text-sm font-medium text-gray-900">
                                        <?= htmlspecialchars($item['nama_pembeli']) ?>
                                    </span>
                                    <span class="block text-xs text-gray-500">
                                        <?= htmlspecialchars($item['daerah']) ?>
                                    </span>
                                </td>
                                <td class="py-4 px-6">
                                    <span class="px-3 py-1 rounded-full text-xs font-medium inline-flex items-center gap-1
                                        <?php
                                        switch($item['marketplace']) {
                                            case 'shopee':
                                                echo 'bg-orange-50 text-orange-600';
                                                break;
                                            case 'tokopedia':
                                                echo 'bg-green-50 text-green-600';
                                                break;
                                            case 'tiktok':
                                                echo 'bg-gray-50 text-gray-600';
                                                break;
                                        }
                                        ?>">
                                        <span class="w-1.5 h-1.5 rounded-full 
                                            <?php
                                            switch($item['marketplace']) {
                                                case 'shopee':
                                                    echo 'bg-orange-600';
                                                    break;
                                                case 'tokopedia':
                                                    echo 'bg-green-600';
                                                    break;
                                                case 'tiktok':
                                                    echo 'bg-gray-600';
                                                    break;
                                            }
                                            ?>">
                                        </span>
                                        <?= ucfirst($item['marketplace']) ?>
                                    </span>
                                </td>
                                <td class="py-4 px-6 text-sm font-medium text-gray-900">
                                    <?= strtoupper($item['kurir']) ?>
                                </td>
                                <td class="py-4 px-6">
                                    <span class="text-sm font-medium text-gray-900">
                                        <?= $item['no_resi'] ?>
                                    </span>
                                </td>
                                <td class="py-4 px-6">
                                    <span class="px-3 py-1 rounded-full text-xs font-medium
                                        <?php
                                        switch($item['status_pengiriman']) {
                                            case 'pending':
                                                echo 'bg-orange-50 text-orange-600';
                                                break;
                                            case 'dikirim':
                                                echo 'bg-blue-50 text-blue-600';
                                                break;
                                            case 'selesai':
                                                echo 'bg-green-50 text-green-600';
                                                break;
                                        }
                                        ?>">
                                        <?= ucfirst($item['status_pengiriman']) ?>
                                    </span>
                                </td>
                                <td class="py-4 px-6">
                                    <div class="flex gap-2">
                                        <button onclick="checkResi('<?= $item['kurir'] ?>', '<?= $item['no_resi'] ?>')"
                                                class="px-3 py-1.5 text-sm text-blue-600 hover:bg-blue-50 rounded-lg transition-colors inline-flex items-center gap-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                            Lacak
                                        </button>
                                        <button onclick="updateStatus(<?= $item['id'] ?>, '<?= $item['status_pengiriman'] ?>')"
                                                class="px-3 py-1.5 text-sm text-orange-600 hover:bg-orange-50 rounded-lg transition-colors inline-flex items-center gap-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                            Update
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

    <!-- Modal Lacak Resi yang lebih informatif -->
    <div id="trackingModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl shadow-xl max-w-2xl w-full">
                <div class="p-6 border-b border-gray-100 flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-800">Detail Pengiriman</h3>
                    <button onclick="closeTrackingModal()" class="text-gray-400 hover:text-gray-500">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="p-6">
                    <div id="trackingResult" class="space-y-4">
                        <!-- Hasil tracking akan ditampilkan di sini -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Update Status -->
    <div id="updateModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl shadow-xl max-w-md w-full">
                <div class="p-6 border-b border-gray-100">
                    <h3 class="text-lg font-semibold text-gray-800">Update Status Pengiriman</h3>
                </div>
                <div class="p-6">
                    <select id="statusSelect" class="w-full px-4 py-2 border border-gray-200 rounded-xl">
                        <option value="pending">Pending</option>
                        <option value="dikirim">Dikirim</option>
                        <option value="selesai">Selesai</option>
                    </select>
                </div>
                <div class="p-6 border-t border-gray-100 flex justify-end gap-3">
                    <button onclick="closeUpdateModal()"
                            class="px-4 py-2 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 transition-colors">
                        Batal
                    </button>
                    <button onclick="saveStatus()"
                            class="px-4 py-2 bg-blue-500 text-white rounded-xl hover:bg-blue-600 transition-colors">
                        Simpan
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Script untuk filter dan search -->
    <script>
    function filterTable() {
        const statusFilter = document.getElementById('statusFilter').value.toLowerCase();
        const marketplaceFilter = document.getElementById('marketplaceFilter').value.toLowerCase();
        const searchInput = document.getElementById('searchInput').value.toLowerCase();
        const rows = document.querySelectorAll('tbody tr');

        rows.forEach(row => {
            const status = row.querySelector('td:nth-child(6)').textContent.toLowerCase();
            const marketplace = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
            const resi = row.querySelector('td:nth-child(5)').textContent.toLowerCase();
            const pembeli = row.querySelector('td:nth-child(2)').textContent.toLowerCase();

            const matchesStatus = !statusFilter || status.includes(statusFilter);
            const matchesMarketplace = !marketplaceFilter || marketplace.includes(marketplaceFilter);
            const matchesSearch = !searchInput || 
                                resi.includes(searchInput) || 
                                pembeli.includes(searchInput);

            row.style.display = matchesStatus && matchesMarketplace && matchesSearch ? '' : 'none';
        });
    }

    let currentTransactionId = null;

    function checkResi(courier, awb) {
        const trackingModal = document.getElementById('trackingModal');
        const trackingResult = document.getElementById('trackingResult');
        
        trackingResult.innerHTML = '<div class="text-center">Memuat data...</div>';
        trackingModal.classList.remove('hidden');
        
        fetch('pengiriman.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=check_resi&courier=${courier}&awb=${awb}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 200) {
                const history = data.data.history.reverse();
                trackingResult.innerHTML = `
                    <div class="space-y-6">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-500">No. Resi</p>
                                <p class="font-medium">${data.data.summary.awb}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Kurir</p>
                                <p class="font-medium">${data.data.summary.courier}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Status</p>
                                <p class="font-medium">${data.data.summary.status}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Service</p>
                                <p class="font-medium">${data.data.summary.service}</p>
                            </div>
                        </div>
                        <div class="border-t border-gray-100 pt-4">
                            <p class="font-medium mb-4">Riwayat Pengiriman</p>
                            <div class="space-y-4">
                                ${history.map(item => `
                                    <div class="flex gap-4">
                                        <div class="w-32 shrink-0">
                                            <p class="text-sm text-gray-500">${item.date}</p>
                                        </div>
                                        <div>
                                            <p class="text-sm">${item.desc}</p>
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    </div>
                `;
            } else {
                trackingResult.innerHTML = `
                    <div class="text-center text-red-600">
                        Gagal memuat data: ${data.message}
                    </div>
                `;
            }
        })
        .catch(error => {
            trackingResult.innerHTML = `
                <div class="text-center text-red-600">
                    Terjadi kesalahan saat memuat data
                </div>
            `;
        });
    }

    function closeTrackingModal() {
        document.getElementById('trackingModal').classList.add('hidden');
    }

    function updateStatus(id, currentStatus) {
        currentTransactionId = id;
        const statusSelect = document.getElementById('statusSelect');
        statusSelect.value = currentStatus;
        document.getElementById('updateModal').classList.remove('hidden');
    }

    function closeUpdateModal() {
        document.getElementById('updateModal').classList.add('hidden');
        currentTransactionId = null;
    }

    function saveStatus() {
        const status = document.getElementById('statusSelect').value;
        
        fetch('pengiriman.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=update_status&transaksi_id=${currentTransactionId}&status=${status}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Gagal mengupdate status: ' + data.message);
            }
        })
        .catch(error => {
            alert('Terjadi kesalahan saat mengupdate status');
        });
    }

    // Close modals when clicking outside
    document.querySelectorAll('.fixed').forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.add('hidden');
            }
        });
    });
    </script>
</body>
</html> 