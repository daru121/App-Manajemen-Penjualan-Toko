<?php
require_once '../backend/check_session.php';
?>

<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

<nav class="fixed right-4 top-4 left-[280px] h-[60px] z-30">
    <!-- Clean white container with subtle shadow -->
    <div class="relative h-full rounded-2xl bg-white/95 backdrop-blur-xl border border-gray-100 shadow-sm">
        <div class="h-full px-6 flex items-center justify-between">
            <!-- Left side - Breadcrumbs -->
            <div class="flex items-center">
                <!-- Breadcrumb Navigation -->
                <div class="flex items-center gap-2 text-sm">
                    <a href="dashboard.php" class="text-gray-500 hover:text-gray-700 transition-colors">Beranda</a>
                    <?php
                    $current_page = basename($_SERVER['PHP_SELF'], '.php');
                    
                    // Define page hierarchy
                    $breadcrumbs = [];
                    
                    if ($current_page === 'kategori' || $current_page === 'produk') {
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
                        // Add more cases as needed
                    }

                    // Display breadcrumbs
                    foreach ($breadcrumbs as $index => $crumb) {
                        echo '<svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                              </svg>';
                        
                        if ($crumb['active']) {
                            echo '<span class="text-gray-800 font-medium">' . $crumb['text'] . '</span>';
                        } else {
                            echo '<a href="' . $crumb['url'] . '" class="text-gray-500 hover:text-gray-700 transition-colors">' . $crumb['text'] . '</a>';
                        }
                    }
                    ?>
                </div>
            </div>

            <!-- Right side -->
            <div class="flex items-center gap-4">
                <!-- Help Button -->
                <button class="relative p-2 text-gray-600 hover:text-gray-800 rounded-xl group">
                    <div class="absolute inset-0 bg-gray-50 scale-0 rounded-xl transition-transform duration-300 group-hover:scale-100"></div>
                    <svg class="relative w-5 h-5 transform group-hover:scale-110 transition-all duration-300" 
                         fill="none" 
                         stroke="currentColor" 
                         viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </button>

                <!-- Notifications -->
                <button class="relative p-2 text-gray-600 hover:text-gray-800 rounded-xl group">
                    <div class="absolute inset-0 bg-gray-50 scale-0 rounded-xl transition-transform duration-300 group-hover:scale-100"></div>
                    <div class="relative">
                        <svg class="w-5 h-5 transform group-hover:scale-110 transition-all duration-300" 
                             fill="none" 
                             stroke="currentColor" 
                             viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                        <span class="absolute -top-1 -right-1 h-4 w-4 bg-red-500 text-[10px] font-bold text-white rounded-full flex items-center justify-center transform group-hover:scale-110 transition-transform duration-300">2</span>
                    </div>
                </button>

                <!-- Clean Divider -->
                <div class="h-8 w-px bg-gray-200"></div>

                <!-- Profile Dropdown -->
                <div class="relative" x-data="{ isOpen: false }">
                    <button @click="isOpen = !isOpen" 
                            class="flex items-center gap-3 p-1.5 rounded-xl hover:bg-gray-50 transition-all duration-300">
                        <div class="relative">
                            <div class="w-9 h-9 rounded-xl overflow-hidden ring-2 ring-gray-100 transition-transform duration-300 hover:scale-105">
                                <img src="https://api.dicebear.com/7.x/bottts/svg?seed=<?= isset($_SESSION['nama']) ? $_SESSION['nama'] : 'default' ?>&backgroundColor=6366F1&textureChance=50&mouthChance=100&sidesChance=100&spots=50&eyes=happy" 
                                     alt="Profile" 
                                     class="w-full h-full object-cover">
                            </div>
                            <div class="absolute bottom-0 right-0 w-2.5 h-2.5 bg-green-500 rounded-full border-2 border-white"></div>
                        </div>
                        <div class="text-left">
                            <div class="text-sm font-medium text-gray-700">
                                <?= isset($_SESSION['nama']) ? htmlspecialchars($_SESSION['nama']) : 'Guest' ?>
                            </div>
                            <div class="text-xs text-gray-500 flex items-center gap-1">
                                <?= isset($_SESSION['role']) ? htmlspecialchars($_SESSION['role']) : 'User' ?>
                            </div>
                        </div>
                        <svg class="w-5 h-5 text-gray-400 transition-transform duration-300"
                             :class="{'rotate-180': isOpen}" 
                             fill="none" 
                             stroke="currentColor" 
                             viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <!-- Enhanced Clean Dropdown Menu -->
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
                                <a href="#" class="flex items-center gap-3 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50 rounded-xl transition-colors duration-200">
                                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                    Your Profile
                                </a>

                                <a href="#" class="flex items-center gap-3 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50 rounded-xl transition-colors duration-200">
                                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    Settings
                                </a>
                            </div>

                            <div class="border-t border-gray-100"></div>

                            <!-- Sign Out Button -->
                            <div class="p-2">
                                <a href="../backend/logout.php" 
                                   class="flex items-center gap-3 px-3 py-2 text-sm text-red-600 hover:bg-red-50 rounded-xl transition-colors duration-200">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                    </svg>
                                    Sign Out
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</nav> 