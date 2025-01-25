<?php
require_once '../backend/check_session.php';
$current_page = basename($_SERVER['PHP_SELF']);

// Definisikan menu untuk Kasir
$kasirMenuItems = [
    [
        'text' => 'Master Data',
        'icon' => 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10',
        'submenu' => [
            [
                'url' => 'kategori',
                'text' => 'Kategori',
                'icon' => 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z'
            ],
            [
                'url' => 'supplier',
                'text' => 'Supplier',
                'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z'
            ],
            [
                'url' => 'produk',
                'text' => 'Produk',
                'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'
            ]
        ]
    ],
    [
        'text' => 'POS Kasir',
        'url' => 'penjualan',
        'icon' => 'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z'
    ],
    [
        'text' => 'Pengiriman',
        'url' => 'pengiriman',
        'icon' => 'M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12'
    ],
    [
        'text' => 'Absensi',
        'url' => 'scan_absensi',
        'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'
    ]
];

// Definisikan menu untuk Operator (full access)
$operatorMenuItems = [
    [
        'text' => 'Dashboard',
        'url' => 'dashboard',
        'icon' => 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z'
    ],
    [
        'text' => 'Master Data',
        'icon' => 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10',
        'submenu' => [
            [
                'url' => 'kategori',
                'text' => 'Kategori',
                'icon' => 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z'
            ],
            [
                'url' => 'supplier',
                'text' => 'Supplier',
                'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z'
            ],
            [
                'url' => 'produk',
                'text' => 'Produk',
                'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'
            ]
        ]
    ],
    [
        'text' => 'Penjualan',
        'icon' => 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z',
        'submenu' => [
            [
                'url' => 'penjualan',
                'text' => 'POS Kasir',
                'icon' => 'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z'
            ],
            [
                'url' => 'informasi',
                'text' => 'Informasi',
                'icon' => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'
            ],
            [
                'url' => 'laporan',
                'text' => 'Laporan',
                'icon' => 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'
            ]
        ]
    ],
    [
        'text' => 'Pengiriman',
        'url' => 'pengiriman',
        'icon' => 'M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12'
    ],
    [
        'text' => 'Operasional',
        'icon' => 'M3 6v18h18v-18h-18zm5 14c0 .552-.448 1-1 1s-1-.448-1-1v-10c0-.552.448-1 1-1s1 .448 1 1v10zm5 0c0 .552-.448 1-1 1s-1-.448-1-1v-10c0-.552.448-1 1-1s1 .448 1 1v10zm5 0c0 .552-.448 1-1 1s-1-.448-1-1v-10c0-.552.448-1 1-1s1 .448 1 1v10z',
        'submenu' => [
            [
                'url' => 'pengeluaran',
                'text' => 'Pengeluaran',
                'icon' => 'M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z'
            ],
            [
                'url' => 'karyawan',
                'text' => 'Karyawan',
                'icon' => 'M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z'
            ]
        ]
    ],
    [
        'text' => 'Absensi',
        'url' => 'scan_absensi',
        'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'
    ]
];

// Pilih menu berdasarkan role
$menuItems = $_SESSION['role'] === 'Kasir' ? $kasirMenuItems : $operatorMenuItems;
?>

<!-- Toggle Button untuk Mobile - Style Modern & Rounded -->
<button id="toggleSidebar" class="fixed z-[60] p-3 bg-white/90 backdrop-blur-xl shadow-lg sm:hidden hover:bg-white/95 transition-all duration-300 active:scale-95 rounded-full border border-white/20
    <?= isset($_GET['sidebar']) && $_GET['sidebar'] === 'open' ? 'left-[305px]' : 'left-[15px]' ?> top-[10px]">
    <div class="relative w-6 h-6 flex items-center justify-center">
        <!-- Menu Icon dengan animasi -->
        <svg id="menuIcon" class="w-5 h-5 text-gray-700 transition-all duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
        <!-- Close Icon dengan animasi -->
        <svg id="closeIcon" class="hidden absolute w-5 h-5 text-gray-700 transition-all duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
    </div>
</button>

<!-- Overlay dengan animasi fade -->
<div id="sidebarOverlay" 
     class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 opacity-0 pointer-events-none transition-opacity duration-300 sm:hidden">
</div>

