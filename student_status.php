<?php
session_start();

if(!isset($_SESSION['admin']) || !isset($_SESSION['adviser_id'])){
    header("Location: index.php");
    exit();
}

include 'connection.php';

$current_adviser_id = (int)$_SESSION['adviser_id'];

// --- SECTION HANDLING ENGINE ---
if (isset($_GET['section']) && !empty($_GET['section'])) {
    $_SESSION['section'] = mysqli_real_escape_string($conn, $_GET['section']);
}

$section_result = mysqli_query($conn, "
    SELECT section_name FROM section WHERE adviser_id = $current_adviser_id ORDER BY section_name ASC
");

if (!isset($_SESSION['section']) || empty($_SESSION['section'])) {
    $first_sec = mysqli_query($conn, "SELECT section_name FROM section WHERE adviser_id = $current_adviser_id ORDER BY section_name ASC LIMIT 1");
    if ($first_sec && mysqli_num_rows($first_sec) > 0) {
        $row = mysqli_fetch_assoc($first_sec);
        $_SESSION['section'] = $row['section_name'];
    } else {
        $_SESSION['section'] = '';
    }
}

$current_section = isset($_SESSION['section']) ? mysqli_real_escape_string($conn, $_SESSION['section']) : '';

// Fetch all recorded dates to compute totals accurately
$all_dates_result = mysqli_query($conn, "SELECT DISTINCT date FROM attendance");
$recorded_dates = [];
while ($d_row = mysqli_fetch_assoc($all_dates_result)) {
    $recorded_dates[] = $d_row['date'];
}
$total_school_days = count($recorded_dates);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Institutional Attendance Management</title>
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js for High-End Animated Charts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Poppins', sans-serif;
}

body {
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    background: linear-gradient(rgba(10, 20, 40, 0.65), rgba(10, 20, 40, 0.65)),
                url('asians.jpg');
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    overflow-x: hidden;
}

/* ===========================
   ADVANCED ANIMATION KEYFRAMES
   =========================== */
