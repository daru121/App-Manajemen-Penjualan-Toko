<?php
require_once '../backend/check_session.php';
require_once '../backend/database.php';

?>

<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>


<style>
    /* Custom scrollbar styles */
    .scrollbar-thin {
        scrollbar-width: thin;
    }

    .scrollbar-thin::-webkit-scrollbar {
        width: 6px;
    }

    .scrollbar-thin::-webkit-scrollbar-track {
        background: #F9FAFB;
        border-radius: 3px;
    }

    .scrollbar-thin::-webkit-scrollbar-thumb {
        background: #E5E7EB;
        border-radius: 3px;
    }

    .scrollbar-thin::-webkit-scrollbar-thumb:hover {
        background: #D1D5DB;
    }

    /* Mobile Responsive Styles */
    @media (max-width: 640px) {
        .notification-dropdown {
            width: calc(100vw - 2rem);
            left: 1rem;
            right: 1rem;
        }

        .profile-dropdown {
            width: calc(100vw - 2rem);
            left: 1rem;
            right: 1rem;
        }

        /* Tambahkan style untuk memastikan judul tidak terpotong */
        .truncate {
            max-width: calc(100vw - 220px);
            /* Sesuaikan dengan kebutuhan */
        }

        /* Tambahkan spacing yang lebih baik */
        .nav-container {
            padding-left: env(safe-area-inset-left);
            padding-right: env(safe-area-inset-right);
        }
    }

    /* Tambahkan animasi smooth untuk transisi */
    .truncate {
        transition: all 0.3s ease;
    }
</style>

