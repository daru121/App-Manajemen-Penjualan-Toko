<?php
ob_start();
require_once '../backend/check_session.php';
require_once '../backend/database.php';
require_once('../vendor/tecnickcom/tcpdf/tcpdf.php');

// Set timezone di awal file
date_default_timezone_set('Asia/Makassar'); // Set timezone ke WITA

if (!isset($_GET['id'])) {
    header('Location: slip_gaji.php');
    exit;
}

// Create new PDF document
class MYPDF extends TCPDF {
    public function Header() {
        // Header text
        $this->SetFont('helvetica', 'B', 14);
        $this->Cell(0, 5, 'TOKO JAMU AIR MANCUR', 0, 1, 'C');
        $this->SetFont('helvetica', '', 10);
        $this->Cell(0, 5, 'Jamu tradisional, minuman sehat dan kendi. Segar & berkhasiat', 0, 1, 'C');
        $this->Cell(0, 5, 'Jl. Soekarno Hatta, Loa Janan ulu, Kutai Kartanegara Telp: 082154541854', 0, 1, 'C');
        
        // Garis header
        $this->SetLineWidth(0.5);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
    }
}

// Get slip gaji data
$query = "SELECT 
    sg.*,
    u.nama,
    u.role,
    u.bank,
    u.nomor_rekening,
    COALESCE(
        (SELECT SUM(
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
        )
        FROM target_penjualan tp 
        WHERE tp.user_id = sg.user_id 
        AND tp.status = 'Selesai'
        AND DATE_FORMAT(tp.periode_selesai, '%Y-%m') = DATE_FORMAT(sg.tanggal, '%Y-%m')),
        0
    ) as bonus_target
FROM slip_gaji sg
JOIN users u ON sg.user_id = u.id
WHERE sg.id = ?";

$stmt = $conn->prepare($query);
$stmt->execute([$_GET['id']]);
$slip = $stmt->fetch();

if (!$slip) {
    header('Location: slip_gaji.php');
    exit;
}

// Buat instance PDF
$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('PAksesories');
$pdf->SetAuthor('PAksesories');
$pdf->SetTitle('Slip Gaji - ' . $slip['nama']);

// Set margins
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(TRUE, 15);

// Add a page
$pdf->AddPage();

// Beri jarak setelah garis header
$pdf->Ln(4); // Tambah space setelah garis

// Judul Slip dengan spacing yang lebih
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 5, 'SLIP GAJI KARYAWAN', 0, 1, 'C');

// Format periode dengan rentang tanggal
$tanggal = date('Y-m', strtotime($slip['tanggal']));
$awal_bulan = date('d F Y', strtotime($tanggal . '-01'));
$akhir_bulan = date('d F Y', strtotime($tanggal . '-' . date('t', strtotime($tanggal))));
$pdf->Cell(0, 5, '' . $awal_bulan . ' - ' . $akhir_bulan, 0, 1, 'C');
$pdf->Ln(3);

// Informasi Karyawan dengan format baru
$pdf->SetFont('helvetica', '', 10);

// Informasi dalam format yang diminta
$info = [
    ['Nama', $slip['nama']],
    ['Jabatan', $slip['role']],
    ['Bank', $slip['bank'] ?? '-'],
    ['No. Rekening', $slip['nomor_rekening'] ?? '-']
];

// Posisikan informasi di sebelah kiri dengan indent
$pdf->SetX(15); // Sedikit indent dari margin kiri
foreach($info as $row) {
    $pdf->Cell(25, 5, $row[0], 0, 0);
    $pdf->Cell(5, 5, ':', 0, 0);
    $pdf->Cell(60, 5, $row[1], 0, 1);
    $pdf->SetX(15); // Reset X untuk baris berikutnya
}

$pdf->Ln(3);

// Kolom Penghasilan dan Potongan dengan garis bawah
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(95, 7, 'PENGHASILAN', 'B', 0);
$pdf->Cell(0, 7, 'POTONGAN', 'B', 1);

$pdf->SetFont('helvetica', '', 10);

