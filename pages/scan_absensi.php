<?php
// Cek jika ini adalah request scan (ada parameter token dan user_id)
if (isset($_GET['token']) && isset($_GET['user_id'])) {
    // Bypass session check untuk scan QR
    require_once '../backend/database.php';
} else {
    // Tetap require session check untuk halaman utama
    require_once '../backend/check_session.php';
    require_once '../backend/database.php';
}

// Tambahkan di bagian paling atas file setelah require statements
date_default_timezone_set('Asia/Makassar'); // Set timezone ke WITA

// Tambahkan fungsi helper di bagian atas file
function tanggalIndonesia($date) {
    $hari = [
        'Sunday' => 'Minggu',
        'Monday' => 'Senin',
        'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis',
        'Friday' => 'Jumat',
        'Saturday' => 'Sabtu'
    ];
    
    $bulan = [
        'January' => 'Januari',
        'February' => 'Februari',
        'March' => 'Maret',
        'April' => 'April',
        'May' => 'Mei',
        'June' => 'Juni',
        'July' => 'Juli',
        'August' => 'Agustus',
        'September' => 'September',
        'October' => 'Oktober',
        'November' => 'November',
        'December' => 'Desember'
    ];
    
    $tanggal = date('l, d F Y', strtotime($date));
    
    return strtr($tanggal, array_merge($hari, $bulan));
}

// Get all active users/karyawan
$stmt = $conn->prepare("SELECT id, nama FROM users WHERE status = 'Aktif' ORDER BY nama ASC");
$stmt->execute();
$karyawan_list = $stmt->fetchAll();

// Get selected user data (default to logged in user if not selected)
$selected_user_id = $_GET['user_id'] ?? $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT nama FROM users WHERE id = ?");
$stmt->execute([$selected_user_id]);
$selected_user = $stmt->fetch();

// Generate unique token untuk QR Code
function generateQRToken() {
    $timestamp = time();
    $user_id = $_SESSION['user_id'];
    $secret_key = "PAksesories2024"; 
    return hash('sha256', $timestamp . $user_id . $secret_key);
}

