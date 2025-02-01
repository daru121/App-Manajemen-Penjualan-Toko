<?php
require_once '../backend/check_session.php';
require_once '../backend/database.php';

if (isset($_GET['user_id']) && isset($_GET['tanggal'])) {
    $user_id = $_GET['user_id'];
    $tanggal = $_GET['tanggal'];
    
    // Query untuk mendapatkan bonus target
    $queryBonus = "SELECT 
        COALESCE(
            SUM(
                CASE 
                    WHEN tp.jenis_target = 'produk' THEN (
                        SELECT SUM(tpr.insentif_per_unit * tpr.jumlah_target)
                        FROM target_produk tpr
                        WHERE tpr.target_id = tp.id
                    )
                    WHEN tp.jenis_target = 'omset' AND (
                        SELECT SUM(t.total_harga)
                        FROM transaksi t
                        WHERE t.user_id = tp.user_id
                        AND DATE(t.tanggal) BETWEEN tp.periode_mulai AND tp.periode_selesai
                    ) >= tp.target_nominal 
                    THEN (tp.target_nominal * tp.insentif_persen / 100)
                    ELSE 0
                END
            ),
            0
        ) as bonus_target
    FROM target_penjualan tp 
    WHERE tp.user_id = ? 
    AND tp.status = 'Selesai'
    AND DATE_FORMAT(tp.periode_selesai, '%Y-%m') = DATE_FORMAT(?, '%Y-%m')";
    
    $stmt = $conn->prepare($queryBonus);
    $stmt->execute([$user_id, $tanggal]);
    $result = $stmt->fetch();
    
    header('Content-Type: application/json');
    echo json_encode(['bonus_target' => $result['bonus_target']]);
} 