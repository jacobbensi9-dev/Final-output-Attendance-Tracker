<?php
session_start();

if(!isset($_SESSION['admin']) || !isset($_SESSION['adviser_id'])){
    header("Location: index.php");
    exit();
}

include 'connection.php';

$current_adviser_id = (int)$_SESSION['adviser_id'];
$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

// Fetch student details & ensure they belong to an authorized section/adviser
$student_query = mysqli_query($conn, "
    SELECT * FROM student 
    WHERE id = $student_id AND adviser_id = $current_adviser_id
");

if (!$student_query || mysqli_num_rows($student_query) == 0) {
    echo "<script>alert('Student not found or unauthorized.'); window.location.href='student_status.php';</script>";
    exit();
}

$student = mysqli_fetch_assoc($student_query);
$student_name = htmlspecialchars($student['last_name']) . ", " . htmlspecialchars($student['first_name']);
$student_section = htmlspecialchars($student['section']);

// Fetch all unique recorded school dates
$all_dates_result = mysqli_query($conn, "SELECT DISTINCT date FROM attendance ORDER BY date ASC");
$recorded_dates = [];
$total_present = 0;
$total_late = 0;

while ($d_row = mysqli_fetch_assoc($all_dates_result)) {
    $date = $d_row['date'];
    $recorded_dates[] = $date;
    
    // Check attendance status on this specific date
    $check_att = mysqli_query($conn, "SELECT status FROM attendance WHERE student_id = $student_id AND date = '$date'");
    if (mysqli_num_rows($check_att) > 0) {
        $att_row = mysqli_fetch_assoc($check_att);
        $att_status = strtolower(trim($att_row['status']));
        if (strpos($att_status, 'late') !== false) {
            $total_late++;
        } else {
            $total_present++;
        }
    }
}

$total_school_days = count($recorded_dates);
$total_absent = $total_school_days - ($total_present + $total_late);
if ($total_absent < 0) { $total_absent = 0; }
$attendance_rate = $total_school_days > 0 ? round((($total_present + $total_late) / $total_school_days) * 100, 1) : 0;
$present_rate = $total_school_days > 0 ? round(($total_present / $total_school_days) * 100, 1) : 0;
$late_rate = $total_school_days > 0 ? round(($total_late / $total_school_days) * 100, 1) : 0;
$absence_rate = $total_school_days > 0 ? round(($total_absent / $total_school_days) * 100, 1) : 0;

// Determine Monthly Remark and status color based on attendance rate
if ($attendance_rate >= 95) {
    $monthly_remark = "Excellent";
    $remark_color = "#16a34a";
} elseif ($attendance_rate >= 90) {
    $monthly_remark = "Good";
    $remark_color = "#0284c7";
} elseif ($attendance_rate >= 85) {
    $monthly_remark = "Fair";
    $remark_color = "#d97706";
} else {
    $monthly_remark = "Needs Improvement";
    $remark_color = "#dc2626";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Individual Attendance Breakdown - <?php echo $student_name; ?></title>
    <!-- Include Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

@keyframes fadeInScale {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}

@keyframes floatCard {
    0% { transform: translateY(0px); }
    50% { transform: translateY(-5px); }
    100% { transform: translateY(0px); }
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Poppins', sans-serif;
}

body { 
    display: flex; 
    margin: 0; 
    background-color: #f8fafc; 
    color: #1e293b; 
    overflow-x: hidden; 
    min-height: 100vh;
}

.main-wrapper {
    flex-grow: 1;
    padding: 35px 40px;
    box-sizing: border-box;
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
    animation: fadeInScale 0.5s ease-out;
}

/* Formal Top Header Bar */
.top-bar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    border-bottom: 1px solid #e2e8f0;
    padding-bottom: 20px;
}

.page-title {
    color: #0f172a;
    margin: 0;
    font-size: 1.65rem;
    font-weight: 600;
    letter-spacing: -0.2px;
}

.back-btn {
    background: #ffffff;
    color: #334155;
    border: 1px solid #cbd5e1;
    padding: 9px 16px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 500;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.back-btn:hover { 
    background: #f1f5f9;
    color: #0f172a; 
    border-color: #94a3b8;
    transform: translateX(-4px);
}

/* Clean Metrics Grid Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.stat-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 22px;
    text-align: center;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
    transition: transform 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
    animation: floatCard 4s ease-in-out infinite;
}

.stat-card:nth-child(even) {
    animation-delay: 2s;
}

.stat-card:hover {
    transform: translateY(-6px);
    border-color: #cbd5e1;
    box-shadow: 0 10px 25px rgba(2, 132, 199, 0.1);
}

.stat-card h4 { 
    margin: 0 0 8px 0; 
    color: #64748b; 
    font-size: 0.78rem; 
    letter-spacing: 0.5px; 
    text-transform: uppercase; 
    font-weight: 600;
}

.stat-card p { 
    margin: 0; 
    font-size: 1.7rem; 
    font-weight: 700; 
}

/* Main Content Analytics Box */
.page-content-box {
    width: 100%;
    background: #ffffff;
    padding: 30px;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    align-items: center;
}

/* Split Layout */
.content-split-layout {
    display: flex;
    gap: 40px;
    width: 100%;
    justify-content: center;
    align-items: center;
    flex-wrap: wrap;
    margin-top: 10px;
}

.graph-side {
    display: flex;
    flex-direction: column;
    align-items: center;
    flex: 1;
    min-width: 300px;
}

/* Monthly Remark Side Box */
.remarks-side {
    flex: 1;
    min-width: 280px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 24px;
}

.remarks-side h4 {
    margin: 0 0 15px 0;
    font-size: 1.05rem;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 8px;
    border-bottom: 1px solid #e2e8f0;
    padding-bottom: 10px;
    font-weight: 600;
}

.remark-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.remark-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.86rem;
    color: #475569;
    padding: 9px 12px;
    border-radius: 8px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    transition: all 0.2s ease;
}

.remark-item.active {
    background: #f0fdf4;
    border-color: #bbf7d0;
    color: #16a34a;
    font-weight: 600;
    transform: scale(1.02);
}

/* Custom Chart Legend Box */
.chart-legend-box {
    display: flex;
    gap: 18px;
    margin-bottom: 20px;
    background: #f8fafc;
    padding: 8px 18px;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    flex-wrap: wrap;
    justify-content: center;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 500;
    font-size: 0.82rem;
}

.legend-color {
    width: 10px;
    height: 10px;
    border-radius: 50%;
}
    </style>
</head>
<body>

<div class="main-wrapper">
    
    <!-- Top Nav Header -->
    <div class="top-bar-header">
        <div>
            <h1 class="page-title"><?php echo $student_name; ?></h1>
        </div>
        <a href="student_status.php" class="back-btn">&larr; Back to Absentees</a>
    </div>

    <!-- Metrics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <h4>Total School Days</h4>
            <p style="color: #0284c7;"><?php echo $total_school_days; ?></p>
        </div>
        <div class="stat-card">
            <h4>Days Present</h4>
            <p style="color: #16a34a;"><?php echo $total_present; ?></p>
        </div>
        <div class="stat-card">
            <h4>Total Lates</h4>
            <p style="color: #d97706;"><?php echo $total_late; ?></p>
        </div>
        <div class="stat-card">
            <h4>Total Absences</h4>
            <p style="color: #dc2626;"><?php echo $total_absent; ?></p>
        </div>
        <div class="stat-card">
            <h4>Attendance Rate</h4>
            <p style="color: #059669;"><?php echo $attendance_rate; ?>%</p>
        </div>
    </div>

    <!-- Graph Container Box -->
    <div class="page-content-box">
        <div style="width: 100%; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 15px; margin-bottom: 20px;">
            <h3 style="margin: 0; font-size: 1.1rem; font-weight: 600; color: #0f172a;">Attendance Ratio Breakdown (Pie / Doughnut View)</h3>
        </div>
        
        <div class="content-split-layout">
            <!-- Left Side: Graph & Legend -->
            <div class="graph-side">
                <div class="chart-legend-box">
                    <div class="legend-item" style="color: #16a34a;">
                        <div class="legend-color" style="background: #16a34a;"></div>
                        <?php echo $present_rate; ?>% Present
                    </div>
                    <div class="legend-item" style="color: #d97706;">
                        <div class="legend-color" style="background: #f59e0b;"></div>
                        <?php echo $late_rate; ?>% Late
                    </div>
                    <div class="legend-item" style="color: #dc2626;">
                        <div class="legend-color" style="background: #dc2626;"></div>
                        <?php echo $absence_rate; ?>% Absent
                    </div>
                </div>

                <div style="position: relative; height: 260px; width: 100%; max-width: 300px;">
                    <canvas id="studentDoughnutChart"></canvas>
                </div>
            </div>

            <!-- Right Side: Monthly Remark Breakdown Box -->
            <div class="remarks-side">
                <h4>🏆 Monthly Remark Evaluation</h4>
                <ul class="remark-list">
                    <li class="remark-item <?php echo ($monthly_remark === 'Excellent') ? 'active' : ''; ?>">
                        <span>Excellent</span>
                        <span style="font-size: 0.78rem; opacity: 0.8;">(95–100%)</span>
                    </li>
                    <li class="remark-item <?php echo ($monthly_remark === 'Good') ? 'active' : ''; ?>">
                        <span>Good</span>
                        <span style="font-size: 0.78rem; opacity: 0.8;">(90–94%)</span>
                    </li>
                    <li class="remark-item <?php echo ($monthly_remark === 'Fair') ? 'active' : ''; ?>">
                        <span>Fair</span>
                        <span style="font-size: 0.78rem; opacity: 0.8;">(85–89%)</span>
                    </li>
                    <li class="remark-item <?php echo ($monthly_remark === 'Needs Improvement') ? 'active' : ''; ?>">
                        <span>Needs Improvement</span>
                        <span style="font-size: 0.78rem; opacity: 0.8;">(Below 85%)</span>
                    </li>
                </ul>
                <div style="margin-top: 15px; text-align: left; font-size: 0.85rem; color: #64748b; background: #ffffff; padding: 10px 14px; border-radius: 8px; border: 1px solid #e2e8f0;">
                    Current Standing Status: <strong style="color: <?php echo $remark_color; ?>;"><?php echo $monthly_remark; ?></strong>
                </div>
            </div>
        </div>

    </div>

</div>

<script>
    const ctx = document.getElementById('studentDoughnutChart').getContext('2d');
    
    const studentDoughnutChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Present', 'Late', 'Absent'],
            datasets: [{
                data: [<?php echo $total_present; ?>, <?php echo $total_late; ?>, <?php echo $total_absent; ?>],
                backgroundColor: ['#16a34a', '#f59e0b', '#dc2626'],
                borderWidth: 2,
                borderColor: '#ffffff',
                borderRadius: 6,
                hoverOffset: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '72%',
            animation: {
                animateScale: true,
                animateRotate: true,
                duration: 1200,
                easing: 'easeOutQuart'
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0f172a',
                    titleFont: { size: 13, weight: '600', family: 'Poppins' },
                    bodyFont: { size: 12, family: 'Poppins' },
                    padding: 12,
                    cornerRadius: 8,
                    borderColor: '#cbd5e1',
                    borderWidth: 1,
                    callbacks: {
                        label: function(context) {
                            let label = context.label || '';
                            let value = context.raw || 0;
                            let total = <?php echo $total_school_days > 0 ? $total_school_days : 1; ?>;
                            let percentage = Math.round((value / total) * 100);
                            return ` ${label}: ${value} days (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
</script>

</body>
</html>