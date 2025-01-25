<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

// Redirect jika mencoba mengakses dashboard.php sebagai Kasir
if ($_SESSION['role'] === 'Kasir' && basename($_SERVER['PHP_SELF']) === 'dashboard.php') {
    header("Location: penjualan.php");
    exit;
}
?> 