<div id="page-dashboard" class="hidden">
    <div class="bg-white/90 backdrop-blur-xl p-6 md:p-8 rounded-2xl shadow-sm border border-slate-200 relative overflow-hidden">
        <h2 class="text-xl font-bold mb-6">Dashboard Presensi</h2>

        <!-- Summary Cards -->
        <div class="grid md:grid-cols-4 gap-4 mb-6">
            <div class="bg-blue-50 p-4 rounded-lg text-center">
                <div class="text-2xl font-bold text-blue-600" id="totalEmployees">0</div>
                <div class="text-sm text-blue-700">Total Pegawai</div>
            </div>
            <div class="bg-green-50 p-4 rounded-lg text-center">
                <div class="text-2xl font-bold text-green-600" id="presentToday">0</div>
                <div class="text-sm text-green-700">Hadir Hari Ini</div>
            </div>
            <div class="bg-red-50 p-4 rounded-lg text-center">
                <div class="text-2xl font-bold text-red-600" id="lateToday">0</div>
                <div class="text-sm text-red-700">Terlambat Hari Ini</div>
            </div>
            <div class="bg-yellow-50 p-4 rounded-lg text-center">
                <div class="text-2xl font-bold text-yellow-600" id="absentToday">0</div>
                <div class="text-sm text-yellow-700">Tidak Hadir</div>
            </div>
        </div>
        
        <!-- Daily Report Statistics -->
        <div class="mb-8">
            <div class="bg-gradient-to-r from-orange-50 to-amber-50 border border-orange-200 rounded-lg p-6 shadow-md">
                <h3 class="text-lg font-semibold mb-4 text-orange-800 flex items-center">
                    <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Statistik Laporan Harian
                </h3>
                <div class="grid md:grid-cols-2 gap-4 mb-4">
                    <div class="bg-white rounded-lg p-4 border border-orange-200">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600 mb-1">Pegawai Belum Isi Laporan</p>
                                <p class="text-3xl font-bold text-orange-600" id="employeesWithoutReports">0</p>
                                <p class="text-xs text-gray-500 mt-1">orang</p>
                            </div>
                            <div class="bg-orange-100 rounded-full p-4">
                                <svg class="w-8 h-8 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg p-4 border border-orange-200">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600 mb-1">Total Laporan Belum Diisi</p>
                                <p class="text-3xl font-bold text-amber-600" id="totalMissingReports">0</p>
                                <p class="text-xs text-gray-500 mt-1">laporan</p>
                            </div>
                            <div class="bg-amber-100 rounded-full p-4">
                                <svg class="w-8 h-8 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Employee List -->
                <div id="daily-report-employees-list" class="mt-4">
                    <!-- Employee list will be populated here -->
                </div>
                <p class="text-xs text-orange-700 mt-4 text-center">
                    <span class="font-semibold">Catatan:</span> Data dihitung untuk seluruh periode kerja pegawai
                </p>
            </div>
        </div>
        
        <!-- Today's Late Employees -->
        <div class="mb-8">
            <h3 class="text-lg font-semibold mb-4 text-gray-700">Pegawai Terlambat Hari Ini</h3>
            <div class="bg-gray-50 p-4 rounded-lg">
                <canvas id="todayLateChart" width="400" height="200"></canvas>
            </div>
        </div>

        <!-- KPI Absen Section -->
        <div class="mb-8">
            <h3 class="text-lg font-semibold mb-4 text-gray-700">Penilaian KPI Absen</h3>
            <div class="bg-white p-4 rounded-lg shadow-sm">
                <div class="mb-4">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4">
                        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
                            <span class="text-sm text-gray-600">Periode: <span id="kpi-period-range"></span></span>
                            <div class="flex items-center gap-2">
                                <label class="text-sm text-gray-600">Filter:</label>
                                <select id="kpi-filter-type" class="px-3 py-1 border border-gray-300 rounded text-sm">
                                    <option value="period">Periode Lengkap</option>
                                    <option value="monthly">Per Bulan</option>
                                </select>
                                <select id="kpi-filter-month" class="px-3 py-1 border border-gray-300 rounded text-sm hidden">
                                    <option value="">Pilih Bulan</option>
                                </select>
                                <select id="kpi-filter-year" class="px-3 py-1 border border-gray-300 rounded text-sm hidden">
                                    <option value="">Pilih Tahun</option>
                                </select>
                            </div>
                        </div>
                        <button id="refresh-kpi" class="px-3 py-1 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700 transition">
                            Refresh
                        </button>
                        <button id="btn-export-kpi" class="px-3 py-1 bg-green-600 text-white text-sm rounded hover:bg-green-700 transition ml-2">
                            <i class="fi fi-sr-file-excel"></i> Export Excel
                        </button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-700">No</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-700">Nama Pegawai</th>
                                <th class="px-4 py-3 text-center font-medium text-gray-700">Total Hari Kerja</th>
                                <th class="px-4 py-3 text-center font-medium text-gray-700">Ontime</th>
                                <th class="px-4 py-3 text-center font-medium text-gray-700">WFA</th>
                                <th class="px-4 py-3 text-center font-medium text-gray-700">Terlambat</th>
                                <th class="px-4 py-3 text-center font-medium text-gray-700">Izin/Sakit</th>
                                <th class="px-4 py-3 text-center font-medium text-gray-700">Alpha</th>
                                <th class="px-4 py-3 text-center font-medium text-gray-700">Overtime</th>
                                <th class="px-4 py-3 text-center font-medium text-gray-700">Laporan Belum Diisi</th>
                                <th class="px-4 py-3 text-center font-medium text-gray-700">KPI Score</th>
                                <th class="px-4 py-3 text-center font-medium text-gray-700">Status</th>
                            </tr>
                        </thead>
                        <tbody id="kpi-table-body" class="divide-y divide-gray-200">
                            <!-- Data will be populated here -->
                        </tbody>
                    </table>
                </div>
                <div id="kpi-loading" class="text-center py-8 text-gray-500">
                    <div class="inline-block animate-spin rounded-full h-6 w-6 border-b-2 border-indigo-600"></div>
                    <p class="mt-2">Memuat data KPI...</p>
                </div>
                <div id="kpi-empty" class="text-center py-8 text-gray-500 hidden">
                    <p>Tidak ada data KPI untuk ditampilkan</p>
                </div>
            </div>
        </div>

        <!-- Attendance Trend Chart -->
        <div class="mb-8">
            <h3 class="text-lg font-semibold mb-4 text-gray-700">Tren Kejadian Kehadiran 1 Periode</h3>
            <div class="bg-white p-4 rounded-lg shadow-sm">
                <canvas id="attendanceTrendChart" width="400" height="200"></canvas>
            </div>
        </div>                

        <!-- Monthly Attendance Performance -->
        <div class="mb-8">
            <h3 class="text-lg font-semibold mb-4 text-gray-700">Performa Kehadiran Bulan Ini</h3>
            <div class="grid md:grid-cols-2 gap-6">
                <!-- Most Frequently Late -->
                <div class="bg-red-50 p-4 rounded-lg">
                    <h4 class="text-md font-semibold mb-3 text-red-700">Paling Sering Terlambat</h4>
                    <canvas id="mostLateChart" width="300" height="200"></canvas>
                </div>
                
                <!-- Most Attentive -->
                <div class="bg-green-50 p-4 rounded-lg">
                    <h4 class="text-md font-semibold mb-3 text-green-700">Paling Rajin</h4>
                    <canvas id="mostAttentiveChart" width="300" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>