<!-- Sidebar Container -->
<aside id="sidebar" 
       class="fixed inset-y-0 left-0 z-[51] w-[280px] -translate-x-full sm:translate-x-0 transition-transform duration-300">
    <!-- Inner Container dengan padding -->
    <div class="h-full p-4">
        <!-- Floating Container dengan efek glass -->
        <div class="relative h-full rounded-[32px] overflow-hidden shadow-[0_0_50px_-12px_rgba(0,0,0,0.25)] border border-white/10 backdrop-blur-2xl">
            <!-- Background Effect -->
            <div class="absolute inset-0">
                <div class="absolute inset-0 bg-gradient-to-b from-[#0B1437]/95 via-[#1B2B65]/95 to-[#0B1437]/95"></div>
                <!-- Soft Glow -->
                <div class="absolute inset-0">
                    <div class="absolute top-0 -left-1/2 w-2/3 h-1/3 bg-blue-500/20 rounded-full blur-3xl"></div>
                    <div class="absolute bottom-0 -right-1/2 w-2/3 h-1/3 bg-blue-400/20 rounded-full blur-3xl"></div>
                </div>
                <!-- Pattern -->
                <div class="absolute inset-0 opacity-5 bg-[radial-gradient(#fff_1px,transparent_1px)] [background-size:16px_16px]"></div>
            </div>

            <!-- Content Container -->
            <div class="relative h-full flex flex-col">
                <!-- Logo Section -->
                <div class="p-6 border-b border-white/10">
                    <div class="flex items-center gap-3">
                        <div class="w-11 h-11 rounded-2xl overflow-hidden ring-1 ring-white/10 shadow-lg relative group">
                            <div class="absolute inset-0 bg-gradient-to-br from-blue-500/20 to-blue-600/20 opacity-0 group-hover:opacity-100 transition-all duration-500"></div>
                            <img src="../img/gambar.jpg" alt="PAksesories Logo" class="w-full h-full object-cover">
                        </div>
                        <div>
                            <h1 class="text-lg font-bold text-white/90">PAksesories</h1>
                            <p class="text-xs text-blue-300/70">Accessories Store</p>
                        </div>
                    </div>
                </div>

                <!-- Navigation dengan improved scrolling -->
                <nav class="flex-1 p-4 space-y-2 overflow-y-auto custom-scrollbar">
                    <?php
                    foreach ($menuItems as $item):
                        if (isset($item['submenu'])): 
                            $isActive = in_array(basename($_SERVER['PHP_SELF']), array_column($item['submenu'], 'url'));
                        ?>
                            <div class="space-y-1">
                                <!-- Menu Button dengan efek hover yang lebih halus -->
                                <button onclick="toggleSubmenu(this)" 
                                        class="w-full flex items-center px-4 py-3 rounded-xl transition-all duration-300 group
                                               <?= $isActive ? 'bg-white/10 text-white' : 'text-white/70 hover:bg-white/5 hover:text-white' ?>">
                                    <div class="flex items-center justify-center w-10 h-10 mr-3">
                                        <svg class="w-6 h-6 transition-transform duration-300 group-hover:scale-105" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="<?= $item['icon'] ?>"/>
                                        </svg>
                                    </div>
                                    <span class="font-medium text-[15px]"><?= $item['text'] ?></span>
                                    <svg class="w-5 h-5 ml-auto transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </button>

                                <!-- Submenu dengan transisi yang lebih halus -->
                                <div class="submenu ml-4 space-y-1 overflow-hidden transition-all duration-300 <?= $isActive ? '' : 'hidden' ?>">
                                    <?php foreach ($item['submenu'] as $submenu): 
                                        $isSubmenuActive = basename($_SERVER['PHP_SELF']) === $submenu['url'];
                                    ?>
                                        <a href="<?= $submenu['url'] ?>" 
                                           class="flex items-center px-4 py-3 rounded-xl transition-all duration-300 group
                                                  <?= $isSubmenuActive ? 'bg-white/10 text-white' : 'text-white/60 hover:bg-white/5 hover:text-white' ?>">
                                            <div class="flex items-center justify-center w-10 h-10 mr-3">
                                                <svg class="w-6 h-6 transition-transform duration-300 group-hover:scale-105" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="<?= $submenu['icon'] ?>"/>
                                                </svg>
                                            </div>
                                            <span class="font-medium text-[15px]"><?= $submenu['text'] ?></span>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <a href="<?= $item['url'] ?>" 
                               class="flex items-center px-4 py-3 rounded-xl transition-all duration-300 group
                                      <?= basename($_SERVER['PHP_SELF']) === $item['url'] ? 'bg-white/10 text-white' : 'text-white/70 hover:bg-white/5 hover:text-white' ?>">
                                <div class="flex items-center justify-center w-10 h-10 mr-3">
                                    <svg class="w-6 h-6 transition-transform duration-300 group-hover:scale-105" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="<?= $item['icon'] ?>"/>
                                    </svg>
                                </div>
                                <span class="font-medium text-[15px]"><?= $item['text'] ?></span>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </nav>
            </div>
        </div>
    </div>
</aside>

<style>
/* Improved scrollbar */
.custom-scrollbar {
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.2) transparent;
}

.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 20px;
}

