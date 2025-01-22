<?php
require_once '../backend/check_session.php';
require_once '../backend/database.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            // Handle tambah karyawan
            if ($_POST['action'] === 'add') {
                $nama = $_POST['nama'];
                $email = $_POST['email'];
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $tempat_lahir = $_POST['tempat_lahir'];
                $tanggal_lahir = $_POST['tanggal_lahir'];
                $jenis_kelamin = $_POST['jenis_kelamin'];
                $role = $_POST['role'];
                $telepon = $_POST['telepon'];
                $alamat = $_POST['alamat'];
                $gaji = $_POST['gaji'];
                $bank = $_POST['bank'];
                $nomor_rekening = $_POST['nomor_rekening'];
                $tanggal_bergabung = $_POST['tanggal_bergabung'];

                $query = "INSERT INTO users (nama, email, password, tempat_lahir, tanggal_lahir, 
                          jenis_kelamin, role, telepon, alamat, gaji, bank, nomor_rekening, 
                          tanggal_bergabung, status) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Aktif')";

                $stmt = $conn->prepare($query);
                $stmt->execute([
                    $nama,
                    $email,
                    $password,
                    $tempat_lahir,
                    $tanggal_lahir,
                    $jenis_kelamin,
                    $role,
                    $telepon,
                    $alamat,
                    $gaji,
                    $bank,
                    $nomor_rekening,
                    $tanggal_bergabung
                ]);

                $_SESSION['success'] = "Karyawan berhasil ditambahkan";
            }

            // Handle edit karyawan
            else if ($_POST['action'] === 'edit') {
                $id = $_POST['id'];
                $nama = $_POST['nama'];
                $email = $_POST['email'];
                $tempat_lahir = $_POST['tempat_lahir'];
                $tanggal_lahir = $_POST['tanggal_lahir'];
                $jenis_kelamin = $_POST['jenis_kelamin'];
                $role = $_POST['role'];
                $telepon = $_POST['telepon'];
                $alamat = $_POST['alamat'];
                $gaji = $_POST['gaji'];
                $bank = $_POST['bank'];
                $nomor_rekening = $_POST['nomor_rekening'];
                $status = $_POST['status'];
                $tanggal_bergabung = $_POST['tanggal_bergabung'];

                $query = "UPDATE users SET 
                          nama = ?, 
                          email = ?, 
                          tempat_lahir = ?,
                          tanggal_lahir = ?,
                          jenis_kelamin = ?,
                          role = ?, 
                          telepon = ?, 
                          alamat = ?,
                          gaji = ?,
                          bank = ?,
                          nomor_rekening = ?,
                          status = ?,
                          tanggal_bergabung = ?
                          WHERE id = ?";

                $stmt = $conn->prepare($query);
                $stmt->execute([
                    $nama,
                    $email,
                    $tempat_lahir,
                    $tanggal_lahir,
                    $jenis_kelamin,
                    $role,
                    $telepon,
                    $alamat,
                    $gaji,
                    $bank,
                    $nomor_rekening,
                    $status,
                    $tanggal_bergabung,
                    $id
                ]);

                // Update password jika diisi
                if (!empty($_POST['password'])) {
                    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$password, $id]);
                }

                $_SESSION['success'] = "Data karyawan berhasil diperbarui";
            }

            // Handle tambah target
            else if ($_POST['action'] === 'add_target') {
                try {
                    $conn->beginTransaction();

                    $user_id = $_POST['user_id'];
                    $jenis_target = $_POST['jenis_target'];
                    $periode_mulai = $_POST['periode_mulai'];
                    $periode_selesai = $_POST['periode_selesai'];

                    // Cek apakah sudah ada target aktif untuk karyawan tersebut dengan jenis target yang sama
                    $stmt = $conn->prepare("SELECT id FROM target_penjualan 
                                      WHERE user_id = ? 
                                      AND jenis_target = ?
                                      AND status = 'Aktif' 
                                      AND (periode_mulai <= ? AND periode_selesai >= ?)");
                    $stmt->execute([$user_id, $jenis_target, $periode_selesai, $periode_mulai]);

                    if ($stmt->rowCount() > 0) {
                        $_SESSION['error'] = "Karyawan masih memiliki target " . strtoupper($jenis_target) . " aktif pada periode tersebut";
                    } else {
                        if ($jenis_target === 'omset') {
                            // Simpan target omset
                            $target_nominal = str_replace(['Rp', '.', ' '], '', $_POST['target_nominal']);
                            $insentif_persen = $_POST['insentif_persen'];

                            $query = "INSERT INTO target_penjualan (user_id, periode_mulai, periode_selesai, 
                                     target_nominal, insentif_persen, jenis_target) 
                                     VALUES (?, ?, ?, ?, ?, ?)";

                            $stmt = $conn->prepare($query);
                            $stmt->execute([
                                $user_id,
                                $periode_mulai,
                                $periode_selesai,
                                $target_nominal,
                                $insentif_persen,
                                'omset'
                            ]);
                        } else {
                            // Simpan target produk
                            $produk_id = $_POST['produk_id'];
                            $target_jumlah = $_POST['target_jumlah'];
                            $insentif_unit = str_replace(['Rp', '.', ' '], '', $_POST['insentif_unit']);

                            // Simpan target produk ke tabel target_penjualan
                            $query = "INSERT INTO target_penjualan (user_id, periode_mulai, periode_selesai, 
                                     jenis_target) VALUES (?, ?, ?, ?)";

                            $stmt = $conn->prepare($query);
                            $stmt->execute([
                                $user_id,
                                $periode_mulai,
                                $periode_selesai,
                                'produk'
                            ]);

                            $target_id = $conn->lastInsertId();

                            // Simpan detail target produk
                            $query = "INSERT INTO target_produk (target_id, barang_id, jumlah_target, insentif_per_unit) 
                                     VALUES (?, ?, ?, ?)";

                            $stmt = $conn->prepare($query);
                            $stmt->execute([
                                $target_id,
                                $produk_id,
                                $target_jumlah,
                                $insentif_unit
                            ]);
                        }

                        $conn->commit();
                        $_SESSION['success'] = "Target berhasil ditambahkan";
                    }
                } catch (Exception $e) {
                    $conn->rollBack();
                    $_SESSION['error'] = "Terjadi kesalahan: " . $e->getMessage();
                }

                header('Location: karyawan.php?tab=target');
                exit;
            }

            // Handle delete target
            else if ($_POST['action'] === 'delete_target') {
                $id = $_POST['id'];

                $conn->beginTransaction();

                // Hapus target_produk terlebih dahulu (jika ada)
                $stmt = $conn->prepare("DELETE FROM target_produk WHERE target_id = ?");
                $stmt->execute([$id]);

                // Kemudian hapus target_penjualan
                $stmt = $conn->prepare("DELETE FROM target_penjualan WHERE id = ?");
                $stmt->execute([$id]);

                $conn->commit();

                echo json_encode(['success' => true]);
                exit;
            }

            // Handle delete karyawan
            else if ($_POST['action'] === 'delete_karyawan') {
                $id = $_POST['id'];

                $conn->beginTransaction();
                try {
                    // Hapus atau update data terkait di semua tabel yang memiliki relasi

                    // 1. Hapus detail transaksi terkait
                    $stmt = $conn->prepare("DELETE FROM detail_transaksi 
                                           WHERE transaksi_id IN (SELECT id FROM transaksi WHERE user_id = ?)");
                    $stmt->execute([$id]);

                    // 2. Hapus transaksi
                    $stmt = $conn->prepare("DELETE FROM transaksi WHERE user_id = ?");
                    $stmt->execute([$id]);

                    // 3. Hapus target_produk
                    $stmt = $conn->prepare("DELETE FROM target_produk 
                                           WHERE target_id IN (SELECT id FROM target_penjualan WHERE user_id = ?)");
                    $stmt->execute([$id]);

                    // 4. Hapus target_penjualan
                    $stmt = $conn->prepare("DELETE FROM target_penjualan WHERE user_id = ?");
                    $stmt->execute([$id]);

                    // 5. Hapus absensi jika ada
                    $stmt = $conn->prepare("DELETE FROM absensi_karyawan WHERE user_id = ?");
                    $stmt->execute([$id]);

                    // 6. Terakhir hapus data karyawan
                    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$id]);

                    $conn->commit();
                    echo json_encode(['success' => true]);
                } catch (Exception $e) {
                    $conn->rollBack();
                    echo json_encode([
                        'success' => false,
                        'message' => 'Terjadi kesalahan: ' . $e->getMessage()
                    ]);
                }
                exit;
            }
        } catch (PDOException $e) {
            if (isset($conn)) {
                $conn->rollBack();
            }
            echo json_encode([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ]);
            exit;
        }

        header('Location: karyawan.php?tab=karyawan');
        exit;
    }
}

