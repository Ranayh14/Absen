    <!-- Mobile Sidebar Overlay -->
    <div id="mobile-sidebar-overlay" class="fixed inset-0 bg-black/50 z-40 hidden md:hidden backdrop-blur-sm transition-all"></div>
    
    <!-- Mobile Sidebar -->
    <div id="mobile-sidebar" class="fixed top-0 left-0 h-full w-72 bg-white shadow-2xl z-50 transform -translate-x-full transition-transform duration-300 md:hidden font-outfit">
        <div class="p-6 border-b border-gray-100 flex items-center justify-between">
            <span class="text-xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-blue-700 to-blue-500">Menu Admin</span>
            <button id="mobile-sidebar-close" class="p-2 hover:bg-gray-50 rounded-full text-gray-500 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <nav class="p-4 space-y-2">
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
        </nav>
    </div>
    
    <header class="bg-white/80 backdrop-blur-md fixed top-0 left-0 right-0 z-30 border-b border-gray-100">
        <div class="w-full px-4 lg:px-6 py-3 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <button id="mobile-menu-toggle" class="md:hidden p-2 hover:bg-gray-100 rounded-xl text-gray-600 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-indigo-600 rounded-lg flex items-center justify-center text-white font-bold">
                        <i class="fi fi-sr-admin-alt text-sm"></i>
                    </div>
                    <h1 class="text-xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-gray-800 to-gray-600 tracking-tight hidden sm:block">SPBW <span class="text-gray-400 font-light">|</span> ADMIN</h1>
                </div>
            </div>
            
            <div class="flex items-center gap-4">
                <!-- Admin Notifications -->
                <div class="relative">
                    <button id="btn-notifications" class="relative p-2 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-xl transition-all group">
                        <i class="fi fi-sr-bell text-xl"></i>
                        <span id="notif-badge" class="absolute top-1 right-1 w-2.5 h-2.5 bg-red-500 border-2 border-white rounded-full hidden"></span>
                    </button>
                    <!-- Notifications Dropdown -->
                    <div id="dropdown-notifications" class="absolute right-0 mt-3 w-80 sm:w-96 bg-white rounded-2xl shadow-xl border border-gray-100 hidden z-50 overflow-hidden animate-fade-in-up">
                        <div class="p-4 border-b border-gray-50 flex items-center justify-between">
                            <h3 class="font-bold text-gray-800">Permintaan Bantuan</h3>
                            <span id="notif-count" class="text-xs bg-indigo-100 text-indigo-600 px-2 py-0.5 rounded-full font-bold">0</span>
                        </div>
                        <div id="notif-items" class="max-h-96 overflow-y-auto p-2 space-y-1">
                            <!-- Items populated by JS -->
                            <div class="p-8 text-center text-gray-400">
                                <i class="fi fi-sr-inbox text-3xl mb-2 block"></i>
                                <p class="text-xs">Tidak ada permintaan baru</p>
                            </div>
                        </div>
                        <div class="p-3 bg-gray-50 border-t border-gray-100">
                            <button data-tab="help-requests" class="tab-link w-full text-center text-xs font-bold text-indigo-600 hover:text-indigo-700 transition-colors uppercase tracking-wider py-2 bg-indigo-50 rounded-full">
                                Lihat Semua Riwayat
                            </button>
                        </div>
                    </div>
                </div>

                <div class="relative">
                    <button id="btn-profile" class="flex items-center gap-3 p-1 pr-4 bg-white border border-gray-200 hover:border-indigo-300 rounded-full transition-all shadow-sm hover:shadow-md group">
                        <img src="generate-avatar.php?background=e0e7ff&color=4f46e5&name=<?php echo urlencode($_SESSION['user']['nama'] ?? 'A'); ?>&size=32" class="avatar w-9 h-9 rounded-full object-cover ring-2 ring-white" alt="profile">
                         <span class="text-sm font-semibold text-gray-700 group-hover:text-indigo-600 transition-colors hidden sm:inline"><?php echo htmlspecialchars($_SESSION['user']['nama'] ?? 'Admin'); ?></span>
                         <i class="fi fi-sr-angle-small-down text-gray-400"></i>
                    </button>
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
        
        <!-- Admin Navigation Tabs - Inside Header -->
        <div class="w-full px-4 lg:px-6 pb-3">
            <div class="flex flex-wrap gap-2 bg-white p-2 rounded-2xl shadow-sm border border-gray-100">
                 <button data-tab="dashboard" class="tab-link flex-1 py-2.5 px-4 rounded-xl font-bold text-sm text-gray-600 hover:bg-indigo-50 hover:text-indigo-600 transition-all flex items-center justify-center gap-2">
                    <i class="fi fi-sr-dashboard"></i> <span class="hidden sm:inline">Dashboard</span>
                 </button>
                 <button data-tab="members" class="tab-link flex-1 py-2.5 px-4 rounded-xl font-bold text-sm text-gray-600 hover:bg-indigo-50 hover:text-indigo-600 transition-all flex items-center justify-center gap-2">
                    <i class="fi fi-sr-users"></i> <span class="hidden sm:inline">Member</span>
                 </button>
                 <button data-tab="laporan" class="tab-link flex-1 py-2.5 px-4 rounded-xl font-bold text-sm text-gray-600 hover:bg-indigo-50 hover:text-indigo-600 transition-all flex items-center justify-center gap-2">
                    <i class="fi fi-sr-document"></i> <span class="hidden sm:inline">Presensi</span>
                 </button>
                 <button data-tab="admin-monthly" class="tab-link flex-1 py-2.5 px-4 rounded-xl font-bold text-sm text-gray-600 hover:bg-indigo-50 hover:text-indigo-600 transition-all flex items-center justify-center gap-2">
                    <i class="fi fi-sr-calendar"></i> <span class="hidden sm:inline">Laporan</span>
                 </button>
                 <button data-tab="settings" class="tab-link flex-1 py-2.5 px-4 rounded-xl font-bold text-sm text-gray-600 hover:bg-indigo-50 hover:text-indigo-600 transition-all flex items-center justify-center gap-2">
                    <i class="fi fi-sr-settings"></i> <span class="hidden sm:inline">Settings</span>
                  </button>
                  <button data-tab="help-requests" class="tab-link flex-1 py-2.5 px-4 rounded-xl font-bold text-sm text-gray-600 hover:bg-indigo-50 hover:text-indigo-600 transition-all flex items-center justify-center gap-2">
                    <i class="fi fi-sr-interrogation"></i> <span class="hidden sm:inline">Requests</span>
                  </button>
            </div>
        </div>
    </header>

    
    <main class="w-full px-2 sm:px-4 lg:px-6 py-8 overflow-x-auto pt-32">
        <style>
            .tab-link.active-tab {
                background-color: #4f46e5 !important;
                color: white !important;
                box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2), 0 2px 4px -1px rgba(79, 70, 229, 0.1);
            }
            .tab-link.active-tab * {
                color: white !important;
            }
            .tab-link:not(.active-tab):hover {
                background-color: #eef2ff;
                color: #4f46e5;
            }
        </style>

        <?php if (isAdmin()): ?>
            <?php require 'kelola_member.php'; ?>
            <?php require 'data_presensi.php'; ?>
            <?php require 'laporan_bulanan_admin.php'; ?>
            <?php require 'settings.php'; ?>
            <?php require 'dashboard.php'; ?>
            <?php require 'admin_requests.php'; ?>
        <?php endif; ?>
    </main>

    <!-- Modal Tambah/Edit Member -->
    <div id="member-modal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-40 hidden">
        <div class="bg-white p-8 rounded-lg shadow-2xl w-full max-w-md">
            <h2 id="modal-title" class="text-2xl font-bold mb-6">Tambah Member Baru</h2>
            <form id="member-form">
                <input type="hidden" id="member-id">
                <input type="hidden" id="foto-data-url">
                <div class="mb-4">
                    <label class="block text-gray-700">Email</label>
                    <input type="email" id="email" class="w-full p-2 border rounded-lg">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700">NIM</label>
                    <input type="text" id="nim" class="w-full p-2 border rounded-lg" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700">Nama Lengkap</label>
                    <input type="text" id="nama" class="w-full p-2 border rounded-lg" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700">Program Studi</label>
                    <input type="text" id="prodi" class="w-full p-2 border rounded-lg" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700">Nama Startup</label>
                    <input type="text" id="startup" class="w-full p-2 border rounded-lg">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2">Foto Wajah</label>
                    <div id="modal-video-container" class="relative bg-gray-200 rounded-lg w-full aspect-video mb-2 hidden">
                        <video id="modal-video" autoplay playsinline class="w-full h-full object-cover rounded-lg"></video>
                    </div>
                    <canvas id="modal-canvas" class="hidden"></canvas>
                    <img id="foto-preview" class="mt-2 h-32 w-32 object-cover rounded-lg hidden mx-auto mb-2">
                    <div class="grid grid-cols-2 gap-2 mb-2">
                        <button type="button" id="btn-start-camera" class="bg-indigo-500 hover:bg-indigo-600 text-white font-bold py-2 px-4 rounded-lg transition">Buka Kamera</button>
                        <button type="button" id="btn-upload-photo" class="bg-purple-500 hover:bg-purple-600 text-white font-bold py-2 px-4 rounded-lg transition">Upload Foto</button>
                    </div>
                    <input type="file" id="photo-file-input" accept="image/*" class="hidden">
                    <button type="button" id="btn-take-photo" class="w-full bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-lg hidden transition">Ambil Foto</button>
                </div>
                <div id="password-admin-wrapper" class="grid grid-cols-2 gap-2 hidden">
                    <input type="password" id="password-new" placeholder="Password" class="p-2 border rounded-lg">
                    <input type="password" id="password-confirm" placeholder="Konfirmasi" class="p-2 border rounded-lg">
                </div>
                <div class="flex justify-end space-x-4 mt-6">
                    <button type="button" id="btn-cancel-modal" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded-lg">Batal</button>
                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-lg">Simpan</button>
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
    <div id="edit-att-modal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 hidden p-2 sm:p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full mx-2 sm:mx-0 sm:max-w-md" style="max-height: 90vh; overflow-y: auto;">
            <!-- Modal Header -->
            <div class="sticky top-0 bg-gradient-to-r from-indigo-600 to-indigo-700 text-white px-4 sm:px-6 py-3 sm:py-4 rounded-t-2xl flex items-center justify-between z-10">
                <h3 class="text-base sm:text-lg font-bold flex items-center gap-2">
                    <i class="fi fi-rr-edit text-sm sm:text-base"></i>
                    <span class="text-sm sm:text-base">Edit Data Kehadiran</span>
                </h3>
                <button type="button" id="edit-att-cancel" class="text-white/80 hover:text-white hover:bg-white/20 rounded-full p-1.5 transition-all">
                    <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            
            <!-- Modal Body -->
            <form id="edit-att-form" class="p-4 sm:p-6 space-y-3 sm:space-y-4">
                <input type="hidden" id="edit-att-id">
                <input type="hidden" id="edit-att-user-id">
                <input type="hidden" id="edit-att-screenshot-masuk-data">
                <input type="hidden" id="edit-att-screenshot-pulang-data">
                
                <!-- Tanggal -->
                <div>
                    <label class="block text-xs sm:text-sm font-semibold text-gray-700 mb-1.5 sm:mb-2">Tanggal</label>
                    <input type="date" id="edit-att-date" class="w-full px-3 sm:px-4 py-2 sm:py-2.5 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all text-xs sm:text-sm" disabled>
                </div>
                
                <!-- Nama -->
                <div>
                    <label class="block text-xs sm:text-sm font-semibold text-gray-700 mb-1.5 sm:mb-2">Nama</label>
                    <input type="text" id="edit-att-nama" class="w-full px-3 sm:px-4 py-2 sm:py-2.5 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all text-xs sm:text-sm" disabled>
                </div>
                
                <!-- Jam Masuk -->
                <div>
                    <label class="block text-xs sm:text-sm font-semibold text-gray-700 mb-1.5 sm:mb-2">Jam Masuk</label>
                    <div class="flex gap-2">
                        <input type="time" id="edit-att-jam-masuk" class="flex-1 px-3 sm:px-4 py-2 sm:py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all text-xs sm:text-sm">
                        <button type="button" id="edit-att-upload-masuk" class="bg-blue-500 hover:bg-blue-600 text-white px-3 sm:px-4 py-2 sm:py-2.5 rounded-xl text-xs sm:text-sm font-semibold transition-all shadow-sm hover:shadow-md whitespace-nowrap">
                            <i class="fi fi-rr-upload"></i>
                            <span class="hidden sm:inline ml-1">Bukti</span>
                        </button>
                    </div>
                    <div id="edit-att-screenshot-masuk-preview" class="mt-2 sm:mt-3 hidden">
                        <img id="edit-att-screenshot-masuk-img" src="" alt="Screenshot Masuk" class="w-full h-32 sm:h-40 object-cover rounded-xl border-2 border-gray-200">
                        <button type="button" id="edit-att-remove-masuk" class="mt-1.5 sm:mt-2 text-red-600 text-xs sm:text-sm font-semibold hover:text-red-700 flex items-center gap-1">
                            <i class="fi fi-rr-trash"></i> Hapus Bukti
                        </button>
                    </div>
                </div>
                
                <!-- Jam Pulang -->
                <div>
                    <label class="block text-xs sm:text-sm font-semibold text-gray-700 mb-1.5 sm:mb-2">Jam Pulang</label>
                    <div class="flex gap-2">
                        <input type="time" id="edit-att-jam-pulang" class="flex-1 px-3 sm:px-4 py-2 sm:py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all text-xs sm:text-sm">
                        <button type="button" id="edit-att-upload-pulang" class="bg-blue-500 hover:bg-blue-600 text-white px-3 sm:px-4 py-2 sm:py-2.5 rounded-xl text-xs sm:text-sm font-semibold transition-all shadow-sm hover:shadow-md whitespace-nowrap">
                            <i class="fi fi-rr-upload"></i>
                            <span class="hidden sm:inline ml-1">Bukti</span>
                        </button>
                    </div>
                    <div id="edit-att-screenshot-pulang-preview" class="mt-2 sm:mt-3 hidden">
                        <img id="edit-att-screenshot-pulang-img" src="" alt="Screenshot Pulang" class="w-full h-32 sm:h-40 object-cover rounded-xl border-2 border-gray-200">
                        <button type="button" id="edit-att-remove-pulang" class="mt-1.5 sm:mt-2 text-red-600 text-xs sm:text-sm font-semibold hover:text-red-700 flex items-center gap-1">
                            <i class="fi fi-rr-trash"></i> Hapus Bukti
                        </button>
                    </div>
                </div>
                
                <!-- Keterangan -->
                <div>
                    <label class="block text-xs sm:text-sm font-semibold text-gray-700 mb-1.5 sm:mb-2">Keterangan</label>
                    <select id="edit-att-ket" class="w-full px-3 sm:px-4 py-2 sm:py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all text-xs sm:text-sm font-medium">
                        <option value="wfo">WFO</option>
                        <option value="izin">Izin</option>
                        <option value="sakit">Sakit</option>
                        <option value="alpha">Alpha</option>
                        <option value="wfa">WFA</option>
                        <option value="overtime">Overtime</option>
                    </select>
                </div>
                
                <!-- WFA Form -->
                <div id="edit-att-wfa-form" class="hidden bg-blue-50 p-3 sm:p-4 rounded-xl border border-blue-100">
                    <label class="block text-xs sm:text-sm font-semibold text-gray-700 mb-1.5 sm:mb-2">Alasan WFA</label>
                    <textarea id="edit-att-alasan-wfa" class="w-full px-3 sm:px-4 py-2 sm:py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all text-xs sm:text-sm resize-none" rows="3" placeholder="Tulis alasan WFA..."></textarea>
                </div>
                
                <!-- Overtime Form -->
                <div id="edit-att-overtime-form" class="hidden bg-orange-50 p-3 sm:p-4 rounded-xl border border-orange-100 space-y-2 sm:space-y-3">
                    <div>
                        <label class="block text-xs sm:text-sm font-semibold text-gray-700 mb-1.5 sm:mb-2">Alasan Overtime</label>
                        <textarea id="edit-att-alasan-overtime" class="w-full px-3 sm:px-4 py-2 sm:py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all text-xs sm:text-sm resize-none" rows="3" placeholder="Tulis alasan overtime..."></textarea>
                    </div>
                    <div>
                        <label class="block text-xs sm:text-sm font-semibold text-gray-700 mb-1.5 sm:mb-2">Lokasi Overtime</label>
                        <input type="text" id="edit-att-lokasi-overtime" class="w-full px-3 sm:px-4 py-2 sm:py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all text-xs sm:text-sm" placeholder="Tulis lokasi overtime...">
                    </div>
                </div>
                
                <!-- Status -->
                <div>
                    <label class="block text-xs sm:text-sm font-semibold text-gray-700 mb-1.5 sm:mb-2">Status</label>
                    <select id="edit-att-status" class="w-full px-3 sm:px-4 py-2 sm:py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all text-xs sm:text-sm font-medium">
                        <option value="ontime">On Time</option>
                        <option value="terlambat">Terlambat</option>
                    </select>
                </div>
                
                <!-- Add Report Button -->
                <button type="button" id="edit-att-add-report" class="w-full bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white px-4 py-2.5 sm:py-3 rounded-xl font-semibold transition-all shadow-md hover:shadow-lg flex items-center justify-center gap-2 text-xs sm:text-sm">
                    <i class="fi fi-rr-document-signed"></i>
                    Tambahkan Laporan
                </button>
                
                <!-- Action Buttons -->
                <div class="flex gap-2 sm:gap-3 pt-3 sm:pt-4 border-t border-gray-200">
                    <button type="button" id="edit-att-cancel-btn" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 sm:px-4 py-2.5 sm:py-3 rounded-xl font-semibold transition-all text-xs sm:text-sm">
                        Batal
                    </button>
                    <button type="submit" id="edit-att-save" class="flex-1 bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-700 hover:to-indigo-800 text-white px-3 sm:px-4 py-2.5 sm:py-3 rounded-xl font-semibold transition-all shadow-md hover:shadow-lg text-xs sm:text-sm">
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


    <!-- Modal Pilihan Export Data Presensi -->
    <div id="export-presensi-modal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden">
        <div class="bg-white p-6 rounded-lg shadow-2xl w-full max-w-md">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold">Export Data Presensi</h3>
                <button onclick="closeExportDailyModal()" class="text-gray-500 hover:text-gray-700 text-2xl">✕</button>
            </div>
            <form id="export-presensi-form" method="GET" action="?">
                <input type="hidden" name="ajax" value="export_daily">
                <input type="hidden" name="filter_type" id="export-p-filter-type-fallback" value="period">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Periode Data</label>
                    <select id="export-p-range" class="w-full p-2 border border-gray-300 rounded-lg" onchange="const opts=document.getElementById('export-p-monthly-opts'); const fallback=document.getElementById('export-p-filter-type-fallback'); fallback.value=this.value; if(this.value==='monthly') { opts.style.display='block'; opts.classList.remove('hidden'); } else { opts.style.display='none'; opts.classList.add('hidden'); }">
                        <option value="period">Seluruh Periode (Default)</option>
                        <option value="monthly">Bulan Tertentu</option>
                    </select>
                </div>
                
                <div id="export-p-monthly-opts" class="mb-4 hidden p-3 border rounded-lg bg-gray-50">
                    <div class="flex justify-between items-center mb-3">
                        <label class="block text-sm font-bold text-gray-800">Pilih Bulan & Tahun</label>
                        <select id="export-p-year" name="year" class="p-1 border rounded text-xs bg-white focus:ring-1 focus:ring-indigo-500" onchange="updateMonthLabels(this.value)">
                            <?php for($y=2024;$y<=2026;$y++) echo "<option value='$y'".($y==date('Y')?' selected':'').">$y</option>"; ?>
                        </select>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <?php 
                        $m_names = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
                        for($i=1;$i<=12;$i++): ?>
                            <label class="flex items-center space-x-2 text-xs p-1.5 border bg-white rounded shadow-sm hover:border-indigo-400 cursor-pointer transition-colors month-item">
                                <input type="checkbox" name="export_months[]" value="<?= $i ?>" <?= ($i==date('n')?'checked':'') ?> class="export-month-cb w-3 h-3 text-indigo-600 rounded">
                                <span class="month-label text-gray-700"><?= $m_names[$i] ?> <span class="text-[9px] text-gray-400 year-suffix"><?= date('Y') ?></span></span>
                            </label>
                        <?php endfor; ?>
                    </div>
                    <div class="mt-3 flex justify-between items-center px-1">
                        <button type="button" onclick="qsa('.export-month-cb').forEach(cb=>cb.checked=true)" class="text-[10px] text-indigo-600 hover:font-bold transition-all">Select All</button>
                        <button type="button" onclick="qsa('.export-month-cb').forEach(cb=>cb.checked=false)" class="text-[10px] text-red-500 hover:font-bold transition-all">Clear All</button>
                    </div>
                </div>
                <script>
                function updateMonthLabels(year) {
                    qsa('.year-suffix').forEach(el => el.textContent = year);
                }
                </script>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Format Tampilan</label>
                    <div class="space-y-2">
                        <label class="flex items-center space-x-2 p-2 border rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="radio" name="export_format" value="combined" checked>
                            <span class="text-sm">Combined (Satu sheet untuk semua pegawai)</span>
                        </label>
                        <label class="flex items-center space-x-2 p-2 border rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="radio" name="export_format" value="per_employee">
                            <span class="text-sm">Per Pegawai (Satu sheet per pegawai)</span>
                        </label>
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <button type="button" onclick="closeExportDailyModal()" class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded">Batal</button>
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded">Download Excel</button>
                </div>
            </form>
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

