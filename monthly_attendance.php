<?php
// Database connection (Update with your actual DB credentials)
$host = 'localhost';
$db   = 'attendancer';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (\PDOException $e) {
    $pdo = null;
}

// Fetch all distinct years dynamically from database for unlimited year options
$availableYears = [];
if ($pdo) {
    try {
        $yearStmt = $pdo->query("SELECT DISTINCT YEAR(date_column) as yr FROM attendance_table ORDER BY yr DESC");
        $availableYears = $yearStmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (\Exception $e) {
        $availableYears = [];
    }
}
// Fallback if no records found or no connection yet
if (empty($availableYears)) {
    $availableYears = [date('Y'), date('Y') - 1, date('Y') - 2];
}

// Handle AJAX requests for chart data updates
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json');
    $view = $_GET['view'] ?? 'monthly';
    $year = $_GET['year'] ?? date('Y');

    $labels = [];
    $dataValues = [];

    if ($pdo) {
        if ($view === 'yearly') {
            $stmt = $pdo->prepare("SELECT YEAR(date_column) as timeframe, COUNT(*) as total FROM attendance_table GROUP BY YEAR(date_column) ORDER BY timeframe ASC");
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $labels = array_column($results, 'timeframe');
            $dataValues = array_column($results, 'total');
        } else {
            $labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            $monthlyData = array_fill(0, 12, 0);

            $stmt = $pdo->prepare("SELECT MONTH(date_column) as m, COUNT(*) as total FROM attendance_table WHERE YEAR(date_column) = ? GROUP BY MONTH(date_column)");
            $stmt->execute([$year]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($results as $row) {
                $monthIndex = (int)$row['m'] - 1;
                $monthlyData[$monthIndex] = (int)$row['total'];
            }
            $dataValues = $monthlyData;
        }
    } else {
        if ($view === 'yearly') {
            $labels = ['2024', '2025', '2026'];
            $dataValues = [150, 420, 890];
        } else {
            $labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            $dataValues = [0, 0, 0, 0, 0, 0, 0, 1, 0, 0, 0, 0];
        }
    }

    echo json_encode(['labels' => $labels, 'data' => $dataValues]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly & Yearly Attendance Analytics</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-[#090d16] text-slate-100 font-sans flex h-screen overflow-hidden">

    <!-- Sidebar Navigation -->
    <aside class="w-64 bg-[#0b101d] border-r border-slate-800 flex flex-col justify-between p-4">
        <div>
            <div class="mb-8 px-2">
                <h1 class="text-blue-400 font-bold text-lg">Asian School</h1>
                <p class="text-xs text-slate-400">Attendance Portal</p>
            </div>
            <nav class="space-y-2">
                <a href="index.php" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-slate-400 hover:bg-slate-800 hover:text-white transition"> Home</a>
                <a href="dashboard.php" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-slate-400 hover:bg-slate-800 hover:text-white transition">Main Dashboard</a>
                <a href="attendance_graph.php" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm bg-blue-600 text-white font-medium shadow-lg shadow-blue-600/30"> Monthly Record</a>
            </nav>
        </div>
        <div>
            <a href="logout.php" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-red-400 hover:bg-red-500/10 transition">🚪 Logout</a>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="flex-1 flex flex-col overflow-y-auto">
        <!-- Top Header -->
        <header class="p-8 pb-4 border-b border-slate-800/60 flex justify-between items-end">
            <div>
                <h2 class="text-2xl font-bold tracking-tight text-white">Attendance Analytics</h2>
                <p class="text-xs text-slate-400 mt-1">Track and detect trends in student attendance counts across months and years.</p>
                <div class="text-xs text-blue-400 mt-2 font-mono">Current Section: passcode</div>
            </div>
        </header>

        <!-- Dashboard Content -->
        <div class="p-8">
            <!-- Chart Container Box -->
            <div class="bg-[#111827] border border-slate-800 rounded-2xl p-6 shadow-xl">
                
                <!-- Controls Header -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                    <!-- View Switcher Tabs -->
                    <div class="inline-flex rounded-lg bg-slate-900 p-1 border border-slate-800">
                        <button onclick="switchView('monthly')" id="monthlyBtn" class="px-4 py-1.5 text-xs font-semibold text-white bg-blue-600 rounded-md transition shadow">Monthly</button>
                        <button onclick="switchView('yearly')" id="yearlyBtn" class="px-4 py-1.5 text-xs font-semibold text-slate-400 hover:text-white transition">Yearly</button>
                    </div>

                    <!-- Dynamic Unlimited Year Selector -->
                    <div id="yearFilterContainer" class="flex items-center gap-2">
                        <label for="yearSelect" class="text-xs text-slate-400">Select Year:</label>
                        <select id="yearSelect" onchange="fetchChartData()" class="bg-slate-900 border border-slate-700 text-slate-200 text-xs rounded-lg px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                            <?php foreach ($availableYears as $yr): ?>
                                <option value="<?php echo $yr; ?>" <?php echo ($yr == date('Y')) ? 'selected' : ''; ?>>
                                    <?php echo $yr; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Canvas Graph Element -->
                <div class="relative w-full h-[380px]">
                    <canvas id="attendanceChart"></canvas>
                </div>
            </div>
        </div>
    </main>

    <!-- JavaScript with Fully Smooth Animations -->
    <script>
        let currentView = 'monthly';
        let attendanceChart;

        window.addEventListener('DOMContentLoaded', () => {
            const ctx = document.getElementById('attendanceChart').getContext('2d');
            
            const gradient = ctx.createLinearGradient(0, 0, 0, 350);
            gradient.addColorStop(0, 'rgba(59, 130, 246, 0.35)');
            gradient.addColorStop(1, 'rgba(59, 130, 246, 0.0)');

            attendanceChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                    datasets: [{
                        label: 'Number of Students Attending',
                        data: [0, 0, 0, 0, 0, 0, 0, 1, 0, 0, 0, 0],
                        borderColor: '#3b82f6',
                        backgroundColor: gradient,
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#0f172a',
                        pointBorderColor: '#3b82f6',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    // Ultra-smooth animation configuration
                    animation: {
                        duration: 1500, // 1.5 seconds smooth entrance/transition
                        easing: 'easeInOutCubic'
                    },
                    transitions: {
                        active: {
                            animation: {
                                duration: 1000
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            labels: {
                                color: '#94a3b8',
                                font: { family: 'Inter, sans-serif', size: 12 }
                            }
                        },
                        tooltip: {
                            backgroundColor: '#0f172a',
                            titleColor: '#f8fafc',
                            bodyColor: '#94a3b8',
                            borderColor: '#334155',
                            borderWidth: 1,
                            padding: 12,
                            displayColors: false
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { color: '#64748b' }
                        },
                        y: {
                            grid: { color: 'rgba(255, 255, 255, 0.04)' },
                            ticks: { color: '#64748b', precision: 0 },
                            beginAtZero: true
                        }
                    }
                }
            });

            fetchChartData();
        });

        function switchView(viewType) {
            currentView = viewType;
            const monthlyBtn = document.getElementById('monthlyBtn');
            const yearlyBtn = document.getElementById('yearlyBtn');
            const yearFilterContainer = document.getElementById('yearFilterContainer');

            if (viewType === 'monthly') {
                monthlyBtn.className = "px-4 py-1.5 text-xs font-semibold text-white bg-blue-600 rounded-md transition shadow";
                yearlyBtn.className = "px-4 py-1.5 text-xs font-semibold text-slate-400 hover:text-white transition";
                yearFilterContainer.style.display = 'flex';
            } else {
                yearlyBtn.className = "px-4 py-1.5 text-xs font-semibold text-white bg-blue-600 rounded-md transition shadow";
                monthlyBtn.className = "px-4 py-1.5 text-xs font-semibold text-slate-400 hover:text-white transition";
                yearFilterContainer.style.display = 'none';
            }

            fetchChartData();
        }

        function fetchChartData() {
            const yearSelect = document.getElementById('yearSelect');
            const year = yearSelect ? yearSelect.value : new Date().getFullYear();
            const url = `attendance_graph.php?ajax=1&view=${currentView}&year=${year}`;

            fetch(url)
                .then(response => response.json())
                .then(result => {
                    attendanceChart.data.labels = result.labels;
                    attendanceChart.data.datasets[0].data = result.data;
                    attendanceChart.update(); // Triggers smooth wave animation seamlessly
                })
                .catch(error => console.error('Error loading attendance data:', error));
        }
    </script>
</body>
</html>