// Handle get data karyawan untuk edit
if (isset($_GET['action']) && $_GET['action'] === 'get_karyawan' && isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $karyawan = $stmt->fetch();

    echo json_encode($karyawan);
    exit;
}

// Handle active tab
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'karyawan';

// Query untuk mengambil data karyawan (dari tabel users)
$query = "SELECT id, nama, email, tempat_lahir, tanggal_lahir, jenis_kelamin, 
          role, status, telepon, alamat, gaji, bank, nomor_rekening, tanggal_bergabung 
          FROM users 
          WHERE role IN ('Operator', 'Kasir')
          ORDER BY nama ASC";
$stmt = $conn->query($query);
$karyawan = $stmt->fetchAll();

// Update query untuk menampilkan target dan menghitung insentif
$queryTarget = "
    SELECT 
        tp.*,
        u.nama as nama_karyawan,
        tpr.jumlah_target,
        tpr.insentif_per_unit,
        b.nama_barang,
        CASE 
            WHEN tp.jenis_target = 'omset' THEN 
                COALESCE((
                    SELECT SUM(total_harga) 
                    FROM transaksi 
                    WHERE user_id = tp.user_id 
                    AND tanggal BETWEEN tp.periode_mulai AND tp.periode_selesai
                ), 0)
            ELSE 
                COALESCE((
                    SELECT SUM(dt.jumlah)
                    FROM detail_transaksi dt 
                    JOIN transaksi t ON dt.transaksi_id = t.id 
                    WHERE t.user_id = tp.user_id 
                    AND t.tanggal BETWEEN tp.periode_mulai AND tp.periode_selesai
                    AND dt.barang_id = tpr.barang_id
                ), 0)
        END as total_pencapaian,
        CASE 
            WHEN tp.jenis_target = 'omset' THEN 
                (tp.target_nominal * tp.insentif_persen / 100)
            ELSE 
                (tpr.jumlah_target * tpr.insentif_per_unit)
        END as total_insentif
    FROM target_penjualan tp
    JOIN users u ON tp.user_id = u.id
    LEFT JOIN target_produk tpr ON tp.id = tpr.target_id
    LEFT JOIN barang b ON tpr.barang_id = b.id
    ORDER BY tp.created_at DESC";

try {
    $stmtTarget = $conn->query($queryTarget);
    $targets = $stmtTarget->fetchAll(PDO::FETCH_ASSOC);

    // Update status target berdasarkan progress
    foreach ($targets as $target) {
        $persentase = 0;

        if ($target['jenis_target'] === 'omset') {
            if ($target['target_nominal'] > 0) {
                $persentase = ($target['total_pencapaian'] / $target['target_nominal']) * 100;
            }
        } else {
            if ($target['jumlah_target'] > 0) {
                $persentase = ($target['total_pencapaian'] / $target['jumlah_target']) * 100;
            }
        }

        // Update status jika progress 100% atau lebih
        if ($persentase >= 100 && $target['status'] === 'Aktif') {
            $stmt = $conn->prepare("UPDATE target_penjualan SET status = 'Selesai' WHERE id = ?");
            $stmt->execute([$target['id']]);
        }
    }

    // Ambil data target terbaru setelah update
    $stmtTarget = $conn->query($queryTarget);
    $targets = $stmtTarget->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $targets = [];
}