// Data penghasilan
$penghasilan = [
    ['Gaji Pokok', $slip['gaji_pokok']],
    ['Bonus', $slip['bonus'] - $slip['bonus_target']],
    ['Bonus Target', $slip['bonus_target']],
];

// Data potongan
$potongan = [
    ['Potongan', $slip['potongan']],
];

// Hitung max rows untuk penghasilan dan potongan
$max_rows = max(count($penghasilan), count($potongan));

// Print rows
for($i = 0; $i < $max_rows; $i++) {
    // Penghasilan
    if(isset($penghasilan[$i])) {
        $pdf->Cell(60, 5, $penghasilan[$i][0], 0, 0);
        $pdf->Cell(35, 5, '= Rp ' . number_format($penghasilan[$i][1], 0, ',', '.'), 0, 0);
    } else {
        $pdf->Cell(95, 5, '', 0, 0);
    }
    
    // Potongan
    if(isset($potongan[$i])) {
        $pdf->Cell(60, 5, $potongan[$i][0], 0, 0);
        $pdf->Cell(35, 5, '= Rp ' . number_format($potongan[$i][1], 0, ',', '.'), 0, 0);
    }
    
    $pdf->Ln();
}

// Total
$total_penghasilan = $slip['gaji_pokok'] + $slip['bonus'];
$total_potongan = $slip['potongan'];

$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(60, 5, 'Total (A)', 0, 0);
$pdf->Cell(35, 5, 'Rp ' . number_format($total_penghasilan, 0, ',', '.'), 0, 0);
$pdf->Cell(60, 5, 'Total (B)', 0, 0);
$pdf->Cell(35, 5, 'Rp ' . number_format($total_potongan, 0, ',', '.'), 0, 1);

$pdf->Ln(5);

// Penerimaan Bersih
$pdf->SetLineWidth(0.2);
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(1);
$pdf->Cell(60, 7, 'PENERIMAAN BERSIH (A - B)', 0, 0);
$pdf->Cell(0, 7, '= Rp ' . number_format($slip['total_gaji'], 0, ',', '.'), 0, 1);
$pdf->SetFont('helvetica', 'I', 8);
$pdf->Cell(0, 5, 'Terbilang: # ' . terbilang($slip['total_gaji']) . ' rupiah #', 0, 1);
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());

$pdf->Ln(10);

// Tanda tangan
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 5, 'Samarinda, ' . date('d F Y', strtotime($slip['tanggal'])), 0, 1, 'R');
$pdf->Cell(0, 5, 'Pemilik Toko', 0, 1, 'R');
$pdf->Ln(15);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(0, 5, 'SITI ROHMAH', 0, 1, 'R');

// Output PDF
ob_end_clean();
$pdf->Output('slip_gaji_' . $slip['nama'] . '_' . date('F_Y', strtotime($slip['tanggal'])) . '.pdf', 'I');

// Fungsi terbilang
function terbilang($angka) {
    $angka = abs($angka);
    $baca = array('', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas');
    $terbilang = '';
    
    if ($angka < 12) {
        $terbilang = ' ' . $baca[$angka];
    } elseif ($angka < 20) {
        $terbilang = terbilang($angka - 10) . ' belas';
    } elseif ($angka < 100) {
        $terbilang = terbilang($angka/10) . ' puluh' . terbilang($angka % 10);
    } elseif ($angka < 200) {
        $terbilang = ' seratus' . terbilang($angka - 100);
    } elseif ($angka < 1000) {
        $terbilang = terbilang($angka/100) . ' ratus' . terbilang($angka % 100);
    } elseif ($angka < 2000) {
        $terbilang = ' seribu' . terbilang($angka - 1000);
    } elseif ($angka < 1000000) {
        $terbilang = terbilang($angka/1000) . ' ribu' . terbilang($angka % 1000);
    } elseif ($angka < 1000000000) {
        $terbilang = terbilang($angka/1000000) . ' juta' . terbilang($angka % 1000000);
    }
    
    return $terbilang;
}
?> 