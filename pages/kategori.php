<?php
session_start();
require_once '../backend/database.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $nama_kategori = $_POST['nama_kategori'];
            $stmt = $conn->prepare("INSERT INTO kategori (nama_kategori) VALUES (?)");
            $stmt->execute([$nama_kategori]);
        } elseif ($_POST['action'] === 'edit') {
            $id = $_POST['kategori_id'];
            $nama_kategori = $_POST['nama_kategori'];
            $stmt = $conn->prepare("UPDATE kategori SET nama_kategori = ? WHERE id = ?");
            $stmt->execute([$nama_kategori, $id]);
        } elseif ($_POST['action'] === 'delete') {
            $id = $_POST['kategori_id'];
            $stmt = $conn->prepare("DELETE FROM kategori WHERE id = ?");
            $stmt->execute([$id]);
        }
        header("Location: kategori.php");
        exit;
    }
}

// Query untuk mengambil semua kategori
$query = "SELECT k.*, COUNT(b.id) as total_items 
          FROM kategori k 
          LEFT JOIN barang b ON k.id = b.kategori_id 
          GROUP BY k.id 
          ORDER BY k.nama_kategori ASC";
$stmt = $conn->query($query);
$categories = $stmt->fetchAll();

$itemsPerPage = 5; // Jumlah item per halaman
$currentPage = 1; // Halaman default
$totalItems = count($categories); // Total semua kategori
$totalPages = ceil($totalItems / $itemsPerPage); // Total halaman
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kategori Barang - PAksesories</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <?php include '../components/sidebar.php'; ?>
    <?php include '../components/navbar.php'; ?>

    <div class="ml-64 pt-16 min-h-screen bg-gray-50/50">
        <div class="p-8">
            <!-- Header Section dengan gradient modern -->
            <div class="mb-8 bg-gradient-to-br from-indigo-600 via-blue-500 to-blue-400 rounded-3xl p-10 text-white shadow-2xl relative overflow-hidden">
                <!-- Decorative elements -->
                <div class="absolute top-0 right-0 w-96 h-96 bg-white/10 rounded-full -translate-y-32 translate-x-32 blur-3xl"></div>
                <div class="absolute bottom-0 left-0 w-96 h-96 bg-blue-500/20 rounded-full translate-y-32 -translate-x-32 blur-3xl"></div>
                
                <div class="relative flex justify-between items-center">
                    <div>
                        <h1 class="text-4xl font-bold mb-3">Kategori</h1>
                        <p class="text-blue-100 text-lg">Kelola data kategori produk</p>
                    </div>
                    <button onclick="showAddModal()" 
                            class="px-5 py-3 bg-white/10 hover:bg-white/20 text-white rounded-xl flex items-center gap-3 transition-all duration-300 backdrop-blur-sm">
                        <div class="p-2 bg-white/10 rounded-lg">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                        </div>
                        <span class="font-medium">Tambah Kategori</span>
                    </button>
                </div>
            </div>

            <!-- Table Card dengan glass effect -->
            <div class="bg-white/70 backdrop-blur-xl rounded-3xl shadow-xl border border-gray-200/70">
                <!-- Table Header -->
                <div class="p-6 border-b border-gray-100">
                    <div class="flex items-center justify-between">
                        <!-- Show entries dengan style modern -->
                        <div class="flex items-center gap-3">
                            <label class="text-sm font-medium text-gray-600">Tampilkan</label>
                            <select id="entriesSelect" 
                                    onchange="changeEntries(this.value)" 
                                    class="px-4 py-2 bg-gray-50/50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/50 transition-all duration-300">
                                <option value="5">5 Data</option>
                                <option value="10">10 Data</option>
                            </select>
                        </div>

                        <!-- Search box dengan style modern -->
                        <div class="relative">
                            <input type="text" 
                                   id="searchInput"
                                   placeholder="Cari kategori..." 
                                   oninput="searchTable()"
                                   class="w-72 pl-12 pr-4 py-2.5 bg-gray-50/50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/50 transition-all duration-300">
                            <div class="absolute left-4 top-2.5 text-gray-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Table Content -->
                <div class="overflow-x-auto p-1">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-50/50">
                                <th class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider py-4 px-6">No</th>
                                <th class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider py-4 px-6">Nama Kategori</th>
                                <th class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider py-4 px-6">Jumlah Produk</th>
                                <th class="text-right text-xs font-semibold text-gray-500 uppercase tracking-wider py-4 px-6">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100" id="categoryTableBody">
                            <?php foreach ($categories as $index => $category): ?>
                            <tr class="hover:bg-gray-50/50 transition-colors duration-200" data-page="<?= floor($index / $itemsPerPage) + 1 ?>">
                                <td class="py-4 px-6 text-sm text-gray-600"><?= $index + 1 ?></td>
                                <td class="py-4 px-6 text-sm text-gray-800 font-medium"><?= htmlspecialchars($category['nama_kategori']) ?></td>
                                <td class="py-4 px-6">
                                    <span class="px-3 py-1.5 bg-blue-50 text-blue-600 rounded-full text-xs font-medium inline-flex items-center gap-1">
                                        <span class="w-1 h-1 rounded-full bg-blue-600"></span>
                                        <?= $category['total_items'] ?> Produk
                                    </span>
                                </td>
                                <td class="py-4 px-6">
                                    <div class="flex items-center justify-end gap-2">
                                        <button onclick="showEditModal(<?= $category['id'] ?>, '<?= htmlspecialchars($category['nama_kategori']) ?>')" 
                                                class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-all duration-200">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                        </button>
                                        <button onclick="showDeleteModal(<?= $category['id'] ?>)" 
                                                class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-all duration-200">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination section dengan style modern -->
                <div class="p-6 border-t border-gray-100">
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-gray-600" id="showingInfo">
                            Showing 1 to <?= min($itemsPerPage, $totalItems) ?> of <?= $totalItems ?> entries
                        </p>
                        <div class="flex items-center gap-2">
                            <button onclick="changePage('prev')" 
                                    id="prevButton"
                                    class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200">
                                Previous
                            </button>
                            <div id="pageNumbers" class="flex items-center gap-1">
                                <!-- Page numbers will be inserted here by JavaScript -->
                            </div>
                            <button onclick="changePage('next')" 
                                    id="nextButton"
                                    class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200">
                                Next
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div id="categoryModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl p-6 w-full max-w-md">
            <div class="flex justify-between items-center mb-6">
                <h3 id="modalTitle" class="text-xl font-bold text-gray-800">Tambah Kategori</h3>
                <button onclick="closeCategoryModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <form id="categoryForm" method="POST">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="kategori_id" id="kategoriId" value="">
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nama Kategori</label>
                    <input type="text" 
                           name="nama_kategori" 
                           id="namaKategori"
                           required 
                           class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500/50">
                </div>
                <div class="flex justify-end gap-4">
                    <button type="button" 
                            onclick="closeCategoryModal()"
                            class="px-4 py-2 text-gray-500 hover:text-gray-700">
                        Batal
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-all duration-200">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-xl w-[400px] overflow-hidden">
            <div class="p-6">
                <div class="flex items-center justify-center mb-6">
                    <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                </div>
                <h3 class="text-lg font-medium text-center mb-4">Konfirmasi Hapus</h3>
                <p class="text-gray-500 text-center mb-6">Apakah Anda yakin ingin menghapus kategori ini?</p>
                <form id="deleteForm" method="POST" class="flex justify-center gap-3">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="kategori_id" id="deleteId">
                    <button type="button" onclick="closeDeleteModal()" 
                            class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                        Batal
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors">
                        Hapus
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Modal functions (same as before)
        function showAddModal() {
            document.getElementById('modalTitle').textContent = 'Tambah Kategori';
            document.getElementById('formAction').value = 'add';
            document.getElementById('kategoriId').value = '';
            document.getElementById('namaKategori').value = '';
            document.getElementById('categoryModal').classList.remove('hidden');
        }

        function showEditModal(id, nama) {
            document.getElementById('modalTitle').textContent = 'Edit Kategori';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('kategoriId').value = id;
            document.getElementById('namaKategori').value = nama;
            document.getElementById('categoryModal').classList.remove('hidden');
        }

        function closeCategoryModal() {
            document.getElementById('categoryModal').classList.add('hidden');
        }

        function showDeleteModal(id) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        // Close delete modal when clicking outside
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });

        let currentPage = 1;
        let itemsPerPage = 5; // Default value
        const totalPages = <?= $totalPages ?>;

        function updateTable() {
            // Hide all rows
            const rows = document.querySelectorAll('#categoryTableBody tr');
            let visibleCount = 0;
            
            rows.forEach(row => {
                if (!row.classList.contains('hidden')) {
                    visibleCount++;
                    const rowPage = Math.floor((visibleCount - 1) / itemsPerPage) + 1;
                    row.dataset.page = rowPage;
                    row.style.display = rowPage === currentPage ? '' : 'none';
                } else {
                    row.style.display = 'none';
                }
            });

            // Update showing info
            const totalItems = document.querySelectorAll('#categoryTableBody tr:not(.hidden)').length;
            const start = totalItems === 0 ? 0 : ((currentPage - 1) * itemsPerPage) + 1;
            const end = Math.min(currentPage * itemsPerPage, totalItems);
            document.getElementById('showingInfo').textContent = 
                `Showing ${start} to ${end} of ${totalItems} entries`;

            // Update pagination buttons
            document.getElementById('prevButton').disabled = currentPage === 1;
            document.getElementById('nextButton').disabled = currentPage === totalPages;

            // Update page numbers
            const pageNumbers = document.getElementById('pageNumbers');
            pageNumbers.innerHTML = '';
            
            for (let i = 1; i <= totalPages; i++) {
                const button = document.createElement('button');
                button.className = `px-3 py-1 text-sm rounded-md ${currentPage === i ? 
                    'bg-blue-600 text-white' : 
                    'text-gray-500 hover:text-gray-700'}`;
                button.textContent = i;
                button.onclick = () => changePage(i);
                pageNumbers.appendChild(button);
            }

            // Update row numbers
            let visibleIndex = 1;
            document.querySelectorAll('#categoryTableBody tr:not(.hidden)').forEach(row => {
                const numberCell = row.getElementsByTagName('td')[0];
                numberCell.textContent = visibleIndex++;
            });
        }

        function changePage(action) {
            const rows = document.querySelectorAll('#categoryTableBody tr:not(.hidden)');
            const totalItems = rows.length;
            const totalPages = Math.ceil(totalItems / itemsPerPage);

            if (action === 'prev' && currentPage > 1) {
                currentPage--;
            } else if (action === 'next' && currentPage < totalPages) {
                currentPage++;
            } else if (typeof action === 'number') {
                currentPage = action;
            }

            updateTable();
        }

        function searchTable() {
            const searchInput = document.getElementById('searchInput');
            const filter = searchInput.value.toLowerCase();
            const rows = document.querySelectorAll('#categoryTableBody tr');
            let visibleRows = 0;
            let firstVisibleIndex = 0;

            rows.forEach((row, index) => {
                const nameCell = row.getElementsByTagName('td')[1]; // Kolom nama kategori
                const txtValue = nameCell.textContent || nameCell.innerText;

                if (txtValue.toLowerCase().indexOf(filter) > -1) {
                    // Hitung ulang nomor halaman untuk baris yang cocok
                    const newPage = Math.floor(visibleRows / itemsPerPage) + 1;
                    row.dataset.page = newPage;
                    
                    if (visibleRows === 0) {
                        firstVisibleIndex = index;
                    }
                    
                    visibleRows++;
                    row.classList.remove('hidden');
                } else {
                    row.classList.add('hidden');
                }
            });

            // Update total items dan pagination
            const filteredTotalItems = visibleRows;
            const filteredTotalPages = Math.ceil(filteredTotalItems / itemsPerPage);

            // Reset ke halaman pertama saat melakukan pencarian
            currentPage = 1;

            // Update tampilan tabel
            updateTableWithSearch(filteredTotalItems, filteredTotalPages, firstVisibleIndex);
        }

        function updateTableWithSearch(filteredTotal, filteredPages, firstVisibleIndex) {
            // Update showing info
            const start = filteredTotal === 0 ? 0 : ((currentPage - 1) * itemsPerPage) + 1;
            const end = Math.min(currentPage * itemsPerPage, filteredTotal);
            document.getElementById('showingInfo').textContent = 
                `Showing ${start} to ${end} of ${filteredTotal} entries`;

            // Update pagination buttons
            document.getElementById('prevButton').disabled = currentPage === 1;
            document.getElementById('nextButton').disabled = currentPage === filteredPages;

            // Update page numbers
            const pageNumbers = document.getElementById('pageNumbers');
            pageNumbers.innerHTML = '';
            
            for (let i = 1; i <= filteredPages; i++) {
                const button = document.createElement('button');
                button.className = `px-3 py-1 text-sm rounded-md ${currentPage === i ? 
                    'bg-blue-600 text-white' : 
                    'text-gray-500 hover:text-gray-700'}`;
                button.textContent = i;
                button.onclick = () => changePage(i);
                pageNumbers.appendChild(button);
            }

            // Show/hide rows based on current page
            const rows = document.querySelectorAll('#categoryTableBody tr:not(.hidden)');
            rows.forEach((row, index) => {
                const rowPage = Math.floor(index / itemsPerPage) + 1;
                row.style.display = rowPage === currentPage ? '' : 'none';
            });

            // Update row numbers
            let visibleIndex = 1;
            document.querySelectorAll('#categoryTableBody tr:not(.hidden)').forEach(row => {
                const numberCell = row.getElementsByTagName('td')[0];
                numberCell.textContent = visibleIndex++;
            });
        }

        function changeEntries(value) {
            itemsPerPage = parseInt(value);
            currentPage = 1; // Reset ke halaman pertama
            
            const rows = document.querySelectorAll('#categoryTableBody tr:not(.hidden)');
            const totalItems = rows.length;
            const totalPages = Math.ceil(totalItems / itemsPerPage);
            
            // Update page numbers untuk setiap baris
            rows.forEach((row, index) => {
                const newPage = Math.floor(index / itemsPerPage) + 1;
                row.dataset.page = newPage;
            });

            // Update tampilan
            updateTableDisplay(totalItems, totalPages);
        }

        function updateTableDisplay(totalItems, totalPages) {
            const rows = document.querySelectorAll('#categoryTableBody tr:not(.hidden)');
            
            // Sembunyikan/tampilkan baris sesuai halaman saat ini
            rows.forEach((row, index) => {
                const rowPage = Math.floor(index / itemsPerPage) + 1;
                row.style.display = rowPage === currentPage ? '' : 'none';
            });

            // Update info showing entries
            const start = totalItems === 0 ? 0 : ((currentPage - 1) * itemsPerPage) + 1;
            const end = Math.min(currentPage * itemsPerPage, totalItems);
            document.getElementById('showingInfo').textContent = 
                `Showing ${start} to ${end} of ${totalItems} entries`;

            // Update pagination buttons
            document.getElementById('prevButton').disabled = currentPage === 1;
            document.getElementById('nextButton').disabled = currentPage === totalPages;

            // Update page numbers
            const pageNumbers = document.getElementById('pageNumbers');
            pageNumbers.innerHTML = '';
            
            for (let i = 1; i <= totalPages; i++) {
                const button = document.createElement('button');
                button.className = `px-3 py-1 text-sm rounded-md ${currentPage === i ? 
                    'bg-blue-600 text-white' : 
                    'text-gray-500 hover:text-gray-700'}`;
                button.textContent = i;
                button.onclick = () => changePage(i);
                pageNumbers.appendChild(button);
            }

            // Update row numbers
            let visibleIndex = 1;
            rows.forEach(row => {
                if (row.style.display !== 'none') {
                    const numberCell = row.getElementsByTagName('td')[0];
                    numberCell.textContent = visibleIndex++;
                }
            });
        }

        // Inisialisasi tabel saat halaman dimuat
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('#categoryTableBody tr');
            const totalItems = rows.length;
            const totalPages = Math.ceil(totalItems / itemsPerPage);
            
            rows.forEach((row, index) => {
                row.dataset.page = Math.floor(index / itemsPerPage) + 1;
            });

            updateTableDisplay(totalItems, totalPages);
        });
    </script>
</body>
</html>