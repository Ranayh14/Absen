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
        <div class="relative w-full rounded-2xl overflow-hidden shadow-lg mb-8 animate-fade-in-up">
            <div class="absolute inset-0 bg-gradient-to-r from-blue-600 via-blue-700 to-indigo-800"></div>
            <!-- Decorative Circles -->
            <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-8 -mt-8 blur-xl"></div>
            <div class="absolute bottom-0 left-0 w-24 h-24 bg-blue-400/20 rounded-full -ml-6 -mb-6 blur-lg"></div>
            
            <div class="relative p-6 flex flex-row items-center justify-between gap-6">
                <div class="text-white flex-1 z-10">
                    <h2 class="text-2xl md:text-3xl font-bold mb-2 tracking-tight">
                        Halo <?php echo explode(' ', $_SESSION['user']['nama'])[0]; ?>!
                        <span class="text-blue-200 block text-lg md:text-xl font-normal mt-1">Jangan Lupa Laporan.</span>
                    </h2>
                    <p class="text-blue-100 text-sm mb-4 max-w-md">
                        Pastikan presensi masuk dan pulang tercatat, serta laporan harian terisi dengan benar.
                    </p>
                    <div class="flex flex-wrap gap-3">
                    <a href="?page=presensi-masuk" class="bg-white hover:bg-gray-50 text-indigo-600 font-semibold py-2 px-4 rounded-xl shadow-md transition-all flex items-center gap-2 text-sm">
                        <i class="fi fi-sr-sign-in-alt"></i>
                        <span>Presensi Masuk</span>
                    </a>
                    <a href="?page=presensi-pulang" class="bg-indigo-500 hover:bg-indigo-400 text-white font-semibold py-2 px-4 rounded-xl shadow-md transition-all flex items-center gap-2 border border-white/20 text-sm">
                        <i class="fi fi-sr-sign-out-alt"></i>
                        <span>Presensi Pulang</span>
                    </a>
                </div>
                </div>
                
                <!-- SVG Robot Cat Character -->
                <div class="relative w-24 h-24 md:w-28 md:h-28 flex items-center justify-center flex-shrink-0">
                    <div class="w-full h-full bg-gradient-to-tr from-blue-400/20 to-indigo-400/20 backdrop-blur-sm rounded-2xl absolute border border-white/20"></div>
                    <div id="robot-cat-character" class="relative z-10 w-full h-full emotion-happy">
                        <svg viewBox="0 0 400 400" width="100%" height="100%">
                            <!-- DEFINITIONS -->
                            <defs>
                                <linearGradient id="metalGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" style="stop-color:#f8fafc;stop-opacity:1" />
                                    <stop offset="100%" style="stop-color:#94a3b8;stop-opacity:1" />
                                </linearGradient>
                                <symbol id="heart-shape" viewBox="0 0 32 32">
                                    <path d="M16 28.5L14.1 26.8C7.3 20.6 2.8 16.5 2.8 11.5C2.8 7.4 6 4.2 10.1 4.2C12.4 4.2 14.6 5.3 16 7C17.4 5.3 19.6 4.2 21.9 4.2C26 4.2 29.2 7.4 29.2 11.5C29.2 16.5 24.7 20.6 17.9 26.8L16 28.5Z" />
                                </symbol>
                            </defs>

                            <!-- SHADOW -->
                            <ellipse cx="200" cy="370" rx="90" ry="12" fill="rgba(0,0,0,0.15)" />

                            <!-- === EKOR (DI BELAKANG BADAN) === -->
                            <!-- Ekor Happy -->
                            <g id="tail-happy-state">
                                <g id="tail-happy-group">
                                    <path d="M260 250 Q350 240 340 160" fill="none" stroke="url(#metalGrad)" stroke-width="14" stroke-linecap="round" />
                                    <use href="#heart-shape" x="325" y="125" width="30" height="30" fill="url(#metalGrad)" transform="rotate(-15, 340, 140)" />
                                </g>
                            </g>

                            <!-- Ekor Sad -->
                            <g id="tail-sad-state" class="hidden">
                                <path d="M260 250 Q310 260 330 350" fill="none" stroke="url(#metalGrad)" stroke-width="14" stroke-linecap="round" />
                            </g>

                            <!-- Ekor Angry -->
                            <g id="tail-angry-state" class="hidden">
                                <path id="tail-angry-v3" d="M260 250 L290 220 L310 260 L330 210 L350 250 L370 180" fill="none" stroke="url(#metalGrad)" stroke-width="12" stroke-linecap="round" />
                            </g>

                            <!-- === TUBUH === -->
                            <g id="cat-body">
                                <!-- Kaki Belakang -->
                                <rect x="235" y="290" width="32" height="75" rx="16" fill="#64748b" stroke="#1e293b" stroke-width="3"/>
                                <rect x="135" y="290" width="32" height="75" rx="16" fill="#64748b" stroke="#1e293b" stroke-width="3"/>
                                
                                <!-- Kaki Depan -->
                                <rect x="210" y="300" width="38" height="70" rx="19" fill="url(#metalGrad)" stroke="#1e293b" stroke-width="4"/>
                                <rect x="150" y="300" width="38" height="70" rx="19" fill="url(#metalGrad)" stroke="#1e293b" stroke-width="4"/>
                                
                                <!-- Baut kaki -->
                                <circle cx="230" cy="315" r="3" fill="#1e293b" opacity="0.4"/>
                                <circle cx="170" cy="315" r="3" fill="#1e293b" opacity="0.4"/>

                                <!-- Badan Utama -->
                                <path d="M120 210 Q120 170 200 170 Q280 170 280 210 L280 300 Q280 330 200 330 Q120 330 120 300 Z" fill="url(#metalGrad)" stroke="#1e293b" stroke-width="4"/>
                                
                                <!-- Detail Panel -->
                                <path d="M135 230 L265 230" stroke="#1e293b" stroke-width="1" opacity="0.2"/>
                                <circle cx="260" cy="250" r="15" fill="#1e293b" opacity="0.1"/>
                                <circle cx="260" cy="250" r="8" fill="var(--glow-cyan)" class="glow-cyan" id="body-light"/>
                            </g>

                            <!-- === KEPALA === -->
                            <!-- Happy Head -->
                            <g id="head-happy-state">
                                <!-- Floating Hearts -->
                                <use href="#heart-shape" x="80" y="80" width="25" height="25" fill="#00f2ff" class="floating-heart" style="animation-delay: 0s" />
                                <use href="#heart-shape" x="290" y="100" width="20" height="20" fill="#00f2ff" class="floating-heart" style="animation-delay: 0.7s" />
                                
                                <g id="head-base">
                                    <path d="M100 140 Q100 90 180 90 Q260 90 260 140 L260 195 Q260 235 180 235 Q100 235 100 195 Z" fill="url(#metalGrad)" stroke="#1e293b" stroke-width="5"/>
                                    <!-- Telinga -->
                                    <path d="M125 105 L95 45 L165 95 Z" fill="url(#metalGrad)" stroke="#1e293b" stroke-width="4"/>
                                    <path d="M235 105 L265 45 L195 95 Z" fill="url(#metalGrad)" stroke="#1e293b" stroke-width="4"/>
                                    <!-- Visor -->
                                    <rect x="120" y="130" width="120" height="75" rx="37" fill="#1e293b"/>
                                    <!-- Eyes Happy -->
                                    <path d="M140 165 Q155 145 170 165" fill="none" stroke="var(--glow-cyan)" stroke-width="6" stroke-linecap="round" class="glow-cyan"/>
                                    <path d="M190 165 Q205 145 220 165" fill="none" stroke="var(--glow-cyan)" stroke-width="6" stroke-linecap="round" class="glow-cyan"/>
                                </g>
                            </g>

                            <!-- Sad Head -->
                            <g id="head-sad-state" class="hidden">
                                <g id="head-sad-v3">
                                    <path d="M100 140 Q100 90 180 90 Q260 90 260 140 L260 195 Q260 235 180 235 Q100 235 100 195 Z" fill="url(#metalGrad)" stroke="#1e293b" stroke-width="5"/>
                                    <!-- Telinga Animated -->
                                    <path id="ear-l-sad" d="M125 105 L85 125 L155 120 Z" fill="url(#metalGrad)" stroke="#1e293b" stroke-width="4"/>
                                    <path id="ear-r-sad" d="M235 105 L275 125 L205 120 Z" fill="url(#metalGrad)" stroke="#1e293b" stroke-width="4"/>
                                    <!-- Visor -->
                                    <rect x="120" y="130" width="120" height="75" rx="37" fill="#1e293b"/>
                                    <!-- Eyes Sad -->
                                    <path d="M145 175 Q155 160 165 175" fill="none" stroke="var(--glow-cyan)" stroke-width="5" stroke-linecap="round" opacity="0.5"/>
                                    <path d="M195 175 Q205 160 215 175" fill="none" stroke="var(--glow-cyan)" stroke-width="5" stroke-linecap="round" opacity="0.5"/>
                                    <!-- Tears -->
                                    <circle cx="155" cy="185" r="3" fill="var(--glow-cyan)" opacity="0.8">
                                        <animate attributeName="cy" from="185" to="210" dur="2s" repeatCount="indefinite" />
                                        <animate attributeName="opacity" from="0.8" to="0" dur="2s" repeatCount="indefinite" />
                                    </circle>
                                </g>
                            </g>

                            <!-- Angry Head -->
                            <g id="head-angry-state" class="hidden">
                                <!-- Smoke -->
                                <circle cx="110" cy="60" r="12" fill="#cbd5e1" class="smoke-puff" />
                                <circle cx="250" cy="50" r="10" fill="#cbd5e1" class="smoke-puff" style="animation-delay: 0.8s"/>
                                
                                <g id="head-angry-v3">
                                    <path d="M100 140 Q100 90 180 90 Q260 90 260 140 L260 195 Q260 235 180 235 Q100 235 100 195 Z" fill="url(#metalGrad)" stroke="#1e293b" stroke-width="5"/>
                                    <path d="M125 105 L95 45 L165 95 Z" fill="url(#metalGrad)" stroke="#1e293b" stroke-width="4"/>
                                    <path d="M235 105 L265 45 L195 95 Z" fill="url(#metalGrad)" stroke="#1e293b" stroke-width="4"/>
                                    <!-- Visor -->
                                    <rect x="120" y="130" width="120" height="75" rx="37" fill="#1e293b"/>
                                    <!-- Eyes Angry (Red) -->
                                    <path d="M140 170 L170 155" fill="none" stroke="var(--glow-red)" stroke-width="8" stroke-linecap="round" class="glow-red"/>
                                    <path d="M190 155 L220 170" fill="none" stroke="var(--glow-red)" stroke-width="8" stroke-linecap="round" class="glow-red"/>
                                    <!-- Electric Spark -->
                                    <path d="M175 80 L185 60 L195 75" fill="none" stroke="#fbbf24" stroke-width="3" class="glow-cyan"/>
                                </g>
                            </g>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
        
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

    <!-- Modal Tambah/Edit Member -->
    <div id="member-modal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-40 hidden p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
            <!-- Modal Header -->
            <div class="sticky top-0 bg-gradient-to-r from-blue-600 to-blue-700 text-white px-6 py-4 rounded-t-2xl flex items-center justify-between z-10">
                <h2 id="modal-title" class="text-lg font-bold flex items-center gap-2">
                    <i class="fi fi-sr-user-add"></i>
                    Tambah Member Baru
                </h2>
                <button type="button" id="btn-cancel-modal" class="text-white/80 hover:text-white hover:bg-white/20 rounded-full p-1.5 transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            
            <!-- Modal Body -->
            <form id="member-form" class="p-6 space-y-4">
                <input type="hidden" id="member-id">
                <input type="hidden" id="foto-data-url">
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Email</label>
                    <input type="email" id="email" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all text-sm">
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">NIM <span class="text-red-500">*</span></label>
                    <input type="text" id="nim" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all text-sm" required>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Nama Lengkap <span class="text-red-500">*</span></label>
                    <input type="text" id="nama" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all text-sm" required>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Program Studi <span class="text-red-500">*</span></label>
                    <input type="text" id="prodi" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all text-sm" required>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Nama Startup</label>
                    <input type="text" id="startup" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all text-sm">
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Foto Wajah</label>
                    <div id="modal-video-container" class="relative bg-gray-100 rounded-xl w-full aspect-video mb-3 hidden overflow-hidden">
                        <video id="modal-video" autoplay playsinline class="w-full h-full object-cover"></video>
                    </div>
                    <canvas id="modal-canvas" class="hidden"></canvas>
                    <img id="foto-preview" class="mt-2 h-40 w-40 object-cover rounded-xl border-2 border-gray-200 hidden mx-auto mb-3">
                    <div class="grid grid-cols-2 gap-2 mb-2">
                        <button type="button" id="btn-start-camera" class="bg-indigo-500 hover:bg-indigo-600 text-white font-semibold py-2.5 px-4 rounded-xl transition-all shadow-sm hover:shadow-md flex items-center justify-center gap-2">
                            <i class="fi fi-sr-camera"></i>
                            <span class="hidden sm:inline">Kamera</span>
                        </button>
                        <button type="button" id="btn-upload-photo" class="bg-purple-500 hover:bg-purple-600 text-white font-semibold py-2.5 px-4 rounded-xl transition-all shadow-sm hover:shadow-md flex items-center justify-center gap-2">
                            <i class="fi fi-sr-upload"></i>
                            <span class="hidden sm:inline">Upload</span>
                        </button>
                    </div>
                    <input type="file" id="photo-file-input" accept="image/*" class="hidden">
                    <button type="button" id="btn-take-photo" class="w-full bg-green-500 hover:bg-green-600 text-white font-semibold py-2.5 px-4 rounded-xl hidden transition-all shadow-sm hover:shadow-md">
                        <i class="fi fi-sr-camera"></i> Ambil Foto
                    </button>
                </div>
                
                <div id="password-admin-wrapper" class="grid grid-cols-1 sm:grid-cols-2 gap-3 hidden">
                    <input type="password" id="password-new" placeholder="Password Baru" class="px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all text-sm">
                    <input type="password" id="password-confirm" placeholder="Konfirmasi Password" class="px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all text-sm">
                </div>
                
                <div class="flex gap-3 pt-4 border-t border-gray-200">
                    <button type="button" id="btn-cancel-modal-btn" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-3 rounded-xl font-semibold transition-all">
                        Batal
                    </button>
                    <button type="submit" class="flex-1 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white px-4 py-3 rounded-xl font-semibold transition-all shadow-md hover:shadow-lg">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal QR Code Google Authenticator -->
    <div id="ga-qr-modal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden">
        <div class="bg-white p-8 rounded-lg shadow-2xl w-full max-w-md">
            <h2 class="text-2xl font-bold mb-4">QR Code Google Authenticator</h2>
            <div class="mb-4">
                <p class="text-sm text-gray-600 mb-2" id="ga-qr-email"></p>
                <p class="text-xs text-gray-500 mb-4">Scan QR code ini dengan aplikasi Google Authenticator di smartphone Anda.</p>
                <div class="flex justify-center bg-gray-50 p-4 rounded-lg">
                    <img id="ga-qr-image" src="" alt="QR Code" class="max-w-full h-auto">
                </div>
                <p class="text-xs text-gray-500 mt-4 text-center">
                    Setelah memindai QR code, gunakan kode OTP dari Google Authenticator untuk reset password.
                </p>
            </div>
            <div class="flex justify-end">
                <button type="button" id="btn-close-ga-qr" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded-lg">Tutup</button>
            </div>
        </div>
    </div>

    <!-- Modal Edit Kehadiran -->
    <div id="edit-att-modal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 hidden p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md max-h-[90vh] overflow-y-auto">
            <!-- Modal Header -->
            <div class="sticky top-0 bg-gradient-to-r from-indigo-600 to-indigo-700 text-white px-6 py-4 rounded-t-2xl flex items-center justify-between z-10">
                <h3 class="text-lg font-bold flex items-center gap-2">
                    <i class="fi fi-sr-edit"></i>
                    Edit Data Kehadiran
                </h3>
                <button type="button" id="edit-att-cancel" class="text-white/80 hover:text-white hover:bg-white/20 rounded-full p-1.5 transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            
            <!-- Modal Body -->
            <form id="edit-att-form" class="p-6 space-y-4">
                <input type="hidden" id="edit-att-id">
                <input type="hidden" id="edit-att-user-id">
                <input type="hidden" id="edit-att-screenshot-masuk-data">
                <input type="hidden" id="edit-att-screenshot-pulang-data">
                
                <!-- Tanggal -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Tanggal</label>
                    <input type="date" id="edit-att-date" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all text-sm" disabled>
                </div>
                
                <!-- Nama -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Nama</label>
                    <input type="text" id="edit-att-nama" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all text-sm" disabled>
                </div>
                
                <!-- Jam Masuk -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Jam Masuk</label>
                    <div class="flex gap-2">
                        <input type="time" id="edit-att-jam-masuk" class="flex-1 px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all text-sm">
                        <button type="button" id="edit-att-upload-masuk" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2.5 rounded-xl text-sm font-semibold transition-all shadow-sm hover:shadow-md whitespace-nowrap">
                            <i class="fi fi-sr-upload"></i>
                            <span class="hidden sm:inline ml-1">Bukti</span>
                        </button>
                    </div>
                    <div id="edit-att-screenshot-masuk-preview" class="mt-3 hidden">
                        <img id="edit-att-screenshot-masuk-img" src="" alt="Screenshot Masuk" class="w-full h-40 object-cover rounded-xl border-2 border-gray-200">
                        <button type="button" id="edit-att-remove-masuk" class="mt-2 text-red-600 text-sm font-semibold hover:text-red-700 flex items-center gap-1">
                            <i class="fi fi-sr-trash"></i> Hapus Bukti
                        </button>
                    </div>
                </div>
                
                <!-- Jam Pulang -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Jam Pulang</label>
                    <div class="flex gap-2">
                        <input type="time" id="edit-att-jam-pulang" class="flex-1 px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all text-sm">
                        <button type="button" id="edit-att-upload-pulang" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2.5 rounded-xl text-sm font-semibold transition-all shadow-sm hover:shadow-md whitespace-nowrap">
                            <i class="fi fi-sr-upload"></i>
                            <span class="hidden sm:inline ml-1">Bukti</span>
                        </button>
                    </div>
                    <div id="edit-att-screenshot-pulang-preview" class="mt-3 hidden">
                        <img id="edit-att-screenshot-pulang-img" src="" alt="Screenshot Pulang" class="w-full h-40 object-cover rounded-xl border-2 border-gray-200">
                        <button type="button" id="edit-att-remove-pulang" class="mt-2 text-red-600 text-sm font-semibold hover:text-red-700 flex items-center gap-1">
                            <i class="fi fi-sr-trash"></i> Hapus Bukti
                        </button>
                    </div>
                </div>
                
                <!-- Keterangan -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Keterangan</label>
                    <select id="edit-att-ket" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all text-sm font-medium">
                        <option value="wfo">WFO</option>
                        <option value="izin">Izin</option>
                        <option value="sakit">Sakit</option>
                        <option value="alpha">Alpha</option>
                        <option value="wfa">WFA</option>
                        <option value="overtime">Overtime</option>
                    </select>
                </div>
                
                <!-- WFA Form -->
                <div id="edit-att-wfa-form" class="hidden bg-blue-50 p-4 rounded-xl border border-blue-100">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Alasan WFA</label>
                    <textarea id="edit-att-alasan-wfa" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all text-sm resize-none" rows="3" placeholder="Tulis alasan WFA..."></textarea>
                </div>
                
                <!-- Overtime Form -->
                <div id="edit-att-overtime-form" class="hidden bg-orange-50 p-4 rounded-xl border border-orange-100 space-y-3">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Alasan Overtime</label>
                        <textarea id="edit-att-alasan-overtime" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all text-sm resize-none" rows="3" placeholder="Tulis alasan overtime..."></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Lokasi Overtime</label>
                        <input type="text" id="edit-att-lokasi-overtime" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all text-sm" placeholder="Tulis lokasi overtime...">
                    </div>
                </div>
                
                <!-- Status -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
                    <select id="edit-att-status" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all text-sm font-medium">
                        <option value="ontime">On Time</option>
                        <option value="terlambat">Terlambat</option>
                    </select>
                </div>
                
                <!-- Add Report Button -->
                <button type="button" id="edit-att-add-report" class="w-full bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white px-4 py-3 rounded-xl font-semibold transition-all shadow-md hover:shadow-lg flex items-center justify-center gap-2">
                    <i class="fi fi-sr-document-signed"></i>
                    Tambahkan Laporan
                </button>
                
                <!-- Action Buttons -->
                <div class="flex gap-3 pt-4 border-t border-gray-200">
                    <button type="button" id="edit-att-cancel-btn" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-3 rounded-xl font-semibold transition-all">
                        Batal
                    </button>
                    <button type="submit" id="edit-att-save" class="flex-1 bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-700 hover:to-indigo-800 text-white px-4 py-3 rounded-xl font-semibold transition-all shadow-md hover:shadow-lg">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Laporan Harian Admin -->
    <div id="admin-daily-report-modal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden">
        <div class="bg-white p-6 rounded-lg shadow-2xl w-full max-w-2xl">
            <h3 class="text-xl font-bold mb-4">Laporan Harian Pegawai</h3>
            <div class="mb-4">
                <p class="text-sm text-gray-600">Nama: <span id="admin-dr-nama" class="font-semibold"></span></p>
                <p class="text-sm text-gray-600">Tanggal: <span id="admin-dr-date" class="font-semibold"></span></p>
            </div>
            
            <!-- Bukti Izin/Sakit Section -->
            <div id="admin-dr-bukti-section" class="mb-4 hidden">
                <label class="block text-sm text-gray-600 mb-2">Bukti Izin/Sakit:</label>
                <div id="admin-dr-bukti-container" class="mb-2">
                    <!-- Bukti image will be inserted here -->
                </div>
                <div class="flex gap-2">
                    <button type="button" id="admin-dr-edit-bukti" class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded text-sm">Edit Bukti</button>
                    <button type="button" id="admin-dr-delete-bukti" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm">Hapus Bukti</button>
                </div>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm text-gray-600 mb-2">Isi Laporan Harian:</label>
                <textarea id="admin-dr-content" rows="8" class="w-full p-3 border rounded-lg" placeholder="Tulis detail pekerjaan pegawai hari ini..."></textarea>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" id="admin-dr-cancel" class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded">Batal</button>
                <button type="button" id="admin-dr-save" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded">Simpan</button>
            </div>
        </div>
    </div>

    <!-- Confirm Modal -->
    <div id="confirm-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-70 hidden">
        <div class="bg-white p-8 rounded-lg shadow-2xl w-full max-w-sm text-center">
            <p id="confirm-modal-message" class="text-lg mb-6">Apakah Anda yakin?</p>
            <div class="flex justify-center space-x-4">
                <button id="btn-confirm-no" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-6 rounded-lg">Tidak</button>
                <button id="btn-confirm-yes" class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-6 rounded-lg">Ya</button>
            </div>
        </div>
    </div>

    <!-- WFA Reason Modal -->
    <div id="wfa-reason-modal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden">
        <div class="bg-white p-6 rounded-lg shadow-2xl w-full max-w-md">
            <h3 class="text-xl font-bold mb-3">Alasan Kerja di Luar Kantor</h3>
            <p class="text-sm text-gray-600 mb-3">Anda berada di luar wilayah Telkom University. Silakan isi alasan bekerja di luar kantor untuk melanjutkan presensi (WFA).</p>
            <textarea id="wfa-reason-input" class="w-full p-3 border rounded mb-4" rows="4" placeholder="Tulis alasan Anda..."></textarea>
            <div class="flex justify-end gap-2">
                <button id="wfa-reason-cancel" class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded">Batal</button>
                <button id="wfa-reason-submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded">Kirim</button>
            </div>
        </div>
    </div>

    <!-- Early Leave Reason Modal -->
    <div id="early-leave-reason-modal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden">
        <div class="bg-white p-6 rounded-lg shadow-2xl w-full max-w-md">
            <h3 class="text-xl font-bold mb-3">Alasan Pulang Awal</h3>
            <p class="text-sm text-gray-600 mb-3">Anda pulang sebelum jam yang ditentukan. Silakan isi alasan pulang awal untuk melanjutkan presensi pulang.</p>
            <textarea id="early-leave-reason-input" class="w-full p-3 border rounded mb-4" rows="4" placeholder="Tulis alasan pulang awal Anda..."></textarea>
            <div class="flex justify-end gap-2">
                <button id="early-leave-reason-cancel" class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded">Batal</button>
                <button id="early-leave-reason-submit" class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded">Kirim</button>
            </div>
        </div>
    </div>

    <!-- Izin/Sakit Input Modal -->
    <div id="izin-sakit-modal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden">
        <div class="bg-white p-6 rounded-lg shadow-2xl w-full max-w-md">
            <h3 class="text-xl font-bold mb-4">Input Keterangan</h3>
            <form id="izin-sakit-form">
                <div class="mb-4">
                    <label class="block text-sm text-gray-600 mb-2">Jenis Keterangan</label>
                    <select id="izin-sakit-type" class="w-full p-2 border rounded-lg" required>
                        <option value="">Pilih jenis...</option>
                        <option value="izin">Izin</option>
                        <option value="sakit">Sakit</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm text-gray-600 mb-2">Keterangan</label>
                    <textarea id="izin-sakit-alasan" class="w-full p-3 border rounded" rows="4" placeholder="Tulis keterangan izin/sakit..." required></textarea>
                </div>
                <div class="mb-4">
                    <label class="block text-sm text-gray-600 mb-2">Upload Bukti</label>
                    <input type="file" id="izin-sakit-bukti" accept="image/*" class="w-full p-2 border rounded" required>
                    <p class="text-xs text-gray-500 mt-1">Maksimal 5MB. Format: JPG, PNG, GIF</p>
                    <div id="izin-sakit-preview" class="mt-2 hidden">
                        <img id="izin-sakit-preview-img" src="" alt="Preview" class="w-full h-32 object-cover rounded border">
                    </div>
                    <div id="izin-sakit-error" class="mt-2 text-red-600 text-sm hidden"></div>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" id="izin-sakit-cancel" class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded">Batal</button>
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Ket Detail Modal -->
    <div id="ket-detail-modal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden">
        <div class="bg-white p-6 rounded-lg shadow-2xl w-full max-w-2xl">
            <div class="flex justify-between items-center mb-4">
                <h3 id="ket-detail-title" class="text-xl font-bold"></h3>
                <button onclick="qs('#ket-detail-modal').classList.add('hidden'); qs('#ket-detail-modal').classList.remove('flex')" class="text-gray-500 hover:text-gray-700 text-2xl">✕</button>
            </div>
            <div id="ket-detail-content"></div>
        </div>
    </div>

    <!-- Modal Absence -->
    <div id="absence-modal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden">
        <div class="bg-white p-6 rounded-lg shadow-2xl w-full max-w-lg">
            <h3 class="text-xl font-bold mb-4">Input Keterangan Manual</h3>
            <div class="grid gap-3">
                <label class="text-sm text-gray-600 flex flex-col">
                    <span class="mb-1">Cari Pegawai</span>
                    <input type="text" id="abs-search" class="p-2 border rounded-lg" placeholder="Cari nama/NIM...">
                </label>
                <label class="text-sm text-gray-600 flex flex-col">
                    <span class="mb-1">Pilih Pegawai</span>
                    <select id="abs-user" class="p-2 border rounded-lg"></select>
                </label>
                <label class="text-sm text-gray-600 flex flex-col">
                    <span class="mb-1">Tanggal</span>
                    <input type="date" id="abs-date" class="p-2 border rounded-lg" value="<?php echo date('Y-m-d'); ?>">
                </label>
                <div class="mb-3">
                    <label class="block text-sm text-gray-600 mb-1">Keterangan</label>
                    <select id="abs-type" class="w-full p-2 border rounded-lg">
                        <option value="izin">Izin</option>
                        <option value="sakit">Sakit</option>
                        <option value="wfa">WFA</option>
                        <option value="overtime">Overtime</option>
                    </select>
                </div>
                <div id="abs-wfa-form" class="grid gap-2 hidden">
                    <label class="text-sm text-gray-600 flex flex-col">
                        <span class="mb-1">Jam Masuk</span>
                        <input type="time" id="abs-jam-masuk" class="p-2 border rounded-lg">
                    </label>
                    <label class="text-sm text-gray-600 flex flex-col">
                        <span class="mb-1">Jam Pulang</span>
                        <input type="time" id="abs-jam-pulang" class="p-2 border rounded-lg">
                    </label>
                    <label class="text-sm text-gray-600 flex flex-col">
                        <span class="mb-1">Alasan WFA</span>
                        <textarea id="abs-alasan-wfa" class="p-2 border rounded-lg" rows="3" placeholder="Tulis alasan WFA..."></textarea>
                    </label>
                </div>
                <div id="abs-overtime-form" class="grid gap-2 hidden">
                    <label class="text-sm text-gray-600 flex flex-col">
                        <span class="mb-1">Jam Masuk</span>
                        <input type="time" id="abs-jam-masuk-ot" class="p-2 border rounded-lg">
                    </label>
                    <label class="text-sm text-gray-600 flex flex-col">
                        <span class="mb-1">Jam Pulang</span>
                        <input type="time" id="abs-jam-pulang-ot" class="p-2 border rounded-lg">
                    </label>
                    <label class="text-sm text-gray-600 flex flex-col">
                        <span class="mb-1">Alasan Overtime</span>
                        <textarea id="abs-alasan-overtime" class="p-2 border rounded-lg" rows="3" placeholder="Tulis alasan overtime..."></textarea>
                    </label>
                    <label class="text-sm text-gray-600 flex flex-col">
                        <span class="mb-1">Lokasi Overtime</span>
                        <input type="text" id="abs-lokasi-overtime" class="p-2 border rounded-lg" placeholder="Tulis lokasi overtime...">
                    </label>
                </div>
            </div>
            <div class="flex justify-end gap-2 mt-4">
                <button id="abs-cancel" class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded">Batal</button>
                <button id="abs-save" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded">Simpan</button>
            </div>
        </div>
    </div>

    <!-- Manual Holidays Modal -->
    <div id="manual-holidays-modal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden">
        <div class="bg-white p-6 rounded-lg shadow-2xl w-full max-w-xl">
            <h3 class="text-xl font-bold mb-4">Kelola Hari Libur Manual</h3>
            <div class="flex gap-2 mb-3">
                <input type="date" id="mh-date" class="p-2 border rounded">
                <input type="text" id="mh-name" class="flex-1 p-2 border rounded" placeholder="Nama/Alasan libur (mis. Demo, Bencana)">
                <button id="mh-add" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded">Tambah</button>
            </div>
            <div class="overflow-x-auto max-h-80 overflow-y-auto">
                <table class="min-w-full bg-white bordered">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="py-2 px-3 text-left">Tanggal</th>
                            <th class="py-2 px-3 text-left">Keterangan</th>
                            <th class="py-2 px-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="mh-body"></tbody>
                </table>
            </div>
            <div class="text-right mt-3">
                <button id="mh-close" class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded">Tutup</button>
            </div>
        </div>
    </div>

    <!-- Modal Edit Bukti Izin/Sakit -->
    <div id="edit-bukti-modal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-70 hidden">
        <div class="bg-white p-6 rounded-lg shadow-2xl w-full max-w-lg">
            <h3 class="text-xl font-bold mb-4">Edit Bukti Izin/Sakit</h3>
            <div class="grid gap-3">
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Upload Bukti Baru</label>
                    <input type="file" id="edit-bukti-file" accept="image/*" class="w-full p-3 border rounded-lg">
                    <p class="text-xs text-gray-500 mt-1">Maksimal 5MB. Format: JPG, PNG, GIF</p>
                </div>
                <div class="mt-2">
                    <video id="edit-bukti-video" autoplay playsinline class="w-full h-48 object-cover rounded-lg hidden"></video>
                    <canvas id="edit-bukti-canvas" class="hidden"></canvas>
                    <img id="edit-bukti-preview" class="mt-2 h-32 w-32 object-cover rounded-lg hidden">
                    <button type="button" id="edit-bukti-capture" class="mt-2 bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded text-sm hidden">Ambil Foto</button>
                </div>
                <div id="edit-bukti-current" class="hidden">
                    <label class="block text-sm text-gray-600 mb-1">Bukti Saat Ini:</label>
                    <img id="edit-bukti-current-img" class="w-full max-w-md h-48 object-cover rounded border">
                </div>
            </div>
            <div class="flex justify-end gap-2 mt-4">
                <button type="button" id="edit-bukti-cancel" class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded">Batal</button>
                <button type="button" id="edit-bukti-save" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded">Simpan</button>
            </div>
        </div>
    </div>

    <!-- Modal Screenshot -->
    <div id="screenshot-modal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden">
        <div class="bg-white p-6 rounded-lg shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 id="screenshot-modal-title" class="text-xl font-bold"></h3>
                <button onclick="closeScreenshotModal()" class="text-gray-500 hover:text-gray-700 text-2xl">✕</button>
            </div>
            <div class="text-center">
                <img id="screenshot-modal-image" src="" alt="Screenshot" class="max-w-full max-h-[70vh] object-contain mx-auto rounded-lg shadow-lg">
            </div>
        </div>
    </div>

    <!-- Modal Daily Report Review -->
    <div id="dr-modal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden">
        <div class="bg-white p-6 rounded-lg shadow-2xl w-full max-w-2xl relative">
            <button id="dr-close" class="absolute top-3 right-3 text-gray-500">✕</button>
            <h3 class="text-xl font-bold mb-2">Laporan Harian</h3>
            <div id="dr-content" class="whitespace-pre-wrap border p-3 rounded mb-3 text-sm"></div>
            <textarea id="dr-evaluation" class="w-full border rounded p-2" rows="4" placeholder="Evaluasi admin..."></textarea>
            <div class="flex justify-end gap-2 mt-4">
                <button id="dr-disapprove" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded">Disapprove</button>
                <button id="dr-approve" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded">Approve</button>
            </div>
        </div>
    </div>

    <!-- Modal Jadwal Kerja -->
    <div id="work-schedule-modal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden">
        <div class="bg-white p-6 rounded-lg shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold">Kelola Jadwal Kerja</h3>
                <button id="work-schedule-close" class="text-gray-500 hover:text-gray-700 text-2xl">✕</button>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Pilih Pegawai</label>
                <select id="work-schedule-user" class="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">Pilih pegawai...</option>
                </select>
            </div>
            
            <div id="work-schedule-form" class="hidden">
                <div class="mb-4">
                    <h4 class="text-lg font-semibold mb-3">Jadwal Kerja Mingguan</h4>
                    <div class="space-y-3">
                        <div class="grid grid-cols-7 gap-2 text-sm font-medium text-gray-700">
                            <div>Hari</div>
                            <div>Bekerja</div>
                            <div>Jam Masuk</div>
                            <div>Jam Pulang</div>
                            <div>Durasi</div>
                            <div>Status</div>
                            <div>Aksi</div>
                        </div>
                        
                        <div id="work-schedule-days" class="space-y-2">
                            <!-- Days will be populated by JavaScript -->
                        </div>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Mulai Bekerja</label>
                    <input id="work-start-date" type="date" class="p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                    <p class="text-xs text-gray-500 mt-1">Digunakan sebagai tanggal awal perhitungan KPI pegawai.</p>
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <button id="work-schedule-cancel" class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded">Batal</button>
                    <button id="work-schedule-save" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded">Simpan Jadwal</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Floating Help Button -->
    <?php if (!isAdmin()): ?>
    <div id="admin-help-btn" class="fixed bottom-6 left-6 z-[60] animate-bounce-slow">
        <button class="w-14 h-14 md:w-16 md:h-16 bg-gradient-to-tr from-blue-600 to-indigo-700 rounded-full shadow-2xl flex items-center justify-center text-white hover:scale-110 active:scale-95 transition-all group relative">
            <i class="fi fi-rs-headset text-2xl md:text-3xl"></i>
            <span class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 rounded-full border-2 border-white hidden" id="help-notif-dot"></span>
        </button>
    </div>

    <!-- Admin Help Modal -->
    <div id="admin-help-modal" class="fixed inset-0 z-[70] hidden flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" id="help-modal-overlay"></div>
        <div class="bg-white w-full max-w-md rounded-3xl shadow-2xl overflow-hidden flex flex-col relative z-80 animate-fade-in-up max-h-[90vh]">
            <!-- Header -->
            <div class="bg-gradient-to-r from-blue-600 to-indigo-700 p-6 text-white flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-white/20 rounded-2xl flex items-center justify-center">
                        <i class="fi fi-rs-headset text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-lg">Admin Help Center</h3>
                        <p class="text-xs text-blue-100">Solusi bantuan cepat untuk Anda</p>
                    </div>
                </div>
                <button id="close-help-modal" class="p-2 hover:bg-white/20 rounded-full transition-colors">
                    <i class="fi fi-rr-cross-small text-xl"></i>
                </button>
            </div>

            <!-- Chat Content -->
            <div id="help-chat-content" class="flex-1 overflow-y-auto p-6 space-y-4 bg-gray-50 flex flex-col min-h-[400px]">
                <!-- Initial Message -->
                <div class="flex flex-col gap-2">
                    <div class="bg-indigo-600 text-white p-4 rounded-2xl rounded-tl-none shadow-sm max-w-[85%] text-sm leading-relaxed">
                        Halo <b><?php echo explode(' ', $_SESSION['user']['nama'])[0]; ?></b>, ada yang bisa kami bantu hari ini? Silakan pilih jenis bantuan di bawah ini.
                    </div>
                    <span class="text-[10px] text-gray-400 px-1"><?php echo date('H:i'); ?></span>
                </div>

                <!-- Action Options -->
                <div id="help-options" class="grid gap-2">
                    <button onclick="showHelpForm('past_attendance')" class="w-full text-left p-4 bg-white border border-gray-100 rounded-2xl shadow-sm hover:border-blue-500 hover:shadow-md transition-all flex items-center gap-4 group">
                        <div class="w-10 h-10 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center group-hover:bg-blue-600 group-hover:text-white transition-all">
                            <i class="fi fi-rr-calendar-clock"></i>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-gray-800">Presensi yang Terlewat</p>
                            <p class="text-[10px] text-gray-500">Izin/Sakit hari sebelumnya</p>
                        </div>
                    </button>
                    <button onclick="showHelpForm('late_attendance')" class="w-full text-left p-4 bg-white border border-gray-100 rounded-2xl shadow-sm hover:border-blue-500 hover:shadow-md transition-all flex items-center gap-4 group">
                        <div class="w-10 h-10 bg-purple-50 text-purple-600 rounded-xl flex items-center justify-center group-hover:bg-purple-600 group-hover:text-white transition-all">
                            <i class="fi fi-rr-clock-three"></i>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-gray-800">Lupa/Kendala Presensi</p>
                            <p class="text-[10px] text-gray-500">Belum absen atau aplikasi error</p>
                        </div>
                    </button>
                    <button onclick="showHelpForm('bug_report')" class="w-full text-left p-4 bg-white border border-gray-100 rounded-2xl shadow-sm hover:border-red-500 hover:shadow-md transition-all flex items-center gap-4 group">
                        <div class="w-10 h-10 bg-red-50 text-red-600 rounded-xl flex items-center justify-center group-hover:bg-red-600 group-hover:text-white transition-all">
                            <i class="fi fi-rr-bug"></i>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-gray-800">Laporkan Masalah/Bug</p>
                            <p class="text-[10px] text-gray-500">Aplikasi tidak berjalan semestinya</p>
                        </div>
                    </button>
                </div>

                <!-- Dynamic Forms Container -->
                <div id="help-form-container" class="hidden space-y-4">
                    <!-- Past Attendance Form -->
                    <div id="form-past_attendance" class="hidden bg-white p-5 rounded-2xl shadow-sm border border-gray-100 flex flex-col gap-4">
                        <h4 class="font-bold text-sm text-gray-800 flex items-center gap-2">
                            <i class="fi fi-rr-calendar-clock text-blue-600"></i> Request Izin/Sakit
                        </h4>
                        <div class="space-y-3">
                            <div>
                                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-wider px-1">Tanggal</label>
                                <input type="date" id="past-date" class="w-full mt-1 p-3 bg-gray-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-wider px-1">Jenis Izin</label>
                                <select id="past-type" class="w-full mt-1 p-3 bg-gray-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-blue-500">
                                    <option value="izin">Izin</option>
                                    <option value="sakit">Sakit</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-wider px-1">Alasan</label>
                                <textarea id="past-reason" rows="3" class="w-full mt-1 p-3 bg-gray-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-blue-500" placeholder="Tulis alasan lengkap..."></textarea>
                            </div>
                            <div>
                                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-wider px-1">Bukti (Foto)</label>
                                <div class="mt-1 flex items-center gap-2">
                                    <button type="button" onclick="qs('#past-bukti-input').click()" class="flex-1 bg-blue-50 text-blue-600 p-3 rounded-xl border border-dashed border-blue-200 text-xs font-semibold hover:bg-blue-100 transition-all flex items-center justify-center gap-2">
                                        <i class="fi fi-rr-camera"></i> <span id="past-bukti-text">Pilih Foto</span>
                                    </button>
                                    <input type="file" id="past-bukti-input" class="hidden" accept="image/*" onchange="handleFileSelect(this, 'past-bukti-text', 'past-bukti-data')">
                                    <input type="hidden" id="past-bukti-data">
                                </div>
                            </div>
                        </div>
                        <div class="flex gap-2 mt-2">
                            <button onclick="cancelHelpForm()" class="flex-1 py-3 text-xs font-bold text-gray-500 hover:bg-gray-100 rounded-xl transition-all">Batal</button>
                            <button onclick="submitHelpRequest('past_attendance')" class="flex-[2] py-3 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-xl transition-all shadow-md">Kirim Request</button>
                        </div>
                    </div>

                    <!-- Late Attendance Form -->
                    <div id="form-late_attendance" class="hidden bg-white p-5 rounded-2xl shadow-sm border border-gray-100 flex flex-col gap-4">
                        <h4 class="font-bold text-sm text-gray-800 flex items-center gap-2">
                            <i class="fi fi-rr-clock-three text-purple-600"></i> Request Lupa Presensi
                        </h4>
                        <div class="space-y-3">
                            <div>
                                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-wider px-1">Tanggal</label>
                                <input type="date" id="late-date" class="w-full mt-1 p-3 bg-gray-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-blue-500" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            
                            <!-- New Fields: Attendance Type & Reason -->
                            <div>
                                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-wider px-1">Tipe Presensi</label>
                                <select id="late-attendance-type" class="w-full mt-1 p-3 bg-gray-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-blue-500" onchange="toggleLateReason()">
                                    <option value="wfo">Work From Office (WFO)</option>
                                    <option value="wfa">Work From Anywhere (WFA)</option>
                                    <option value="overtime">Lembur (Overtime)</option>
                                </select>
                            </div>
                            <div id="late-reason-container" class="hidden">
                                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-wider px-1 text-red-500">Alasan (Wajib untuk WFA/Overtime)</label>
                                <textarea id="late-reason" rows="2" class="w-full mt-1 p-3 bg-gray-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-blue-500" placeholder="Jelaskan alasan WFA atau detail Overtime..."></textarea>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="text-[10px] font-bold text-gray-400 uppercase tracking-wider px-1">Jam Masuk</label>
                                    <input type="time" id="late-jam-masuk" class="w-full mt-1 p-3 bg-gray-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-blue-500">
                                </div>
                                <div>
                                     <label class="text-[10px] font-bold text-gray-400 uppercase tracking-wider px-1">Jam Pulang <span class="text-[8px] font-normal text-gray-300">(Opsional)</span></label>
                                     <input type="time" id="late-jam-pulang" class="w-full mt-1 p-3 bg-gray-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-blue-500">
                                 </div>
                            </div>
                            <div class="space-y-2">
                                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-wider px-1">Bukti Presensi</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <button onclick="startLatePresensiNow()" class="bg-indigo-600 text-white p-3 rounded-xl text-xs font-semibold hover:bg-indigo-700 transition-all flex flex-col items-center justify-center gap-1 shadow-sm">
                                        <i class="fi fi-rr-face-viewfinder text-lg"></i>
                                        <span>Presensi Sekarang</span>
                                    </button>
                                    <button type="button" onclick="qs('#late-bukti-input').click()" class="bg-purple-50 text-purple-600 p-3 rounded-xl border border-dashed border-purple-200 text-xs font-semibold hover:bg-purple-100 transition-all flex flex-col items-center justify-center gap-1">
                                        <i class="fi fi-rr-upload text-lg"></i>
                                        <span id="late-bukti-text">Upload Foto</span>
                                    </button>
                                    <input type="file" id="late-bukti-input" class="hidden" accept="image/*" onchange="handleFileSelect(this, 'late-bukti-text', 'late-bukti-data')">
                                    <input type="hidden" id="late-bukti-data">
                                </div>
                                <p class="text-[9px] text-gray-400 italic">* Pilih 'Presensi Sekarang' untuk validasi wajah & lokasi real-time. Kosongkan jam pulang jika Anda berencana presensi pulang secara normal nanti.</p>
                            </div>
                        </div>
                        <div class="flex gap-2 mt-2">
                            <button onclick="cancelHelpForm()" class="flex-1 py-3 text-xs font-bold text-gray-500 hover:bg-gray-100 rounded-xl transition-all">Batal</button>
                            <button onclick="submitHelpRequest('late_attendance')" class="flex-[2] py-3 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl transition-all shadow-md">Kirim Request</button>
                        </div>
                    </div>

                    <!-- Bug Report Form -->
                    <div id="form-bug_report" class="hidden bg-white p-5 rounded-2xl shadow-sm border border-gray-100 flex flex-col gap-4">
                        <h4 class="font-bold text-sm text-gray-800 flex items-center gap-2">
                            <i class="fi fi-rr-bug text-red-600"></i> Laporkan Masalah
                        </h4>
                        <div class="space-y-3">
                            <div>
                                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-wider px-1">Deskripsi Masalah</label>
                                <textarea id="bug-desc" rows="4" class="w-full mt-1 p-3 bg-gray-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-blue-500" placeholder="Jelaskan kendala yang dialami..."></textarea>
                            </div>
                            <div>
                                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-wider px-1">Bukti Foto (Opsional)</label>
                                <div class="mt-1">
                                    <button type="button" onclick="qs('#bug-bukti-input').click()" class="w-full bg-red-50 text-red-600 p-3 rounded-xl border border-dashed border-red-200 text-xs font-semibold hover:bg-red-100 transition-all flex items-center justify-center gap-2">
                                        <i class="fi fi-rr-camera"></i> <span id="bug-bukti-text">Lampirkan Screenshot</span>
                                    </button>
                                    <input type="file" id="bug-bukti-input" class="hidden" accept="image/*" onchange="handleFileSelect(this, 'bug-bukti-text', 'bug-bukti-data')">
                                    <input type="hidden" id="bug-bukti-data">
                                </div>
                            </div>
                        </div>
                        <div class="flex gap-2 mt-2">
                            <button onclick="cancelHelpForm()" class="flex-1 py-3 text-xs font-bold text-gray-500 hover:bg-gray-100 rounded-xl transition-all">Batal</button>
                            <button onclick="submitHelpRequest('bug_report')" class="flex-[2] py-3 text-xs font-bold text-white bg-red-600 hover:bg-red-700 rounded-xl transition-all shadow-md">Laporkan</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Status -->
            <div class="p-4 bg-white border-t border-gray-100 text-center">
                <p class="text-[10px] text-gray-400">Request Anda akan ditinjau oleh Administrator.</p>
            </div>
        </div>
    </div>

    <script>
        // Chat Toggle Logic
        const helpBtn = document.getElementById('admin-help-btn');
        const helpModal = document.getElementById('admin-help-modal');
        const helpClose = document.getElementById('close-help-modal');
        const helpOverlay = document.getElementById('help-modal-overlay');

        if (helpBtn) {
            helpBtn.onclick = () => {
                helpModal.classList.remove('hidden');
                // Re-check sessionStorage for redirects from presensi
                checkSessionRedirect();
            };
        }

        if (helpClose) helpClose.onclick = () => helpModal.classList.add('hidden');
        if (helpOverlay) helpOverlay.onclick = () => helpModal.classList.add('hidden');

        function showHelpForm(type) {
            const options = document.getElementById('help-options');
            const container = document.getElementById('help-form-container');
            if (options) options.classList.add('hidden');
            if (container) container.classList.remove('hidden');
            
            // Hide all forms first
            ['past_attendance', 'late_attendance', 'bug_report'].forEach(t => {
                const f = document.getElementById('form-' + t);
                if (f) f.classList.add('hidden');
            });
            
            // Show requested form
            const targetForm = document.getElementById('form-' + type);
            if (targetForm) {
                targetForm.classList.remove('hidden');
            } else {
                console.warn('Help form not found:', type);
                return;
            }
            
            // Reset forms
            if (type === 'late_attendance') {
                // Reset new fields
                qs('#late-attendance-type').value = 'wfo';
                qs('#late-reason').value = '';
                toggleLateReason();

                const pending = sessionStorage.getItem('late_req_pending');
                if (pending) {
                    const data = JSON.parse(pending);
                    qs('#late-date').value = data.tanggal;
                    qs('#late-jam-masuk').value = data.jam_masuk;
                    qs('#late-jam-pulang').value = data.jam_pulang;
                    
                    if (sessionStorage.getItem('late_req_face_verified')) {
                        qs('#late-bukti-text').textContent = '✅ Wajah Terverifikasi';
                        qs('#late-bukti-text').classList.add('text-green-600');
                    }
                }
            }
        }

        function cancelHelpForm() {
            document.getElementById('help-options').classList.remove('hidden');
            document.getElementById('help-form-container').classList.add('hidden');
        }

        async function handleFileSelect(input, labelId, hiddenId) {
            const label = document.getElementById(labelId);
            const hidden = document.getElementById(hiddenId);
            const file = input.files[0];
            if (!file) return;

            if (file.size > 2 * 1024 * 1024) {
                showNotif('Ukuran file terlalu besar. Maksimal 2MB.', false);
                input.value = '';
                return;
            }

            label.textContent = "⌛ Memproses...";
            const reader = new FileReader();
            reader.onload = function(e) {
                hidden.value = e.target.result;
                label.textContent = "✅ " + file.name.substring(0, 15) + "...";
            };
            reader.readAsDataURL(file);
        }

        function toggleLateReason() {
            const type = document.getElementById('late-attendance-type').value;
            const container = document.getElementById('late-reason-container');
            if (type === 'wfa' || type === 'overtime') {
                container.classList.remove('hidden');
            } else {
                container.classList.add('hidden');
            }
        }

        async function submitHelpRequest(type) {
            let data = { ajax: 'submit_help_request', request_type: type };

            if (type === 'past_attendance') {
                data.tanggal = qs('#past-date').value;
                data.jenis_izin = qs('#past-type').value;
                data.alasan_izin = qs('#past-reason').value;
                data.bukti_izin = qs('#past-bukti-data').value;
                if (!data.tanggal || !data.alasan_izin || !data.bukti_izin) return showNotif('Lengkapi semua field!', false);
            } else if (type === 'late_attendance') {
                data.tanggal = qs('#late-date').value;
                data.jam_masuk = qs('#late-jam-masuk').value;
                data.jam_pulang = qs('#late-jam-pulang').value;
                
                // New Fields
                data.attendance_type = qs('#late-attendance-type').value;
                data.attendance_reason = qs('#late-reason').value;

                if ((data.attendance_type === 'wfa' || data.attendance_type === 'overtime') && !data.attendance_reason) {
                    return showNotif('Wajib mengisi alasan untuk ' + data.attendance_type.toUpperCase(), false);
                }

                // Priority for Bukti: Session-based face verification first
                const faceVerified = sessionStorage.getItem('late_req_face_verified');
                if (faceVerified) {
                    const verifiedData = JSON.parse(faceVerified);
                    data.bukti_presensi = verifiedData.screenshot;
                    data.lokasi_presensi = verifiedData.lokasi;
                } else {
                    data.bukti_presensi = qs('#late-bukti-data').value;
                    data.lokasi_presensi = "Upload Bukti Manual";
                }
                
                if (!data.tanggal || !data.jam_masuk || !data.bukti_presensi) return showNotif('Lengkapi tanggal, jam masuk, dan bukti wajah!', false);
                if (!data.jam_pulang) data.jam_pulang = null; // Ensure null if empty
            } else if (type === 'bug_report') {
                data.bug_description = qs('#bug-desc').value;
                data.bug_proof = qs('#bug-bukti-data').value;
                if (!data.bug_description) return showNotif('Deskripsi bug wajib diisi!', false);
            }

            try {
                const res = await api('?ajax=submit_help_request', data, { method: 'POST' });
                if (res.ok) {
                    showNotif(res.message, true);
                    // Reset
                    cancelHelpForm();
                    helpModal.classList.add('hidden');
                    
                    // Clear late req session
                    sessionStorage.removeItem('late_req_pending');
                    sessionStorage.removeItem('late_req_face_verified');
                    sessionStorage.removeItem('late_req_redirected');
                    
                    // Add success message to chat bubble history (visual only for current session)
                    addChatMessage("Sistem: Request " + type.replace('_', ' ') + " Anda telah terkirim.");
                } else {
                    showNotif(res.message, false);
                }
            } catch (e) {
                showNotif('Gagal mengirim request: ' + e.message, false);
            }
        }

        function addChatMessage(msg) {
            const chat = document.getElementById('help-chat-content');
            const div = document.createElement('div');
            div.className = "flex flex-col gap-1 items-end";
            div.innerHTML = `
                <div class="bg-blue-50 text-gray-700 p-4 rounded-2xl rounded-tr-none shadow-sm max-w-[85%] text-xs italic">
                    ${msg}
                </div>
                <span class="text-[9px] text-gray-400 px-1">${new Date().getHours()}:${new Date().getMinutes()}</span>
            `;
            chat.appendChild(div);
            chat.scrollTop = chat.scrollHeight;
        }

        function startLatePresensiNow() {
            const tanggal = qs('#late-date').value;
            const jm = qs('#late-jam-masuk').value;
            const jp = qs('#late-jam-pulang').value;
            
            if (!tanggal || !jm) {
                showNotif('Tolong isi tanggal dan jam masuk terlebih dahulu!', false);
                return;
            }

            // Save state to sessionStorage before redirect
            sessionStorage.setItem('late_req_pending', JSON.stringify({
                tanggal: tanggal,
                jam_masuk: jm,
                jam_pulang: jp
            }));
            sessionStorage.setItem('late_req_redirected', 'true');
            
            // Redirect to presensi with special mode
            window.location.href = '?page=presensi-masuk&mode=late_req';
        }

        function checkSessionRedirect() {
            // Check if we just came back from face verification
            if (sessionStorage.getItem('late_req_face_verified')) {
                showHelpForm('late_attendance');
                showNotif('Wajah berhasil diverifikasi!', true);
            } else if (sessionStorage.getItem('late_req_redirected')) {
                // If redirected but didn't finish, still show the form
                showHelpForm('late_attendance');
                sessionStorage.removeItem('late_req_redirected');
            }
        }

        // Initialize session clearing on logout signal (already handled by server-side session_destroy, but for chat UI we reset on every page load since it's hardcoded)
        // For actual persistence during single login session, we could use localStorage, but user requested clear on logout/login.
        // Hardcoded approach in HTML is "Clear on login" by default. 
    </script>

    <style>
        @keyframes bounce-slow {
            0%, 100% { transform: translateY(-5%); animation-timing-function: cubic-bezier(0.8,0,1,1); }
            50% { transform: translateY(0); animation-timing-function: cubic-bezier(0,0,0.2,1); }
        }
        .animate-bounce-slow {
            animation: bounce-slow 2s infinite;
        }
        .z-80 { z-index: 80; }
    </style>
    <?php endif; ?>

    <!-- Robot Cat Components -->
    <link rel="stylesheet" href="<?php echo dirname($_SERVER['PHP_SELF']); ?>/assets/css/robot_cat_animations.css">
    <script src="<?php echo dirname($_SERVER['PHP_SELF']); ?>/assets/js/robot_cat_character.js"></script>
</body>
</html>
