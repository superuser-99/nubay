<?php
require_once '../../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

// Get today's statistics - ADD APPROVAL STATUS FILTER
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

$stats = [
    'hadir' => 0,
    'sakit' => 0,
    'izin' => 0,
    'Telat' => 0,
    'alpha' => 0
];

// Get today's counts - ADD APPROVAL STATUS FILTER
$sql = "SELECT status, COUNT(*) as count FROM absensi 
        WHERE tanggal = :today 
        AND approval_status = 'Approved'
        GROUP BY status";
$stmt = $conn->prepare($sql);
$stmt->execute(['today' => $today]);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $stats[strtolower($row['status'])] = $row['count'];
}

// Get yesterday's counts for comparison - ADD APPROVAL STATUS FILTER
$yesterday_stats = [
    'hadir' => 0,
    'sakit' => 0,
    'izin' => 0,
    'Telat' => 0,
    'alpha' => 0
];

$sql = "SELECT status, COUNT(*) as count FROM absensi 
        WHERE tanggal = :yesterday 
        AND approval_status = 'Approved'
        GROUP BY status";
$stmt = $conn->prepare($sql);
$stmt->execute(['yesterday' => $yesterday]);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $yesterday_stats[strtolower($row['status'])] = $row['count'];
}

// Calculate percentage changes
$percentage_changes = [];
foreach ($stats as $status => $count) {
    if ($yesterday_stats[$status] > 0) {
        $change = (($count - $yesterday_stats[$status]) / $yesterday_stats[$status]) * 100;
        $percentage_changes[$status] = round($change);
    } else if ($count > 0) {
        // If yesterday was 0 but today has data, it's a 100% increase
        $percentage_changes[$status] = 100;
    } else {
        // No change if both are 0
        $percentage_changes[$status] = 0;
    }
}

// Get weekly statistics - ADD APPROVAL STATUS FILTER
$sql = "SELECT 
            DATE(tanggal) as date,
            MIN(DATE_FORMAT(tanggal, '%d %b')) as date_label,
            status,
            COUNT(*) as count
        FROM absensi
        WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        AND approval_status = 'Approved'
        GROUP BY DATE(tanggal), status
        ORDER BY date ASC";
$stmt = $conn->query($sql);
$weeklyStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get notifications
$sql = "SELECT a.id, s.nama_lengkap, s.foto_profil, a.status, a.created_at, a.bukti_foto, a.keterangan
        FROM absensi a
        JOIN siswa s ON a.siswa_id = s.id
        WHERE a.approval_status = 'Pending'
        ORDER BY a.created_at DESC
        LIMIT 5";
$notifications = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Get recent activities
$sql = "SELECT al.*, COALESCE(a.nama_lengkap, s.nama_lengkap, 'System') as user_name,
        COALESCE(a.foto_profil, s.foto_profil, 'assets/default/photo-profile.png') as user_photo,
        DATE_FORMAT(al.created_at, '%H:%i') as time
        FROM activity_log al
        LEFT JOIN admin a ON al.user_type = 'admin' AND al.user_id = a.id
        LEFT JOIN siswa s ON al.user_type = 'siswa' AND al.user_id = s.id
        ORDER BY al.created_at DESC LIMIT 10";
$activities = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Get pending approvals count
$sql = "SELECT COUNT(*) as pending FROM absensi WHERE approval_status = 'Pending'";
$pending = $conn->query($sql)->fetch(PDO::FETCH_ASSOC)['pending'];

