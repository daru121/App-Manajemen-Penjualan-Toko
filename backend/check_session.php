<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index");
    exit;
}

// Set timezone di awal file
date_default_timezone_set('Asia/Makassar'); // Set timezone ke WITA

// Redirect jika mencoba mengakses dashboard sebagai Kasir
if ($_SESSION['role'] === 'Kasir' && basename($_SERVER['PHP_SELF']) === 'dashboard.php') {
    header("Location: penjualan");
    exit;
}
?> 