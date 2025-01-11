<?php
require_once '../backend/check_session.php';
require_once '../backend/database.php';

// Ambil data user
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Handle update profile
if (isset($_POST['update_profile'])) {
    try {
        $nama = $_POST['nama'];
        $email = $_POST['email'];
        
        // Update user data
        $stmt = $conn->prepare("UPDATE users SET nama = ?, email = ? WHERE id = ?");
        $stmt->execute([$nama, $email, $_SESSION['user_id']]);
        
        // Update session
        $_SESSION['nama'] = $nama;
        $_SESSION['email'] = $email;
        
        $success = "Profil berhasil diperbarui!";
    } catch(PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Handle change password
if (isset($_POST['change_password'])) {
    try {
        $old_password = $_POST['old_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Verify old password
        if (!password_verify($old_password, $user['password'])) {
            throw new Exception("Password lama tidak sesuai!");
        }
        
        // Verify new passwords match
        if ($new_password !== $confirm_password) {
            throw new Exception("Password baru tidak cocok!");
        }
        
        // Update password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashed_password, $_SESSION['user_id']]);
        
        $success = "Password berhasil diubah!";
    } catch(Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle avatar upload
if (isset($_FILES['avatar'])) {
    try {
        $file = $_FILES['avatar'];
        $allowed_types = ['image/jpeg', 'image/png'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($file['type'], $allowed_types)) {
            throw new Exception("Tipe file tidak didukung! Gunakan JPG atau PNG.");
        }
        
        if ($file['size'] > $max_size) {
            throw new Exception("Ukuran file terlalu besar! Maksimal 5MB.");
        }
        
        $upload_dir = '../uploads/avatars/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $filename = uniqid() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
        $destination = $upload_dir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            // Update database with new avatar
            $stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
            $stmt->execute([$filename, $_SESSION['user_id']]);
            
            $success = "Avatar berhasil diperbarui!";
        } else {
            throw new Exception("Gagal mengupload file!");
        }
    } catch(Exception $e) {
        $error = $e->getMessage();
    }
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
    </style>
</head>
<body class="bg-gray-50">
    <?php include '../components/sidebar.php'; ?>
    <?php include '../components/navbar.php'; ?>

    <div class="ml-64 pt-16 min-h-screen bg-gradient-to-br from-gray-50 to-gray-100">
        <div class="p-8">
            <!-- Header Section -->
            <div class="mb-8 gradient-animation rounded-3xl p-10 text-white shadow-2xl relative overflow-hidden">
                <!-- Decorative elements -->
                <div class="absolute top-0 right-0 w-64 h-64 bg-white/10 rounded-full -translate-y-32 translate-x-32 blur-3xl"></div>
                <div class="absolute bottom-0 left-0 w-64 h-64 bg-blue-500/20 rounded-full translate-y-32 -translate-x-32 blur-3xl"></div>
                
                <div class="relative">
                    <h1 class="text-3xl font-bold mb-2">Profile Settings</h1>
                    <p class="text-blue-100 text-lg">Manage your account settings and preferences</p>
                </div>
            </div>

            <!-- Alert Messages dengan animasi yang lebih halus -->
            <?php if (isset($success)): ?>
            <div class="mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 animate-fade-in">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-emerald-100 rounded-xl">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-medium text-emerald-900">Success!</h3>
                        <p class="text-sm text-emerald-700"><?= $success ?></p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
            <div class="mb-6 p-4 rounded-2xl bg-red-50 text-red-700 border border-red-100 animate-fade-in">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-red-100 rounded-xl">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <p class="font-medium"><?= $error ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Grid Container -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Profile Card -->
                <div class="lg:col-span-1">
                    <div class="glass-effect rounded-3xl p-8 custom-shadow">
                        <div class="text-center">
                            <div class="relative inline-block group">
                                <div class="w-40 h-40 rounded-2xl overflow-hidden ring-4 ring-gray-50 shadow-lg transition-transform duration-300">
                                    <?php if ($user['avatar']): ?>
                                        <img src="../uploads/avatars/<?= $user['avatar'] ?>" 
                                             alt="Profile" 
                                             class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <img src="https://api.dicebear.com/7.x/bottts/svg?seed=<?= $user['nama'] ?>" 
                                             alt="Profile" 
                                             class="w-full h-full object-cover">
                                    <?php endif; ?>
                                </div>
                                <button onclick="document.getElementById('avatarInput').click()" 
                                        class="absolute bottom-2 right-2 p-3 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition-all duration-300 shadow-lg">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                              d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                              d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                </button>
                            </div>
                            <h2 class="mt-6 text-2xl font-bold text-gray-800"><?= htmlspecialchars($user['nama']) ?></h2>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-50 text-blue-600 mt-2">
                                <?= htmlspecialchars($user['role']) ?>
                            </span>
                        </div>

                        <!-- Info Cards dengan hover effect yang lebih halus -->
                        <div class="mt-8 space-y-4">
                            <div class="p-4 rounded-2xl bg-gray-50 hover:bg-gray-100/80 transition-colors duration-300">
                                <div class="flex items-center gap-4">
                                    <div class="p-3 bg-blue-100 rounded-xl">
                                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                  d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Email</p>
                                        <p class="text-base font-semibold text-gray-800 mt-1"><?= htmlspecialchars($user['email']) ?></p>
                                    </div>
                                </div>
                            </div>

                            <div class="p-4 rounded-2xl bg-gray-50 hover:bg-gray-100/80 transition-colors duration-300">
                                <div class="flex items-center gap-4">
                                    <div class="p-3 bg-green-100 rounded-xl">
                                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                  d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Status</p>
                                        <p class="text-base font-semibold text-gray-800 mt-1"><?= htmlspecialchars($user['status']) ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Settings Cards -->
                <div class="lg:col-span-2 space-y-8">
                    <!-- Profile Settings -->
                    <div class="glass-effect rounded-3xl p-8 custom-shadow">
                        <h3 class="text-xl font-bold text-gray-800 mb-6">Profile Information</h3>
                        <form action="" method="POST" class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Nama</label>
                                    <input type="text" 
                                           name="nama" 
                                           value="<?= htmlspecialchars($user['nama']) ?>" 
                                           class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all duration-300">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                                    <input type="email" 
                                           name="email" 
                                           value="<?= htmlspecialchars($user['email']) ?>" 
                                           class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all duration-300">
                                </div>
                            </div>
                            <div class="pt-4">
                                <button type="submit" 
                                        name="update_profile" 
                                        class="px-8 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-xl hover:shadow-lg transition-all duration-300">
                                    Update Profile
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Change Password -->
                    <div class="glass-effect rounded-3xl p-8 custom-shadow">
                        <h3 class="text-xl font-bold text-gray-800 mb-6">Change Password</h3>
                        <form action="" method="POST" class="space-y-6">
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
    </script>
</body>
</html> 