@keyframes fadeInSlideUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes rowCascade {
    from {
        opacity: 0;
        transform: translateX(-15px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes pulseGlow {
    0% { box-shadow: 0 0 0 0 rgba(56, 189, 248, 0.4); }
    70% { box-shadow: 0 0 0 8px rgba(56, 189, 248, 0); }
    100% { box-shadow: 0 0 0 0 rgba(56, 189, 248, 0); }
}

@keyframes floatingBadge {
    0% { transform: translateY(0px); }
    50% { transform: translateY(-3px); }
    100% { transform: translateY(0px); }
}

/* ===========================
   TOP GLOBAL HEADER STYLES
   =========================== */
.main-app-header {
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.4);
    border-radius: 12px;
    padding: 18px 24px;
    margin-bottom: 25px;
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    align-items: center;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    animation: fadeInSlideUp 0.5s ease-out;
}

.header-brand {
    display: flex;
    align-items: center;
    gap: 12px;
}

.header-center-title {
    text-align: center;
}

.header-center-title h1 {
    font-size: 1.5rem;
    color: #0f172a;
    margin: 0;
    font-weight: 600;
    line-height: 1.2;
}

.header-center-title p {
    color: rgba(15, 23, 42, 0.7);
    font-size: 0.8rem;
    margin-top: 2px;
    margin-bottom: 0;
}

.header-user-info {
    font-size: 0.82rem;
    color: #475569;
    background: rgba(255, 255, 255, 0.6);
    padding: 6px 14px;
    border-radius: 20px;
    border: 1px solid rgba(255, 255, 255, 0.8);
    justify-self: end;
}

.portal-body { 
    display: flex; 
    margin: 0; 
    background: transparent !important; 
    font-family: 'Poppins', sans-serif; 
    overflow-x: hidden; 
}

.portal-body .app-sidebar {
    width: 280px;
    background: rgba(255, 255, 255, 0.88);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border-right: 1px solid rgba(255, 255, 255, 0.4);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 20px;
    box-sizing: border-box;
    height: 100vh;
    position: fixed;
    top: 0;
    left: 0;
    z-index: 1000;
    transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1), width 0.4s ease, padding 0.4s ease;
    overflow-y: auto;
    box-shadow: 4px 0 24px rgba(0, 0, 0, 0.08);
}

.portal-body .app-sidebar.collapsed {
    transform: translateX(-100%);
    width: 0;
    padding: 0;
    border-right: none;
    overflow: hidden;
}

.portal-body .sidebar-top-header {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    margin-bottom: 25px;
}

.portal-body .sidebar-brand {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 8px;
    width: 100%;
}

.portal-body .sidebar-logo {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    object-fit: cover;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
}

.portal-body .sidebar-brand h3 { 
    color: #0f172a; 
    margin: 0; 
    font-size: 1.15rem; 
    font-weight: 600;
}

.portal-body .sidebar-brand p { 
    color: #64748b; 
    margin: 0; 
    font-size: 0.72rem; 
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.portal-body .toggle-sidebar-btn {
    background: rgba(255, 255, 255, 0.7);
    border: 1px solid rgba(203, 213, 225, 0.8);
    color: #334155;
    padding: 5px 9px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.85rem;
    transition: all 0.25s ease;
}

.portal-body .sidebar-top-header .toggle-sidebar-btn {
    position: absolute;
    top: 0;
    right: 0;
}

.portal-body .toggle-sidebar-btn:hover { 
    background: #ffffff; 
    color: #0f172a; 
    transform: scale(1.08);
}

.portal-body .nav-menu-wrapper {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 10px;
}

.portal-body .nav-view-btn { 
    background: rgba(255, 255, 255, 0.6); 
    backdrop-filter: blur(4px);
    border: 1px solid rgba(226, 232, 240, 0.8); 
    color: #475569; 
    padding: 12px 14px; 
    font-weight: 500; 
    font-size: 0.85rem; 
    border-radius: 8px; 
    cursor: pointer; 
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1); 
    text-decoration: none; 
    display: flex;
    align-items: center;
    gap: 12px;
}

.portal-body .nav-view-btn i {
    font-size: 1rem;
    width: 20px;
    text-align: center;
    transition: transform 0.3s ease;
}

.portal-body .nav-view-btn:hover { 
    border-color: #cbd5e1; 
    color: #0f172a; 
    background: rgba(255, 255, 255, 0.9);
    transform: translateX(6px);
}

.portal-body .nav-view-btn:hover i {
    transform: scale(1.2);
}

.portal-body .nav-view-btn.active-nav-btn { 
    background: #1e293b; 
    color: white; 
    border-color: #1e293b; 
    box-shadow: 0 6px 16px rgba(30, 41, 59, 0.25); 
    font-weight: 600;
}

.portal-body .nav-logout-btn {
    color: #ef4444 !important;
    border-color: rgba(254, 202, 202, 0.8) !important;
}

.portal-body .nav-logout-btn:hover {
    background: rgba(254, 242, 242, 0.9) !important;
    border-color: #fca5a5 !important;
}

.portal-body .sidebar-footer {
    font-size: 0.72rem;
    color: #64748b;
    text-align: center;
    margin-top: 15px;
    border-top: 1px solid rgba(241, 245, 249, 0.8);
    padding-top: 15px;
}

.portal-body .main-wrapper {
    flex-grow: 1;
    margin-left: 280px;
    transition: margin-left 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    padding: 25px 40px 35px 40px;
    box-sizing: border-box;
    width: calc(100% - 280px);
    min-height: 100vh;
    background: transparent;
    animation: fadeIn 0.6s ease-out forwards;
}

.portal-body .app-sidebar.collapsed ~ .main-wrapper {
    margin-left: 0;
    width: 100%;
}

.portal-body .external-open-sidebar {
    position: fixed;
    top: 20px;
    left: 20px;
    z-index: 999;
    display: none;
    animation: fadeIn 0.3s ease-out;
}

.portal-body .app-sidebar.collapsed ~ .external-open-sidebar {
    display: block;
}

/* Custom Section Dropdown Styles */
.portal-body .custom-sec-dropdown { 
    position: relative; 
    display: inline-block; 
}

.portal-body .sec-dropdown-btn { 
    padding: 10px 16px; 
    background: rgba(255, 255, 255, 0.8); 
    backdrop-filter: blur(8px);
    color: #0f172a; 
    border: 1px solid rgba(205, 213, 225, 0.8); 
    border-radius: 8px; 
    font-weight: 500; 
    font-size: 0.88rem;
    cursor: pointer; 
    outline: none; 
    display: flex; 
    align-items: center; 
    gap: 10px; 
    transition: all 0.25s ease;
}

.portal-body .sec-dropdown-btn:hover {
    background: #ffffff;
    border-color: #0284c7;
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(2, 132, 199, 0.15);
    animation: pulseGlow 2s infinite;
}

.portal-body .sec-dropdown-menu { 
    display: block;
    opacity: 0;
    visibility: hidden;
    transform: translateY(12px) scale(0.96);
    position: absolute; 
    top: 120%; 
    right: 0; 
    min-width: 220px; 
    background: rgba(255, 255, 255, 0.95); 
    backdrop-filter: blur(12px);
    border: 1px solid rgba(205, 213, 225, 0.8); 
    border-radius: 8px; 
    box-shadow: 0 16px 35px rgba(0,0,0,0.15); 
    z-index: 1000; 
    overflow: hidden; 
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.portal-body .sec-dropdown-menu.show { 
    opacity: 1;
    visibility: visible;
    transform: translateY(0) scale(1);
}

.portal-body .sec-dropdown-item { 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    padding: 10px 14px; 
    border-bottom: 1px solid #f1f5f9; 
    transition: background 0.2s ease; 
}

.portal-body .sec-dropdown-item:hover { 
    background: rgba(248, 250, 252, 0.9); 
}

.portal-body .sec-dropdown-item .sec-title { 
    color: #334155; 
    text-decoration: none; 
    font-weight: 400; 
    font-size: 0.86rem; 
    flex-grow: 1; 
    cursor: pointer; 
}

.portal-body .sec-dropdown-item .sec-title.active { 
    color: #0284c7; 
    font-weight: 600; 
}

/* LIVE SEARCH BAR STYLES */
.search-bar-container {
    position: relative;
    max-width: 340px;
    width: 100%;
}

.search-bar-container input {
    width: 100%;
    padding: 10px 14px 10px 38px;
    border: 1px solid rgba(203, 213, 225, 0.8);
    border-radius: 8px;
    font-size: 0.86rem;
    color: #0f172a;
    background: rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(4px);
    outline: none;
    transition: all 0.25s ease;
}

.search-bar-container input:focus {
    border-color: #0284c7;
    background: #ffffff;
    box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
}

.search-bar-container i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #64748b;
    font-size: 0.86rem;
    pointer-events: none;
}

.portal-body .page-content-box {
    width: 100%;
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.5);
    box-sizing: border-box;
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.12);
    animation: fadeInSlideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.portal-body .content-heading {
    color: #0f172a;
    margin-top: 0;
    margin-bottom: 4px;
    font-size: 1.1rem;
    font-weight: 600;
}

