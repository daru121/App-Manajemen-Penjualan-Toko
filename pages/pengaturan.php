<?php
session_start();
require_once '../backend/check_session.php';
require_once '../backend/database.php';

// Ambil data user
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Handle update profile
if (isset($_POST['update_profile'])) {
    try {
        // Validasi input
        if (empty($_POST['nama'])) {
            throw new Exception("Nama tidak boleh kosong!");
        }
        if (empty($_POST['email'])) {
            throw new Exception("Email tidak boleh kosong!");
        }

        // Cek email sudah digunakan atau belum (kecuali email sendiri)
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$_POST['email'], $_SESSION['user_id']]);
        if ($stmt->fetch()) {
            throw new Exception("Email sudah digunakan!");
        }

        // Update user data
        $stmt = $conn->prepare("UPDATE users SET nama = ?, email = ?, telepon = ?, alamat = ? WHERE id = ?");
        $stmt->execute([
            $_POST['nama'],
            $_POST['email'],
            $_POST['telepon'],
            $_POST['alamat'],
            $_SESSION['user_id']
        ]);

        $_SESSION['success'] = "Profil berhasil diperbarui!";
        header("Location: pengaturan.php");
        exit;
    } catch(Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header("Location: pengaturan.php");
        exit;
    }
}

// Handle change password
if (isset($_POST['change_password'])) {
    try {
        // Validasi input
        if (empty($_POST['old_password']) || empty($_POST['new_password']) || empty($_POST['confirm_password'])) {
            throw new Exception("Semua field password harus diisi!");
        }

        // Verifikasi password lama
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $current_password = $stmt->fetchColumn();

        if (!password_verify($_POST['old_password'], $current_password)) {
            throw new Exception("Password lama tidak sesuai!");
        }

        // Validasi password baru
        if ($_POST['new_password'] !== $_POST['confirm_password']) {
            throw new Exception("Konfirmasi password tidak sesuai!");
        }

        if (strlen($_POST['new_password']) < 6) {
            throw new Exception("Password minimal 6 karakter!");
        }

        // Update password
        $new_password_hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$new_password_hash, $_SESSION['user_id']]);

        $_SESSION['success'] = "Password berhasil diubah!";
        header("Location: pengaturan.php");
        exit;
    } catch(Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header("Location: pengaturan.php");
        exit;
    }
}

