<?php
$host = 'localhost';
$db_name = 'u394234331_daru';
$username = 'u394234331_darucaraka';
$password = 'lN5e:al@+';

try {
    $dsn = "mysql:host=$host;dbname=$db_name;charset=utf8mb4";
    $conn = new PDO($dsn, $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Set timezone untuk koneksi database
    $conn->exec("SET time_zone = '+08:00'");
    $conn->exec("SET @@session.time_zone = '+08:00'");
} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    exit();
}
?>