/* Mobile Styles */
@media (max-width: 640px) {
    #sidebar {
        width: 85%;
        max-width: 320px;
        transform: translateX(-100%);
    }
    
    #sidebar.active {
        transform: translateX(0) !important;
    }
    
    #sidebarOverlay.active {
        opacity: 1 !important;
        pointer-events: auto !important;
    }
    
    #menuIcon.hidden {
        display: none !important;
    }
    
    #closeIcon.show {
        display: block !important;
    }
}

/* Improved animations */
.menu-item {
    transition: all 0.3s ease;
}

.menu-item:hover {
    transform: translateX(4px);
}

.submenu {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    transform-origin: top;
}

.submenu.hidden {
    display: none;
}

.submenu.opacity-0 {
    opacity: 0;
    transform: scale(0.95);
}

.submenu.opacity-100 {
    opacity: 1;
    transform: scale(1);
}

/* Tambahkan style untuk efek hover yang lebih modern */
#toggleSidebar {
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    transform-origin: center;
}

#toggleSidebar:hover {
    box-shadow: 0 4px 25px rgba(0, 0, 0, 0.12);
}

#toggleSidebar:active {
    transform: scale(0.95);
}

/* Animasi untuk icon */
#menuIcon, #closeIcon {
    transform-origin: center;
}

#menuIcon.hidden {
    transform: rotate(-180deg) scale(0.8);
    opacity: 0;
}

#closeIcon.show {
    transform: rotate(0) scale(1);
    opacity: 1;
}
</style>

<script>
// Modifikasi fungsi toggleSidebar
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const menuIcon = document.getElementById('menuIcon');
    const closeIcon = document.getElementById('closeIcon');
    const toggleButton = document.getElementById('toggleSidebar');
    
    if (sidebar.classList.contains('active')) {
        // Menutup sidebar
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
        menuIcon.classList.remove('hidden');
        closeIcon.classList.remove('show');
        document.body.style.overflow = '';
        toggleButton.style.left = '15px'; // Sesuaikan dengan posisi awal di navbar
    } else {
        // Membuka sidebar
        sidebar.classList.add('active');
        overlay.classList.add('active');
        menuIcon.classList.add('hidden');
        closeIcon.classList.add('show');
        document.body.style.overflow = 'hidden';
        toggleButton.style.left = '305px'; // Sesuaikan dengan class left-[305px]
    }
}

// Event listener untuk overlay
document.getElementById('sidebarOverlay').addEventListener('click', toggleSidebar);

// Event listener untuk toggle button
document.getElementById('toggleSidebar').addEventListener('click', function(e) {
    e.preventDefault();
    e.stopPropagation();
    toggleSidebar();
});

// Enhanced initialization
document.addEventListener('DOMContentLoaded', function() {
    // Initialize active submenu
    const currentPage = window.location.pathname.split('/').pop();
    const activeSubmenu = document.querySelector(`a[href="${currentPage}"]`)?.closest('.submenu');
    if (activeSubmenu) {
        activeSubmenu.classList.remove('hidden', 'opacity-0', 'scale-95');
        activeSubmenu.classList.add('opacity-100', 'scale-100');
        const arrow = activeSubmenu.previousElementSibling.querySelector('svg:last-child');
        arrow.style.transform = 'rotate(180deg)';
    }

    // Handle click outside
    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('sidebar');
        const toggleBtn = document.getElementById('toggleSidebar');
        
        if (window.innerWidth < 640) {
            if (!sidebar.contains(event.target) && 
                !toggleBtn.contains(event.target) && 
                sidebar.classList.contains('active')) {
                toggleSidebar();
            }
        }
    });

    // Handle escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && document.getElementById('sidebar').classList.contains('active')) {
            toggleSidebar();
        }
    });

    // Handle resize
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (window.innerWidth >= 640) {
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('sidebarOverlay');
                const menuIcon = document.getElementById('menuIcon');
                const closeIcon = document.getElementById('closeIcon');
                
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
                menuIcon.classList.remove('hidden');
                closeIcon.classList.remove('show');
                document.body.style.overflow = '';
            }
        }, 250);
    });
});

// Tambahkan kembali fungsi toggleSubmenu
function toggleSubmenu(button) {
    const submenu = button.nextElementSibling;
    const arrow = button.querySelector('svg:last-child');
    
    if (submenu.classList.contains('hidden')) {
        // Show submenu with improved animation
        submenu.classList.remove('hidden');
        requestAnimationFrame(() => {
            submenu.classList.remove('opacity-0', 'scale-95');
            submenu.classList.add('opacity-100', 'scale-100');
            arrow.style.transform = 'rotate(180deg)';
        });
    } else {
        // Hide submenu with smooth transition
        submenu.classList.add('opacity-0', 'scale-95');
        arrow.style.transform = 'rotate(0)';
        setTimeout(() => submenu.classList.add('hidden'), 300);
    }
}
</script> 