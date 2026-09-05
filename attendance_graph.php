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

// Year handling for analytics
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$prev_year = $selected_year - 1;
$next_year = $selected_year + 1;

// Fetch monthly attendance data for the selected section and year
$monthly_counts = array_fill(1, 12, 0);
$total_year_checkins = 0;
$active_months_count = 0;
$peak_month_name = 'None';
$peak_month_count = 0;

$months_full = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

if (!empty($current_section)) {
    $query = "
        SELECT MONTH(STR_TO_DATE(a.date, '%Y-%m-%d')) as m_num, COUNT(a.id) as cnt
        FROM attendance a
        JOIN student s ON a.student_id = s.id
        WHERE s.section = '$current_section' AND s.adviser_id = $current_adviser_id AND YEAR(STR_TO_DATE(a.date, '%Y-%m-%d')) = $selected_year
        GROUP BY m_num
    ";
    $res = mysqli_query($conn, $query);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $m = (int)$row['m_num'];
            $c = (int)$row['cnt'];
            if ($m >= 1 && $m <= 12) {
                $monthly_counts[$m] = $c;
                $total_year_checkins += $c;
                $active_months_count++;
                if ($c > $peak_month_count) {
                    $peak_month_count = $c;
                    $peak_month_name = $months_full[$m];
                }
            }
        }
    }
}
$max_count = max(array_values($monthly_counts)) > 0 ? max(array_values($monthly_counts)) : 1;

// Fetch attendance categorized by each section for the selected year
$section_analytics = [];
$cat_query = "
    SELECT s.section, COUNT(a.id) as total_checkins
    FROM student s
    LEFT JOIN attendance a ON s.id = a.student_id AND YEAR(STR_TO_DATE(a.date, '%Y-%m-%d')) = $selected_year
    WHERE s.adviser_id = $current_adviser_id
    GROUP BY s.section
    ORDER BY s.section ASC
";
$cat_res = mysqli_query($conn, $cat_query);
if ($cat_res) {
    while ($row = mysqli_fetch_assoc($cat_res)) {
        $section_analytics[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Analytics Dashboard</title>
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
   ENHANCED FORMAL ANIMATIONS
   =========================== */
@keyframes smoothFadeInUp {
    0% {
        opacity: 0;
        transform: translateY(25px);
    }
    100% {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes smoothFadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes barGrowUp {
    0% {
        height: 0%;
        opacity: 0.3;
    }
    100% {
        /* height set inline */
        opacity: 1;
    }
}

@keyframes formalPulse {
    0% { box-shadow: 0 0 0 0 rgba(2, 132, 199, 0.3); }
    70% { box-shadow: 0 0 0 10px rgba(2, 132, 199, 0); }
    100% { box-shadow: 0 0 0 0 rgba(2, 132, 199, 0); }
}

@keyframes floatIndicator {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-4px); }
}

/* Apply staggered animations to main blocks */
.main-app-header, .top-action-bar {
    animation: smoothFadeInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) both;
}

.dashboard-grid {
    animation: smoothFadeInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) both;
}

.content-card:nth-of-type(2) {
    animation: smoothFadeInUp 1s cubic-bezier(0.16, 1, 0.3, 1) both;
}

/* ===========================
   TOP GLOBAL HEADER STYLES
   =========================== */
.main-app-header {
    background: rgba(255, 255, 255, 0.88);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.4);
    border-radius: 12px;
    padding: 18px 24px;
    margin-bottom: 25px;
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    align-items: center;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
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

/* SIDEBAR UNCHANGED PERMITTED SPECIFICATION */
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
    animation: smoothFadeIn 0.3s ease-out;
}

.portal-body .app-sidebar.collapsed ~ .external-open-sidebar {
    display: block;
}

/* Custom Section Dropdown Styles with Animation */
.portal-body .custom-sec-dropdown { 
    position: relative; 
    display: inline-block; 
}

.portal-body .sec-dropdown-btn { 
    padding: 10px 16px; 
    background: rgba(255, 255, 255, 0.9); 
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
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.portal-body .sec-dropdown-btn:hover {
    background: #ffffff;
    border-color: #0284c7;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(2, 132, 199, 0.15);
    animation: formalPulse 2s infinite;
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
    background: rgba(255, 255, 255, 0.98); 
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
    transition: background 0.2s ease, padding-left 0.2s ease; 
}

.portal-body .sec-dropdown-item:hover { 
    background: rgba(240, 249, 255, 0.8); 
    padding-left: 18px;
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

/* ===========================
   ENHANCED CONTENT STYLING
   =========================== */
.top-action-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: rgba(255, 255, 255, 0.88);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.4);
    border-radius: 12px;
    padding: 18px 24px;
    margin-bottom: 25px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
}