// Query untuk absensi hari ini
$today = date('Y-m-d');
$queryAbsensi = "SELECT ak.*, u.nama as nama_karyawan
                FROM absensi_karyawan ak
                JOIN users u ON ak.user_id = u.id
                WHERE DATE(ak.tanggal) = ?
                ORDER BY ak.jam_masuk DESC";
$stmtAbsensi = $conn->prepare($queryAbsensi);
$stmtAbsensi->execute([$today]);
$absensi = $stmtAbsensi->fetchAll();

// Fungsi PHP untuk warna progress
function getProgressColor($percentage)
{
    if ($percentage >= 100) return 'bg-green-500';
    if ($percentage >= 75) return 'bg-blue-500';
    if ($percentage >= 50) return 'bg-yellow-500';
    return 'bg-red-500';
}

// Fungsi PHP untuk format angka
function formatNumber($number, $isRupiah = false)
{
    if ($isRupiah) {
        return 'Rp ' . number_format($number, 0, ',', '.');
    }
    return number_format($number, 0, ',', '.');
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Karyawan - PAksesories</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>

<body class="bg-gray-50">
    <?php include '../components/sidebar.php'; ?>
    <?php include '../components/navbar.php'; ?>

    <div class="ml-64 p-8 pt-24">
        <!-- Header - Menggunakan gradient yang lebih mewah -->
        <div class="bg-gradient-to-r from-blue-600 via-blue-500 to-blue-400 rounded-[2rem] p-8 mb-8 shadow-lg">
            <div class="max-w-3xl">
                <h1 class="text-3xl font-bold text-white mb-3">Manajemen Karyawan</h1>
                <p class="text-blue-100 text-lg">Kelola data dan performa karyawan Anda dengan lebih efisien</p>
            </div>
        </div>

        <!-- Menu Cards - Styling yang lebih mewah -->
        <div class="flex gap-4 mb-8">
            <a href="?tab=karyawan"
                class="flex items-center gap-4 px-6 py-4 bg-white rounded-2xl shadow-sm transition-all duration-300 hover:shadow-md
                      <?= $activeTab === 'karyawan' ? 'ring-2 ring-blue-500 bg-blue-50/50' : 'text-gray-600 hover:bg-gray-50' ?>">
                <div class="<?= $activeTab === 'karyawan' ? 'bg-blue-500 text-white' : 'bg-blue-100 text-blue-500' ?> p-3 rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
                <div>
                    <span class="font-semibold text-lg block">Data Karyawan</span>
                    <span class="text-sm text-gray-500">Kelola informasi karyawan</span>
                </div>
            </a>

            <a href="?tab=target"
                class="flex items-center gap-4 px-6 py-4 bg-white rounded-2xl shadow-sm transition-all duration-300 hover:shadow-md
                      <?= $activeTab === 'target' ? 'ring-2 ring-blue-500 bg-blue-50/50' : 'text-gray-600 hover:bg-gray-50' ?>">
                <div class="<?= $activeTab === 'target' ? 'bg-blue-500 text-white' : 'bg-blue-100 text-blue-500' ?> p-3 rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                </div>
                <div>
                    <span class="font-semibold text-lg block">Target & Komisi</span>
                    <span class="text-sm text-gray-500">Kelola target dan komisi</span>
                </div>
            </a>

            <a href="?tab=absensi"
                class="flex items-center gap-4 px-6 py-4 bg-white rounded-2xl shadow-sm transition-all duration-300 hover:shadow-md
                      <?= $activeTab === 'absensi' ? 'ring-2 ring-blue-500 bg-blue-50/50' : 'text-gray-600 hover:bg-gray-50' ?>">
                <div class="<?= $activeTab === 'absensi' ? 'bg-blue-500 text-white' : 'bg-blue-100 text-blue-500' ?> p-3 rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <span class="font-semibold text-lg block">Absensi</span>
                    <span class="text-sm text-gray-500">Kelola absensi karyawan</span>
                </div>
            </a>
        </div>

        <!-- Tambahkan div untuk toast notification di bagian atas konten -->
        <div id="toast-notification" class="fixed top-4 right-4 z-50 transform transition-all duration-300 translate-x-full">
            <div class="flex items-center p-4 mb-4 rounded-lg shadow-lg min-w-[300px]" role="alert">
                <div class="inline-flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-lg">
                    <svg class="w-5 h-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5Zm3.707 8.207-4 4a1 1 0 0 1-1.414 0l-2-2a1 1 0 0 1 1.414-1.414L9 10.586l3.293-3.293a1 1 0 0 1 1.414 1.414Z" />
                    </svg>
                </div>
                <div id="toast-message" class="ml-3 text-sm font-normal"></div>
                <button type="button" onclick="hideToast()" class="ml-auto -mx-1.5 -my-1.5 rounded-lg p-1.5 hover:text-gray-900 inline-flex items-center justify-center h-8 w-8">
                    <span class="sr-only">Close</span>
                    <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Content Section - Styling yang lebih mewah -->
        <div id="tab-karyawan" class="tab-content <?= $activeTab === 'karyawan' ? '' : 'hidden' ?>">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100">
                <div class="p-8">
                    <div class="flex justify-between items-center mb-8">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-800 mb-1">Daftar Karyawan</h2>
                            <p class="text-gray-500">Kelola data karyawan Anda</p>
                        </div>
                        <button onclick="showModal('modal-tambah-karyawan')"
                            class="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                            Tambah Karyawan
                        </button>
                    </div>

                    <!-- Tabel dengan styling yang lebih rapi -->
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b border-gray-100">
                                    <th class="pb-5 text-sm font-semibold text-gray-600 uppercase tracking-wider text-left px-4 w-[150px]">Nama</th>
                                    <th class="pb-5 text-sm font-semibold text-gray-600 uppercase tracking-wider text-left px-4 w-[180px]">Email</th>
                                    <th class="pb-5 text-sm font-semibold text-gray-600 uppercase tracking-wider text-left px-4 w-[180px]">Tempat, Tgl Lahir</th>
                                    <th class="pb-5 text-sm font-semibold text-gray-600 uppercase tracking-wider text-left px-4 w-[120px]">Jenis Kelamin</th>
                                    <th class="pb-5 text-sm font-semibold text-gray-600 uppercase tracking-wider text-left px-4 w-[130px]">Telepon</th>
                                    <th class="pb-5 text-sm font-semibold text-gray-600 uppercase tracking-wider text-left px-4 w-[150px]">Alamat</th>
                                    <th class="pb-5 text-sm font-semibold text-gray-600 uppercase tracking-wider text-left px-4 w-[130px]">Gaji</th>
                                    <th class="pb-5 text-sm font-semibold text-gray-600 uppercase tracking-wider text-left px-4 w-[180px]">Bank & No. Rek</th>
                                    <th class="pb-5 text-sm font-semibold text-gray-600 uppercase tracking-wider text-left px-4 w-[130px]">Tgl Bergabung</th>
                                    <th class="pb-5 text-sm font-semibold text-gray-600 uppercase tracking-wider text-left px-4 w-[100px]">Role</th>
                                    <th class="pb-5 text-sm font-semibold text-gray-600 uppercase tracking-wider text-left px-4 w-[100px]">Status</th>
                                    <th class="pb-5 text-sm font-semibold text-gray-600 uppercase tracking-wider text-left px-4 w-[80px]">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <?php foreach ($karyawan as $k): ?>
                                    <tr class="text-sm text-gray-600 hover:bg-gray-50/50 transition-colors duration-200">
                                        <td class="py-4 px-4 truncate"><?= htmlspecialchars($k['nama']) ?></td>
                                        <td class="py-4 px-4 truncate"><?= htmlspecialchars($k['email']) ?></td>
                                        <td class="py-4 px-4">
                                            <?= $k['tempat_lahir'] ? htmlspecialchars($k['tempat_lahir']) : '-' ?>
                                            <?= $k['tanggal_lahir'] ? ', ' . date('d/m/Y', strtotime($k['tanggal_lahir'])) : '' ?>
                                        </td>
                                        <td class="py-4 px-4"><?= htmlspecialchars($k['jenis_kelamin'] ?: '-') ?></td>
                                        <td class="py-4 px-4"><?= htmlspecialchars($k['telepon'] ?: '-') ?></td>
                                        <td class="py-4 px-4 truncate max-w-[150px]"><?= htmlspecialchars($k['alamat'] ?: '-') ?></td>
                                        <td class="py-4 px-4">
                                            <?= $k['gaji'] ? 'Rp ' . number_format($k['gaji'], 0, ',', '.') : 'Rp 0' ?>
                                        </td>
                                        <td class="py-4 px-4">
                                            <?php if ($k['bank'] && $k['nomor_rekening']): ?>
                                                <?= htmlspecialchars($k['bank']) ?> - <?= htmlspecialchars($k['nomor_rekening']) ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-4 px-4"><?= $k['tanggal_bergabung'] ? date('d/m/Y', strtotime($k['tanggal_bergabung'])) : '-' ?></td>
                                        <td class="py-4 px-4">
                                            <span class="px-3 py-1 rounded-full text-xs font-medium inline-block min-w-[90px] text-center
                                            <?= $k['role'] === 'Operator' ? 'bg-purple-100 text-purple-700' : 'bg-orange-100 text-orange-700' ?>">
                                                <?= htmlspecialchars($k['role']) ?>
                                            </span>
                                        </td>
                                        <td class="py-4 px-4">
                                            <span class="px-3 py-1 rounded-full text-xs font-medium inline-block min-w-[80px] text-center
                                            <?= $k['status'] === 'Aktif' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                                                <?= htmlspecialchars($k['status']) ?>
                                            </span>
                                        </td>
                                        <td class="py-4 px-4">
                                            <div class="flex items-center gap-2">
                                                <button onclick="editKaryawan(<?= $k['id'] ?>)"
                                                    class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors duration-200">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                </button>
                                                <button onclick="deleteKaryawan(<?= $k['id'] ?>)"
                                                    class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors duration-200">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
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
        </div>

        <!-- Modal Tambah Karyawan -->
        <div id="modal-tambah-karyawan" class="fixed inset-0 z-50 hidden overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75"></div>

                <div class="relative bg-white rounded-2xl max-w-md w-full">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4">Tambah Karyawan</h3>

                        <form id="form-tambah-karyawan" method="POST">
                            <input type="hidden" name="action" value="add">
                            <div class="space-y-4">
                                <!-- Data Pribadi -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Nama</label>
                                    <input type="text" name="nama" required class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Tempat Lahir</label>
                                        <input type="text" name="tempat_lahir" required class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Lahir</label>
                                        <input type="date" name="tanggal_lahir" required class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Jenis Kelamin</label>
                                    <select name="jenis_kelamin" required class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                        <option value="Laki-laki">Laki-laki</option>
                                        <option value="Perempuan">Perempuan</option>
                                    </select>
                                </div>

                                <!-- Kontak -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                    <input type="email" name="email" required class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                                    <input type="password" name="password" required class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Telepon</label>
                                    <input type="tel" name="telepon" required class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Alamat</label>
                                    <textarea name="alamat" required rows="2" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-1 focus:ring-blue-500"></textarea>
                                </div>

                                <!-- Informasi Pekerjaan -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                                    <select name="role" required class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                        <option value="Operator">Operator</option>
                                        <option value="Kasir">Kasir</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Gaji</label>
                                    <input type="text" name="gaji" id="edit-gaji" required
                                        oninput="formatRupiah(this)"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Bank</label>
                                        <select name="bank" required class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                            <option value="BCA">BCA</option>
                                            <option value="Mandiri">Mandiri</option>
                                            <option value="BNI">BNI</option>
                                            <option value="BRI">BRI</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Nomor Rekening</label>
                                        <input type="text" name="nomor_rekening" required class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Bergabung</label>
                                    <input type="date" name="tanggal_bergabung" required class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                </div>
                            </div>

                            <div class="mt-6 flex justify-end gap-3">
                                <button type="button" onclick="hideModal('modal-tambah-karyawan')" class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 focus:outline-none">
                                    Batal
                                </button>
                                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none">
                                    Simpan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Edit Karyawan -->
        <div id="modal-edit-karyawan" class="fixed inset-0 z-50 hidden overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75"></div>

                <div class="relative bg-white rounded-2xl max-w-md w-full">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4">Edit Karyawan</h3>

                        <form id="form-edit-karyawan" method="POST">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="id" id="edit-id">
                            <div class="space-y-4">
                                <!-- Data Pribadi -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Nama</label>
                                    <input type="text" name="nama" id="edit-nama" required
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Tempat Lahir</label>
                                        <input type="text" name="tempat_lahir" id="edit-tempat_lahir" required
                                            class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Lahir</label>
                                        <input type="date" name="tanggal_lahir" id="edit-tanggal_lahir" required
                                            class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Jenis Kelamin</label>
                                    <select name="jenis_kelamin" id="edit-jenis_kelamin" required
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                        <option value="Laki-laki">Laki-laki</option>
                                        <option value="Perempuan">Perempuan</option>
                                    </select>
                                </div>

                                <!-- Kontak -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                    <input type="email" name="email" id="edit-email" required
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Password (Kosongkan jika tidak diubah)</label>
                                    <input type="password" name="password" id="edit-password"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Telepon</label>
                                    <input type="tel" name="telepon" id="edit-telepon" required
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Alamat</label>
                                    <textarea name="alamat" id="edit-alamat" rows="2" required
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-1 focus:ring-blue-500"></textarea>
                                </div>

                                <!-- Informasi Pekerjaan -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                                    <select name="role" id="edit-role" required
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                        <option value="Operator">Operator</option>
                                        <option value="Kasir">Kasir</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                    <select name="status" id="edit-status" required
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                        <option value="Aktif">Aktif</option>
                                        <option value="Tidak Aktif">Tidak Aktif</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Gaji</label>
                                    <input type="text" name="gaji" id="edit-gaji" required
                                        oninput="formatRupiah(this)"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Bank</label>
                                        <select name="bank" id="edit-bank" required
                                            class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                            <option value="BCA">BCA</option>
                                            <option value="Mandiri">Mandiri</option>
                                            <option value="BNI">BNI</option>
                                            <option value="BRI">BRI</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Nomor Rekening</label>
                                        <input type="text" name="nomor_rekening" id="edit-nomor_rekening" required
                                            class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Bergabung</label>
                                    <input type="date" name="tanggal_bergabung" id="edit-tanggal_bergabung" required
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                </div>
                            </div>

                            <div class="mt-6 flex justify-end gap-3">
                                <button type="button" onclick="hideModal('modal-edit-karyawan')"
                                    class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 focus:outline-none">
                                    Batal
                                </button>
                                <button type="submit"
                                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none">
                                    Simpan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Content: Target & Komisi -->
        <div id="tab-target" class="tab-content <?= $activeTab === 'target' ? '' : 'hidden' ?>">
            <div class="bg-white rounded-xl shadow-sm">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-lg font-semibold">Target Penjualan & Komisi</h2>
                        <button type="button"
                            onclick="showModal('modal-tambah-target')"
                            class="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 text-white rounded-xl hover:bg-blue-700">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                            Tambah Target
                        </button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Karyawan
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Periode
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Jenis Target
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Target
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Progress
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Insentif
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($targets)): ?>
                                    <tr>
                                        <td colspan="8" class="px-6 py-4 text-center text-gray-500">
                                            Belum ada data target penjualan
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($targets as $t): ?>
                                        <?php
                                        // Hitung persentase dan format pencapaian
                                        if ($t['jenis_target'] === 'omset') {
                                            $target = $t['target_nominal'];
                                            $pencapaian = formatNumber($t['total_pencapaian'], true);
                                        } else {
                                            $target = $t['jumlah_target'];
                                            $pencapaian = formatNumber($t['total_pencapaian']) . ' unit';
                                        }
                                        $persentase = $target > 0 ? min(($t['total_pencapaian'] / $target) * 100, 100) : 0;
                                        ?>
                                        <tr>
                                            <td class="px-6 py-4"><?= htmlspecialchars($t['nama_karyawan']) ?></td>
                                            <td class="px-6 py-4">
                                                <?= date('d/m/Y', strtotime($t['periode_mulai'])) ?> -
                                                <?= date('d/m/Y', strtotime($t['periode_selesai'])) ?>
                                            </td>
                                            <td class="px-6 py-4">
                                                <span class="px-2 py-1 text-xs rounded-full 
                                                    <?= $t['jenis_target'] === 'omset' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800' ?>">
                                                    <?= ucfirst($t['jenis_target']) ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4">
                                                <?php if ($t['jenis_target'] === 'omset'): ?>
                                                    <?= formatNumber($t['target_nominal'], true) ?>
                                                <?php else: ?>
                                                    <?= formatNumber($t['jumlah_target']) ?> <?= htmlspecialchars($t['nama_barang']) ?>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="flex flex-col gap-1">
                                                    <div class="flex justify-between text-sm">
                                                        <span>Progress</span>
                                                        <span><?= number_format($persentase, 1) ?>%</span>
                                                    </div>
                                                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                                                        <div class="h-2.5 rounded-full transition-all duration-500 <?= getProgressColor($persentase) ?>"
                                                            style="width: <?= $persentase ?>%">
                                                        </div>
                                                    </div>
                                                    <div class="text-sm text-gray-500">
                                                        <?= $pencapaian ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <?php if ($t['jenis_target'] === 'omset'): ?>
                                                    <?= $t['insentif_persen'] ?>%
                                                    <div class="text-sm text-gray-500 mt-1">
                                                        (<?= formatNumber($t['total_insentif'], true) ?>)
                                                    </div>
                                                <?php else: ?>
                                                    <?= formatNumber($t['insentif_per_unit'], true) ?>/unit
                                                    <div class="text-sm text-gray-500 mt-1">
                                                        (<?= formatNumber($t['total_insentif'], true) ?>)
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4">
                                                <span class="px-2 py-1 text-xs rounded-full 
                                                    <?= $t['status'] === 'Aktif' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                                                    <?= htmlspecialchars($t['status']) ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="flex items-center gap-2">
                                                    <button onclick="deleteTarget(<?= $t['id'] ?>)"
                                                        class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors duration-200">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Content: Absensi -->
        <div id="tab-absensi" class="tab-content <?= $activeTab === 'absensi' ? '' : 'hidden' ?>">
            <div class="bg-white rounded-xl shadow-sm">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-lg font-semibold">Absensi Karyawan</h2>
                        <div>
                            <input type="date" id="tanggalAbsensi" value="<?= date('Y-m-d') ?>"
                                class="border rounded-lg px-3 py-2 mr-2">
                            <button onclick="showAddAbsensiModal()" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600">
                                Input Absensi
                            </button>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Karyawan</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Jam Masuk</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Jam Keluar</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Keterangan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($absensi as $a): ?>
                                    <tr>
                                        <td class="px-6 py-4"><?= htmlspecialchars($a['nama_karyawan']) ?></td>
                                        <td class="px-6 py-4"><?= $a['jam_masuk'] ? date('H:i', strtotime($a['jam_masuk'])) : '-' ?></td>
                                        <td class="px-6 py-4"><?= $a['jam_keluar'] ? date('H:i', strtotime($a['jam_keluar'])) : '-' ?></td>
                                        <td class="px-6 py-4">
                                            <span class="px-2 py-1 text-xs rounded-full 
        <?php
                                    switch ($a['status']) {
                                        case 'Hadir':
                                            echo 'bg-green-100 text-green-800';
                                            break;
                                        case 'Izin':
                                            echo 'bg-yellow-100 text-yellow-800';
                                            break;
                                        case 'Sakit':
                                            echo 'bg-orange-100 text-orange-800';
                                            break;
                                        case 'Alfa':
                                            echo 'bg-red-100 text-red-800';
                                            break;
                                    }
        ?>">
                                                <?= htmlspecialchars($a['status']) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4"><?= htmlspecialchars($a['keterangan'] ?? '-') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Tambah Target -->
    <div id="modal-tambah-target" class="fixed inset-0 z-50 hidden">
        <div class="fixed inset-0 bg-black bg-opacity-50"></div>
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="relative bg-white rounded-2xl max-w-3xl w-full mx-auto">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold">Tambah Target Penjualan</h3>
                        <button type="button"
                            onclick="hideModal('modal-tambah-target')"
                            class="text-gray-400 hover:text-gray-500">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <form action="karyawan.php" method="POST" id="form-tambah-target">
                        <input type="hidden" name="action" value="add_target">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Karyawan</label>
                                <select name="user_id" required class="w-full rounded-lg border border-gray-300 px-3 py-2">
                                    <option value="">Pilih Karyawan</option>
                                    <?php
                                    $stmt = $conn->query("SELECT id, nama FROM users WHERE role IN ('Operator', 'Kasir') AND status = 'Aktif'");
                                    while ($k = $stmt->fetch()) {
                                        echo "<option value='{$k['id']}'>{$k['nama']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Jenis Target</label>
                                <select name="jenis_target" id="jenis-target" required onchange="toggleTargetForm()"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2">
                                    <option value="omset">Target Omset</option>
                                    <option value="produk">Target Produk</option>
                                </select>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Periode Mulai</label>
                                    <input type="date" name="periode_mulai" required
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Periode Selesai</label>
                                    <input type="date" name="periode_selesai" required
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2">
                                </div>
                            </div>

                            <div id="form-target-omset">
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Target Nominal</label>
                                    <input type="text" name="target_nominal" id="target_nominal"
                                        oninput="formatRupiah(this); hitungTotalInsentif();"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2">
                                </div>
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Insentif (%)</label>
                                    <input type="number" name="insentif_persen" id="insentif_persen"
                                        min="0" max="100" step="0.01" oninput="hitungTotalInsentif()"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2">
                                </div>
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Total Insentif yang didapatkan</label>
                                    <div class="w-full rounded-lg border border-gray-300 px-3 py-2 bg-gray-50">
                                        <span id="total_insentif">Rp 0</span>
                                    </div>
                                </div>
                            </div>

                            <div id="form-target-produk" class="hidden">
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Produk</label>
                                    <select name="produk_id" class="w-full rounded-lg border border-gray-300 px-3 py-2">
                                        <option value="">Pilih Produk</option>
                                        <?php
                                        $stmt = $conn->query("SELECT b.id, b.nama_barang, s.jumlah as stok 
                                                    FROM barang b 
                                                    JOIN stok s ON b.id = s.barang_id 
                                                            WHERE s.jumlah > 0");
                                        while ($b = $stmt->fetch()) {
                                            echo "<option value='{$b['id']}' data-stok='{$b['stok']}'>
                                                        {$b['nama_barang']} (Stok: {$b['stok']})
                                                    </option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Target Jumlah</label>
                                    <input type="number" name="target_jumlah" id="target_jumlah" min="1"
                                        oninput="hitungTotalInsentifProduk()"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2">
                                </div>
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Insentif per Unit</label>
                                    <input type="text" name="insentif_unit" id="insentif_unit"
                                        oninput="formatRupiah(this); hitungTotalInsentifProduk()"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2">
                                </div>
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Total Insentif yang didapatkan</label>
                                    <div class="w-full rounded-lg border border-gray-300 px-3 py-2 bg-gray-50">
                                        <span id="total_insentif_produk">Rp 0</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end gap-3">
                            <button type="button" onclick="hideModal('modal-tambah-target')"
                                class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">
                                Batal
                            </button>
                            <button type="submit"
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                Simpan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Tambahkan Modal Konfirmasi Delete -->
    <div id="modal-konfirmasi-delete" class="fixed inset-0 z-50 hidden">
        <div class="fixed inset-0 bg-black bg-opacity-50"></div>
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="relative bg-white rounded-xl max-w-md w-full mx-auto p-6">
                <div class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-900">Konfirmasi Hapus</h3>
                    <p class="text-gray-500 mt-2">Apakah Anda yakin ingin menghapus target ini?</p>
                </div>
                <div class="flex justify-end gap-3">
                    <button onclick="hideDeleteModal()"
                        class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors duration-200">
                        Batal
                    </button>
                    <button onclick="confirmDelete()"
                        class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors duration-200">
                        Hapus
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Tambahkan Modal Konfirmasi Delete Karyawan -->
    <div id="modal-konfirmasi-delete-karyawan" class="fixed inset-0 z-50 hidden">
        <div class="fixed inset-0 bg-black bg-opacity-50"></div>
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="relative bg-white rounded-xl max-w-md w-full mx-auto p-6">
                <div class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-900">Konfirmasi Hapus</h3>
                    <p class="text-gray-500 mt-2">Apakah Anda yakin ingin menghapus karyawan ini?</p>
                </div>
                <div class="flex justify-end gap-3">
                    <button onclick="hideDeleteKaryawanModal()"
                        class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors duration-200">
                        Batal
                    </button>
                    <button onclick="confirmDeleteKaryawan()"
                        class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors duration-200">
                        Hapus
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Tambahkan fungsi showTab kembali di awal script
        function showTab(tabId) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.add('hidden');
            });

            // Show selected tab content
            document.getElementById('tab-' + tabId).classList.remove('hidden');

            // Update URL without page reload
            const url = new URL(window.location);
            url.searchParams.set('tab', tabId);
            window.history.pushState({}, '', url);

            // Update tab buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                if (btn.textContent.toLowerCase().includes(tabId)) {
                    btn.classList.add('text-blue-600', 'border-b-2', 'border-blue-600', 'font-semibold');
                    btn.classList.remove('text-gray-500');
                } else {
                    btn.classList.remove('text-blue-600', 'border-b-2', 'border-blue-600', 'font-semibold');
                    btn.classList.add('text-gray-500');
                }
            });
        }

        // Fungsi untuk menampilkan modal
        function showModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
            document.getElementById(modalId).classList.remove('hidden');
        }

        // Fungsi untuk menyembunyikan modal
        function hideModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.getElementById(modalId).classList.add('hidden');
        }

        // Event listener untuk format rupiah
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('input[name="target_nominal"], input[name="insentif_unit[]"]').forEach(input => {
                input.addEventListener('input', function() {
                    formatRupiah(this);
                });
            });
        });

        function editKaryawan(id) {
            fetch(`karyawan.php?action=get_karyawan&id=${id}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('edit-id').value = data.id;
                    document.getElementById('edit-nama').value = data.nama;
                    document.getElementById('edit-email').value = data.email;
                    document.getElementById('edit-tempat_lahir').value = data.tempat_lahir;
                    document.getElementById('edit-tanggal_lahir').value = data.tanggal_lahir;
                    document.getElementById('edit-jenis_kelamin').value = data.jenis_kelamin;
                    document.getElementById('edit-role').value = data.role;
                    document.getElementById('edit-telepon').value = data.telepon;
                    document.getElementById('edit-alamat').value = data.alamat;
                    document.getElementById('edit-gaji').value = new Intl.NumberFormat('id-ID').format(data.gaji);
                    document.getElementById('edit-bank').value = data.bank;
                    document.getElementById('edit-nomor_rekening').value = data.nomor_rekening;
                    document.getElementById('edit-tanggal_bergabung').value = data.tanggal_bergabung;
                    document.getElementById('edit-status').value = data.status || 'Aktif';

                    showModal('modal-edit-karyawan');
                })
                .catch(error => console.error('Error:', error));
        }

        // Tambahkan event listener untuk form submit
        document.getElementById('form-tambah-karyawan').addEventListener('submit', function(e) {
            e.preventDefault();
            // Hapus format sebelum submit
            let gajiInput = this.querySelector('[name="gaji"]');
            gajiInput.value = gajiInput.value.replace(/[^\d]/g, '');
            this.submit();
        });

        document.getElementById('form-edit-karyawan').addEventListener('submit', function(e) {
            e.preventDefault();
            // Hapus format sebelum submit
            let gajiInput = this.querySelector('[name="gaji"]');
            gajiInput.value = gajiInput.value.replace(/[^\d]/g, '');
            this.submit();
        });

        /* Fungsi untuk format rupiah */
        function formatRupiah(input) {
            let value = input.value.replace(/[^\d]/g, '');
            if (value !== '') {
                value = parseInt(value).toLocaleString('id-ID');
                input.value = value;
            }
        }

        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast-notification');
            const toastMessage = document.getElementById('toast-message');
            const toastDiv = toast.querySelector('div');
            const toastIcon = toast.querySelector('svg');

            // Set pesan
            toastMessage.textContent = message;

            // Set warna berdasarkan tipe
            if (type === 'success') {
                toastDiv.className = 'flex items-center p-4 mb-4 text-green-800 bg-green-50 rounded-lg shadow-lg min-w-[300px]';
                toastIcon.className = 'w-5 h-5 text-green-600';
            } else if (type === 'error') {
                toastDiv.className = 'flex items-center p-4 mb-4 text-red-800 bg-red-50 rounded-lg shadow-lg min-w-[300px]';
                toastIcon.className = 'w-5 h-5 text-red-600';
            }

            // Tampilkan toast
            toast.classList.remove('translate-x-full');
            toast.classList.add('translate-x-0');

            // Sembunyikan toast setelah 3 detik
            setTimeout(hideToast, 3000);
        }

        function hideToast() {
            const toast = document.getElementById('toast-notification');
            toast.classList.remove('translate-x-0');
            toast.classList.add('translate-x-full');
        }

        // Update fungsi submit form untuk menampilkan toast
        document.getElementById('form-tambah-karyawan').addEventListener('submit', function(e) {
            e.preventDefault();
            let gajiInput = this.querySelector('[name="gaji"]');
            gajiInput.value = gajiInput.value.replace(/[^\d]/g, '');

            // Submit form
            this.submit();

            // Tampilkan toast
            showToast('Data karyawan berhasil ditambahkan!', 'success');
        });

        document.getElementById('form-edit-karyawan').addEventListener('submit', function(e) {
            e.preventDefault();
            let gajiInput = this.querySelector('[name="gaji"]');
            gajiInput.value = gajiInput.value.replace(/[^\d]/g, '');

            // Submit form
            this.submit();

            // Tampilkan toast
            showToast('Data karyawan berhasil diperbarui!', 'success');
        });

        // Tambahkan pengecekan session untuk menampilkan toast saat halaman dimuat
        <?php if (isset($_SESSION['success'])): ?>
            showToast('<?= $_SESSION['success'] ?>', 'success');
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            showToast('<?= $_SESSION['error'] ?>', 'error');
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        function toggleTargetForm() {
            const jenisTarget = document.getElementById('jenis-target').value;
            const formOmset = document.getElementById('form-target-omset');
            const formProduk = document.getElementById('form-target-produk');

            if (jenisTarget === 'omset') {
                formOmset.classList.remove('hidden');
                formProduk.classList.add('hidden');
                document.getElementById('total_insentif').textContent = 'Rp 0';
            } else {
                formOmset.classList.add('hidden');
                formProduk.classList.remove('hidden');
                document.getElementById('total_insentif_produk').textContent = 'Rp 0';
            }
        }

        // Event listener untuk select produk
        document.addEventListener('DOMContentLoaded', function() {
            const selectProduk = document.querySelector('select[name="produk_id"]');
            if (selectProduk) {
                selectProduk.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    const stok = selectedOption.dataset.stok;
                    document.querySelector('.stok-info').textContent = stok || '0';
                });
            }
        });

        // Event listener untuk form tambah target
        document.getElementById('form-tambah-target').addEventListener('submit', function(e) {
            e.preventDefault();

            // Hapus format rupiah sebelum submit
            const jenisTarget = document.getElementById('jenis-target').value;
            if (jenisTarget === 'omset') {
                let targetNominal = this.querySelector('[name="target_nominal"]');
                targetNominal.value = targetNominal.value.replace(/[^\d]/g, '');
            } else {
                let insentifUnit = this.querySelector('[name="insentif_unit"]');
                insentifUnit.value = insentifUnit.value.replace(/[^\d]/g, '');
            }

            // Submit form
            this.submit();
        });

        // Tambahkan fungsi untuk menghitung total insentif omset
        function hitungTotalInsentif() {
            const targetNominal = document.getElementById('target_nominal').value.replace(/[^\d]/g, '');
            const insentifPersen = document.getElementById('insentif_persen').value;

            if (targetNominal && insentifPersen) {
                const totalInsentif = (parseFloat(targetNominal) * parseFloat(insentifPersen)) / 100;
                document.getElementById('total_insentif').textContent =
                    'Rp ' + new Intl.NumberFormat('id-ID').format(totalInsentif);
            } else {
                document.getElementById('total_insentif').textContent = 'Rp 0';
            }
        }

        // Tambahkan fungsi untuk menghitung total insentif produk
        function hitungTotalInsentifProduk() {
            const targetJumlah = document.getElementById('target_jumlah').value;
            const insentifUnit = document.getElementById('insentif_unit').value.replace(/[^\d]/g, '');

            if (targetJumlah && insentifUnit) {
                const totalInsentif = parseFloat(targetJumlah) * parseFloat(insentifUnit);
                document.getElementById('total_insentif_produk').textContent =
                    'Rp ' + new Intl.NumberFormat('id-ID').format(totalInsentif);
            } else {
                document.getElementById('total_insentif_produk').textContent = 'Rp 0';
            }
        }

        let targetIdToDelete = null;

        function deleteTarget(id) {
            targetIdToDelete = id;
            document.getElementById('modal-konfirmasi-delete').classList.remove('hidden');
        }

        function hideDeleteModal() {
            document.getElementById('modal-konfirmasi-delete').classList.add('hidden');
            targetIdToDelete = null;
        }

        function confirmDelete() {
            if (targetIdToDelete) {
                // Kirim request delete ke server
                fetch('karyawan.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=delete_target&id=${targetIdToDelete}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        hideDeleteModal();
                        if (data.success) {
                            showToast('Target berhasil dihapus', 'success');
                            // Reload halaman setelah berhasil hapus
                            setTimeout(() => {
                                window.location.reload();
                            }, 1000);
                        } else {
                            showToast(data.message || 'Gagal menghapus target', 'error');
                        }
                    })
                    .catch(error => {
                        hideDeleteModal();
                        showToast('Terjadi kesalahan', 'error');
                    });
            }
        }

        let karyawanIdToDelete = null;

        function deleteKaryawan(id) {
            karyawanIdToDelete = id;
            document.getElementById('modal-konfirmasi-delete-karyawan').classList.remove('hidden');
        }

        function hideDeleteKaryawanModal() {
            document.getElementById('modal-konfirmasi-delete-karyawan').classList.add('hidden');
            karyawanIdToDelete = null;
        }

        function confirmDeleteKaryawan() {
            if (karyawanIdToDelete) {
                fetch('karyawan.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=delete_karyawan&id=${karyawanIdToDelete}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        hideDeleteKaryawanModal();
                        if (data.success) {
                            showToast('Karyawan berhasil dihapus', 'success');
                            setTimeout(() => {
                                window.location.reload();
                            }, 1000);
                        } else {
                            showToast(data.message || 'Gagal menghapus karyawan', 'error');
                        }
                    })
                    .catch(error => {
                        hideDeleteKaryawanModal();
                        showToast('Terjadi kesalahan', 'error');
                    });
            }
        }
    </script>
</body>

</html>