.portal-body .section-label {
    color: #475569;
    font-size: 0.85rem;
    margin: 0;
}

/* Highly Interactive Analytics Grid */
.analytics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 20px;
    padding: 24px 30px;
    background: rgba(248, 250, 252, 0.5);
    backdrop-filter: blur(8px);
    border-bottom: 1px solid rgba(226, 232, 240, 0.8);
}

.analytics-card {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(203, 213, 225, 0.8);
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 6px 20px rgba(0,0,0,0.04);
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    overflow: hidden;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.analytics-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 28px rgba(2, 132, 199, 0.15);
    border-color: #38bdf8;
}

.analytics-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: linear-gradient(90deg, #0284c7, #38bdf8, #059669);
    background-size: 200% 100%;
    animation: gradientShift 4s ease infinite;
}

@keyframes gradientShift {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}

.analytics-card h4 {
    font-size: 0.95rem;
    color: #0f172a;
    margin-bottom: 15px;
    font-weight: 600;
}

.chart-container-large {
    position: relative;
    width: 210px;
    height: 210px;
}

.portal-body .data-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 5px;
}

.portal-body .data-table thead tr {
    border-bottom: 2px solid rgba(226, 232, 240, 0.8);
    color: #475569;
    text-align: left;
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.8px;
}

.portal-body .data-table th, 
.portal-body .data-table td {
    padding: 14px 12px;
}

/* Explicit alignment rule for centered columns */
.portal-body .data-table th.center-text,
.portal-body .data-table td.center-text {
    text-align: center;
}