.action-bar-title h1 {
    font-size: 1.25rem;
    color: #0f172a;
    font-weight: 600;
}

.action-bar-title p {
    font-size: 0.8rem;
    color: rgba(15, 23, 42, 0.7);
    margin-top: 2px;
}

.toolbar-group {
    display: flex;
    align-items: center;
    gap: 12px;
}

/* Year Switcher with Soft Hover Transitions */
.year-switcher-box {
    display: flex;
    align-items: center;
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(205, 213, 225, 0.8);
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
    transition: all 0.25s ease;
}

.year-switcher-box:hover {
    border-color: #cbd5e1;
    box-shadow: 0 6px 15px rgba(0,0,0,0.06);
}

.year-nav-link {
    padding: 8px 12px;
    background: transparent;
    color: #475569;
    text-decoration: none;
    font-size: 0.8rem;
    transition: all 0.2s ease;
}

.year-nav-link:hover {
    background: #0284c7;
    color: #ffffff;
}

.year-label-val {
    padding: 8px 14px;
    background: #ffffff;
    color: #0f172a;
    font-weight: 600;
    font-size: 0.82rem;
    border-left: 1px solid rgba(205, 213, 225, 0.8);
    border-right: 1px solid rgba(205, 213, 225, 0.8);
}

/* LAYOUT GRIDS */
.dashboard-grid {
    display: grid;
    grid-template-columns: 2.8fr 1.2fr;
    gap: 20px;
    margin-bottom: 25px;
}

