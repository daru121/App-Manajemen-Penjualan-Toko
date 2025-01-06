-- Create database
CREATE DATABASE IF NOT EXISTS simplepos;
USE simplepos;

-- Create users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nama VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('Admin', 'Operator', 'Kasir') DEFAULT 'Operator',
    status ENUM('Aktif', 'Tidak Aktif') DEFAULT 'Aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create kategori table
CREATE TABLE kategori (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nama_kategori VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create barang table
CREATE TABLE barang (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nama_barang VARCHAR(100) NOT NULL,
    gambar VARCHAR(255) NULL,
    kategori_id INT,
    harga_modal DECIMAL(10,2) NOT NULL,
    harga DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (kategori_id) REFERENCES kategori(id)
);

-- Create stok table
CREATE TABLE stok (
    id INT PRIMARY KEY AUTO_INCREMENT,
    barang_id INT NOT NULL,
    jumlah INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (barang_id) REFERENCES barang(id)
);

-- Create pembeli table
CREATE TABLE pembeli (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nama VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create transaksi table
CREATE TABLE transaksi (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    pembeli_id INT,
    total_harga DECIMAL(10,2) NOT NULL,
    pembayaran DECIMAL(10,2) NOT NULL,
    kembalian DECIMAL(10,2) NOT NULL,
    marketplace ENUM('offline', 'shopee', 'tokopedia', 'tiktok') NOT NULL DEFAULT 'offline',
    tanggal TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (pembeli_id) REFERENCES pembeli(id)
);

-- Create detail_transaksi table
CREATE TABLE detail_transaksi (
    id INT PRIMARY KEY AUTO_INCREMENT,
    transaksi_id INT NOT NULL,
    barang_id INT NOT NULL,
    jumlah INT NOT NULL,
    harga DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (transaksi_id) REFERENCES transaksi(id),
    FOREIGN KEY (barang_id) REFERENCES barang(id)
);

-- Create notifications table
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type VARCHAR(50) NOT NULL, -- 'low_stock', 'out_of_stock', 'high_profit', 'new_order', etc
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reference_id INT NULL, -- ID referensi ke tabel lain (misal: id_barang)
    reference_type VARCHAR(50) NULL -- Tipe referensi ('product', 'order', etc)
);

-- Insert default admin user
INSERT INTO users (nama, email, password, role, status) VALUES 
('Admin', 'admin@admin.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'Aktif');

-- Insert sample kategori
INSERT INTO kategori (nama_kategori) VALUES
('Aksesoris HP'),
('Aksesoris Laptop'),
('Aksesoris Gaming'),
('Aksesoris Fashion');

-- Insert sample barang
INSERT INTO barang (nama_barang, gambar, kategori_id, harga_modal, harga) VALUES
('Headset Gaming RGB', 'headset.jpg', 3, 50000, 75000),
('Screen Protector iPhone', 'screen.jpg', 1, 15000, 25000),
('Mouse Pad Gaming', 'mouse.jpg', 3, 20000, 35000),
('Cooling Pad Laptop', 'cooling.jpg', 2, 75000, 120000),
('Phone Holder', 'phone.jpg', 1, 10000, 20000);

-- Insert sample stok
INSERT INTO stok (barang_id, jumlah) VALUES
(1, 50),
(2, 100),
(3, 75),
(4, 30),
(5, 60);

-- Add daerah column to transaksi table if not exists
ALTER TABLE transaksi 
ADD COLUMN IF NOT EXISTS daerah VARCHAR(50) NULL;