.portal-body .data-table tbody tr {
    border-bottom: 1px solid rgba(241, 245, 249, 0.8);
    color: #1e293b;
    font-size: 0.88rem;
    animation: rowCascade 0.5s ease-out forwards;
    opacity: 0;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

.portal-body .data-table tbody tr:hover {
    background: rgba(248, 250, 252, 0.8);
    transform: scale(1.004) translateX(4px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
}

.portal-body .data-table td.student-name {
    font-weight: 500;
}

.portal-body .data-table td.student-id {
    text-align: center;
    color: #0284c7;
    font-family: monospace;
    font-size: 0.9rem;
}

.portal-body .empty-table-msg {
    text-align: center;
    padding: 40px;
    color: #64748b;
    font-size: 0.88rem;
}

.portal-body .status-badge {
    background: rgba(240, 253, 244, 0.9);
    color: #16a34a;
    border: 1px solid #bbf7d0;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 0.72rem;
    font-weight: 600;
    letter-spacing: 0.4px;
    display: inline-block;
    position: relative;
    overflow: hidden;
    animation: floatingBadge 3s ease-in-out infinite;
}

.portal-body .status-badge::after {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.6), transparent);
    animation: badgeShimmer 2.5s infinite;
}

@keyframes badgeShimmer {
    0% { left: -100%; }
    20% { left: 100%; }
    100% { left: 100%; }
}

