<?php
session_start();
require_once 'backend/database.php';

// Handle registration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = $_POST['nama'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = 'Operator'; // Default role
    $status = 'Aktif'; // Default status

    try {
    // Check if email already exists
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        
        if ($check->rowCount() > 0) {
            $error = "Email sudah terdaftar!";
        } else {
    // Insert new user
            $stmt = $conn->prepare("INSERT INTO users (nama, email, password, role, status) VALUES (?, ?, ?, ?, ?)");
            if ($stmt->execute([$nama, $email, $password, $role, $status])) {
                $_SESSION['success'] = "Register Berhasil, Silahkan Login";
                header("Location: index.php");
                exit;
            } else {
                $error = "Registration gagal!";
            }
        }
    } catch(PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jamu Air Mancur - Register</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #ffffff;
        }
        
        .glass-card {
            background: #ffffff;
            border: 1px solid rgba(0, 0, 0, 0.05);
            box-shadow: 
                0 8px 32px rgba(0, 0, 0, 0.05),
                0 2px 4px rgba(0, 0, 0, 0.02);
        }
        
        .gradient-border {
            position: relative;
            border-radius: 24px;
            padding: 1.5px;
            background: linear-gradient(
                135deg,
                rgba(255, 255, 255, 0.6) 0%,
                rgba(99, 102, 241, 0.5) 25%,
                rgba(59, 130, 246, 0.5) 75%,
                rgba(255, 255, 255, 0.6) 100%
            );
            box-shadow: 
                0 0 20px rgba(99, 102, 241, 0.15),
                0 0 40px rgba(59, 130, 246, 0.1);
        }
        
        .luxury-shadow {
            box-shadow:
                0 0 0 1px rgba(255, 255, 255, 0.1),
                0 8px 20px rgba(0, 0, 0, 0.08),
                0 16px 48px rgba(0, 0, 0, 0.08);
        }
        
        .hover-lift {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .hover-lift:hover {
            transform: translateY(-2px);
            box-shadow: 
                0 12px 40px rgba(0, 0, 0, 0.12),
                0 2px 6px rgba(0, 0, 0, 0.04);
        }
        
        @keyframes shine {
            from { background-position: 200% center; }
            to { background-position: -200% center; }
        }
        
        .animate-shine {
            background: linear-gradient(
                120deg,
                rgba(255,255,255,0) 30%,
                rgba(255,255,255,0.8) 50%,
                rgba(255,255,255,0) 70%
            );
            background-size: 200% auto;
            animation: shine 3s linear infinite;
        }
        
        .input-container {
            position: relative;
            margin-bottom: 1.5rem;
        }
        
        .input-field {
            width: 100%;
            padding: 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.75rem;
            outline: none;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .input-label {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
            font-size: 1rem;
            transition: all 0.3s ease;
            pointer-events: none;
            padding: 0 0.5rem;
            margin-top: 0.5px;
            line-height: 1;
        }
        
        .input-field:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }
        
        .input-field:not(:focus):not(:placeholder-shown) {
            border-color: #e5e7eb;
            box-shadow: none;
        }
        
        .input-field:focus + .input-label,
        .input-field:not(:placeholder-shown) + .input-label {
            top: 0;
            font-size: 0.875rem;
            padding: 0 0.5rem;
            margin-top: -1px;
            background-color: white;
        }
        
        .input-field:focus + .input-label {
            color: #6366f1;
        }
        
        .input-field:not(:focus):not(:placeholder-shown) + .input-label {
            color: #6b7280;
        }
        
        .btn-shine {
            position: relative;
            overflow: hidden;
        }
        
        .btn-shine::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(
                45deg,
                transparent 0%,
                rgba(255, 255, 255, 0.1) 30%,
                rgba(255, 255, 255, 0.2) 50%,
                rgba(255, 255, 255, 0.1) 70%,
                transparent 100%
            );
            transform: rotate(45deg);
            animation: shine-effect 3s infinite;
        }
        
        @keyframes shine-effect {
            0% { transform: translateX(-100%) rotate(45deg); }
            100% { transform: translateX(100%) rotate(45deg); }
        }
        
        .input-group {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 0.5rem;
            transition: all 0.2s ease;
        }
        
        .input-icon:hover {
            background: rgba(99, 102, 241, 0.1);
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        .float-animation {
            animation: float 6s ease-in-out infinite;
        }

        .fixed.inset-0.pointer-events-none.overflow-hidden {
            display: none;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md relative z-10">
        <div class="gradient-border">
            <div class="glass-card p-10 rounded-[23px] luxury-shadow">
                <!-- Logo & Title -->
                <div class="text-center mb-10">
                    <div class="inline-flex items-center justify-center w-24 h-24 rounded-3xl 
                        bg-gradient-to-br from-indigo-600 via-blue-600 to-blue-700 shadow-2xl 
                        mb-10 transform hover:scale-110 transition-all duration-500 
                        relative overflow-hidden group float-animation">
                        <img src="img/Jamu.png" alt="Logo" class="w-12 h-12 object-cover rounded-xl">
                        <div class="absolute inset-0 animate-shine"></div>
                        <div class="absolute inset-0 bg-gradient-to-t from-black/30 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                    </div>
                    <h1 class="text-4xl font-bold bg-gradient-to-r from-indigo-600 via-blue-500 to-blue-400 bg-clip-text text-transparent mb-3 tracking-tight">
                        Daftar Akun
                    </h1>
                    <p class="text-gray-500/90 tracking-wide font-medium">Daftar untuk memulai</p>
                </div>

                <?php if (isset($error)): ?>
                    <div class="mb-6 p-4 rounded-xl bg-red-50/50 backdrop-blur-sm border border-red-100/50">
                        <p class="text-sm text-red-600"><?= htmlspecialchars($error) ?></p>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-7">
                    <!-- Full Name Field -->
                    <div class="input-container">
                        <input type="text" 
                               name="nama" 
                               id="nama"
                               class="input-field"
                               placeholder=" "
                               required>
                        <label for="nama" class="input-label">Nama Lengkap</label>
                    </div>

                    <!-- Email Field -->
                    <div class="input-container">
                        <input type="email" 
                               name="email" 
                               id="email"
                               class="input-field"
                               placeholder=" "
                               required>
                        <label for="email" class="input-label">Email</label>
                    </div>

                    <!-- Password Field -->
                    <div class="input-container">
                        <input type="password" 
                               name="password" 
                               id="password"
                               class="input-field"
                               placeholder=" "
                               required>
                        <label for="password" class="input-label">Password</label>
                        <button type="button" 
                                onclick="togglePassword('password', 'eyeIcon')"
                                class="absolute right-3 top-1/2 -translate-y-1/2 p-2 hover:bg-gray-100 rounded-full">
                            <svg id="eyeIcon" class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Confirm Password Field -->
                    <div class="input-container">
                        <input type="password" 
                               name="confirm_password" 
                               id="confirm_password"
                               class="input-field"
                               placeholder=" "
                               required>
                        <label for="confirm_password" class="input-label">Konfirmasi Password</label>
                        <button type="button" 
                                onclick="togglePassword('confirm_password', 'eyeIconConfirm')"
                                class="absolute right-3 top-1/2 -translate-y-1/2 p-2 hover:bg-gray-100 rounded-full">
                            <svg id="eyeIconConfirm" class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Register Button -->
                    <button type="submit" 
                            class="w-full py-4 px-5 bg-gradient-to-r from-indigo-600 via-blue-600 to-blue-700 text-white rounded-xl shadow-lg hover:shadow-xl hover:scale-[1.02] transform transition-all duration-300 hover:from-indigo-500 hover:via-blue-500 hover:to-blue-600 btn-shine">
                        <span class="relative z-10 font-semibold tracking-wide">Daftar Akun</span>
                    </button>
                </form>
            </div>
        </div>

        <!-- Enhanced Footer -->
        <p class="text-center text-sm text-gray-600/90 mt-10 backdrop-blur-sm py-3 tracking-wide">
            Sudah memiliki akun? 
            <a href="index.php" class="text-indigo-600 hover:text-indigo-700 font-semibold transition-all hover:underline decoration-2 underline-offset-4 hover:decoration-indigo-400">
                Masuk
            </a>
        </p>
    </div>

    <script>
        // Copy semua script dari index.php dan tambahkan fungsi untuk confirm password
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('.input-field');
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');

            inputs.forEach(input => {
                if (input.value) {
                    input.classList.add('has-value');
                }
                
                input.addEventListener('input', function() {
                    if (this.value) {
                        this.classList.add('has-value');
                    } else {
                        this.classList.remove('has-value');
                    }
                });
            });

            // Password validation
            function validatePasswords() {
                if (confirmPassword.value === '') {
                    confirmPassword.style.borderColor = '#e5e7eb';
                    return;
                }

                if (password.value !== confirmPassword.value) {
                    confirmPassword.style.borderColor = '#ef4444';
                } else {
                    confirmPassword.style.borderColor = '#e5e7eb';
                }
            }

            password.addEventListener('input', validatePasswords);
            confirmPassword.addEventListener('input', validatePasswords);
        });

        function togglePassword(inputId, iconId) {
            const passwordInput = document.getElementById(inputId);
            const eyeIcon = document.getElementById(iconId);
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.innerHTML = `
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                `;
            } else {
                passwordInput.type = 'password';
                eyeIcon.innerHTML = `
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                `;
            }
        }
    </script>
</body>
</html> 