// Handle absensi request
if (isset($_GET['token']) && isset($_GET['user_id'])) {
    try {
        $qr_token = $_GET['token'];
        $user_id = $_GET['user_id'];
        $current_time = date('H:i:s');
        
        // Verifikasi token (toleransi 30 detik)
        $timestamp = time();
        $valid_token = false;
        
        // Cek token untuk 30 detik terakhir
        for ($i = 0; $i <= 30; $i++) {
            $check_time = $timestamp - $i;
            $check_token = hash('sha256', $check_time . $user_id . "PAksesories2024");
            if ($qr_token === $check_token) {
                $valid_token = true;
                break;
            }
        }
        
        if ($valid_token) {
            // Cek apakah sudah absen hari ini
            $check_query = "SELECT * FROM absensi_karyawan WHERE user_id = ? AND DATE(tanggal) = CURRENT_DATE";
            $stmt = $conn->prepare($check_query);
            $stmt->execute([$user_id]);
            
            if ($stmt->rowCount() > 0) {
                // Ambil data absensi terlebih dahulu
                $absensi_data = $stmt->fetch();
                $jam_masuk = $absensi_data['jam_masuk'];
                
                // Update jam keluar
                $update_query = "UPDATE absensi_karyawan SET jam_keluar = ? WHERE user_id = ? AND DATE(tanggal) = CURRENT_DATE";
                $stmt = $conn->prepare($update_query);
                $stmt->execute([$current_time, $user_id]);
                
                $message = "Absen pulang berhasil!";
                $status = "success";
                $jam_keluar = $current_time;
            } else {
                // Insert absen masuk baru
                $insert_query = "INSERT INTO absensi_karyawan (user_id, tanggal, jam_masuk, status) VALUES (?, CURRENT_DATE, ?, 'Hadir')";
                $stmt = $conn->prepare($insert_query);
                $stmt->execute([$user_id, $current_time]);
                
                $message = "Absen masuk berhasil!";
                $status = "success";
                $jam_masuk = $current_time;
                $jam_keluar = null;
            }
        } else {
            $message = "QR Code tidak valid atau kadaluarsa!";
            $status = "error";
        }
    } catch (Exception $e) {
        $message = "Terjadi kesalahan: " . $e->getMessage();
        $status = "error";
    }
    
    // Tampilkan halaman hasil scan
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>  
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Hasil Absensi - Jamu Air Mancur</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    </head>
    <body class="bg-gray-100 flex items-center justify-center min-h-screen p-4">
        <div class="max-w-md w-full">
            <div class="bg-white rounded-2xl p-8 shadow-lg border border-gray-100">
                <!-- Icon Success/Error -->
                <div class="flex justify-center mb-4">
                    <?php if ($status === 'success'): ?>
                        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center animate-bounce">
                            <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </div>
                    <?php else: ?>
                        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center animate-bounce">
                            <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Message -->
                <div class="text-center mb-8">
                    <h2 class="text-2xl font-bold mb-2 <?= $status === 'success' ? 'text-green-600' : 'text-red-600' ?>">
                        <?= htmlspecialchars($message) ?>
                    </h2>
                    <p class="text-gray-600 mb-2">
                        <?= tanggalIndonesia(date('Y-m-d')) ?>
                    </p>
                    <p class="text-xl font-medium text-blue-600">
                        <?= date('H:i:s') ?> WITA
                    </p>
                    <?php if ($status === 'success'): ?>
                        <div class="mt-6 p-4 bg-gray-50 rounded-xl">
                            <div class="space-y-2">
                                <?php if ($jam_keluar): ?>
                                    <div class="flex justify-between items-center">
                                        <span class="text-gray-600">Jam Masuk:</span>
                                        <span class="text-green-600 font-medium"><?= date('H:i:s', strtotime($jam_masuk)) ?> WITA</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-gray-600">Jam Keluar:</span>
                                        <span class="text-blue-600 font-medium"><?= date('H:i:s', strtotime($jam_keluar)) ?> WITA</span>
                                    </div>
                                <?php else: ?>
                                    <div class="flex justify-between items-center">
                                        <span class="text-gray-600">Jam Masuk:</span>
                                        <span class="text-green-600 font-medium"><?= date('H:i:s', strtotime($jam_masuk)) ?> WITA</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Buttons -->
                <div class="flex justify-center gap-4">
                    <button onclick="window.close()" 
                            class="px-6 py-2.5 bg-gray-800 text-white rounded-xl hover:bg-gray-700 
                                   transition-all duration-300 flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                        Tutup
                    </button>
                    <button onclick="window.location.reload()" 
                            class="px-6 py-2.5 bg-blue-600 text-white rounded-xl hover:bg-blue-700 
                                   transition-all duration-300 flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        Scan Lagi
                    </button>
                </div>
            </div>
        </div>

        <script>
            // Tambahkan animasi fade in
            document.querySelector('.max-w-md').classList.add('animate-fadeIn');
        </script>

        <style>
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(-20px); }
                to { opacity: 1; transform: translateY(0); }
            }
            
            .animate-fadeIn {
                animation: fadeIn 0.5s ease-out forwards;
            }
            
            .animate-bounce {
                animation: bounce 1s infinite;
            }
            
            @keyframes bounce {
                0%, 100% { transform: translateY(-5%); }
                50% { transform: translateY(0); }
            }
        </style>
    </body>
    </html>
    <?php
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Absensi - Jamu Air Mancur</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
</head>
<body class="bg-gray-50">
    <?php include '../components/sidebar.php'; ?>
    <?php include '../components/navbar.php'; ?>
    
    <div class="p-0 sm:p-4 sm:ml-64">
        <div class="ml-0 sm:ml-4 p-4 sm:p-8 pt-20 sm:pt-24">
            <!-- Header dengan padding yang disesuaikan -->
            <div class="bg-gradient-to-r from-blue-600 via-blue-500 to-blue-400 rounded-[1.5rem] sm:rounded-[2rem] p-6 sm:p-8 mb-6 sm:mb-8 shadow-lg">
                <div class="max-w-3xl">
                    <h1 class="text-2xl sm:text-3xl font-bold text-white mb-2 sm:mb-3">Absensi Karyawan</h1>
                    <p class="text-blue-100 text-base sm:text-lg">QR Code untuk absensi hari ini</p>
                </div>
            </div>

            <!-- Karyawan Selection dengan padding yang disesuaikan -->
            <div class="bg-white rounded-xl sm:rounded-2xl p-4 sm:p-6 shadow-sm border border-gray-100 mb-6 sm:mb-8">
                <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4 sm:gap-6">
                    <!-- Icon dan Label -->
                    <div class="flex items-center gap-3 w-full sm:w-auto">
                        <div class="p-3 bg-blue-50 rounded-xl">
                            <svg class="w-5 h-5 sm:w-6 sm:h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                        <label for="karyawan" class="font-medium text-gray-700">Pilih Karyawan:</label>
                    </div>

                    <!-- Select dengan styling yang lebih modern -->
                    <div class="flex-1 relative w-full">
                        <select id="karyawan" onchange="changeKaryawan(this.value)" 
                                class="w-full appearance-none bg-gray-50 px-4 py-3 rounded-xl border border-gray-200 
                                       focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 
                                       text-gray-700 font-medium transition-all duration-200
                                       hover:bg-gray-100 cursor-pointer">
                            <?php foreach ($karyawan_list as $karyawan): ?>
                                <option value="<?= $karyawan['id'] ?>" 
                                        <?= $karyawan['id'] == $selected_user_id ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($karyawan['nama']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- QR Code Card dengan padding yang disesuaikan -->
            <div class="bg-white rounded-xl sm:rounded-2xl p-6 sm:p-8 shadow-sm border border-gray-100 relative overflow-hidden">
                <!-- Content -->
                <div class="relative">
                    <!-- Header dengan icon -->
                    <div class="text-center mb-6 sm:mb-8">
                        <div class="inline-block p-3 bg-blue-50 rounded-2xl mb-4">
                            <svg class="w-6 h-6 sm:w-8 sm:h-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <h2 class="text-xl sm:text-2xl font-bold text-gray-800 mb-2">
                            <?= tanggalIndonesia(date('Y-m-d')) ?>
                        </h2>
                        <p class="text-gray-500 mb-2">
                            <?= htmlspecialchars($selected_user['nama']) ?>
                        </p>
                        <p class="text-base sm:text-lg font-medium text-blue-600" id="current-time"></p>
                    </div>

                    <!-- QR Code dengan ukuran yang disesuaikan -->
                    <div class="flex justify-center mb-6 sm:mb-8">
                        <div class="relative w-full max-w-[250px] sm:max-w-[300px]">
                            <div class="absolute -inset-4 bg-gradient-to-r from-blue-500 to-purple-500 rounded-2xl opacity-10"></div>
                            <div class="relative bg-white p-4 sm:p-6 rounded-xl shadow-lg border border-gray-100">
                                <div class="absolute -inset-1 bg-gradient-to-r from-blue-500 to-purple-500 rounded-lg opacity-10"></div>
                                <div id="qrcode" class="relative"></div>
                            </div>
                            <!-- Corner Decorations -->
                            <div class="absolute -top-2 -left-2 w-4 h-4 border-t-2 border-l-2 border-blue-500 rounded-tl"></div>
                            <div class="absolute -top-2 -right-2 w-4 h-4 border-t-2 border-r-2 border-blue-500 rounded-tr"></div>
                            <div class="absolute -bottom-2 -left-2 w-4 h-4 border-b-2 border-l-2 border-blue-500 rounded-bl"></div>
                            <div class="absolute -bottom-2 -right-2 w-4 h-4 border-b-2 border-r-2 border-blue-500 rounded-br"></div>
                        </div>
                    </div>

                    <!-- Status Bar dengan animasi -->
                    <div class="text-center">
                        <div class="relative max-w-md mx-auto">
                            <div class="h-1.5 w-full bg-gray-100 rounded-full overflow-hidden">
                                <div class="countdown-progress h-full bg-gradient-to-r from-blue-500 to-purple-500 rounded-full"></div>
                            </div>
                            <p class="text-sm font-medium text-gray-600 mt-3" id="countdown"></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Toast Notification yang responsif -->
            <div id="toast-notification" class="fixed top-4 right-4 left-4 sm:left-auto z-50 hidden">
                <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-4 max-w-md mx-auto sm:mx-0">
                    <div class="flex items-center gap-3">
                        <div class="flex-shrink-0">
                            <div id="toast-icon" class="w-10 h-10 rounded-full flex items-center justify-center">
                                <!-- Icon akan diisi oleh JavaScript -->
                            </div>
                        </div>
                        <div class="flex-1">
                            <p id="toast-message" class="font-medium"></p>
                            <p id="toast-time" class="text-sm text-gray-500 mt-1"></p>
                        </div>
                        <button onclick="hideToast()" class="text-gray-400 hover:text-gray-500">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Function to handle karyawan change
        function changeKaryawan(userId) {
            window.location.href = `scan_absensi.php?user_id=${userId}`;
        }

        // Generate QR Code dengan URL lengkap
        function generateQR(token) {
            const baseUrl = window.location.origin + window.location.pathname;
            const userId = '<?= $selected_user_id ?>';
            const qrUrl = `${baseUrl}?token=${token}&user_id=${userId}`;
            
            const qr = qrcode(0, 'L');
            qr.addData(qrUrl);
            qr.make();
            document.getElementById('qrcode').innerHTML = qr.createImgTag(6);
        }

        // Update QR Code setiap 30 detik
        function updateQRCode() {
            const timestamp = Math.floor(Date.now() / 1000);
            const userId = '<?= $selected_user_id ?>';
            const secretKey = 'PAksesories2024';
            const token = CryptoJS.SHA256(timestamp + userId + secretKey).toString();
            generateQR(token);
            startCountdown();
        }

        // Countdown timer
        function startCountdown() {
            let timeLeft = 30;
            const countdownEl = document.getElementById('countdown');
            
            const timer = setInterval(() => {
                if (timeLeft > 0) {
                    countdownEl.innerHTML = `
                        <span class="inline-flex items-center gap-2">
                            <svg class="w-4 h-4 text-blue-500 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="font-medium">Memperbarui QR Code dalam ${timeLeft} detik</span>
                        </span>
                    `;
                }
                timeLeft--;

                if (timeLeft < 0) {
                    clearInterval(timer);
                    document.querySelector('.countdown-progress').style.animation = 'none';
                    setTimeout(() => {
                        document.querySelector('.countdown-progress').style.animation = 'countdown 30s linear infinite';
                    }, 100);
                }
            }, 1000);
        }

        // Update QR Code pertama kali dan set interval
        updateQRCode();
        setInterval(updateQRCode, 30000);

        // Fungsi untuk menampilkan toast
        function showToast(message, status = 'success') {
            const toast = document.getElementById('toast-notification');
            const toastMessage = document.getElementById('toast-message');
            const toastTime = document.getElementById('toast-time');
            const toastIcon = document.getElementById('toast-icon');
            
            // Set pesan dan waktu
            toastMessage.textContent = message;
            toastTime.textContent = new Date().toLocaleTimeString();
            
            // Set icon dan warna berdasarkan status
            if (status === 'success') {
                toastIcon.className = 'w-10 h-10 bg-green-100 rounded-full flex items-center justify-center';
                toastIcon.innerHTML = `
                    <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                `;
                toastMessage.className = 'font-medium text-green-600';
            } else {
                toastIcon.className = 'w-10 h-10 bg-red-100 rounded-full flex items-center justify-center';
                toastIcon.innerHTML = `
                    <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                `;
                toastMessage.className = 'font-medium text-red-600';
            }
            
            // Tampilkan toast
            toast.classList.remove('hidden');
            
            // Sembunyikan toast setelah 5 detik
            setTimeout(hideToast, 5000);
        }

        // Fungsi untuk menyembunyikan toast
        function hideToast() {
            document.getElementById('toast-notification').classList.add('hidden');
        }

        // Cek parameter URL untuk status absensi
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('scan_status')) {
            const status = urlParams.get('scan_status');
            const message = urlParams.get('message');
            showToast(decodeURIComponent(message), status);
            
            // Bersihkan parameter URL
            window.history.replaceState({}, document.title, window.location.pathname);
        }

        // Update fungsi updateCurrentTime untuk menggunakan waktu server
        function updateCurrentTime() {
            const now = new Date();
            // Konversi ke WITA (UTC+8)
            now.setHours(now.getHours());
            
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            const timeEl = document.getElementById('current-time');
            
            timeEl.innerHTML = `
                <span class="inline-flex items-center gap-2">
                    <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="text-blue-600">${hours}:${minutes}:${seconds} WITA</span>
                </span>
            `;
        }

        // Sinkronkan waktu dengan server saat halaman dimuat
        fetch('get_server_time.php')
            .then(response => response.text())
            .then(serverTime => {
                const time = new Date(serverTime);
                // Set waktu client sesuai server
                const offset = time - new Date();
                
                // Update waktu setiap detik dengan offset dari server
                setInterval(() => {
                    const now = new Date(new Date().getTime() + offset);
                    updateCurrentTime(now);
                }, 1000);
            });
    </script>

    <!-- Tambahkan style untuk animasi -->
    <style>
        @keyframes countdown {
            from { width: 100%; }
            to { width: 0%; }
        }

        .countdown-progress {
            animation: countdown 30s linear infinite;
        }

        /* Tambahkan animasi hover untuk QR code */
        #qrcode:hover {
            transform: scale(1.02);
            transition: all 0.3s ease;
        }

        /* Efek glass morphism */
        .glass-effect {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }

        /* Remove default select styling in some browsers */
        select {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
        }
        
        /* Custom hover effect for select */
        select:hover + .pointer-events-none svg {
            color: #3b82f6; /* text-blue-500 */
        }
    </style>
</body>
</html> 