// Handle avatar upload
if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
    try {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['avatar']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            throw new Exception("Format file tidak diizinkan!");
        }

        if ($_FILES['avatar']['size'] > 800000) {
            throw new Exception("Ukuran file terlalu besar! Maksimal 800KB");
        }

        $new_filename = uniqid() . "." . $ext;
        $upload_path = "../uploads/avatars/";

        // Buat direktori jika belum ada
        if (!file_exists($upload_path)) {
            mkdir($upload_path, 0777, true);
        }

        // Hapus avatar lama jika ada
        if ($user['avatar'] && file_exists($upload_path . $user['avatar'])) {
            unlink($upload_path . $user['avatar']);
        }

        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_path . $new_filename)) {
            $stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
            $stmt->execute([$new_filename, $_SESSION['user_id']]);

            $_SESSION['success'] = "Avatar berhasil diperbarui!";
        } else {
            throw new Exception("Gagal mengupload file!");
        }
    } catch(Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    header("Location: pengaturan.php");
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - PAksesories</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }

        /* Gradient Background Animation */
        .gradient-animation {
            background: linear-gradient(120deg, #4F46E5, #2563EB, #3B82F6, #60A5FA);
            background-size: 300% 300%;
            animation: gradient 15s ease infinite;
        }

        @keyframes gradient {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* Glass Effect */
        .glass-effect {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        /* Custom Shadows */
        .custom-shadow {
            box-shadow: 0 0 50px -12px rgba(0, 0, 0, 0.12);
        }

        /* Smooth Transitions */
        .smooth-transition {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        #alert {
            opacity: 1;
            transform: translateY(0);
            transition: all 0.3s ease-in-out;
        }

        /* Remove previous tab styles */
        #accountTab, #securityTab {
            transition: all 0.3s ease;
        }

        /* Update hover effects */
        .hover\:bg-white\/50:hover {
            background-color: rgb(255 255 255 / 0.5);
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include '../components/sidebar.php'; ?>
    <?php include '../components/navbar.php'; ?>

    <div class="p-4 sm:ml-64 pt-20 sm:p-8">
        <div class="p-4 sm:p-8">
            <!-- Header Section - Responsive -->
            <div class="mb-6 gradient-animation rounded-xl sm:rounded-3xl p-6 sm:p-10 text-white shadow-lg sm:shadow-2xl relative overflow-hidden">
                <!-- Decorative elements -->
                <div class="absolute top-0 right-0 w-64 h-64 bg-white/10 rounded-full -translate-y-32 translate-x-32 blur-3xl"></div>
                <div class="absolute bottom-0 left-0 w-64 h-64 bg-blue-500/20 rounded-full translate-y-32 -translate-x-32 blur-3xl"></div>
                
                <div class="relative">
                    <h1 class="text-2xl sm:text-3xl font-bold mb-2">Pengaturan Akun</h1>
                    <p class="text-sm sm:text-lg text-blue-100">Ubah informasi akun dan keamanan Anda</p>
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

            <!-- Tab Navigation - Enhanced Style -->
            <div class="p-1.5 bg-gray-100/80 backdrop-blur-xl rounded-2xl inline-flex gap-2 shadow-sm mb-6">
                <button onclick="switchTab('account')" 
                        id="accountTab"
                        class="flex items-center gap-2 px-6 py-3 rounded-xl font-medium transition-all duration-300">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" 
                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    Account
                </button>
                
                <button onclick="switchTab('security')" 
                        id="securityTab"
                        class="flex items-center gap-2 px-6 py-3 rounded-xl font-medium transition-all duration-300">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" 
                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    Keamanan
                </button>
            </div>

            <!-- Account Tab Content -->
            <div id="accountContent" class="space-y-4 sm:space-y-6">
                <div class="glass-effect rounded-xl sm:rounded-3xl p-4 sm:p-8 custom-shadow bg-white/60 backdrop-blur-xl">
                    <div class="flex flex-col lg:flex-row gap-6 sm:gap-12">
                        <!-- Left Side - Photo & Status -->
                        <div class="w-full lg:w-80">
                            <!-- Profile Photo -->
                            <div class="text-center p-4 sm:p-8 bg-gradient-to-b from-gray-50 to-white rounded-xl sm:rounded-2xl border border-gray-100 shadow-sm">
                                <div class="relative inline-block">
                                    <div class="w-28 h-28 sm:w-40 sm:h-40 rounded-2xl overflow-hidden ring-4 ring-white shadow-xl transition-transform duration-300">
                                        <?php if ($user['avatar']): ?>
                                            <img id="previewImage" 
                                                 src="../uploads/avatars/<?= $user['avatar'] ?>" 
                                                 alt="Profile" 
                                                 class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <img id="previewImage" 
                                                 src="https://api.dicebear.com/7.x/bottts/svg?seed=<?= $user['nama'] ?>&backgroundColor=6366F1&textureChance=50&mouthChance=100&sidesChance=100&spots=50&eyes=happy" 
                                                 alt="Profile" 
                                                 class="w-full h-full object-cover">
                                        <?php endif; ?>
                                    </div>
                                    <button onclick="document.getElementById('avatarInput').click()" 
                                            class="absolute -bottom-3 right-0 left-0 mx-auto w-11 h-11 p-2 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition-all duration-300 shadow-lg flex items-center justify-center group">
                                        <svg class="w-5 h-5 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                    </button>
                                    <input type="file" id="avatarInput" name="avatar" class="hidden" accept="image/*">
                                </div>
                                <div class="mt-6">
                                    <h3 class="text-xl font-semibold text-gray-800"><?= htmlspecialchars($user['nama']) ?></h3>
                                    <p class="text-sm text-gray-500 mt-1"><?= htmlspecialchars($user['email']) ?></p>
                                </div>
                                <p class="mt-4 text-xs text-gray-400">Allowed JPG, GIF or PNG. Max size of 800K</p>
                            </div>

                            <!-- Status Cards - Responsive -->
                            <div class="mt-4 sm:mt-6 space-y-3 sm:space-y-4">
                                <!-- Role Card -->
                                <div class="p-4 sm:p-5 rounded-xl sm:rounded-2xl bg-gradient-to-br from-blue-50 to-indigo-50/50 border border-blue-100/50">
                                    <div class="flex items-center gap-4">
                                        <div class="p-3 bg-blue-600/10 rounded-xl">
                                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-blue-900/60">Role</p>
                                            <p class="text-lg font-semibold text-blue-900"><?= htmlspecialchars($user['role']) ?></p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Status Card -->
                                <div class="p-4 sm:p-5 rounded-xl sm:rounded-2xl bg-gradient-to-br from-emerald-50 to-green-50/50 border border-emerald-100/50">
                                    <div class="flex items-center gap-4">
                                        <div class="p-3 bg-emerald-600/10 rounded-xl">
                                            <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-emerald-900/60">Status</p>
                                            <p class="text-lg font-semibold text-emerald-900"><?= htmlspecialchars($user['status']) ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Side - Form -->
                        <div class="flex-1">
                            <div class="p-4 sm:p-8 bg-gradient-to-b from-gray-50 to-white rounded-xl sm:rounded-2xl border border-gray-100 shadow-sm">
                                <!-- Form Header -->
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 sm:mb-8">
                                    <h3 class="text-lg sm:text-xl font-semibold text-gray-800">Personal Information</h3>
                                    <span class="px-3 py-1 text-xs font-medium text-blue-700 bg-blue-50 rounded-lg">Profile Details</span>
                                </div>

                                <!-- Form Fields -->
                                <form action="" method="POST" class="space-y-6">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
                                        <!-- Nama -->
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium text-gray-600">Nama</label>
                                            <div class="relative">
                                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                                    </svg>
                                                </div>
                                                <input type="text" 
                                                       name="nama" 
                                                       value="<?= htmlspecialchars($user['nama']) ?>" 
                                                       class="w-full pl-11 pr-4 py-2.5 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all duration-300">
                                            </div>
                                        </div>

                                        <!-- Email -->
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium text-gray-600">Email</label>
                                            <div class="relative">
                                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                                    </svg>
                                                </div>
                                                <input type="email" 
                                                       name="email" 
                                                       value="<?= htmlspecialchars($user['email']) ?>" 
                                                       class="w-full pl-11 pr-4 py-2.5 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all duration-300">
                                            </div>
                                        </div>

                                        <!-- Telepon -->
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium text-gray-600">Telepon</label>
                                            <div class="relative">
                                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                                    </svg>
                                                </div>
                                                <input type="tel" 
                                                       name="telepon" 
                                                       value="<?= htmlspecialchars($user['telepon']) ?>" 
                                                       class="w-full pl-11 pr-4 py-2.5 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all duration-300">
                                            </div>
                                        </div>

                                        <!-- Alamat -->
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium text-gray-600">Alamat</label>
                                            <div class="relative">
                                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    </svg>
                                                </div>
                                                <input type="text" 
                                                       name="alamat" 
                                                       value="<?= htmlspecialchars($user['alamat']) ?>" 
                                                       class="w-full pl-11 pr-4 py-2.5 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all duration-300">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Form Buttons -->
                                    <div class="flex flex-col sm:flex-row justify-end gap-3 pt-6 sm:pt-8 border-t border-gray-100 mt-6 sm:mt-8">
                                        <button type="reset" 
                                                class="px-6 py-2.5 border border-gray-200 text-gray-600 rounded-xl hover:bg-gray-50 transition-all duration-300 flex items-center gap-2 group">
                                            <svg class="w-5 h-5 group-hover:rotate-180 transition-transform duration-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                            </svg>
                                            Reset
                                        </button>
                                        <button type="submit" 
                                                name="update_profile" 
                                                class="px-6 py-2.5 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-xl hover:shadow-lg transition-all duration-300 flex items-center gap-2 group">
                                            <svg class="w-5 h-5 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 13l4 4L19 7"/>
                                            </svg>
                                            Save Changes
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Security Tab Content -->
            <div id="securityContent" class="space-y-4 sm:space-y-6 hidden">
                <!-- Change Password Card -->
                <div class="glass-effect rounded-xl sm:rounded-3xl p-4 sm:p-8 custom-shadow">
                    <h3 class="text-lg sm:text-xl font-bold text-gray-800 mb-4 sm:mb-6">Change Password</h3>
                    <form action="" method="POST" class="space-y-4 sm:space-y-6">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Password Lama</label>
                                <input type="password" 
                                       name="old_password" 
                                       class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all duration-300">
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Password Baru</label>
                                    <input type="password" 
                                           name="new_password" 
                                           class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all duration-300">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Konfirmasi Password</label>
                                    <input type="password" 
                                           name="confirm_password" 
                                           class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all duration-300">
                                </div>
                            </div>
                        </div>
                        <div class="pt-4">
                            <button type="submit" 
                                    name="change_password" 
                                    class="px-8 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-xl hover:shadow-lg transition-all duration-300">
                                Change Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Handle avatar upload
    document.getElementById('avatarInput').addEventListener('change', function() {
        const formData = new FormData();
        formData.append('avatar', this.files[0]);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => window.location.reload())
        .catch(error => console.error('Error:', error));
    });

    function switchTab(tab) {
        // Hide all content
        document.getElementById('accountContent').classList.add('hidden');
        document.getElementById('securityContent').classList.add('hidden');
        
        // Remove active classes from all tabs
        document.getElementById('accountTab').classList.remove(
            'bg-white', 'text-blue-600', 'shadow-lg', 'shadow-blue-500/10', 'scale-[1.02]', 'ring-1', 'ring-black/5'
        );
        document.getElementById('securityTab').classList.remove(
            'bg-white', 'text-blue-600', 'shadow-lg', 'shadow-blue-500/10', 'scale-[1.02]', 'ring-1', 'ring-black/5'
        );
        
        // Add inactive classes
        document.getElementById('accountTab').classList.add('text-gray-500', 'hover:text-gray-600', 'hover:bg-white/50');
        document.getElementById('securityTab').classList.add('text-gray-500', 'hover:text-gray-600', 'hover:bg-white/50');
        
        // Show selected content and activate tab
        if (tab === 'account') {
            document.getElementById('accountContent').classList.remove('hidden');
            document.getElementById('accountTab').classList.remove('text-gray-500', 'hover:text-gray-600', 'hover:bg-white/50');
            document.getElementById('accountTab').classList.add(
                'bg-white', 'text-blue-600', 'shadow-lg', 'shadow-blue-500/10', 'scale-[1.02]', 'ring-1', 'ring-black/5'
            );
        } else {
            document.getElementById('securityContent').classList.remove('hidden');
            document.getElementById('securityTab').classList.remove('text-gray-500', 'hover:text-gray-600', 'hover:bg-white/50');
            document.getElementById('securityTab').classList.add(
                'bg-white', 'text-blue-600', 'shadow-lg', 'shadow-blue-500/10', 'scale-[1.02]', 'ring-1', 'ring-black/5'
            );
        }
    }

    // Initialize with account tab active
    document.addEventListener('DOMContentLoaded', function() {
        switchTab('account');
    });

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
</body>
</html> 