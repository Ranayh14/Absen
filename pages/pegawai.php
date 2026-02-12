    <!-- Mobile Sidebar Overlay -->
    <div id="mobile-sidebar-overlay" class="fixed inset-0 bg-black/50 z-40 hidden md:hidden backdrop-blur-sm transition-all"></div>
    
    <!-- Mobile Sidebar -->
    <div id="mobile-sidebar" class="fixed top-0 left-0 h-full w-72 bg-white shadow-2xl z-50 transform -translate-x-full transition-transform duration-300 md:hidden font-outfit">
        <div class="p-6 border-b border-gray-100 flex items-center justify-between">
            <span class="text-xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-blue-700 to-blue-500">Menu</span>
            <button id="mobile-sidebar-close" class="p-2 hover:bg-gray-50 rounded-full text-gray-500 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <nav class="p-4 space-y-2">
            <?php if (isAdmin()): ?>
                <button data-tab="dashboard" class="mobile-tab-link w-full text-left py-3 px-4 font-semibold text-gray-600 hover:bg-blue-50 hover:text-blue-600 rounded-xl transition duration-300 flex items-center gap-3">
                    <i class="fi fi-sr-dashboard"></i> Dashboard
                </button>
                <button data-tab="members" class="mobile-tab-link w-full text-left py-3 px-4 font-semibold text-gray-600 hover:bg-blue-50 hover:text-blue-600 rounded-xl transition duration-300 flex items-center gap-3">
                    <i class="fi fi-sr-users"></i> Kelola Member
                </button>
                <button data-tab="laporan" class="mobile-tab-link w-full text-left py-3 px-4 font-semibold text-gray-600 hover:bg-blue-50 hover:text-blue-600 rounded-xl transition duration-300 flex items-center gap-3">
                    <i class="fi fi-sr-document"></i> Data Presensi
                </button>
                <button data-tab="admin-monthly" class="mobile-tab-link w-full text-left py-3 px-4 font-semibold text-gray-600 hover:bg-blue-50 hover:text-blue-600 rounded-xl transition duration-300 flex items-center gap-3">
                    <i class="fi fi-sr-calendar"></i> Laporan Bulanan
                </button>
                <button data-tab="settings" class="mobile-tab-link w-full text-left py-3 px-4 font-semibold text-gray-600 hover:bg-blue-50 hover:text-blue-600 rounded-xl transition duration-300 flex items-center gap-3">
                    <i class="fi fi-sr-settings"></i> Settings
                </button>
            <?php else: ?>
                <button data-tab="rekap" class="mobile-tab-link w-full text-left py-3 px-4 font-semibold text-gray-600 hover:bg-blue-50 hover:text-blue-600 rounded-xl transition duration-300 flex items-center gap-3">
                    <i class="fi fi-sr-list-check"></i> Rekap Hadir
                </button>
                <button data-tab="laporan-bulanan" class="mobile-tab-link w-full text-left py-3 px-4 font-semibold text-gray-600 hover:bg-blue-50 hover:text-blue-600 rounded-xl transition duration-300 flex items-center gap-3">
                    <i class="fi fi-sr-document-signed"></i> Laporan Bulanan
                </button>
            <?php endif; ?>
        </nav>
    </div>
    
    <header class="bg-white/80 backdrop-blur-md sticky top-0 z-30 border-b border-gray-100">
        <div class="w-full px-4 lg:px-6 py-3 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <button id="mobile-menu-toggle" class="md:hidden p-2 hover:bg-gray-100 rounded-xl text-gray-600 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center text-white font-bold">
                        <i class="fi fi-sr-shield-check text-sm"></i>
                    </div>
                    <h1 class="text-xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-gray-800 to-gray-600 tracking-tight hidden sm:block">SPBW <span class="text-gray-400 font-light">|</span> <?php echo isAdmin() ? 'ADMIN' : 'PEGAWAI'; ?></h1>
                </div>
            </div>
            
            <div class="flex items-center gap-4">
                <!-- Employee Notifications -->
                <div class="relative">
                    <button id="btn-pegawai-notif" class="relative p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-xl transition-all group">
                        <i class="fi fi-sr-bell text-xl"></i>
                        <span id="notif-pegawai-badge" class="absolute top-1 right-1 w-2.5 h-2.5 bg-red-500 border-2 border-white rounded-full hidden"></span>
                    </button>
                    <!-- Notifications Dropdown -->
                    <div id="dropdown-pegawai-notif" class="absolute right-0 mt-3 w-80 sm:w-96 bg-white rounded-2xl shadow-xl border border-gray-100 hidden z-50 overflow-hidden animate-fade-in-up">
                        <div class="p-4 border-b border-gray-50 flex items-center justify-between bg-blue-600 text-white">
                            <h3 class="font-bold">Notifikasi Status Request</h3>
                            <button id="btn-mark-all-read" class="text-[10px] uppercase font-bold bg-white/20 hover:bg-white/30 px-2 py-1 rounded-lg transition-all">Baca Semua</button>
                        </div>
                        <!-- Filter Tabs -->
                        <div class="flex border-b border-gray-100 bg-gray-50/50">
                            <button id="tab-notif-unread" class="flex-1 py-3 text-xs font-bold text-blue-600 border-b-2 border-blue-600 bg-white transition-all">Belum Dibaca</button>
                            <button id="tab-notif-read" class="flex-1 py-3 text-xs font-bold text-gray-400 hover:text-gray-600 transition-all">Sudah Dibaca</button>
                        </div>
                        <div id="notif-pegawai-items" class="max-h-96 overflow-y-auto p-2 space-y-1">
                            <div class="p-8 text-center text-gray-400">
                                <i class="fi fi-sr-inbox text-3xl mb-2 block"></i>
                                <p class="text-xs">Tidak ada notifikasi baru</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="relative">
                    <button id="btn-profile" class="flex items-center gap-3 p-1 pr-4 bg-white border border-gray-200 hover:border-blue-300 rounded-full transition-all shadow-sm hover:shadow-md group">
                        <img src="generate-avatar.php?background=eff6ff&color=3b82f6&name=<?php echo urlencode($_SESSION['user']['nama'] ?? 'A'); ?>&size=32" class="avatar w-9 h-9 rounded-full object-cover ring-2 ring-white" alt="profile">
                         <span class="text-sm font-semibold text-gray-700 group-hover:text-blue-600 transition-colors hidden sm:inline"><?php echo htmlspecialchars($_SESSION['user']['nama'] ?? 'Akun'); ?></span>
                         <i class="fi fi-sr-angle-small-down text-gray-400"></i>
                    </button>
                    <!-- Dropdown Code Preserved -->
                    <div id="dropdown-profile" class="absolute right-0 mt-2 w-48 bg-white rounded-2xl shadow-xl border border-gray-100 hidden z-50 overflow-hidden animate-fade-in-up">
                        <?php if(isset($_SESSION['user'])): ?>
                            <div class="px-4 py-3 bg-gray-50 border-b border-gray-100">
                                <p class="text-xs text-gray-500 uppercase font-bold tracking-wider mb-1">Signed in as</p>
                                <p class="text-sm font-medium text-gray-800 truncate"><?php echo htmlspecialchars($_SESSION['user']['email'] ?? ''); ?></p>
                            </div>
                            <a href="?page=logout" class="block px-4 py-3 text-sm text-red-600 hover:bg-red-50 transition-colors flex items-center gap-2">
                                <i class="fi fi-sr-sign-out-alt"></i> Logout
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="w-full px-2 sm:px-4 lg:px-6 py-8 overflow-x-auto">
        
        <?php if (!isAdmin()): ?>
        <!-- Hero Banner for Pegawai -->
        <?php require 'components/robot_cat.php'; ?>
        
        <!-- Tabs Navigation -->
        <div class="flex items-center gap-2 mb-6 overflow-x-auto pb-2 scrollbar-hide">
             <button data-tab="rekap" class="tab-link active-tab px-6 py-2.5 rounded-full font-bold text-sm transition-all shadow-sm border border-transparent">
                Rekap Harian
            </button>
             <button data-tab="laporan-bulanan" class="tab-link px-6 py-2.5 rounded-full font-bold text-sm text-gray-500 hover:bg-white hover:shadow-sm border border-transparent transition-all">
                Laporan Bulanan
            </button>
        </div>
        <style>
            .active-tab {
                background-color: #1e293b; /* Slate 800 */
                color: white;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            }
            .tab-link:not(.active-tab):hover {
                background-color: white;
                color: #3b82f6;
            }
        </style>
        <?php endif; ?>

        <!-- Admin Navbar (Hidden if not admin, but kept for logic) -->
        <?php if (isAdmin()): ?>
        <div class="flex flex-wrap gap-2 mb-8 bg-white p-2 rounded-2xl shadow-sm border border-gray-100">
             <button data-tab="dashboard" class="tab-link flex-1 py-2 px-4 rounded-xl font-semibold text-gray-600 hover:bg-blue-50 hover:text-blue-600 transition-colors">Dashboard</button>
             <button data-tab="members" class="tab-link flex-1 py-2 px-4 rounded-xl font-semibold text-gray-600 hover:bg-blue-50 hover:text-blue-600 transition-colors">Member</button>
             <button data-tab="laporan" class="tab-link flex-1 py-2 px-4 rounded-xl font-semibold text-gray-600 hover:bg-blue-50 hover:text-blue-600 transition-colors">Presensi</button>
             <button data-tab="admin-monthly" class="tab-link flex-1 py-2 px-4 rounded-xl font-semibold text-gray-600 hover:bg-blue-50 hover:text-blue-600 transition-colors">Laporan</button>
             <button data-tab="settings" class="tab-link flex-1 py-2 px-4 rounded-xl font-semibold text-gray-600 hover:bg-blue-50 hover:text-blue-600 transition-colors">Settings</button>
        </div>
        <?php endif; ?>
        
        <!-- Pegawai: Rekap Hadir -->
        <?php require 'rekap_hadir.php'; ?>

        <!-- Pegawai: Laporan Bulanan -->
        <?php require 'laporan_bulanan_pegawai.php'; ?>

        <?php if (isAdmin()): ?>
            <?php require 'kelola_member.php'; ?>
            <?php require 'data_presensi.php'; ?>
            <?php require 'laporan_bulanan_admin.php'; ?>
            <?php require 'settings.php'; ?>
            <?php require 'dashboard.php'; ?>
        <?php endif; ?>

    </main>


    <!-- Modals -->
    <?php require 'components/modals_common.php'; ?>
    <?php require 'components/modals_pegawai.php'; ?>
    <?php require 'components/modals_admin.php'; ?>


    <!-- Robot Cat Components (Inlined for compatibility) -->
    <style id="robot-cat-styles">
        <?php 
        $css_path = __DIR__ . '/../assets/css/robot_cat_animations.css';
        if (file_exists($css_path)) {
            echo file_get_contents($css_path);
        } else {
            echo "/* Error: CSS file not found at $css_path */";
        }
        ?>
    </style>
    <script id="robot-cat-script">
        <?php 
        $js_path = __DIR__ . '/../assets/js/robot_cat_character.js';
        if (file_exists($js_path)) {
            echo file_get_contents($js_path);
        } else {
            echo "console.error('Error: JS file not found at $js_path');";
        }
        ?>
    </script>