.content-card {
    background: rgba(255, 255, 255, 0.88);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid rgba(255, 255, 255, 0.6);
    border-radius: 14px;
    padding: 24px;
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.content-card:hover {
    box-shadow: 0 16px 45px rgba(0, 0, 0, 0.14);
}

.card-header-flex {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 1px solid rgba(226, 232, 240, 0.8);
}

.card-heading-title {
    font-size: 1rem;
    font-weight: 600;
    color: #0f172a;
}

/* MODERN DYNAMIC BAR GRAPH WITH SMOOTH HOVER EFFECTS */
.chart-body-wrapper {
    display: flex;
    align-items: flex-end;
    height: 260px;
    margin-top: 15px;
}

.chart-y-axis {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    height: 100%;
    padding-right: 12px;
    font-size: 0.72rem;
    color: #64748b;
    text-align: right;
    font-weight: 500;
}

.chart-graph-canvas {
    flex-grow: 1;
    height: 100%;
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    border-left: 1px solid rgba(203, 213, 225, 0.8);
    border-bottom: 1px solid rgba(203, 213, 225, 0.8);
    padding: 0 10px;
    position: relative;
}

.canvas-grid-line {
    position: absolute;
    left: 0;
    right: 0;
    border-top: 1px dashed rgba(226, 232, 240, 0.8);
    pointer-events: none;
}

.chart-bar-column {
    display: flex;
    flex-direction: column;
    align-items: center;
    flex: 1;
    height: 100%;
    justify-content: flex-end;
    position: relative;
}

.chart-vertical-bar {
    width: 18px;
    background: linear-gradient(180deg, #38bdf8 0%, #0284c7 100%);
    border-radius: 6px 6px 0 0;
    position: relative;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(2, 132, 199, 0.2);
    animation: barGrowUp 1s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    transition: background 0.3s ease, transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.3s ease;
}

.chart-vertical-bar:hover {
    background: linear-gradient(180deg, #7dd3fc 0%, #0284c7 100%);
    transform: scaleY(1.04) scaleX(1.1);
    box-shadow: 0 8px 20px rgba(2, 132, 199, 0.4);
}

.chart-bar-tip {
    position: absolute;
    top: -35px;
    left: 50%;
    transform: translateX(-50%) translateY(5px);
    background: #0f172a;
    color: #ffffff;
    font-size: 0.68rem;
    padding: 4px 8px;
    border-radius: 6px;
    white-space: nowrap;
    opacity: 0;
    pointer-events: none;
    box-shadow: 0 6px 15px rgba(0,0,0,0.15);
    transition: opacity 0.25s ease, transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    z-index: 10;
}

.chart-vertical-bar:hover .chart-bar-tip {
    opacity: 1;
    transform: translateX(-50%) translateY(0);
}

.chart-x-axis-lbl {
    margin-top: 8px;
    font-size: 0.72rem;
    color: #475569;
    font-weight: 500;
    transition: color 0.2s ease;
}

.chart-bar-column:hover .chart-x-axis-lbl {
    color: #0284c7;
    font-weight: 600;
}

/* STATISTICAL SUMMARY STACK WITH HOVER DEPTH */
.stats-column-stack {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.stat-item-box {
    background: rgba(248, 250, 252, 0.85);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(203, 213, 225, 0.8);
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.02);
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.stat-item-box:hover {
    background: #ffffff;
    border-color: #0284c7;
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(2, 132, 199, 0.1);
}

.stat-item-label {
    font-size: 0.75rem;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    font-weight: 600;
    margin-bottom: 6px;
}

.stat-item-number {
    font-size: 1.35rem;
    font-weight: 600;
    color: #0f172a;
    transition: color 0.2s ease;
}

.stat-item-box:hover .stat-item-number {
    color: #0284c7;
}

/* SECTION BREAKDOWN TILES WITH LIVELY HOVER PHYSICS */
.section-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
    gap: 15px;
}

.section-card-box {
    background: rgba(248, 250, 252, 0.85);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(203, 213, 225, 0.8);
    border-radius: 12px;
    padding: 16px;
    text-align: center;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0,0,0,0.02);
    transition: all 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.section-card-box:hover {
    background: #ffffff;
    border-color: #0284c7;
    transform: translateY(-5px) scale(1.02);
    box-shadow: 0 12px 30px rgba(2, 132, 199, 0.18);
}

.section-card-box.highlighted {
    background: rgba(240, 249, 255, 0.95);
    border-color: #0284c7;
    box-shadow: 0 6px 20px rgba(2, 132, 199, 0.12);
    animation: floatIndicator 3s ease-in-out infinite;
}

.section-card-box h4 {
    font-size: 0.85rem;
    color: #0f172a;
    font-weight: 600;
    margin-bottom: 4px;
}

.section-card-box .count-badge {
    font-size: 1.2rem;
    font-weight: 600;
    color: #0284c7;
    transition: transform 0.3s ease;
}

.section-card-box:hover .count-badge {
    transform: scale(1.1);
}

.section-card-box p {
    font-size: 0.72rem;
    color: #64748b;
    margin-top: 2px;
}

@media(max-width: 900px) {
    .portal-body .main-wrapper { margin-left: 0; width: 100%; padding: 15px; }
    .dashboard-grid { grid-template-columns: 1fr; }
    .top-action-bar { flex-direction: column; align-items: stretch; gap: 15px; }
}
    </style>
</head>
<body class="portal-body">

<!-- LEFT COLLAPSIBLE SIDEBAR WITH ICONS (UNCHANGED) -->
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
            <a href="attendance_graph.php" class="nav-view-btn active-nav-btn">
                <i class="fa-solid fa-chart-line"></i> Monthly Record
            </a>
            <a href="dashboard.php" class="nav-view-btn">
                <i class="fa-solid fa-house"></i> Main Calendar
            </a>
            <a href="student_status.php" class="nav-view-btn">
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

<!-- Button to reopen sidebar when closed (UNCHANGED) -->
<button type="button" class="toggle-sidebar-btn external-open-sidebar" id="openSidebarBtn" title="Open Sidebar">&#x2630; Menu</button>

<div class="main-wrapper">
    
    <!-- TOP ACTION TOOLBAR -->
    <div class="top-action-bar">
        <div class="action-bar-title">
            <h1>Attendance Analytics</h1>
            <p>Active Section Overview: <span style="color: #0284c7; font-weight: 600;"><?php echo htmlspecialchars($current_section); ?></span></p>
        </div>
        
        <div class="toolbar-group">
            <!-- Year Switcher Component -->
            <div class="year-switcher-box">
                <a href="?year=<?php echo $prev_year; ?>&section=<?php echo urlencode($current_section); ?>" class="year-nav-link"><i class="fa-solid fa-chevron-left"></i></a>
                <span class="year-label-val"><?php echo $selected_year; ?></span>
                <a href="?year=<?php echo $next_year; ?>&section=<?php echo urlencode($current_section); ?>" class="year-nav-link"><i class="fa-solid fa-chevron-right"></i></a>
            </div>

            <!-- Section Selector Dropdown -->
            <div class="custom-sec-dropdown">
                <button type="button" class="sec-dropdown-btn" id="secDropdownBtn">
                    <i class="fa-solid fa-layer-group" style="color: #0284c7;"></i>
                    <span>Section: <?php echo htmlspecialchars($current_section ?: 'Select'); ?></span>
                    <i class="fa-solid fa-chevron-down" style="font-size: 0.75rem; color: #64748b; margin-left: 4px;"></i>
                </button>
                <div class="sec-dropdown-menu" id="secDropdownMenu">
                    <?php if($section_result && mysqli_num_rows($section_result) > 0): while($sec = mysqli_fetch_array($section_result)): 
                        $sec_val = $sec[0];
                        if(empty($sec_val)) continue;
                        $is_active = ($sec_val == $current_section);
                    ?>
                        <div class="sec-dropdown-item">
                            <span class="sec-title <?php echo $is_active ? 'active' : ''; ?>" onclick="window.location.href='?section=<?php echo urlencode($sec_val); ?>&year=<?php echo $selected_year; ?>';">
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

    <!-- MAIN DASHBOARD SPLIT CONTENT GRID -->
    <div class="dashboard-grid">
        <!-- Monthly Distribution Bar Chart Card -->
        <div class="content-card">
            <div class="card-header-flex">
                <h3 class="card-heading-title">Monthly Check-in Analytics (<?php echo $selected_year; ?>)</h3>
                <span style="font-size: 0.76rem; color: #64748b; font-weight: 500;">Frequency Trend</span>
            </div>

            <?php
            $months_short = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            
            $y_max = $max_count;
            $y_75  = round($max_count * 0.75);
            $y_50  = round($max_count * 0.50);
            $y_25  = round($max_count * 0.25);
            ?>

            <div class="chart-body-wrapper">
                <div class="chart-y-axis">
                    <span><?php echo $y_max; ?></span>
                    <span><?php echo $y_75; ?></span>
                    <span><?php echo $y_50; ?></span>
                    <span><?php echo $y_25; ?></span>
                    <span>0</span>
                </div>

                <div class="chart-graph-canvas">
                    <div class="canvas-grid-line" style="bottom: 0%;"></div>
                    <div class="canvas-grid-line" style="bottom: 25%;"></div>
                    <div class="canvas-grid-line" style="bottom: 50%;"></div>
                    <div class="canvas-grid-line" style="bottom: 75%;"></div>
                    <div class="canvas-grid-line" style="bottom: 100%;"></div>

                    <?php
                    for ($m = 1; $m <= 12; $m++) {
                        $count = $monthly_counts[$m];
                        $height_pct = ($max_count > 0) ? ($count / $max_count) * 85 : 0;
                        if ($count > 0 && $height_pct < 5) { $height_pct = 5; }
                    ?>
                    <div class="chart-bar-column">
                        <div class="chart-vertical-bar" style="height: <?php echo $height_pct; ?>%;">
                            <div class="chart-bar-tip"><?php echo $count; ?> check-ins</div>
                        </div>
                        <span class="chart-x-axis-lbl"><?php echo $months_short[$m-1]; ?></span>
                    </div>
                    <?php } ?>
                </div>
            </div>
        </div>

        <!-- Metric Summary Panel -->
        <div class="stats-column-stack">
            <div class="stat-item-box">
                <div class="stat-item-label">Total Annual Check-ins</div>
                <div class="stat-item-number"><?php echo number_format($total_year_checkins); ?></div>
            </div>
            <div class="stat-item-box">
                <div class="stat-item-label">Peak Performance Month</div>
                <div class="stat-item-number" style="font-size: 1.1rem; margin-top: 2px;"><?php echo htmlspecialchars($peak_month_name); ?> <span style="font-size: 0.76rem; color: #64748b; font-weight: normal;">(<?php echo $peak_month_count; ?>)</span></div>
            </div>
            <div class="stat-item-box">
                <div class="stat-item-label">Active Reporting Span</div>
                <div class="stat-item-number"><?php echo $active_months_count; ?> / 12 Months</div>
            </div>
        </div>
    </div>

    <!-- COMPARATIVE SECTION SUMMARY CARD -->
    <div class="content-card">
        <div class="card-header-flex">
            <h3 class="card-heading-title">Comparative Section Performance (<?php echo $selected_year; ?>)</h3>
        </div>
        
        <div class="section-cards-grid">
            <?php if (!empty($section_analytics)): foreach ($section_analytics as $sec_data): 
                $is_current_tile = ($sec_data['section'] == $current_section);
            ?>
                <div class="section-card-box <?php echo $is_current_tile ? 'highlighted' : ''; ?>" onclick="window.location.href='?section=<?php echo urlencode($sec_data['section']); ?>&year=<?php echo $selected_year; ?>';">
                    <h4>Section: <?php echo htmlspecialchars($sec_data['section']); ?></h4>
                    <div class="count-badge"><?php echo number_format($sec_data['total_checkins']); ?></div>
                    <p>Total Check-ins</p>
                </div>
            <?php endforeach; else: ?>
                <p style="color: #64748b; font-size: 0.85rem;">No active section attendance records found.</p>
            <?php endif; ?>
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
</script>

</body>
</html>