// Get total students count
$sql = "SELECT COUNT(*) as total FROM siswa";
$total_students = $conn->query($sql)->fetch(PDO::FETCH_ASSOC)['total'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - SMA Informatika Nurul Bayan</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .glass-effect {
            background: rgba(17, 24, 39, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(147, 51, 234, 0.3);
        }

        .menu-active {
            background: linear-gradient(to right, rgba(147, 51, 234, 0.2), rgba(147, 51, 234, 0.05));
            border-left: 4px solid #9333ea;
        }

        /* Add gradient background */
        body {
            background: linear-gradient(135deg, #0F172A 0%, #1E1B4B 100%);
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-in-up {
            animation: fadeInUp 0.3s ease-out forwards;
        }

        /* Mobile responsive styles */
        .sidebar-transition {
            transition: transform 0.3s ease-in-out;
        }

        .mobile-overlay {
            background-color: rgba(0, 0, 0, 0.5);
            transition: opacity 0.3s ease-in-out;
        }

        /* Hide scrollbar */
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }

        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        /* Custom scrollbar styling */
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: rgba(31, 41, 55, 0.5);
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(147, 51, 234, 0.5);
            border-radius: 3px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: rgba(147, 51, 234, 0.7);
        }

        /* Touch-friendly adjustments */
        @media (max-width: 768px) {
            .touch-padding {
                padding-top: 0.75rem;
                padding-bottom: 0.75rem;
            }

            .notification-panel-mobile {
                max-height: 80vh;
                width: 92%;
                margin: 0 auto;
                top: 4rem;
                left: 4%;
                right: 4%;
            }
        }
    </style>
</head>

<body class="min-h-screen text-white bg-fixed">
    <!-- Mobile Overlay - only visible when sidebar is open on mobile -->
    <div id="mobile-overlay" class="fixed inset-0 bg-black/50 z-40 lg:hidden hidden" onclick="toggleSidebar()"></div>

    <!-- Side Navigation -->
    <aside id="sidebar" class="fixed top-0 left-0 h-screen w-64 glass-effect border-r border-purple-900/30 z-50 sidebar-transition -translate-x-full lg:translate-x-0">
        <div class="flex items-center justify-between p-4 lg:p-6 border-b border-purple-900/30">
            <div class="flex items-center gap-3">
                <img src="../../assets/default/logo-sma.png" alt="SMA" class="h-8 lg:h-10 w-auto">
                <div>
                    <h1 class="font-semibold text-sm lg:text-base">SMA NB</h1>
                    <p class="text-xs text-gray-400">Sistem Absensi</p>
                </div>
            </div>
            <!-- Close sidebar button - only visible on mobile -->
            <button class="text-gray-400 hover:text-white lg:hidden" onclick="toggleSidebar()">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <nav class="p-4 space-y-2 overflow-y-auto no-scrollbar" style="max-height: calc(100vh - 76px);">
            <a href="index.php" class="flex items-center gap-3 text-white/90 p-3 rounded-lg menu-active">
                <i class="fas fa-home text-purple-500"></i>
                <span>Dashboard</span>
            </a>
            <a href="../absensi/" class="flex items-center gap-3 text-gray-400 p-3 rounded-lg hover:bg-purple-500/10 transition-colors">
                <i class="fas fa-calendar-check"></i>
                <span>Absensi</span>
            </a>
            <a href="../siswa/" class="flex items-center gap-3 text-gray-400 p-3 rounded-lg hover:bg-purple-500/10 transition-colors">
                <i class="fas fa-users"></i>
                <span>Data Siswa</span>
            </a>
            <a href="../laporan/" class="flex items-center gap-3 text-gray-400 p-3 rounded-lg hover:bg-purple-500/10 transition-colors">
                <i class="fas fa-file-alt"></i>
                <span>Laporan</span>
            </a>
            <a href="../profil/" class="flex items-center gap-3 text-gray-400 p-3 rounded-lg hover:bg-purple-500/10 transition-colors">
                <i class="fas fa-user-cog"></i>
                <span>Profil</span>
            </a>
            <a href="../logout.php" class="flex items-center gap-3 text-gray-400 p-3 rounded-lg hover:bg-red-500/10 hover:text-red-500 transition-colors">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="lg:ml-64 min-h-screen bg-gradient-to-br from-gray-900 to-gray-800">
        <!-- Mobile Header -->
        <div class="lg:hidden bg-gray-900/60 backdrop-blur-lg sticky top-0 z-30 px-4 py-3 flex items-center justify-between border-b border-purple-900/30">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="text-white p-2 -ml-2 rounded-lg hover:bg-gray-800/60" aria-label="Menu">
                    <i class="fas fa-bars text-lg"></i>
                </button>
                <img src="../../assets/default/logo-sma.png" alt="SMA INFORMATIKA NURUL BAYAN" class="h-8 w-auto">
            </div>
            <div class="flex items-center gap-3">
                <span id="current-time-mobile" class="text-sm font-medium hidden sm:block"></span>
               
                <!-- User photo -->
                <img src="../../<?= $_SESSION['admin_photo'] ?: '../../assets/default/photo-profile.png' ?>"
                    alt="Admin" class="h-8 w-8 rounded-full object-cover border border-purple-500/50">
            </div>
        </div>

        <div class="p-4 md:p-8">
            <div class="max-w-7xl mx-auto">
                <!-- Header - Now responsive -->
                <header class="flex flex-wrap justify-between items-center mb-6 lg:mb-8">
                    <div class="w-full sm:w-auto mb-4 sm:mb-0">
                        <h1 class="text-xl md:text-2xl font-bold">Dashboard</h1>
                        <p class="text-gray-400">Overview sistem absensi</p>
                    </div>
                    <div class="flex items-center gap-4">
                    
                        <div class="hidden lg:flex items-center gap-3 px-4 py-2 rounded-lg glass-effect">
                            <img src="../../<?= $_SESSION['admin_photo'] ?: '../../assets/default/photo-profile.png' ?>"
                                alt="Admin" class="h-8 w-8 rounded-full object-cover">
                            <span class="text-sm"><?= $_SESSION['admin_name'] ?></span>
                        </div>
                    </div>
                </header>


                <!-- Statistics Grid - Now responsive with smaller screens support -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 md:gap-6 mb-6 md:mb-8">
                    <!-- Hadir Card -->
                    <div class="glass-effect rounded-xl p-4 md:p-6 hover:bg-purple-900/10 transition-all duration-300 transform hover:scale-[1.02] hover:shadow-lg hover:shadow-green-800/10 cursor-pointer" data-stat="hadir">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-gray-400 text-sm font-medium mb-1">Total Hadir</p>
                                <h3 class="text-2xl font-bold stat-value"><?= $stats['hadir'] ?></h3>
                            </div>
                            <div class="h-10 w-10 md:h-12 md:w-12 rounded-xl bg-gradient-to-br from-green-500/30 to-green-700/30 flex items-center justify-center shadow-md">
                                <i class="fas fa-check text-green-400 text-lg"></i>
                            </div>
                        </div>
                        <div class="mt-3 md:mt-4 text-sm stat-change">
                            <?php if ($percentage_changes['hadir'] > 0): ?>
                                <!-- Positive change - green -->
                                <span class="flex items-center">
                                    <i class="fas fa-arrow-up text-green-400 mr-1"></i>
                                    <span class="text-green-400">+<?= abs($percentage_changes['hadir']) ?>% dari kemarin</span>
                                </span>
                            <?php elseif ($percentage_changes['hadir'] < 0): ?>
                                <!-- Negative change - red -->
                                <span class="flex items-center">
                                    <i class="fas fa-arrow-down text-red-400 mr-1"></i>
                                    <span class="text-red-400">-<?= abs($percentage_changes['hadir']) ?>% dari kemarin</span>
                                </span>
                            <?php else: ?>
                                <span class="flex items-center">
                                    <i class="fas fa-minus text-gray-400 mr-1"></i>
                                    <span class="text-gray-400">Sama dengan kemarin</span>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Sakit Card -->
                    <div class="glass-effect rounded-xl p-4 md:p-6 hover:bg-purple-900/10 transition-all duration-300 transform hover:scale-[1.02] hover:shadow-lg hover:shadow-yellow-800/10 cursor-pointer" data-stat="sakit">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-gray-400 text-sm font-medium mb-1">Total Sakit</p>
                                <h3 class="text-2xl font-bold stat-value"><?= $stats['sakit'] ?></h3>
                            </div>
                            <div class="h-10 w-10 md:h-12 md:w-12 rounded-xl bg-gradient-to-br from-yellow-500/30 to-yellow-700/30 flex items-center justify-center shadow-md">
                                <i class="fas fa-hospital text-yellow-400 text-lg"></i>
                            </div>
                        </div>
                        <div class="mt-3 md:mt-4 text-sm stat-change">
                            <?php if ($percentage_changes['sakit'] > 0): ?>
                                <span class="flex items-center">
                                    <i class="fas fa-arrow-up text-green-400 mr-1"></i>
                                    <span class="text-green-400">+<?= abs($percentage_changes['sakit']) ?>% dari kemarin</span>
                                </span>
                            <?php elseif ($percentage_changes['sakit'] < 0): ?>
                                <span class="flex items-center">
                                    <i class="fas fa-arrow-down text-red-400 mr-1"></i>
                                    <span class="text-red-400">-<?= abs($percentage_changes['sakit']) ?>% dari kemarin</span>
                                </span>
                            <?php else: ?>
                                <span class="flex items-center">
                                    <i class="fas fa-minus text-gray-400 mr-1"></i>
                                    <span class="text-gray-400">Sama dengan kemarin</span>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Izin Card -->
                    <div class="glass-effect rounded-xl p-4 md:p-6 hover:bg-purple-900/10 transition-all duration-300 transform hover:scale-[1.02] hover:shadow-lg hover:shadow-purple-800/10 cursor-pointer" data-stat="izin">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-gray-400 text-sm font-medium mb-1">Total Izin</p>
                                <h3 class="text-2xl font-bold stat-value"><?= $stats['izin'] ?></h3>
                            </div>
                            <div class="h-10 w-10 md:h-12 md:w-12 rounded-xl bg-gradient-to-br from-purple-500/30 to-purple-700/30 flex items-center justify-center shadow-md">
                                <i class="fas fa-clipboard-list text-purple-400 text-lg"></i>
                            </div>
                        </div>
                        <div class="mt-3 md:mt-4 text-sm stat-change">
                            <?php if ($percentage_changes['izin'] > 0): ?>
                                <span class="flex items-center">
                                    <i class="fas fa-arrow-up text-green-400 mr-1"></i>
                                    <span class="text-green-400">+<?= abs($percentage_changes['izin']) ?>% dari kemarin</span>
                                </span>
                            <?php elseif ($percentage_changes['izin'] < 0): ?>
                                <span class="flex items-center">
                                    <i class="fas fa-arrow-down text-red-400 mr-1"></i>
                                    <span class="text-red-400">-<?= abs($percentage_changes['izin']) ?>% dari kemarin</span>
                                </span>
                            <?php else: ?>
                                <span class="flex items-center">
                                    <i class="fas fa-minus text-gray-400 mr-1"></i>
                                    <span class="text-gray-400">Sama dengan kemarin</span>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Terlambat Card -->
                    <div class="glass-effect rounded-xl p-4 md:p-6 hover:bg-purple-900/10 transition-all duration-300 transform hover:scale-[1.02] hover:shadow-lg hover:shadow-orange-800/10 cursor-pointer" data-stat="terlambat">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-gray-400 text-sm font-medium mb-1">Total Telat</p>
                                <h3 class="text-2xl font-bold stat-value"><?= $stats['terlambat'] ?></h3>
                            </div>
                            <div class="h-10 w-10 md:h-12 md:w-12 rounded-xl bg-gradient-to-br from-orange-500/30 to-orange-700/30 flex items-center justify-center shadow-md">
                                <i class="fas fa-clock text-orange-400 text-lg"></i>
                            </div>
                        </div>
                        <div class="mt-3 md:mt-4 text-sm stat-change">
                            <?php if ($percentage_changes['terlambat'] > 0): ?>
                                <span class="flex items-center">
                                    <i class="fas fa-arrow-up text-green-400 mr-1"></i>
                                    <span class="text-green-400">+<?= abs($percentage_changes['terlambat']) ?>% dari kemarin</span>
                                </span>
                            <?php elseif ($percentage_changes['terlambat'] < 0): ?>
                                <span class="flex items-center">
                                    <i class="fas fa-arrow-down text-red-400 mr-1"></i>
                                    <span class="text-red-400">-<?= abs($percentage_changes['terlambat']) ?>% dari kemarin</span>
                                </span>
                            <?php else: ?>
                                <span class="flex items-center">
                                    <i class="fas fa-minus text-gray-400 mr-1"></i>
                                    <span class="text-gray-400">Sama dengan kemarin</span>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Alpha Card -->
                    <div class="glass-effect rounded-xl p-4 md:p-6 hover:bg-purple-900/10 transition-all duration-300 transform hover:scale-[1.02] hover:shadow-lg hover:shadow-red-800/10 cursor-pointer" data-stat="alpha">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-gray-400 text-sm font-medium mb-1">Total Alpha</p>
                                <h3 class="text-2xl font-bold stat-value"><?= $stats['alpha'] ?></h3>
                            </div>
                            <div class="h-10 w-10 md:h-12 md:w-12 rounded-xl bg-gradient-to-br from-red-500/30 to-red-700/30 flex items-center justify-center shadow-md">
                                <i class="fas fa-user-times text-red-400 text-lg"></i>
                            </div>
                        </div>
                        <div class="mt-3 md:mt-4 text-sm stat-change">
                            <?php if ($percentage_changes['alpha'] > 0): ?>
                                <span class="flex items-center">
                                    <i class="fas fa-arrow-up text-green-400 mr-1"></i>
                                    <span class="text-green-400">+<?= abs($percentage_changes['alpha']) ?>% dari kemarin</span>
                                </span>
                            <?php elseif ($percentage_changes['alpha'] < 0): ?>
                                <span class="flex items-center">
                                    <i class="fas fa-arrow-down text-red-400 mr-1"></i>
                                    <span class="text-red-400">-<?= abs($percentage_changes['alpha']) ?>% dari kemarin</span>
                                </span>
                            <?php else: ?>
                                <span class="flex items-center">
                                    <i class="fas fa-minus text-gray-400 mr-1"></i>
                                    <span class="text-gray-400">Sama dengan kemarin</span>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Charts & Activities Grid - Now responsive -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Chart - Now adaptive to mobile -->
                    <div class="glass-effect rounded-xl p-4 md:p-6 lg:col-span-2">
                        <h3 class="text-lg font-semibold mb-3 md:mb-4">Statistik Kehadiran Mingguan</h3>
                        <div class="relative h-[300px] md:h-[400px]">
                            <canvas id="attendanceChart"></canvas>
                            <!-- Debug info -->
                            <div class="text-xs text-gray-500 mt-2 debug-info hidden">
                                <p>Data points: <span id="debug-count">0</span></p>
                            </div>
                        </div>
                    </div>

    <script src="<?= str_repeat('../', substr_count($_SERVER['PHP_SELF'], '/') - 1) ?>assets/js/diome.js"></script>
    <script>
        // Chart initialization for weekly data
        const ctx = document.getElementById('attendanceChart').getContext('2d');
        const weeklyData = <?= json_encode($weeklyStats) ?>;

        console.log('Weekly data:', weeklyData); // Add debugging

        function preprocessChartData(data) {
            // Create a map of all dates in the dataset
            const dateMap = {};

            // Handle empty data case
            if (!data || data.length === 0) {
                return {
                    dates: ['Today'],
                    result: {
                        'Hadir': [0],
                        'Sakit': [0],
                        'Izin': [0],
                        'Telat': [0],
                        'Alpha': [0]
                    }
                };
            }

            data.forEach(item => {
                if (!dateMap[item.date_label]) {
                    dateMap[item.date_label] = {
                        date: item.date_label
                    };
                }
            });

            // Get unique dates and statuses
            const dates = Object.keys(dateMap).sort();
            const statuses = ['Hadir', 'Sakit', 'Izin', 'Telat', 'Alpha'];

            // Create a structured dataset for the chart
            const result = {};
            statuses.forEach(status => {
                result[status] = dates.map(date => {
                    const match = data.find(item =>
                        item.date_label === date &&
                        item.status === status
                    );
                    return match ? parseInt(match.count) : 0;
                });
            });

            console.log('Preprocessed:', {
                dates,
                result
            }); // Add debugging
            return {
                dates,
                result
            };
        }

        function initChart() {
            const {
                dates,
                result
            } = preprocessChartData(weeklyData);

            // Debug info
            document.getElementById('debug-count').textContent = dates.length;

            const statusColors = {
                'Hadir': '#10B981',
                'Sakit': '#EAB308',
                'Izin': '#8B5CF6',
                'Telat': '#F97316',
                'Alpha': '#EF4444'
            };

            // Create datasets
            const datasets = [];

            for (const status in result) {
                if (result.hasOwnProperty(status)) {
                    datasets.push({
                        label: status,
                        data: result[status],
                        backgroundColor: statusColors[status] + '20',
                        borderColor: statusColors[status],
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: statusColors[status],
                        pointRadius: 4,
                        pointHoverRadius: 6
                    });
                }
            }

            const chartConfig = {
                type: 'line',
                data: {
                    labels: dates,
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(255, 255, 255, 0.1)'
                            },
                            ticks: {
                                color: '#9CA3AF',
                                font: {
                                    size: 11
                                }
                            }
                        },
                        x: {
                            grid: {
                                color: 'rgba(255, 255, 255, 0.1)'
                            },
                            ticks: {
                                color: '#9CA3AF',
                                font: {
                                    size: 11
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                color: '#9CA3AF',
                                usePointStyle: true,
                                font: {
                                    size: 12
                                }
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(17, 24, 39, 0.9)',
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            padding: 12,
                            borderColor: 'rgba(147, 51, 234, 0.3)',
                            borderWidth: 1,
                            displayColors: true,
                            usePointStyle: true
                        }
                    }
                }
            };

            // Destroy existing chart if it exists
            if (window.attendanceChart instanceof Chart) {
                window.attendanceChart.destroy();
            }

            // Create new chart
            window.attendanceChart = new Chart(ctx, chartConfig);
        }


        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            try {
                initChart();
            } catch (error) {
                console.error('Error initializing chart:', error);
            }
        });

        // Handle window resize
        let resizeTimer;
        window.addEventListener('resize', () => {
            // Throttle resize events to avoid excessive redraws
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => {
                initChart();
            }, 250);
        });

        // Mobile sidebar functions 
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('mobile-overlay');

            if (sidebar.classList.contains('-translate-x-full')) {
                // Open sidebar
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            } else {
                // Close sidebar
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }
        }


        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `fixed bottom-4 right-4 px-6 py-3 rounded-lg glass-effect 
          border border-${type === 'success' ? 'green' : 'red'}-500/30 
          text-white z-50 animate-fade-in-up`;
            toast.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check' : 'times'} 
           text-${type === 'success' ? 'green' : 'red'}-500 mr-2"></i>
        ${message}
    `;
            document.body.appendChild(toast);

            // Auto remove after 3 seconds
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(10px)';
                toast.style.transition = 'opacity 0.3s, transform 0.3s';

                setTimeout(() => {
                    toast.remove();
                }, 300);
            }, 3000);
        }

        // Update time for mobile view
        function updateMobileTime() {
            const mobileTimeElement = document.getElementById('current-time-mobile');
            if (mobileTimeElement) {
                const now = new Date();
                const timeString = now.toLocaleTimeString('id-ID', {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false
                });
                mobileTimeElement.textContent = timeString;
            }
        }

        // Add mobile time updater
        setInterval(updateMobileTime, 60000); // Update every minute
        updateMobileTime(); // Initial call

        // Make sure sidebar closes when pressing escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                // Check if notification panel is open and close it
                const notificationPanel = document.getElementById('notificationPanel');
                if (notificationPanel && !notificationPanel.classList.contains('hidden')) {
                    notificationPanel.classList.add('hidden');
                    document.body.classList.remove('overflow-hidden');
                    return;
                }

                // Close sidebar on mobile
                const sidebar = document.getElementById('sidebar');
                if (window.innerWidth < 1024 && !sidebar.classList.contains('-translate-x-full')) {
                    toggleSidebar();
                }
            }
        });

        // Fix viewport height issues on mobile browsers
        function setMobileHeight() {
            const vh = window.innerHeight * 0.01;
            document.documentElement.style.setProperty('--vh', `${vh}px`);
        }

        window.addEventListener('resize', setMobileHeight);
        setMobileHeight();

        // Optional: Add a function to refresh dashboard data periodically (every 5 minutes)
        function refreshDashboardData() {
            fetch('../api/dashboard_data.php')
                .then(response => response.json())
                .then(data => {
                    // Update stats
                    if (data.stats) {
                        Object.keys(data.stats).forEach(key => {
                            const card = document.querySelector(`[data-stat="${key}"] .stat-value`);
                            if (card) {
                                card.textContent = data.stats[key];
                            }
                        });
                    }

                    // Update notification count if changed
                    if (data.pending_count !== undefined) {
                        updateNotificationUI(data.pending_count);
                    }
                })
                .catch(error => console.error('Error refreshing data:', error));
        }

        // Start refreshing data every 5 minutes
        setInterval(refreshDashboardData, 5 * 60 * 1000);
    </script>
</body>

</html>