.portal-body .view-graph-btn {
    background: rgba(240, 249, 255, 0.9);
    color: #0284c7;
    border: 1px solid #bae6fd;
    padding: 6px 14px;
    border-radius: 6px;
    font-size: 0.76rem;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.portal-body .view-graph-btn:hover {
    background: #0284c7;
    color: #ffffff;
    border-color: #0284c7;
    transform: translateY(-2px) scale(1.05);
    box-shadow: 0 6px 16px rgba(2, 132, 199, 0.4);
}

@media(max-width: 900px) {
    .portal-body .main-wrapper { margin-left: 0; width: 100%; padding: 15px; }
    .main-app-header { grid-template-columns: 1fr; gap: 15px; text-align: center; }
    .header-user-info { justify-self: center; }
    .header-brand { justify-content: center; }
}
    </style>
</head>
<body class="portal-body">

<!-- LEFT COLLAPSIBLE SIDEBAR WITH ICONS -->
<div class="app-sidebar" id="appSidebar">
    <div>
        <div class="sidebar-top-header">
            <div class="sidebar-brand">
                <img src="Logo.jpg" alt="Asian School Logo" class="sidebar-logo">
                <div>
                    <h3>Asian School</h3>
                    <p>Faculty Portal</p>
                </div>
            </div>
            <button type="button" class="toggle-sidebar-btn" id="closeSidebarBtn" title="Close Sidebar">&#x2715;</button>
        </div>

        <div class="nav-menu-wrapper">
            <a href="attendance_graph.php" class="nav-view-btn">
                <i class="fa-solid fa-chart-line"></i> Monthly Record
            </a>
            <a href="dashboard.php" class="nav-view-btn">
                <i class="fa-solid fa-house"></i> Main Calendar
            </a>
            <a href="student_status.php" class="nav-view-btn active-nav-btn">
                <i class="fa-solid fa-user-graduate"></i> Status Reports
            </a>
            <a href="logout.php" class="nav-view-btn nav-logout-btn">
                <i class="fa-solid fa-right-from-bracket"></i> Logout
            </a>
        </div>
    </div>
    
    <div class="sidebar-footer">
        Institutional Registry &copy; 2026
    </div>
</div>

<!-- Button to reopen sidebar when closed -->
<button type="button" class="toggle-sidebar-btn external-open-sidebar" id="openSidebarBtn" title="Open Sidebar">&#x2630; Menu</button>

<div class="main-wrapper">
    
    <!-- HEADER -->
    <header class="main-app-header">
        <div class="header-brand">
            <span></span>
        </div>

        <div class="header-center-title">
            <h1>REPORTS</h1>
            <p>Comprehensive Attendance, Lateness & Absenteeism Registry</p>
        </div>

        <div class="header-user-info">
        </div>
    </header>

    <!-- MAIN CARD CONTAINER -->
    <div class="page-content-box" style="padding: 0; overflow: hidden;">
        
        <!-- Clean Card Header with Section Selection Dropdown Button -->
        <div style="padding: 24px 30px; border-bottom: 1px solid rgba(226, 232, 240, 0.8); background: rgba(255, 255, 255, 0.6); backdrop-filter: blur(8px); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
            <div>
                <h3 class="content-heading" style="margin-bottom: 4px;">Whole School Year Attendance & Lates Record</h3>
                <p class="section-label">Active Section Registry: <span style="color: #0284c7; font-weight: 500;"><?php echo htmlspecialchars($current_section); ?></span></p>
            </div>

            <!-- Custom Section Dropdown -->
            <div style="display: flex; align-items: center; gap: 10px;">
                <div class="custom-sec-dropdown">
                    <button type="button" class="sec-dropdown-btn" id="secDropdownBtn">
                        <i class="fa-solid fa-layer-group" style="color: #0284c7;"></i>
                        <span>Section: <?php echo htmlspecialchars($current_section); ?></span>
                        <i class="fa-solid fa-chevron-down" style="font-size: 0.75rem; color: #64748b; margin-left: 4px;"></i>
                    </button>
                    <div class="sec-dropdown-menu" id="secDropdownMenu">
                        <?php if($section_result && mysqli_num_rows($section_result) > 0): while($sec = mysqli_fetch_array($section_result)): 
                            $sec_val = $sec[0];
                            if(empty($sec_val)) continue;
                            $is_active = ($sec_val == $current_section);
                        ?>
                            <div class="sec-dropdown-item">
                                <span class="sec-title <?php echo $is_active ? 'active' : ''; ?>" onclick="window.location.href='?section=<?php echo urlencode($sec_val); ?>';">
                                    Section: <?php echo htmlspecialchars($sec_val); ?>
                                </span>
                            </div>
                        <?php endwhile; else: ?>
                            <div class="sec-dropdown-item">
                                <span style="color: #64748b; font-size: 0.85rem; padding: 5px;">No sections available.</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- PHP Data Prep for Doughnut Charts including Lates -->
        <?php
        $students_query = mysqli_query($conn, "
            SELECT id, first_name, last_name 
            FROM student 
            WHERE section = '$current_section' AND adviser_id = $current_adviser_id
        ");

        $student_records = [];
        $section_total_present = 0;
        $section_total_late = 0;
        $section_total_possible = 0;

        if ($students_query && mysqli_num_rows($students_query) > 0) {
            while ($student = mysqli_fetch_assoc($students_query)) {
                $s_id = $student['id'];
                $s_name = htmlspecialchars($student['last_name']) . ", " . htmlspecialchars($student['first_name']);

                $present_count = 0;
                $late_count = 0;
                if ($total_school_days > 0) {
                    $att_q = mysqli_query($conn, "SELECT status FROM attendance WHERE student_id = $s_id");
                    while ($att_r = mysqli_fetch_assoc($att_q)) {
                        $status_val = strtolower(trim($att_r['status']));
                        if (strpos($status_val, 'late') !== false) {
                            $late_count++;
                        } else {
                            $present_count++;
                        }
                    }
                }

                $total_absences = $total_school_days - ($present_count + $late_count);
                if ($total_absences < 0) { $total_absences = 0; }

                $section_total_present += $present_count;
                $section_total_late += $late_count;
                $section_total_possible += $total_school_days;

                $student_records[] = [
                    'id' => $s_id,
                    'name' => $s_name,
                    'present' => $present_count,
                    'lates' => $late_count,
                    'absences' => $total_absences
                ];
            }

            usort($student_records, function ($a, $b) {
                if ($b['absences'] === $a['absences']) {
                    return $b['lates'] <=> $a['lates'];
                }
                return $b['absences'] <=> $a['absences'];
            });
        }

        $section_total_absences = $section_total_possible - ($section_total_present + $section_total_late);
        if($section_total_absences < 0) $section_total_absences = 0;
        
        $attendance_rate = ($section_total_possible > 0) ? round((($section_total_present + $section_total_late) / $section_total_possible) * 100, 1) : 0;
        $present_rate = ($section_total_possible > 0) ? round(($section_total_present / $section_total_possible) * 100, 1) : 0;
        $late_rate = ($section_total_possible > 0) ? round(($section_total_late / $section_total_possible) * 100, 1) : 0;
        $absentee_rate = ($section_total_possible > 0) ? round(($section_total_absences / $section_total_possible) * 100, 1) : 0;
        ?>

        <!-- Advanced Analytics Section: Doughnut Graph + Breakdown metrics -->
        <div class="analytics-grid">
            <div class="analytics-card">
                <h4>Section-Wide Attendance & Late Ratios</h4>
                <div class="chart-container-large">
                    <canvas id="overallDoughnutChart"></canvas>
                </div>
                <div style="margin-top: 15px; display: flex; gap: 15px; font-size: 0.78rem; flex-wrap: wrap; justify-content: center;">
                    <span style="color: #16a34a; font-weight: 600;"><i class="fa-solid fa-circle" style="font-size: 8px;"></i> Present: <?php echo $section_total_present; ?></span>
                    <span style="color: #d97706; font-weight: 600;"><i class="fa-solid fa-circle" style="font-size: 8px;"></i> Late: <?php echo $section_total_late; ?></span>
                    <span style="color: #e11d48; font-weight: 600;"><i class="fa-solid fa-circle" style="font-size: 8px;"></i> Absent: <?php echo $section_total_absences; ?></span>
                </div>
            </div>
            
            <div class="analytics-card" style="justify-content: center; text-align: left; align-items: flex-start;">
                <h4>Registry Control Panel Metrics</h4>
                <p style="font-size: 0.86rem; color: #334155; margin-bottom: 10px;"><i class="fa-regular fa-calendar-days" style="color: #0284c7; width: 20px;"></i> <strong>Total School Days Tracked:</strong> <?php echo $total_school_days; ?></p>
                <p style="font-size: 0.86rem; color: #334155; margin-bottom: 10px;"><i class="fa-solid fa-users" style="color: #0284c7; width: 20px;"></i> <strong>Enrolled Students in Section:</strong> <?php echo count($student_records); ?></p>
                <p style="font-size: 0.86rem; color: #334155; margin-bottom: 10px;"><i class="fa-solid fa-chart-pie" style="color: #0284c7; width: 20px;"></i> <strong>Overall Section Health:</strong> <?php echo ($attendance_rate >= 85) ? '<span style="color: #16a34a; font-weight: 600;">Optimal Stable</span>' : '<span style="color: #ca8a04; font-weight: 600;">Needs Monitoring</span>'; ?></p>
                <p style="font-size: 0.78rem; color: #475569; margin-top: 5px; line-height: 1.4;">Interactive charts display dynamic ratios including Present, Late, and Absent distributions.</p>
            </div>
        </div>

        <!-- Table Container with Live Search Bar -->
        <div style="padding: 24px 30px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
                <h4 style="font-size: 1rem; color: #0f172a; margin: 0; font-weight: 600;">StudentsOverview</h4>
                
                <!-- Live Search Bar Component -->
                <div class="search-bar-container">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="studentSearchInput" placeholder="Search student name or ID..." onkeyup="filterStudentTable()">
                </div>
            </div>

            <!-- Perfectly aligned table headers and cells -->
            <table class="data-table" id="studentTable" style="margin-top: 0;">
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th class="center-text">Permanent ID</th>
                        <th class="center-text">Status Flag</th>
                        <th class="center-text">Administrative Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (!empty($student_records)) {
                        $anim_delay = 0.05;
                        foreach ($student_records as $index => $student) {
                            $current_delay = round($anim_delay * $index, 2);
                    ?>
                    <tr style="animation-delay: <?php echo $current_delay; ?>s;">
                        <td class="student-name"><?php echo $student['name']; ?></td>
                        <td class="center-text student-id">#<?php echo $student['id']; ?></td>
                        <td class="center-text">
                            <span class="status-badge">LOGGED</span>
                        </td>
                        <td class="center-text">
                            <a href="student_graph.php?student_id=<?php echo $student['id']; ?>" class="view-graph-btn">
                                View Status
                            </a>
                        </td>
                    </tr>
                    <?php
                        }
                    } else {
                        echo "<tr><td colspan='4' class='empty-table-msg'>No student records found in this section.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
            <!-- Empty state fallback when search yields no match -->
            <div id="noSearchMatchMsg" style="display: none; text-align: center; padding: 30px; color: #64748b; font-size: 0.88rem;">
                No matching students found for your search query.
            </div>
        </div>

    </div>

</div>

<script>
    const appSidebar = document.getElementById('appSidebar');
    const closeSidebarBtn = document.getElementById('closeSidebarBtn');
    const openSidebarBtn = document.getElementById('openSidebarBtn');

    closeSidebarBtn.addEventListener('click', () => {
        appSidebar.classList.add('collapsed');
    });

    openSidebarBtn.addEventListener('click', () => {
        appSidebar.classList.remove('collapsed');
    });

    const secBtn = document.getElementById('secDropdownBtn');
    const secMenu = document.getElementById('secDropdownMenu');

    secBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        secMenu.classList.toggle('show');
    });

    window.addEventListener('click', () => {
        secMenu.classList.remove('show');
    });

    // Real-Time Table Filtering Engine
    function filterStudentTable() {
        const input = document.getElementById('studentSearchInput');
        const filter = input.value.toLowerCase().trim();
        const table = document.getElementById('studentTable');
        const tr = table.getElementsByTagName('tr');
        const noMatchMsg = document.getElementById('noSearchMatchMsg');
        let visibleCount = 0;

        for (let i = 1; i < tr.length; i++) {
            const nameTd = tr[i].getElementsByClassName('student-name')[0];
            const idTd = tr[i].getElementsByClassName('student-id')[0];
            
            if (nameTd && idTd) {
                const nameText = nameTd.textContent || nameTd.innerText;
                const idText = idTd.textContent || idTd.innerText;

                if (nameText.toLowerCase().indexOf(filter) > -1 || idText.toLowerCase().indexOf(filter) > -1) {
                    tr[i].style.display = "";
                    visibleCount++;
                } else {
                    tr[i].style.display = "none";
                }
            }
        }

        if (visibleCount === 0 && tr.length > 1) {
            noMatchMsg.style.display = "block";
            table.style.display = "none";
        } else {
            noMatchMsg.style.display = "none";
            table.style.display = "table";
        }
    }

    // Custom Chart.js Plugin to Draw Live Center Percentage Text in Doughnut Rings
    const centerTextPlugin = {
        id: 'centerText',
        beforeDraw(chart) {
            if (chart.config.options.elements && chart.config.options.elements.center) {
                const ctx = chart.ctx;
                const centerConfig = chart.config.options.elements.center;
                const fontStyle = centerConfig.fontStyle || 'Poppins';
                const text = centerConfig.text;
                const subText = centerConfig.subText || '';
                
                ctx.restore();
                const fontSize = (centerConfig.fontSize || 22).toFixed(2);
                ctx.font = `600 ${fontSize}px ${fontStyle}`;
                ctx.fillStyle = centerConfig.color || '#0f172a';
                ctx.textBaseline = 'middle';

                const textWidth = ctx.measureText(text).width;
                const centerX = (chart.chartArea.left + chart.chartArea.right) / 2;
                const centerY = (chart.chartArea.top + chart.chartArea.bottom) / 2;

                ctx.fillText(text, centerX - textWidth / 2, centerY - 6);

                if (subText) {
                    ctx.font = `400 10px ${fontStyle}`;
                    ctx.fillStyle = '#64748b';
                    const subWidth = ctx.measureText(subText).width;
                    ctx.fillText(subText, centerX - subWidth / 2, centerY + 12);
                }
                ctx.save();
            }
        }
    };

    Chart.register(centerTextPlugin);

    // Render Large Overview Doughnut Chart with Custom Bouncy Animation & Center Text
    const overallCtx = document.getElementById('overallDoughnutChart').getContext('2d');
    new Chart(overallCtx, {
        type: 'doughnut',
        data: {
            labels: ['Present', 'Late', 'Absent'],
            datasets: [{
                data: [<?php echo $section_total_present; ?>, <?php echo $section_total_late; ?>, <?php echo $section_total_absences; ?>],
                backgroundColor: ['#16a34a', '#f59e0b', '#e11d48'],
                borderWidth: 3,
                borderColor: '#ffffff',
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
                duration: 1400,
                easing: 'easeOutQuart'
            },
            elements: {
                center: {
                    text: '<?php echo $attendance_rate; ?>%',
                    subText: 'Attendance Rate',
                    color: '#0f172a',
                    fontSize: 20
                }
            },
            plugins: {
                legend: { 
                    position: 'bottom', 
                    labels: { 
                        boxWidth: 12, 
                        font: { size: 11, family: 'Poppins' },
                        color: '#334155'
                    } 
                },
                tooltip: {
                    backgroundColor: 'rgba(15, 23, 42, 0.95)',
                    titleFont: { family: 'Poppins', size: 12 },
                    bodyFont: { family: 'Poppins', size: 12 },
                    padding: 12,
                    cornerRadius: 8
                }
            }
        }
    });
</script>

</body>
</html>