<nav class="fixed right-0 top-0 left-0 sm:left-[280px] h-[60px] z-30 px-4 sm:px-0 sm:right-4 sm:top-4">
    <!-- Clean white container with subtle shadow -->
    <div class="relative h-full rounded-none sm:rounded-2xl bg-white/95 backdrop-blur-xl border-b sm:border border-gray-100 shadow-sm">
        <div class="h-full px-4 sm:px-6 flex items-center justify-between">
            <!-- Left side - Breadcrumbs - Responsive -->
            <div class="flex items-center">
                <!-- Mobile Title - dengan padding dan styling yang lebih baik -->
                <div class="flex sm:hidden items-center pl-12 flex-1 min-w-0">
                    <div class="w-full py-1">
                        <h1 class="text-base font-semibold text-gray-800 truncate mt-0.5">
                            <?php
                            $current_page = basename($_SERVER['PHP_SELF'], '.php');
                            $page_titles = [
                                'dashboard' => '',  // Kosongkan untuk mobile
                                'kategori' => '',   // Kosongkan untuk mobile
                                'produk' => '',     // Kosongkan untuk mobile
                                'supplier' => '',   // Kosongkan untuk mobile
                                'penjualan' => '',  // Kosongkan untuk mobile
                                'informasi' => '',  // Kosongkan untuk mobile
                                'laporan' => '',    // Kosongkan untuk mobile
                                'pengiriman' => '', // Kosongkan untuk mobile
                                'pengeluaran' => '', // Kosongkan untuk mobile
                                'karyawan' => '',   // Kosongkan untuk mobile
                                'scan_absensi' => '' // Kosongkan untuk mobile
                            ];
                            echo $page_titles[$current_page] ?? '';
                            ?>
                        </h1>
                    </div>
                </div>

                <!-- Desktop Breadcrumbs -->
                <div class="hidden sm:flex items-center">
                    <div class="flex items-center gap-2 text-sm">
                        <?php
                        $current_page = basename($_SERVER['PHP_SELF'], '.php');

                        // Only show Beranda for dashboard.php
                        if ($current_page === 'dashboard') {
                            echo '<span class="text-gray-500 hidden sm:inline">Dashboard</span>';
                        } else if ($current_page === 'scan_absensi') {
                            echo '<span class="text-gray-500 hidden sm:inline">Absensi</span>';
                        } else if ($current_page === 'pengiriman') {
                            echo '<span class="text-gray-500 hidden sm:inline">Pengiriman</span>';
                        }

                        // Define page hierarchy
                        $breadcrumbs = [];

                        if ($current_page === 'kategori' || $current_page === 'produk' || $current_page === 'supplier') {
                            $breadcrumbs[] = [
                                'text' => 'Master Data',
                                'url' => '#',
                                'active' => false
                            ];
                        }

                        // Add current page
                        switch ($current_page) {
                            case 'kategori':
                                $breadcrumbs[] = [
                                    'text' => 'Kategori',
                                    'url' => 'kategori.php',
                                    'active' => true
                                ];
                                break;
                            case 'produk':
                                $breadcrumbs[] = [
                                    'text' => 'Produk',
                                    'url' => 'produk.php',
                                    'active' => true
                                ];
                                break;
                            case 'supplier':
                                $breadcrumbs[] = [
                                    'text' => 'Supplier',
                                    'url' => 'supplier.php',
                                    'active' => true
                                ];
                                break;
                            case 'penjualan':
                                $breadcrumbs[] = [
                                    'text' => 'Penjualan',
                                    'url' => '#',
                                    'active' => false
                                ];
                                $breadcrumbs[] = [
                                    'text' => 'POS Kasir',
                                    'url' => 'penjualan.php',
                                    'active' => true
                                ];
                                break;
                            case 'informasi':
                                $breadcrumbs[] = [
                                    'text' => 'Penjualan',
                                    'url' => '#',
                                    'active' => false
                                ];
                                $breadcrumbs[] = [
                                    'text' => 'Informasi',
                                    'url' => 'informasi.php',
                                    'active' => true
                                ];
                                break;
                            case 'laporan':
                                $breadcrumbs[] = [
                                    'text' => 'Penjualan',
                                    'url' => '#',
                                    'active' => false
                                ];
                                $breadcrumbs[] = [
                                    'text' => 'Laporan',
                                    'url' => 'laporan.php',
                                    'active' => true
                                ];
                                break;
                            case 'users':
                                $breadcrumbs[] = [
                                    'text' => 'Users',
                                    'url' => 'users.php',
                                    'active' => true
                                ];
                                break;
                            case 'pengeluaran':
                                $breadcrumbs[] = [
                                    'text' => 'Operasional',
                                    'url' => '#',
                                    'active' => false
                                ];
                                $breadcrumbs[] = [
                                    'text' => 'Pengeluaran',
                                    'url' => 'pengeluaran.php',
                                    'active' => true
                                ];
                                break;
                            case 'karyawan':
                                $breadcrumbs[] = [
                                    'text' => 'Operasional',
                                    'url' => '#',
                                    'active' => false
                                ];
                                $breadcrumbs[] = [
                                        'text' => 'Karyawan',
                                        'url' => 'karyawan.php',
                                    'active' => true
                                ];
                                break;
                            case 'slip_gaji':
                                $breadcrumbs[] = [
                                    'text' => 'Operasional',
                                    'url' => '#',
                                    'active' => false
                                ];
                                $breadcrumbs[] = [
                                    'text' => 'Gaji Karyawan',
                                    'url' => 'slip_gaji.php',
                                    'active' => true
                                ];
                                break;
                            case 'pengaturan':
                                $breadcrumbs[] = [
                                    'text' => 'Pengaturan',
                                    'url' => 'pengaturan.php',
                                    'active' => true
                                ];
                                break;
                            case 'tentang':
                                $breadcrumbs[] = [
                                    'text' => 'Tentang',
                                    'url' => 'tentang.php',
                                    'active' => true
                                ];
                                break;
                        }

                        // Output breadcrumbs
                        foreach ($breadcrumbs as $index => $crumb) {
                            if ($index > 0) {
                                echo '<svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                      </svg>';
                            }

                            if ($crumb['active']) {
                                echo '<span class="text-gray-800 font-medium truncate max-w-[150px]">' . $crumb['text'] . '</span>';
                            } else {
                                echo '<a href="' . $crumb['url'] . '" class="text-gray-500 hover:text-gray-700 transition-colors truncate max-w-[150px]">' . $crumb['text'] . '</a>';
                            }
                        }
                        ?>
                    </div>
                </div>
            </div>

            <!-- Right side - Adjust gap for mobile -->
            <div class="flex items-center gap-1 sm:gap-4">
                <!-- Clean Divider - Hide on mobile -->
                <div class="hidden sm:block h-8 w-px bg-gray-200"></div>

                <!-- Profile Dropdown - Adjusted for mobile -->
                <div class="relative" x-data="{ isOpen: false }">
                    <button @click="isOpen = !isOpen"
                        class="flex items-center gap-2 sm:gap-3 p-1.5 rounded-xl hover:bg-gray-50 transition-all duration-300">
                        <div class="relative">
                            <div class="w-8 sm:w-9 h-8 sm:h-9 rounded-xl overflow-hidden ring-2 ring-gray-100">
                                <?php if (isset($_SESSION['user_id'])):
                                    $stmt = $conn->prepare("SELECT avatar FROM users WHERE id = ?");
                                    $stmt->execute([$_SESSION['user_id']]);
                                    $userAvatar = $stmt->fetch(PDO::FETCH_COLUMN);

                                    if ($userAvatar && file_exists("../uploads/avatars/" . $userAvatar)): ?>
                                        <img src="../uploads/avatars/<?= htmlspecialchars($userAvatar) ?>"
                                            alt="Profile"
                                            class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <img src="https://api.dicebear.com/7.x/bottts/svg?seed=<?= $_SESSION['nama'] ?>&backgroundColor=6366F1&textureChance=50&mouthChance=100&sidesChance=100&spots=50&eyes=happy"
                                            alt="Profile"
                                            class="w-full h-full object-cover">
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            <div class="absolute bottom-0 right-0 w-2.5 h-2.5 bg-green-500 rounded-full border-2 border-white"></div>
                        </div>
                        <!-- Hide name on mobile -->
                        <div class="hidden sm:block text-left">
                            <div class="text-sm font-medium text-gray-700">
                                <?= isset($_SESSION['nama']) ? htmlspecialchars($_SESSION['nama']) : 'Guest' ?>
                            </div>
                            <div class="text-xs text-gray-500">
                                <?= isset($_SESSION['role']) ? htmlspecialchars($_SESSION['role']) : 'User' ?>
                            </div>
                        </div>
                        <svg class="w-5 h-5 text-gray-400 transition-transform duration-300 hidden sm:block"
                            :class="{'rotate-180': isOpen}"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <!-- Profile Dropdown Menu - Adjusted for mobile -->
                    <div x-show="isOpen"
                        @click.outside="isOpen = false"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100 scale-100"
                        x-transition:leave-end="opacity-0 scale-95"
                        class="absolute right-0 mt-2 w-60 origin-top-right"
                        style="display: none;">

                        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
                            <!-- User Info Section -->
                            <div class="px-4 py-4 bg-gray-50 border-b border-gray-100">
                                <p class="text-xs font-medium text-gray-400 uppercase">Signed in as</p>
                                <p class="text-sm font-medium text-gray-700 mt-1">
                                    <?= isset($_SESSION['email']) ? htmlspecialchars($_SESSION['email']) : 'guest@example.com' ?>
                                </p>
                            </div>

                            <!-- Menu Items -->
                            <div class="p-2 space-y-1">
                                <a href="pengaturan" class="flex items-center gap-3 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50 rounded-xl transition-colors duration-200">
                                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    Pengaturan
                                </a>

                                <a href="tentang" class="flex items-center gap-3 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50 rounded-xl transition-colors duration-200">
                                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Tentang Saya
                                </a>
                            </div>

                            <div class="border-t border-gray-100"></div>

                            <!-- Sign Out Button -->
                            <div class="p-2">
                                <a href="../backend/logout.php"
                                    class="flex items-center gap-3 px-3 py-2 text-sm text-red-600 hover:bg-red-50 rounded-xl transition-colors duration-200">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                    </svg>
                                    Keluar
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</nav>