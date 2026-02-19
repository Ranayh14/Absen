<?php
/**
 * Common Modals used in both Admin and Pegawai Dashboards
 */
?>

<!-- Modal Edit Kehadiran -->
<div id="edit-att-modal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 hidden p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md max-h-[90vh] overflow-y-auto">
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

<!-- Modal QR Code Google Authenticator -->
<div id="ga-qr-modal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden p-4">
    <div class="bg-white p-6 sm:p-8 rounded-lg shadow-2xl w-full max-w-md">
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

<!-- Modal Laporan Harian Admin -->
<div id="admin-daily-report-modal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden p-4">
    <div class="bg-white p-6 rounded-lg shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
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
<div id="confirm-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-70 hidden p-4">
    <div class="bg-white p-6 sm:p-8 rounded-lg shadow-2xl w-full max-w-sm text-center">
        <p id="confirm-modal-message" class="text-lg mb-6">Apakah Anda yakin?</p>
        <div class="flex justify-center space-x-4">
            <button id="btn-confirm-no" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-6 rounded-lg">Tidak</button>
            <button id="btn-confirm-yes" class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-6 rounded-lg">Ya</button>
        </div>
    </div>
</div>

<!-- Ket Detail Modal -->
<div id="ket-detail-modal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden p-4">
    <div class="bg-white p-6 rounded-lg shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
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
