<?php
require_once '../backend/check_session.php';
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="fixed inset-y-0 left-0 flex items-center px-4 z-50">
    <!-- Floating Container dengan efek glass yang lebih baik -->
    <div class="relative w-64 h-[96%] rounded-[32px] overflow-hidden shadow-[0_0_50px_-12px_rgba(0,0,0,0.25)] border border-white/5 backdrop-blur-2xl">
        <!-- Background Effect yang lebih halus -->
        <div class="absolute inset-0">
            <div class="absolute inset-0 bg-gradient-to-b from-[#0B1437]/90 via-[#1B2B65]/90 to-[#0B1437]/90"></div>
            <!-- Soft Glow Effect -->
            <div class="absolute inset-0">
                <div class="absolute top-0 -left-1/2 w-2/3 h-1/3 bg-blue-500/20 rounded-full blur-3xl"></div>
                <div class="absolute bottom-0 -right-1/2 w-2/3 h-1/3 bg-blue-400/20 rounded-full blur-3xl"></div>
            </div>
            <!-- Subtle Pattern -->
            <div class="absolute inset-0 opacity-5 bg-[radial-gradient(#fff_1px,transparent_1px)] [background-size:16px_16px]"></div>
        </div>

        <!-- Content Container -->
        <div class="relative h-full flex flex-col">
            <!-- Logo Section dengan efek glass yang lebih halus -->
            <div class="p-6 border-b border-white/5">
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

            <!-- Navigation dengan spacing yang lebih baik -->
            <nav class="flex-1 p-4 space-y-2 overflow-y-auto custom-scrollbar">
                <?php
                $menuItems = [
                    ['url' => 'dashboard.php', 'text' => 'Dashboard', 'icon' => 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z'],
                    [
                        'text' => 'Master Data',
                        'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
                        'submenu' => [
                            ['url' => 'kategori.php', 'text' => 'Kategori', 'icon' => 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z'],
                            ['url' => 'produk.php', 'text' => 'Produk', 'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4']
                        ]
                    ],
                    [
                        'text' => 'Penjualan',
                        'icon' => 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z',
                        'submenu' => [
                            ['url' => 'penjualan.php', 'text' => 'POS Kasir', 'icon' => 'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z'],
                            ['url' => 'informasi.php', 'text' => 'Informasi', 'icon' => 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z']
                        ]
                    ],
                    ['url' => 'users.php', 'text' => 'Users', 'icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z']
                ];

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
</aside>

<style>
/* Custom Scrollbar yang lebih halus */
.custom-scrollbar::-webkit-scrollbar {
    width: 3px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 10px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.2);
}
</style>

<!-- Enhanced JavaScript for smoother animations -->
<script>
function toggleSubmenu(button) {
    const submenu = button.nextElementSibling;
    const arrow = button.querySelector('svg:last-child');
    
    if (submenu.classList.contains('hidden')) {
        // Show submenu
        submenu.classList.remove('hidden');
        setTimeout(() => {
            submenu.classList.remove('opacity-0', 'scale-95');
            submenu.classList.add('opacity-100', 'scale-100');
        }, 10);
        arrow.style.transform = 'rotate(180deg)';
    } else {
        // Hide submenu
        submenu.classList.add('opacity-0', 'scale-95');
        setTimeout(() => {
            submenu.classList.add('hidden');
        }, 300);
        arrow.style.transform = 'rotate(0)';
    }
}

// Initialize active submenu
document.addEventListener('DOMContentLoaded', function() {
    const currentPage = window.location.pathname.split('/').pop();
    const activeSubmenu = document.querySelector(`a[href="${currentPage}"]`)?.closest('.submenu');
    if (activeSubmenu) {
        activeSubmenu.classList.remove('hidden', 'opacity-0', 'scale-95');
        activeSubmenu.classList.add('opacity-100', 'scale-100');
        const arrow = activeSubmenu.previousElementSibling.querySelector('svg:last-child');
        arrow.style.transform = 'rotate(180deg)';
    }
});
</script> 