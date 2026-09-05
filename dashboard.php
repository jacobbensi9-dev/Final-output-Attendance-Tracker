<?php
    session_start();

    if(!isset($_SESSION['admin']) || !isset($_SESSION['adviser_id'])){
        header("Location: index.php");
        exit();
    }

    include 'connection.php';

    $current_adviser_id = (int)$_SESSION['adviser_id'];

    // --- EDIT ATTENDANCE TIME ENGINE WITH AUTOMATIC STATUS RECALCULATION ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_attendance_time'])) {
        $att_id_to_edit = (int)$_POST['attendance_id'];
        $target_date_ref = isset($_POST['view_date']) ? mysqli_real_escape_string($conn, $_POST['view_date']) : date('Y-m-d');
        
        $raw_time_in = $_POST['edit_time_in'];
        $raw_time_out = $_POST['edit_time_out'];
        
        $time_in_formatted = !empty($raw_time_in) ? (strlen($raw_time_in) === 5 ? $raw_time_in . ':00' : $raw_time_in) : null;
        $time_out_formatted = !empty($raw_time_out) ? (strlen($raw_time_out) === 5 ? $raw_time_out . ':00' : $raw_time_out) : null;

        // Fetch section start time to dynamically determine status upon edit
        $sec_info_q = mysqli_query($conn, "
            SELECT sec.start_time 
            FROM attendance a 
            JOIN student s ON a.student_id = s.id 
            JOIN section sec ON s.section = sec.section_name 
            WHERE a.id = $att_id_to_edit
        ");
        $sec_info = mysqli_fetch_assoc($sec_info_q);
        $sched_start = $sec_info['start_time'] ?? '03:36:00';

        // Calculate new status based on edited time-in (10 minutes limit rule)
        $new_status = 'Present';
        if ($time_in_formatted) {
            $in_ts = strtotime($time_in_formatted);
            $start_ts = strtotime($sched_start);
            $cutoff_ts = $start_ts + (10 * 60); // 10 minutes limit

            if ($in_ts > $cutoff_ts) {
                $new_status = 'Absent';
            } elseif ($in_ts > $start_ts + 60) {
                $new_status = 'Late/Present';
            }
        }
        
        $time_in_sql = $time_in_formatted ? "'$time_in_formatted'" : "NULL";
        $time_out_sql = $time_out_formatted ? "'$time_out_formatted'" : "NULL";
        
        mysqli_query($conn, "
            UPDATE attendance a 
            JOIN student s ON a.student_id = s.id 
            SET a.time_in = $time_in_sql, a.time_out = $time_out_sql, a.status = '$new_status'
            WHERE a.id = $att_id_to_edit AND s.adviser_id = $current_adviser_id
        ");
        
        $nav_m_redirect = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
        $nav_y_redirect = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
        header("Location: dashboard.php?month=$nav_m_redirect&year=$nav_y_redirect&section=" . urlencode($_SESSION['section'] ?? '') . "&view_date=" . urlencode($target_date_ref));
        exit();
    }

    // --- DELETE INDIVIDUAL PRESENT ATTENDANCE ENGINE ---
    if (isset($_GET['delete_attendance_id']) && !empty($_GET['delete_attendance_id'])) {
        $att_id_to_delete = (int)$_GET['delete_attendance_id'];
        $target_date_ref = isset($_GET['view_date']) ? mysqli_real_escape_string($conn, $_GET['view_date']) : date('Y-m-d');
        
        mysqli_query($conn, "
            DELETE a FROM attendance a 
            JOIN student s ON a.student_id = s.id 
            WHERE a.id = $att_id_to_delete AND s.adviser_id = $current_adviser_id
        ");
        
        $nav_m_redirect = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
        $nav_y_redirect = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
        header("Location: dashboard.php?month=$nav_m_redirect&year=$nav_y_redirect&section=" . urlencode($_SESSION['section'] ?? '') . "&view_date=" . urlencode($target_date_ref));
        exit();
    }

    // --- SECTION HANDLING ENGINE WITH DUPLICATE & TIME CONFLICT CHECK ---
    if (isset($_GET['section']) && !empty($_GET['section'])) {
        $_SESSION['section'] = mysqli_real_escape_string($conn, $_GET['section']);
    }

    if (isset($_GET['delete_section']) && !empty($_GET['delete_section'])) {
        $sec_to_delete = mysqli_real_escape_string($conn, $_GET['delete_section']);
        mysqli_query($conn, "DELETE FROM section WHERE section_name = '$sec_to_delete' AND adviser_id = $current_adviser_id");
        
        if (isset($_SESSION['section']) && $_SESSION['section'] === $sec_to_delete) {
            unset($_SESSION['section']);
        }
        header("Location: dashboard.php");
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_section'])) {
        $new_sec_name = mysqli_real_escape_string($conn, trim($_POST['new_section_name']));
        
        $raw_start = $_POST['start_time'];
        $raw_end = $_POST['end_time'];
        $start_time = mysqli_real_escape_string($conn, (strlen($raw_start) === 5 ? $raw_start . ':00' : $raw_start));
        $end_time = mysqli_real_escape_string($conn, (strlen($raw_end) === 5 ? $raw_end . ':00' : $raw_end));
        
        $original_sec_name = isset($_POST['original_section_name']) ? mysqli_real_escape_string($conn, trim($_POST['original_section_name'])) : '';

        if (!empty($new_sec_name)) {
            $check_dup_query = "SELECT * FROM section WHERE section_name = '$new_sec_name' AND adviser_id = $current_adviser_id";
            if (!empty($original_sec_name)) {
                $check_dup_query .= " AND section_name != '$original_sec_name'";
            }
            $dup_result = mysqli_query($conn, $check_dup_query);

            $check_time_query = "SELECT * FROM section WHERE adviser_id = $current_adviser_id AND (TIME(start_time) < TIME('$end_time') AND TIME(end_time) > TIME('$start_time'))";
            if (!empty($original_sec_name)) {
                $check_time_query .= " AND section_name != '$original_sec_name'";
            }
            $time_result = mysqli_query($conn, $check_time_query);

            if (mysqli_num_rows($dup_result) > 0) {
                header("Location: dashboard.php?sec_error=exists&sec_name=" . urlencode($new_sec_name) . "&st=" . urlencode($raw_start) . "&et=" . urlencode($raw_end));
                exit();
            } else if (mysqli_num_rows($time_result) > 0) {
                header("Location: dashboard.php?sec_error=time_exists&sec_name=" . urlencode($new_sec_name) . "&st=" . urlencode($raw_start) . "&et=" . urlencode($raw_end));
                exit();
            } else {
                if (!empty($original_sec_name) && $original_sec_name !== $new_sec_name) {
                    mysqli_query($conn, "UPDATE student SET section = '$new_sec_name' WHERE section = '$original_sec_name' AND adviser_id = $current_adviser_id");
                    mysqli_query($conn, "DELETE FROM section WHERE section_name = '$original_sec_name' AND adviser_id = $current_adviser_id");
                }

                @mysqli_query($conn, "INSERT INTO section (section_name, adviser_id, start_time, end_time) 
                    VALUES ('$new_sec_name', $current_adviser_id, '$start_time', '$end_time') 
                    ON DUPLICATE KEY UPDATE start_time='$start_time', end_time='$end_time'");
                $_SESSION['section'] = $new_sec_name;
                header("Location: dashboard.php");
                exit();
            }
        }
    }

    $section_result = mysqli_query($conn, "
        SELECT section_name, start_time, end_time FROM section WHERE adviser_id = $current_adviser_id ORDER BY section_name ASC
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

    $nav_month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
    if ($nav_month < 1 || $nav_month > 12) {
        $nav_month = (int)date('m');
    }

    $nav_year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
    if ($nav_year < 2000 || $nav_year > 2099) {
        $nav_year = (int)date('Y');
    }

    // --- NO CLASSES TOGGLE ENGINE ---
    if (isset($_GET['toggle_no_class']) && !empty($_GET['toggle_date'])) {
        $target_date = mysqli_real_escape_string($conn, $_GET['toggle_date']);
        
        $check_nc = mysqli_query($conn, "SELECT id FROM no_classes WHERE section_name = '$current_section' AND adviser_id = $current_adviser_id AND date = '$target_date'");
        
        if (mysqli_num_rows($check_nc) > 0) {
            mysqli_query($conn, "DELETE FROM no_classes WHERE section_name = '$current_section' AND adviser_id = $current_adviser_id AND date = '$target_date'");
        } else {
            mysqli_query($conn, "INSERT INTO no_classes (section_name, adviser_id, date) VALUES ('$current_section', $current_adviser_id, '$target_date')");
        }
        
        header("Location: dashboard.php?month=$nav_month&year=$nav_year&section=" . urlencode($current_section));
        exit();
    }

    $sched_q = mysqli_query($conn, "SELECT start_time, end_time FROM section WHERE section_name = '$current_section' AND adviser_id = $current_adviser_id");
    $sched_data = mysqli_fetch_assoc($sched_q);
    $sec_start = $sched_data['start_time'] ?? '08:00:00';
    $sec_end = $sched_data['end_time'] ?? '09:00:00';

    $month_display_name = date('F', mktime(0, 0, 0, $nav_month, 10));
    $current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Institutional Attendance Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Inter:wght@400;500;600;700;800&display=swap');

* { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }

body.portal-body { 
    display: flex; 
    margin: 0; 
    background: url('asians.jpg') no-repeat center center fixed !important;
    background-size: cover !important;
    font-family: 'Poppins', sans-serif; 
    overflow-x: hidden; 
}

/* Glassmorphism Sidebar */
.portal-body .app-sidebar {
    width: 280px;
    background: rgba(255, 255, 255, 0.75);
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
    box-shadow: 8px 0 32px 0 rgba(31, 38, 135, 0.1);
}

.portal-body .app-sidebar.collapsed {
    transform: translateX(-100%);
    width: 0;
    padding: 0;
    border-right: none;
    overflow: hidden;
}

/* 1. Centered Sidebar Brand Section */
.portal-body .sidebar-top-header { 
    display: flex; 
    flex-direction: column; 
    align-items: center; 
    text-align: center; 
    position: relative; 
    margin-bottom: 25px; 
}

.portal-body .sidebar-brand { 
    display: flex; 
    flex-direction: column; 
    align-items: center; 
    text-align: center; 
    gap: 8px; 
}

/* 2. Balanced 48px by 48px Logo with Circular Border-Radius and Subtle Shadow */
.portal-body .sidebar-logo { 
    width: 48px; 
    height: 48px; 
    border-radius: 50%; 
    object-fit: cover; 
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    transition: transform 0.3s ease; 
}

.portal-body .sidebar-brand:hover .sidebar-logo { transform: rotate(15deg) scale(1.1); }

.portal-body .sidebar-brand h3 { color: #0f172a; margin: 0; font-size: 1.15rem; font-weight: 600; }
.portal-body .sidebar-brand p { color: #64748b; margin: 0; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.5px; }

/* 3. Repositioned Sidebar Close Button to the Absolute Top-Right Corner */
.portal-body .toggle-sidebar-btn {
    background: rgba(255, 255, 255, 0.6);
    backdrop-filter: blur(4px);
    border: 1px solid rgba(203, 213, 225, 0.6);
    color: #334155;
    padding: 5px 9px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.85rem;
    transition: all 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.portal-body #closeSidebarBtn {
    position: absolute;
    top: 0;
    right: 0;
}

.portal-body .toggle-sidebar-btn:hover { background: rgba(255, 255, 255, 0.9); color: #0f172a; transform: scale(1.12); }

.portal-body .nav-menu-wrapper { display: flex; flex-direction: column; gap: 8px; margin-top: 10px; }

.portal-body .nav-view-btn { 
    background: rgba(255, 255, 255, 0.4); 
    backdrop-filter: blur(4px);
    border: 1px solid rgba(255, 255, 255, 0.6); 
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

.portal-body .nav-view-btn .nav-icon {
    width: 18px;
    height: 18px;
    stroke: currentColor;
    stroke-width: 2;
    fill: none;
    stroke-linecap: round;
    stroke-linejoin: round;
    transition: transform 0.25s ease;
    flex-shrink: 0;
}

.portal-body .nav-view-btn:hover { border-color: rgba(203, 213, 225, 0.8); color: #0f172a; background: rgba(255, 255, 255, 0.8); transform: translateX(6px); }
.portal-body .nav-view-btn:hover .nav-icon { transform: scale(1.15); }

.portal-body .nav-view-btn.active-nav-btn { 
    background: rgba(30, 41, 59, 0.9); 
    backdrop-filter: blur(4px);
    color: white; 
    border-color: rgba(30, 41, 59, 0.9); 
    box-shadow: 0 4px 14px rgba(30, 41, 59, 0.25); 
    font-weight: 600;
}

.portal-body .nav-logout-btn { color: #ef4444 !important; border-color: rgba(254, 202, 202, 0.8) !important; }
.portal-body .nav-logout-btn:hover { background: rgba(254, 242, 242, 0.8) !important; border-color: rgba(252, 165, 165, 0.8) !important; transform: translateX(6px); }

.portal-body .sidebar-footer { font-size: 0.72rem; color: #64748b; text-align: center; margin-top: 15px; border-top: 1px solid rgba(241, 245, 249, 0.6); padding-top: 15px; }

.portal-body .main-wrapper {
    flex-grow: 1;
    margin-left: 280px;
    transition: margin-left 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    padding: 30px 40px;
    box-sizing: border-box;
    width: calc(100% - 280px);
    min-height: 100vh;
    background: transparent;
    animation: fadeInPage 0.5s ease-out forwards;
}

@keyframes fadeInPage {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.portal-body .app-sidebar.collapsed ~ .main-wrapper { margin-left: 0; width: 100%; }
.portal-body .external-open-sidebar { position: fixed; top: 20px; left: 20px; z-index: 999; display: none; }
.portal-body .app-sidebar.collapsed ~ .external-open-sidebar { display: block; }

/* Glassmorphism Header Card */
.top-header-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 24px 30px;
    margin-bottom: 25px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
    display: flex;
    justify-content: space-between;
    align-items: flex-start; /* <-- UPDATED TO ALIGN TO TOP */
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.top-header-card:hover {
    box-shadow: 0 12px 40px 0 rgba(31, 38, 135, 0.15);
}

.sec-dropdown-btn { 
    background: rgba(37, 99, 235, 0.9); 
    backdrop-filter: blur(4px);
    color: #ffffff;
    border: 1px solid rgba(29, 78, 216, 0.9); 
    padding: 8px 16px; 
    border-radius: 6px; 
    font-size: 0.85rem; 
    font-weight: 600; 
    cursor: pointer; 
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
}
.sec-dropdown-btn:hover { 
    background: rgba(29, 78, 216, 0.95); 
    box-shadow: 0 6px 16px rgba(37, 99, 235, 0.35);
    transform: translateY(-1px);
}

.dots-menu-btn {
    background: rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(4px);
    border: 1px solid rgba(203, 213, 225, 0.7);
    color: #2563eb;
    padding: 8px 12px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 1.2rem;
    font-weight: bold;
    line-height: 1;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}
.dots-menu-btn:hover { 
    background: rgba(255, 255, 255, 0.95); 
    border-color: #2563eb; 
    transform: rotate(90deg);
}

.dots-menu-wrapper { position: relative; display: inline-block; }

.dots-dropdown-content {
    display: none;
    position: absolute;
    right: 0;
    top: 115%;
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    min-width: 175px;
    box-shadow: 0 12px 32px 0 rgba(31, 38, 135, 0.15);
    border: 1px solid rgba(255, 255, 255, 0.6);
    border-radius: 8px;
    z-index: 1000;
    overflow: hidden;
    transform-origin: top right;
    animation: dropdownPop 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

@keyframes dropdownPop {
    from { opacity: 0; transform: scale(0.9) translateY(-10px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}

.dots-dropdown-content.show { display: block; }

.dots-dropdown-content button {
    width: 100%;
    text-align: left;
    padding: 10px 14px;
    background: none;
    border: none;
    font-size: 0.83rem;
    color: #334155;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
}

.dots-dropdown-content button:hover {
    background-color: rgba(240, 249, 255, 0.8);
    color: #0284c7;
    padding-left: 18px;
}

.dots-dropdown-content button:not(:last-child) {
    border-bottom: 1px solid rgba(241, 245, 249, 0.8);
}

.schedule-badge-info {
    background: rgba(239, 246, 255, 0.8);
    backdrop-filter: blur(4px);
    color: #2563eb;
    border: 1px solid rgba(191, 219, 254, 0.8);
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-block;
    margin-top: 6px;
    transition: transform 0.2s ease;
}
.schedule-badge-info:hover {
    transform: scale(1.03);
}

.calendar-desk-wrapper {
    position: relative;
    padding-top: 18px;
    margin-top: 15px;
}

.calendar-binder-bar {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 36px;
    z-index: 20;
    display: flex;
    justify-content: space-around;
    padding: 0 50px;
    pointer-events: none;
}

.binder-ring {
    width: 14px;
    height: 34px;
    background: linear-gradient(135deg, #e2e8f0 0%, #94a3b8 45%, #475569 50%, #cbd5e1 80%, #ffffff 100%);
    border-radius: 10px;
    box-shadow: 0 6px 8px rgba(0,0,0,0.35), inset 0 2px 2px rgba(255,255,255,0.8), inset 0 -2px 4px rgba(0,0,0,0.4);
    position: relative;
}

.binder-ring::after {
    content: '';
    position: absolute;
    top: 8px;
    left: 2px;
    right: 2px;
    height: 18px;
    background: rgba(0, 0, 0, 0.15);
    border-radius: 4px;
}

/* Glassmorphism Real Calendar Container */
.real-calendar-container {
    background: rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-radius: 12px;
    box-shadow: 
        0 8px 32px 0 rgba(31, 38, 135, 0.15),
        0 25px 50px -12px rgba(15, 23, 42, 0.25);
    overflow: hidden;
    position: relative;
    border: 1px solid rgba(255, 255, 255, 0.6);
}

.calendar-nav-header { 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    background: rgba(30, 41, 59, 0.85);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    padding: 28px 32px 20px 32px; 
    color: #ffffff;
    position: relative;
    border-bottom: 3px solid #0284c7;
    box-shadow: inset 0 -2px 10px rgba(0,0,0,0.3);
}

.calendar-nav-header .month-heading-title {
    font-size: 2rem;
    font-weight: 800;
    letter-spacing: -0.5px;
    text-transform: uppercase;
    color: #ffffff;
    display: flex;
    align-items: center;
    gap: 12px;
    text-shadow: 0 2px 4px rgba(0,0,0,0.5);
    font-family: 'Inter', sans-serif;
}

.calendar-nav-header .month-heading-title span {
    color: #38bdf8;
    font-weight: 300;
}

.calendar-grid-wrapper {
    padding: 0;
    background: rgba(241, 245, 249, 0.5);
}

.calendar-grid { 
    display: grid; 
    grid-template-columns: repeat(7, 1fr); 
    background: rgba(203, 213, 225, 0.6);
    gap: 1px;
    border-bottom: 1px solid rgba(203, 213, 225, 0.6);
}

.calendar-day-header { 
    text-align: center; 
    font-weight: 800; 
    color: #334155; 
    padding: 12px 8px; 
    font-size: 0.75rem; 
    text-transform: uppercase; 
    letter-spacing: 1.5px;
    background: rgba(255, 255, 255, 0.7);
}

.calendar-day-header.weekend-header {
    color: #e11d48;
}

.calendar-cell { 
    background: rgba(255, 255, 255, 0.85); 
    min-height: 118px; 
    padding: 10px; 
    display: flex; 
    flex-direction: column; 
    justify-content: space-between; 
    text-decoration: none; 
    color: #0f172a; 
    position: relative;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

.calendar-cell:hover { 
    background: rgba(255, 255, 255, 0.98);
    z-index: 5;
    transform: translateY(-3px) scale(1.02);
    box-shadow: 0 12px 25px -5px rgba(0, 0, 0, 0.15), 0 0 0 2px #0284c7;
    border-radius: 6px;
}

.calendar-cell.other-month {
    background: rgba(248, 250, 252, 0.5);
    opacity: 0.5;
}

.calendar-cell.today { 
    background: rgba(240, 249, 255, 0.9);
}

.calendar-cell .today-badge {
    font-size: 0.55rem;
    font-weight: 800;
    background: #0284c7;
    color: #ffffff;
    padding: 1px 5px;
    border-radius: 3px;
    display: inline-block;
    margin-bottom: 2px;
    animation: pulseBadge 2s infinite;
}

@keyframes pulseBadge {
    0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(2, 132, 199, 0.4); }
    70% { transform: scale(1.05); box-shadow: 0 0 0 6px rgba(2, 132, 199, 0); }
    100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(2, 132, 199, 0); }
}

.cal-date-num { 
    font-weight: 800; 
    font-size: 1.2rem; 
    color: #1e293b; 
    line-height: 1;
    font-family: 'Inter', sans-serif;
}

.cal-stats { 
    font-size: 0.72rem; 
    display: flex; 
    flex-direction: column; 
    gap: 4px; 
    margin-top: 6px; 
}

.badge-present-count { 
    color: #065f46; 
    background: rgba(236, 253, 245, 0.8); 
    padding: 4px 8px; 
    border-radius: 4px; 
    font-weight: 500; 
    display: flex;
    justify-content: space-between;
    align-items: center;
    border: none;
    font-size: 0.72rem;
    transition: transform 0.2s ease;
}
.badge-present-count:hover { transform: scale(1.02); }

.badge-present-count span {
    font-weight: 700;
    color: #047857;
    background: rgba(255, 255, 255, 0.9);
    padding: 0 5px;
    border-radius: 3px;
    font-size: 0.72rem;
    font-family: 'Inter', sans-serif;
    border: none;
}

.badge-absent-count { 
    color: #991b1b; 
    background: rgba(254, 242, 242, 0.8); 
    padding: 4px 8px; 
    border-radius: 4px; 
    font-weight: 500; 
    display: flex;
    justify-content: space-between;
    align-items: center;
    border: none;
    font-size: 0.72rem;
    transition: transform 0.2s ease;
}
.badge-absent-count:hover { transform: scale(1.02); }

.badge-absent-count span {
    font-weight: 700;
    color: #b91c1c;
    background: rgba(255, 255, 255, 0.9);
    padding: 0 5px;
    border-radius: 3px;
    font-size: 0.72rem;
    font-family: 'Inter', sans-serif;
    border: none;
}

@keyframes stampEntrance {
    0% { transform: scale(1.4) rotate(-12deg); opacity: 0; }
    60% { transform: scale(0.95) rotate(-3deg); opacity: 1; }
    100% { transform: scale(1) rotate(-4deg); opacity: 1; }
}

.no-class-stamp {
    color: #d97706;
    font-weight: 800;
    font-size: 0.75rem;
    text-align: center;
    padding: 5px 2px;
    background: rgba(254, 243, 199, 0.6);
    border: 2px dashed #f59e0b;
    border-radius: 4px;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    transform: rotate(-4deg);
    box-shadow: 0 2px 4px rgba(217, 119, 6, 0.12);
    animation: stampEntrance 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
}

.no-class-stamp i { font-size: 0.7rem; }

@keyframes modalSlideUp {
    from { opacity: 0; transform: translateY(20px) scale(0.97); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

.day-report-modal, .cal-modal, .modal-overlay { 
    display: none; 
    position: fixed; 
    top: 0; left: 0; width: 100%; height: 100%; 
    background: rgba(15,23,42,0.6); 
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    justify-content: center; align-items: center; z-index: 9999; padding: 20px; 
    animation: fadeInPage 0.25s ease-out forwards;
}

.day-report-content, .cal-box, .modal-content { 
    background: rgba(255, 255, 255, 0.92); 
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-radius: 12px; 
    border: 1px solid rgba(255, 255, 255, 0.6); 
    box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); 
    animation: modalSlideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.day-report-content { width: 100%; max-width: 680px; max-height: 85vh; display: flex; flex-direction: column; overflow: hidden; }
.day-report-header { padding: 20px 24px; border-bottom: 1px solid rgba(241, 245, 249, 0.8); display: flex; justify-content: space-between; align-items: flex-start; background: rgba(255, 255, 255, 0.8); }
.day-report-body { padding: 24px; overflow-y: auto; background: transparent; }

.custom-sec-dropdown { position: relative; }

.sec-dropdown-menu { 
    display: none; 
    position: absolute; 
    right: 0; 
    top: 110%; 
    background: rgba(255, 255, 255, 0.92); 
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid rgba(203, 213, 225, 0.8); 
    border-radius: 6px; 
    box-shadow: 0 12px 32px 0 rgba(31, 38, 135, 0.15); 
    z-index: 100; 
    min-width: 180px; 
    animation: dropdownPop 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
.sec-dropdown-menu.show { display: block; }
.sec-dropdown-item { padding: 8px 12px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(241, 245, 249, 0.8); transition: background 0.2s ease; }
.sec-dropdown-item:hover { background: rgba(248, 250, 252, 0.9); }
.sec-title { cursor: pointer; font-size: 0.82rem; color: #334155; }
.sec-title.active { font-weight: 700; color: #0284c7; }
.sec-delete-btn { background: none; border: none; color: #ef4444; font-size: 0.75rem; cursor: pointer; transition: transform 0.2s ease; }
.sec-delete-btn:hover { transform: scale(1.2); }

#topNotificationToast {
    position: fixed;
    top: -100px;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    color: #1e293b;
    padding: 14px 24px;
    border-radius: 8px;
    box-shadow: 0 12px 32px 0 rgba(31, 38, 135, 0.15);
    z-index: 10000;
    display: flex;
    align-items: center;
    gap: 12px;
    border-left: 5px solid #0284c7;
    font-size: 0.9rem;
    font-weight: 500;
    transition: top 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
#topNotificationToast.show { top: 25px; }

.roster-row { transition: background-color 0.2s ease, transform 0.2s ease; }
.roster-row:hover { background-color: rgba(248, 250, 252, 0.8); }
.roster-row td a { transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1); display: inline-block; }
.roster-row td a:hover { transform: scale(1.25); }

/* Enhanced Modern Modal Styles with Glassmorphism */
.modal-box-enhanced {
    background: rgba(255, 255, 255, 0.92);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    width: 100%;
    max-width: 420px;
    padding: 32px;
    border-radius: 16px;
    border: 1px solid rgba(255, 255, 255, 0.6);
    box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.25);
    position: relative;
    animation: modalSlideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.modal-box-enhanced .form-group {
    margin-bottom: 18px;
}

.modal-box-enhanced label {
    display: block;
    font-size: 0.8rem;
    font-weight: 600;
    color: #475569;
    margin-bottom: 6px;
}

.modal-box-enhanced .input-with-icon {
    position: relative;
}

.modal-box-enhanced .input-with-icon i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: 0.85rem;
}

.modal-box-enhanced input[type="text"],
.modal-box-enhanced input[type="time"] {
    width: 100%;
    padding: 10px 12px 10px 36px;
    background: rgba(248, 250, 252, 0.8);
    border: 1px solid rgba(203, 213, 225, 0.8);
    border-radius: 8px;
    font-size: 0.88rem;
    color: #0f172a;
    outline: none;
    transition: all 0.2s ease;
}

.modal-box-enhanced input:focus {
    background: rgba(255, 255, 255, 0.98);
    border-color: #0284c7;
    box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
}

.schedule-duration-preview {
    font-size: 0.75rem;
    color: #0284c7;
    background: rgba(240, 249, 255, 0.8);
    padding: 8px 12px;
    border-radius: 8px;
    margin-bottom: 20px;
    border: 1px solid rgba(186, 230, 253, 0.8);
    display: none;
    align-items: center;
    gap: 8px;
}

/* Formal Tab Button Animation Styles */
.report-tab-btn {
    flex: 1;
    padding: 10px 12px;
    background: rgba(248, 250, 252, 0.8);
    color: #64748b;
    border: 1px solid rgba(226, 232, 240, 0.8);
    border-radius: 6px;
    font-weight: 500;
    font-size: 0.82rem;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    position: relative;
    overflow: hidden;
}

.report-tab-btn::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 50%;
    width: 0;
    height: 2px;
    background: #0284c7;
    transition: all 0.3s ease;
    transform: translateX(-50%);
}

.report-tab-btn:hover {
    background: rgba(241, 245, 249, 0.9);
    color: #0f172a;
    transform: translateY(-1px);
}

.report-tab-btn.active-tab {
    background: rgba(255, 255, 255, 0.95);
    color: #0f172a;
    border-color: rgba(203, 213, 225, 0.9);
    font-weight: 600;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
}

.report-tab-btn.active-tab::after {
    width: 80%;
}

/* Mirror/Symmetrical View for QR Scanner */
#qrReader video {
    transform: scaleX(-1);
    -webkit-transform: scaleX(-1);
}
    </style>
</head>
<body class="portal-body">

<div id="topNotificationToast">
    <i class="fa-solid fa-circle-check" style="color: #10b981; font-size: 1.2rem;"></i>
    <span id="topNotificationText">Notification message</span>
</div>

<div class="app-sidebar" id="appSidebar">
    <div>
        <div class="sidebar-top-header">
            <button type="button" class="toggle-sidebar-btn" id="closeSidebarBtn" title="Close Sidebar">&#x2715;</button>
            <div class="sidebar-brand">
                <img src="logo.jpg" alt="Asian School Logo" class="sidebar-logo">
                <div>
                    <h3>Asian School</h3>
                    <p>Faculty Portal</p>
                </div>
            </div>
        </div>

        <div class="nav-menu-wrapper">
            <a href="attendance_graph.php" class="nav-view-btn <?php echo ($current_page == 'attendance_graph.php') ? 'active-nav-btn' : ''; ?>">
                <i class="fa-solid fa-chart-line"></i>
                <span>Monthly Record</span>
            </a>

            <a href="dashboard.php" class="nav-view-btn <?php echo ($current_page == 'dashboard.php') ? 'active-nav-btn' : ''; ?>">
                <i class="fa-solid fa-house"></i>
                <span>Main Calendar</span>
            </a>

            <a href="student_status.php" class="nav-view-btn <?php echo ($current_page == 'student_status.php') ? 'active-nav-btn' : ''; ?>">
                <i class="fa-solid fa-user-graduate"></i>
                <span>Status Reports</span>
            </a>

            <a href="logout.php" class="nav-view-btn nav-logout-btn">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
    
    <div class="sidebar-footer">
        Institutional Registry &copy; 2026
    </div>
</div>

<button type="button" class="toggle-sidebar-btn external-open-sidebar" id="openSidebarBtn" title="Open Sidebar">&#x2630; Menu</button>

<div class="main-wrapper">
    <div class="container" style="max-width: 100%;">
        
        <div class="top-header-card">
            <div style="display: flex; flex-direction: column; gap: 4px;">
                <h1 style="margin: 0; color: #0f172a; font-size: 1.7rem; font-weight: 700; letter-spacing: -0.5px;">Calendar</h1>
                <p style="margin: 0; color: #64748b; font-size: 0.85rem; font-weight: 400;">System Registry & Verification Module</p>
                <div>
                    <span class="schedule-badge-info">
                        Schedule: <?php echo date('h:i A', strtotime($sec_start)) . ' - ' . date('h:i A', strtotime($sec_end)); ?>
                    </span>
                </div>
            </div>
            
            <div style="display: flex; align-items: center; gap: 10px;">
                <div class="custom-sec-dropdown">
                    <button type="button" class="sec-dropdown-btn" id="secDropdownBtn">
                        <span><?php echo 'Section: ' . htmlspecialchars($current_section); ?></span> 
                        <i class="fa-solid fa-caret-down" style="font-size: 0.9rem;"></i>
                    </button>
                    <div class="sec-dropdown-menu" id="secDropdownMenu">
                        <?php if($section_result && mysqli_num_rows($section_result) > 0): 
                            mysqli_data_seek($section_result, 0);
                            while($sec = mysqli_fetch_assoc($section_result)): 
                            $sec_val = $sec['section_name'];
                            if(empty($sec_val)) continue;
                            $is_active = ($sec_val == $current_section);
                        ?>
                            <div class="sec-dropdown-item">
                                <span class="sec-title <?php echo $is_active ? 'active' : ''; ?>" onclick="window.location.href='?section=<?php echo urlencode($sec_val); ?>';">
                                    <?php echo htmlspecialchars($sec_val); ?>
                                </span>
                                <div style="display: flex; gap: 10px; align-items: center;">
                                    <button type="button" style="background:none; border:none; color:#0284c7; font-size:0.85rem; cursor:pointer;" title="Edit Section" onclick="openEditSectionModal('<?php echo htmlspecialchars($sec_val, ENT_QUOTES); ?>', '<?php echo $sec['start_time']; ?>', '<?php echo $sec['end_time']; ?>')">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <button type="button" class="sec-delete-btn" title="Delete Section" onclick="promptDeleteSection('<?php echo htmlspecialchars($sec_val, ENT_QUOTES); ?>')">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endwhile; else: ?>
                            <div class="sec-dropdown-item"><span style="color: #64748b; font-size: 0.8rem;">No Lists</span></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="dots-menu-wrapper">
                    <button type="button" class="dots-menu-btn" id="dotsMenuBtn">&#x22EE;</button>
                    <div class="dots-dropdown-content" id="dotsDropdownMenu">
                        <button type="button" onclick="document.getElementById('rosterModal').style.display='flex';">Students List</button>
                        <button type="button" onclick="document.getElementById('addSectionModal').style.display='flex';">Add Section</button>
                        <button type="button" id="scanQrDirectBtn">Scan QR Code</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="calendar-desk-wrapper">
            <div class="calendar-binder-bar">
                <div class="binder-ring"></div><div class="binder-ring"></div><div class="binder-ring"></div><div class="binder-ring"></div>
                <div class="binder-ring"></div><div class="binder-ring"></div><div class="binder-ring"></div><div class="binder-ring"></div>
            </div>

            <div id="calendarViewSection" class="real-calendar-container">
                <form method="GET" action="dashboard.php" class="calendar-nav-header">
                    <input type="hidden" name="section" value="<?php echo htmlspecialchars($current_section); ?>">
                    
                    <div class="month-heading-title">
                        <?php echo $month_display_name; ?> <span><?php echo $nav_year; ?></span>
                    </div>

                    <div style="display: flex; gap: 8px; align-items: center;">
                        <select name="month" onchange="this.form.submit()" style="padding: 8px 12px; border: 1px solid rgba(51, 65, 85, 0.8); background: rgba(15, 23, 42, 0.85); color: #ffffff; border-radius: 6px; font-size: 0.85rem; font-weight: 600; cursor: pointer;">
                            <?php 
                            for ($m = 1; $m <= 12; $m++) {
                                $m_name = date('F', mktime(0, 0, 0, $m, 10));
                                $selected = ($m == $nav_month) ? 'selected' : '';
                                echo "<option value='$m' $selected>$m_name</option>";
                            }
                            ?>
                        </select>

                        <select name="year" onchange="this.form.submit()" style="padding: 8px 12px; border: 1px solid rgba(51, 65, 85, 0.8); background: rgba(15, 23, 42, 0.85); color: #ffffff; border-radius: 6px; font-size: 0.85rem; font-weight: 600; cursor: pointer;">
                            <?php 
                            $current_y = (int)date('Y');
                            for ($y = $current_y - 5; $y <= $current_y + 5; $y++) {
                                $selected = ($y == $nav_year) ? 'selected' : '';
                                echo "<option value='$y' $selected>$y</option>";
                            }
                            ?>
                        </select>
                    </div>
                </form>

                <div class="calendar-grid-wrapper">
                    <div class="calendar-grid">
                        <div class="calendar-day-header weekend-header">Sun</div>
                        <div class="calendar-day-header">Mon</div>
                        <div class="calendar-day-header">Tue</div>
                        <div class="calendar-day-header">Wed</div>
                        <div class="calendar-day-header">Thu</div>
                        <div class="calendar-day-header">Fri</div>
                        <div class="calendar-day-header weekend-header">Sat</div>

                        <?php
                        $days_in_month = cal_days_in_month(CAL_GREGORIAN, $nav_month, $nav_year);
                        $first_day_timestamp = strtotime("$nav_year-$nav_month-01");
                        $start_day_of_week = date('w', $first_day_timestamp);
                        
                        $prev_month = $nav_month - 1;
                        $prev_year = $nav_year;
                        if ($prev_month < 1) {
                            $prev_month = 12;
                            $prev_year--;
                        }
                        $days_in_prev_month = cal_days_in_month(CAL_GREGORIAN, $prev_month, $prev_year);
                        $prev_start_day = $days_in_prev_month - $start_day_of_week + 1;

                        for ($i = $prev_start_day; $i <= $days_in_prev_month; $i++) {
                            echo '<div class="calendar-cell other-month"><div class="cal-date-num">' . $i . '</div></div>';
                        }

                        $total_students_q = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM student WHERE section = '$current_section' AND adviser_id = $current_adviser_id");
                        $total_students_row = mysqli_fetch_assoc($total_students_q);
                        $total_section_students = (int)$total_students_row['cnt'];

                        for ($day = 1; $day <= $days_in_month; $day++) {
                            $current_loop_date = sprintf('%04d-%02d-%02d', $nav_year, $nav_month, $day);
                            $is_today = ($current_loop_date == date('Y-m-d')) ? 'today' : '';

                            $nc_check = mysqli_query($conn, "SELECT id FROM no_classes WHERE section_name = '$current_section' AND adviser_id = $current_adviser_id AND date = '$current_loop_date'");
                            $is_no_class = (mysqli_num_rows($nc_check) > 0);

                            $present_count = 0;
                            $absent_count = 0;
                            $has_attendance_logged = false;

                            if (!$is_no_class) {
                                $scan_count_q = mysqli_query($conn, "
                                    SELECT COUNT(a.id) as present_cnt 
                                    FROM attendance a 
                                    JOIN student s ON a.student_id = s.id 
                                    WHERE a.date = '$current_loop_date' 
                                    AND s.section = '$current_section' 
                                    AND s.adviser_id = $current_adviser_id
                                ");
                                $scan_data = mysqli_fetch_assoc($scan_count_q);
                                $present_count = (int)$scan_data['present_cnt'];

                                if ($present_count > 0) {
                                    $has_attendance_logged = true;
                                    $absent_count = max(0, $total_section_students - $present_count);
                                }
                            }
                        ?>
                            <div class="calendar-cell <?php echo $is_today; ?>">
                                <div>
                                    <div style="display: flex; justify-content: space-between; align-items: center; position: relative;">
                                        <?php if (!empty($is_today)): ?>
                                            <div style="position: absolute; left: 50%; transform: translateX(-50%); top: -2px;">
                                                <span class="today-badge">TODAY</span>
                                            </div>
                                        <?php endif; ?>

                                        <a href="?month=<?php echo $nav_month; ?>&year=<?php echo $nav_year; ?>&section=<?php echo urlencode($current_section); ?>&view_date=<?php echo $current_loop_date; ?>" style="text-decoration: none; color: inherit;">
                                            <div class="cal-date-num"><?php echo $day; ?></div>
                                        </a>
                                        
                                        <label style="cursor: pointer; display: flex; align-items: center; gap: 3px;" title="<?php echo $is_no_class ? 'Click to restore classes' : ''; ?>">
                                            <input type="checkbox" <?php echo $is_no_class ? 'checked' : ''; ?> 
                                            onclick="window.location.href='?month=<?php echo $nav_month; ?>&year=<?php echo $nav_year; ?>&section=<?php echo urlencode($current_section); ?>&toggle_no_class=1&toggle_date=<?php echo $current_loop_date; ?>';" 
                                            style="width: 14px; height: 14px; accent-color: #d90606; cursor: pointer;">
                                            <span style="font-size: 0.62rem; font-weight: 600; color: <?php echo $is_no_class ? '#ca0606a9' : '#64748b'; ?>;">
                                                <?php echo $is_no_class ? 'Off' : 'On'; ?>
                                            </span>
                                        </label>
                                    </div>
                                </div>

                                <a href="?month=<?php echo $nav_month; ?>&year=<?php echo $nav_year; ?>&section=<?php echo urlencode($current_section); ?>&view_date=<?php echo $current_loop_date; ?>" style="text-decoration: none; color: inherit;">
                                    <div class="cal-stats">
                                        <?php if ($is_no_class): ?>
                                            <div class="no-class-stamp">
                                                <i class="fa-solid fa-ban"></i> No Classes
                                            </div>
                                        <?php elseif (!$has_attendance_logged): ?>
                                            <div style="font-size: 0.68rem; color: #94a3b8; font-style: italic; padding: 4px 0;">No attendance taken</div>
                                        <?php else: ?>
                                            <span class="badge-present-count">Present <span><?php echo $present_count; ?></span></span>
                                            <span class="badge-absent-count">Absent <span><?php echo $absent_count; ?></span></span>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            </div>
                        <?php } 
                         $total_cells_rendered = $start_day_of_week + $days_in_month;
                        $next_padding = (7 - ($total_cells_rendered % 7)) % 7;
                        for ($next_day = 1; $next_day <= $next_padding; $next_day++) {
                            echo '<div class="calendar-cell other-month"><div class="cal-date-num">' . $next_day . '</div></div>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- UPGRADED PERMANENT STUDENT ROSTER MODAL -->
<div id="rosterModal" class="day-report-modal">
    <div class="day-report-content" style="max-width: 720px;">
        <div class="day-report-header">
            <div>
                <h3 style="color: #0f172a; font-size: 1rem; font-weight: 600;">Permanent Student </h3>
                <p style="color: #64748b; font-size: 0.78rem;">Active Section: <strong><?php echo htmlspecialchars($current_section); ?></strong></p>
            </div>
            <span onclick="document.getElementById('rosterModal').style.display='none';" style="cursor: pointer; font-size: 22px; font-weight: bold; color: #64748b;">&times;</span>
        </div>
        
        <div class="day-report-body">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; gap: 12px;">
                <div style="position: relative; flex-grow: 1;">
                    <i class="fa-solid fa-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.85rem;"></i>
                    <input type="text" id="rosterSearchInput" placeholder="Search student by name or ID..." onkeyup="filterRosterTable()" style="width: 100%; padding: 9px 12px 9px 36px; border: 1px solid rgba(203, 213, 225, 0.8); border-radius: 6px; font-size: 0.85rem; outline: none; background: rgba(248, 250, 252, 0.8);">
                </div>
                <button type="button" onclick="document.getElementById('rosterModal').style.display='none'; document.getElementById('studentModal').style.display='flex';" style="background: #2563eb; color: #fff; border: none; padding: 9px 16px; border-radius: 6px; font-size: 0.85rem; cursor: pointer; font-weight: 500; display: flex; align-items: center; gap: 6px; white-space: nowrap; box-shadow: 0 2px 4px rgba(37, 99, 235, 0.2);">
                    <i class="fa-solid fa-plus"></i> Add Student
                </button>
            </div>

            <div style="max-height: 360px; overflow-y: auto; border: 1px solid rgba(226, 232, 240, 0.8); border-radius: 8px; background: rgba(255, 255, 255, 0.5);">
               <table id="rosterTable" style="width: 100%; border-collapse: collapse;">
    <thead>
        <tr style="background: rgba(248, 250, 252, 0.9); border-bottom: 2px solid rgba(226, 232, 240, 0.8); text-align: left; font-size: 0.8rem; color: #475569;">
            <th style="padding: 12px 16px;">Student Name</th>
            <th style="padding: 12px 16px; text-align: center;">Parents Number</th>
            <th style="padding: 12px 16px; text-align: center;">Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php
    $master_roster_query = mysqli_query($conn, "
        SELECT DISTINCT last_name, first_name, id, parent_number 
        FROM student 
        WHERE section = '$current_section' AND adviser_id = $current_adviser_id
        ORDER BY last_name ASC
    ");
    if (mysqli_num_rows($master_roster_query) > 0) {
        while ($master_row = mysqli_fetch_assoc($master_roster_query)) {
            $master_id = $master_row['id'];
            $f_name = ucwords(strtolower($master_row['first_name']));
            $l_name = ucwords(strtolower($master_row['last_name']));
            $master_name = "$l_name, $f_name";
            $parent_num = !empty($master_row['parent_number']) ? htmlspecialchars($master_row['parent_number']) : 'No Number';
            $initials = strtoupper(substr($master_row['first_name'], 0, 1) . substr($master_row['last_name'], 0, 1));
    ?>
        <tr class="roster-row" style="border-bottom: 1px solid rgba(241, 245, 249, 0.8); font-size: 0.88rem;">
            <td style="padding: 12px 16px; display: flex; align-items: center; gap: 12px;">
                <div style="width: 32px; height: 32px; background: #e0f2fe; color: #0284c7; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.75rem; flex-shrink: 0;">
                    <?php echo $initials; ?>
                </div>
                <div>
                    <div style="font-weight: 600; color: #1e293b;"><?php echo $master_name; ?></div>
                    <div style="font-size: 0.72rem; color: #64748b;">Enrolled Student</div>
                </div>
            </td>
            <td style="padding: 12px 16px; text-align: center; font-family: monospace; font-weight: 600; color: #0f172a;"><?php echo $parent_num; ?></td>
            <td style="padding: 12px 16px; text-align: center;">
                <div style="display: flex; justify-content: center; gap: 14px; align-items: center;">
                    <a href="#" class="view-qr-btn" data-id="<?php echo $master_id; ?>" data-name="<?php echo htmlspecialchars($master_name, ENT_QUOTES); ?>" style="color: #0284c7; font-size: 0.95rem;" title="View QR Badge"><i class="fa-solid fa-id-badge"></i></a>
                    <a href="edit.php?id=<?php echo $master_id; ?>" style="color: #10b981; font-size: 0.9rem;" title="Edit Student"><i class="fa-solid fa-pen"></i></a>
                    <a href="delete.php?id=<?php echo $master_id; ?>" onclick="return confirm('Permanently delete record?');" style="color: #ef4444; font-size: 0.9rem;" title="Delete Student"><i class="fa-solid fa-trash"></i></a>
                </div>
            </td>
        </tr>
        <?php
            }
        } else {
            echo "<tr><td colspan='3' style='text-align: center; color: #94a3b8; padding: 32px;'>No student records found in this section.</td></tr>";
        }
        ?>
    </tbody>
</table>
            </div>
        </div>
    </div>
</div>

<!-- REFINED & FORMAL DAILY ATTENDANCE REPORT MODAL WITH ANIMATED SWITCH TABS -->
<?php if (isset($_GET['view_date'])): 
    $clicked_date = mysqli_real_escape_string($conn, $_GET['view_date']);
    $formatted_modal_title = date('l, d F Y', strtotime($clicked_date));
?>
<div class="day-report-modal" id="dayReportModal" style="display: flex;">
    <div class="day-report-content" style="border-radius: 12px; border: 1px solid rgba(203, 213, 225, 0.8); box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.25); max-width: 780px;">
        
        <!-- Formal Modal Header -->
        <div class="day-report-header" style="background: rgba(255, 255, 255, 0.9); border-bottom: 1px solid rgba(226, 232, 240, 0.8); padding: 20px 28px; color: #0f172a;">
            <div style="display: flex; flex-direction: column; gap: 4px;">
                <h3 style="color: #0f172a; font-size: 1.05rem; font-weight: 700; margin: 0; letter-spacing: 0.2px;">Daily Attendance Registry</h3>
                <p style="color: #64748b; font-size: 0.78rem; margin: 0;">
                    Date: <strong style="color: #334155;"><?php echo $formatted_modal_title; ?></strong> &bull; Section: <strong style="color: #334155;"><?php echo htmlspecialchars($current_section); ?></strong>
                </p>
            </div>
            <a href="dashboard.php?month=<?php echo $nav_month; ?>&year=<?php echo $nav_year; ?>&section=<?php echo urlencode($current_section); ?>" style="color: #64748b; font-size: 1.4rem; font-weight: 500; text-decoration: none; transition: color 0.2s;" onmouseover="this.style.color='#0f172a'" onmouseout="this.style.color='#64748b'">&times;</a>
        </div>
        
        <div class="day-report-body" style="padding: 28px; background: transparent;">
            <?php
            $present_ids = [];
            $present_query = mysqli_query($conn, "
                SELECT a.*, s.id as student_id, s.first_name, s.last_name 
                FROM attendance a JOIN student s ON a.student_id = s.id 
                WHERE a.date = '$clicked_date' AND s.section = '$current_section' AND s.adviser_id = $current_adviser_id
                ORDER BY a.time_in DESC
            ");
            
            $present_students = [];
            while($row = mysqli_fetch_assoc($present_query)) {
                $present_students[] = $row;
                $present_ids[] = $row['student_id'];
            }
            $total_present = count($present_students);

            $all_students_query = mysqli_query($conn, "
                SELECT id, first_name, last_name FROM student 
                WHERE section = '$current_section' AND adviser_id = $current_adviser_id 
                ORDER BY last_name ASC
            ");
            
            $absent_students = [];
            while($student = mysqli_fetch_assoc($all_students_query)) {
                if (!in_array($student['id'], $present_ids)) {
                    $absent_students[] = $student;
                }
            }
            $total_absent = count($absent_students);

            // Filter late students
            $late_students = array_filter($present_students, function($p) {
                return $p['status'] === 'Late/Present';
            });
            $total_late = count($late_students);
            ?>

            <script>
                document.addEventListener("DOMContentLoaded", () => {
                    const badge = document.getElementById('lateCountBadge');
                    if(badge) badge.innerText = "<?php echo $total_late; ?>";
                });
            </script>

            <!-- Formal Animated Switch Button Navigation Bar -->
            <div style="display: flex; background: rgba(241, 245, 249, 0.8); border: 1px solid rgba(226, 232, 240, 0.8); padding: 6px; border-radius: 8px; gap: 8px; margin-bottom: 24px;">
                <button type="button" onclick="switchReportTab('present')" id="presentTabBtn" class="report-tab-btn active-tab">
                    <i class="fa-solid fa-circle-check" style="color: #059669; font-size: 0.9rem;"></i> Present 
                    <span style="background: rgba(236, 253, 245, 0.9); color: #047857; padding: 1px 7px; border-radius: 12px; font-size: 0.72rem; font-weight: 600;"><?php echo $total_present; ?></span>
                </button>
                <button type="button" onclick="switchReportTab('late')" id="lateTabBtn" class="report-tab-btn">
                    <i class="fa-solid fa-clock" style="color: #d97706; font-size: 0.9rem;"></i> Late 
                    <span style="background: rgba(254, 243, 199, 0.9); color: #d97706; padding: 1px 7px; border-radius: 12px; font-size: 0.72rem; font-weight: 600;" id="lateCountBadge">0</span>
                </button>
                <button type="button" onclick="switchReportTab('absent')" id="absentTabBtn" class="report-tab-btn">
                    <i class="fa-solid fa-circle-xmark" style="color: #dc2626; font-size: 0.9rem;"></i> Absent 
                    <span style="background: rgba(254, 242, 242, 0.9); color: #dc2626; padding: 1px 7px; border-radius: 12px; font-size: 0.72rem; font-weight: 600;"><?php echo $total_absent; ?></span>
                </button>
            </div>

            <!-- Present Table Section with Editable Times -->
            <div id="presentReportSection" style="display: block; animation: fadeInPage 0.25s ease-out;">
                <div style="max-height: 310px; overflow-y: auto; border: 1px solid rgba(226, 232, 240, 0.8); border-radius: 8px; background: rgba(255, 255, 255, 0.5);">
                    <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.83rem;">
                        <thead>
                            <tr style="background: rgba(248, 250, 252, 0.9); color: #334155; font-weight: 600; border-bottom: 1px solid rgba(226, 232, 240, 0.8); text-transform: uppercase; font-size: 0.73rem; letter-spacing: 0.5px;">
                                <th style="padding: 12px 16px;">Student Name</th>
                                <th style="padding: 12px 16px;">Status</th>
                                <th style="padding: 12px 16px;">Time-In</th>
                                <th style="padding: 12px 16px;">Time-Out</th>
                                <th style="padding: 12px 16px; text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($total_present > 0): foreach($present_students as $p): 
                                $t_in_val = !empty($p['time_in']) ? date('H:i', strtotime($p['time_in'])) : '';
                                $t_out_val = !empty($p['time_out']) ? date('H:i', strtotime($p['time_out'])) : '';
                            ?>
                            <tr style="border-bottom: 1px solid rgba(241, 245, 249, 0.8); transition: background 0.15s;" onmouseover="this.style.background='rgba(248, 250, 252, 0.8)'" onmouseout="this.style.background='transparent'">
                                <form method="POST" action="dashboard.php?month=<?php echo $nav_month; ?>&year=<?php echo $nav_year; ?>&section=<?php echo urlencode($current_section); ?>&view_date=<?php echo $clicked_date; ?>">
                                    <input type="hidden" name="attendance_id" value="<?php echo $p['id']; ?>">
                                    <input type="hidden" name="view_date" value="<?php echo $clicked_date; ?>">
                                    
                                    <td style="padding: 12px 16px; font-weight: 500; color: #0f172a;">
                                        <?php echo ucwords(strtolower($p['last_name'])) . ", " . ucwords(strtolower($p['first_name'])); ?>
                                    </td>
                                    <td style="padding: 12px 16px;">
                                        <span style="background: rgba(236, 253, 245, 0.9); color: #047857; border: 1px solid rgba(167, 243, 208, 0.8); padding: 3px 10px; border-radius: 4px; font-size: 0.72rem; font-weight: 600;"><?php echo htmlspecialchars($p['status']); ?></span>
                                    </td>
                                    <td style="padding: 12px 16px;">
                                        <input type="time" name="edit_time_in" value="<?php echo $t_in_val; ?>" style="padding: 5px 8px; border: 1px solid rgba(203, 213, 225, 0.8); border-radius: 6px; font-size: 0.8rem; font-family: monospace; background: rgba(248, 250, 252, 0.8); outline: none;" required>
                                    </td>
                                    <td style="padding: 12px 16px;">
                                        <input type="time" name="edit_time_out" value="<?php echo $t_out_val; ?>" style="padding: 5px 8px; border: 1px solid rgba(203, 213, 225, 0.8); border-radius: 6px; font-size: 0.8rem; font-family: monospace; background: rgba(248, 250, 252, 0.8); outline: none;" placeholder="--:--">
                                    </td>
                                    <td style="padding: 12px 16px; text-align: center; white-space: nowrap;">
                                        <button type="submit" name="update_attendance_time" style="background: #0f172a; color: white; border: none; padding: 6px 12px; border-radius: 6px; font-size: 0.75rem; cursor: pointer; font-weight: 500; transition: background 0.2s;" title="Save Time Changes" onmouseover="this.style.background='#334155'" onmouseout="this.style.background='#0f172a'">
                                            <i class="fa-solid fa-check" style="margin-right: 4px;"></i> Save
                                        </button>
                                        <a href="dashboard.php?month=<?php echo $nav_month; ?>&year=<?php echo $nav_year; ?>&section=<?php echo urlencode($current_section); ?>&view_date=<?php echo $clicked_date; ?>&delete_attendance_id=<?php echo $p['id']; ?>" 
                                           onclick="return confirm('Remove attendance record for this student?');" 
                                           style="color: #ef4444; font-size: 0.9rem; text-decoration: none; margin-left: 10px; display: inline-block; vertical-align: middle; transition: transform 0.2s;" 
                                           title="Remove Attendance" onmouseover="this.style.transform='scale(1.2)'" onmouseout="this.style.transform='scale(1)'">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </a>
                                    </td>
                                </form>
                            </tr>
                            <?php endforeach; else: ?>
                            <tr><td colspan="5" style="text-align: center; color: #64748b; padding: 32px; font-size: 0.82rem;">No present records found for this date.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Late Table Section -->
            <div id="lateReportSection" style="display: none; animation: fadeInPage 0.25s ease-out;">
                <div style="max-height: 310px; overflow-y: auto; border: 1px solid rgba(226, 232, 240, 0.8); border-radius: 8px; background: rgba(255, 255, 255, 0.5);">
                    <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.83rem;">
                        <thead>
                            <tr style="background: rgba(248, 250, 252, 0.9); color: #334155; font-weight: 600; border-bottom: 1px solid rgba(226, 232, 240, 0.8); text-transform: uppercase; font-size: 0.73rem; letter-spacing: 0.5px;">
                                <th style="padding: 12px 16px;">Student Name</th>
                                <th style="padding: 12px 16px;">Status</th>
                                <th style="padding: 12px 16px;">Time-In</th>
                                <th style="padding: 12px 16px;">Time-Out</th>
                                <th style="padding: 12px 16px; text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($total_late > 0): foreach($late_students as $l_student): 
                                $lt_in_val = !empty($l_student['time_in']) ? date('H:i', strtotime($l_student['time_in'])) : '';
                                $lt_out_val = !empty($l_student['time_out']) ? date('H:i', strtotime($l_student['time_out'])) : '';
                            ?>
                            <tr style="border-bottom: 1px solid rgba(241, 245, 249, 0.8); transition: background 0.15s;" onmouseover="this.style.background='rgba(248, 250, 252, 0.8)'" onmouseout="this.style.background='transparent'">
                                <form method="POST" action="dashboard.php?month=<?php echo $nav_month; ?>&year=<?php echo $nav_year; ?>&section=<?php echo urlencode($current_section); ?>&view_date=<?php echo $clicked_date; ?>">
                                    <input type="hidden" name="attendance_id" value="<?php echo $l_student['id']; ?>">
                                    <input type="hidden" name="view_date" value="<?php echo $clicked_date; ?>">
                                    
                                    <td style="padding: 12px 16px; font-weight: 500; color: #0f172a;">
                                        <?php echo ucwords(strtolower($l_student['last_name'])) . ", " . ucwords(strtolower($l_student['first_name'])); ?>
                                    </td>
                                    <td style="padding: 12px 16px;">
                                        <span style="background: rgba(254, 243, 199, 0.9); color: #d97706; border: 1px solid rgba(253, 230, 138, 0.8); padding: 3px 10px; border-radius: 4px; font-size: 0.72rem; font-weight: 600;"><?php echo htmlspecialchars($l_student['status']); ?></span>
                                    </td>
                                    <td style="padding: 12px 16px;">
                                        <input type="time" name="edit_time_in" value="<?php echo $lt_in_val; ?>" style="padding: 5px 8px; border: 1px solid rgba(203, 213, 225, 0.8); border-radius: 6px; font-size: 0.8rem; font-family: monospace; background: rgba(248, 250, 252, 0.8); outline: none;" required>
                                    </td>
                                    <td style="padding: 12px 16px;">
                                        <input type="time" name="edit_time_out" value="<?php echo $lt_out_val; ?>" style="padding: 5px 8px; border: 1px solid rgba(203, 213, 225, 0.8); border-radius: 6px; font-size: 0.8rem; font-family: monospace; background: rgba(248, 250, 252, 0.8); outline: none;" placeholder="--:--">
                                    </td>
                                    <td style="padding: 12px 16px; text-align: center; white-space: nowrap;">
                                        <button type="submit" name="update_attendance_time" style="background: #0f172a; color: white; border: none; padding: 6px 12px; border-radius: 6px; font-size: 0.75rem; cursor: pointer; font-weight: 500; transition: background 0.2s;" title="Save Time Changes" onmouseover="this.style.background='#334155'" onmouseout="this.style.background='#0f172a'">
                                            <i class="fa-solid fa-check" style="margin-right: 4px;"></i> Save
                                        </button>
                                        <a href="dashboard.php?month=<?php echo $nav_month; ?>&year=<?php echo $nav_year; ?>&section=<?php echo urlencode($current_section); ?>&view_date=<?php echo $clicked_date; ?>&delete_attendance_id=<?php echo $l_student['id']; ?>" 
                                           onclick="return confirm('Remove attendance record for this student?');" 
                                           style="color: #ef4444; font-size: 0.9rem; text-decoration: none; margin-left: 10px; display: inline-block; vertical-align: middle; transition: transform 0.2s;" 
                                           title="Remove Attendance" onmouseover="this.style.transform='scale(1.2)'" onmouseout="this.style.transform='scale(1)'">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </a>
                                    </td>
                                </form>
                            </tr>
                            <?php endforeach; else: ?>
                            <tr><td colspan="5" style="text-align: center; color: #64748b; padding: 32px; font-size: 0.82rem;">No late arrivals recorded for this date.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Absent Table Section -->
            <div id="absentReportSection" style="display: none; animation: fadeInPage 0.25s ease-out;">
                <div style="max-height: 310px; overflow-y: auto; border: 1px solid rgba(226, 232, 240, 0.8); border-radius: 8px; background: rgba(255, 255, 255, 0.5);">
                    <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.83rem;">
                        <thead>
                            <tr style="background: rgba(248, 250, 252, 0.9); color: #334155; font-weight: 600; border-bottom: 1px solid rgba(226, 232, 240, 0.8); text-transform: uppercase; font-size: 0.73rem; letter-spacing: 0.5px;">
                                <th style="padding: 12px 16px;">Student Name</th>
                                <th style="padding: 12px 16px;">Student ID</th>
                                <th style="padding: 12px 16px;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($total_absent > 0): foreach($absent_students as $ab): ?>
                            <tr style="border-bottom: 1px solid rgba(241, 245, 249, 0.8); transition: background 0.15s;" onmouseover="this.style.background='rgba(248, 250, 252, 0.8)'" onmouseout="this.style.background='transparent'">
                                <td style="padding: 12px 16px; font-weight: 500; color: #0f172a;"><?php echo ucwords(strtolower($ab['last_name'])) . ", " . ucwords(strtolower($ab['first_name'])); ?></td>
                                <td style="padding: 12px 16px; font-family: monospace; color: #475569; font-weight: 500;"><?php echo $ab['id']; ?></td>
                                <td style="padding: 12px 16px;"><span style="background: rgba(254, 242, 242, 0.9); color: #dc2626; border: 1px solid rgba(254, 202, 202, 0.8); padding: 3px 10px; border-radius: 4px; font-size: 0.72rem; font-weight: 600;">Absent</span></td>
                            </tr>
                            <?php endforeach; else: ?>
                            <tr><td colspan="3" style="text-align: center; color: #64748b; padding: 32px; font-size: 0.82rem;">No absences recorded for this date. All students attended.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
        </div>
    </div>
</div>
<?php endif; ?>

<!-- UPGRADED ADD/EDIT SECTION MODAL -->
<div id="addSectionModal" class="cal-modal">
    <div class="modal-box-enhanced">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <div>
                <h3 id="sectionModalTitle" style="margin: 0; font-size: 1.1rem; font-weight: 700; color: #0f172a;">Add New Section</h3>
                <p style="margin: 2px 0 0 0; font-size: 0.78rem; color: #64748b;">Configure class name and daily time limits.</p>
            </div>
            <button type="button" onclick="document.getElementById('addSectionModal').style.display='none'" style="background: none; border: none; font-size: 1.25rem; color: #64748b; cursor: pointer;">&times;</button>
        </div>

        <form method="POST" action="dashboard.php" onsubmit="return validateSectionForm()">
            <input type="hidden" name="original_section_name" id="original_section_name" value="">
            
            <div class="form-group">
                <label>Section Name</label>
                <div class="input-with-icon">
                    <i class="fa-solid fa-layer-group"></i>
                    <input type="text" name="new_section_name" id="new_section_name" placeholder="e.g. BSIT-3A" required>
                </div>
            </div>
            
            <div class="form-group">
                <label>Time-In Starts Schedule</label>
                <div class="input-with-icon">
                    <i class="fa-solid fa-clock"></i>
                    <input type="time" name="start_time" id="sec_start_time" value="<?php echo $sec_start; ?>" onchange="calculateDuration()" required>
                </div>
            </div>
            
            <div class="form-group">
                <label>Time-Out Deadline Schedule</label>
                <div class="input-with-icon">
                    <i class="fa-solid fa-business-time"></i>
                    <input type="time" name="end_time" id="sec_end_time" value="<?php echo $sec_end; ?>" onchange="calculateDuration()" required>
                </div>
            </div>

            <div id="durationPreview" class="schedule-duration-preview">
                <i class="fa-solid fa-circle-info"></i>
                <span id="durationText">Session Duration: 1 Hour</span>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 24px;">
                <button type="button" onclick="document.getElementById('addSectionModal').style.display='none'" style="padding: 10px 16px; background: rgba(241, 245, 249, 0.8); border: 1px solid rgba(203, 213, 225, 0.8); border-radius: 8px; color: #334155; cursor: pointer; font-size: 0.85rem; font-weight: 500;">Cancel</button>
                <button type="submit" name="save_section" style="padding: 10px 20px; background: #0284c7; border: none; border-radius: 8px; color: white; font-weight: 600; cursor: pointer; font-size: 0.85rem; box-shadow: 0 4px 6px rgba(2, 132, 199, 0.2);">Save Schedule</button>
            </div>
        </form>
    </div>
</div>

<div id="qrScannerModal" class="cal-modal">
    <div class="cal-box" style="padding: 20px; max-width: 440px; text-align: center; position: relative;">
        <span id="closeScannerModalBtn" style="position: absolute; top: 10px; right: 15px; font-size: 20px; cursor: pointer;">&times;</span>
        <h3 style="margin-bottom: 8px; font-size: 0.95rem; color: #0f172a;">Institutional QR Verification Scanner</h3>
        <div id="qrReader" style="width: 100%; background: #f8fafc; border-radius: 4px; padding: 8px;"></div>
    </div>
</div>

<!-- REFINED & FORMAL STUDENT REGISTRATION MODAL -->
<div id="studentModal" class="modal-overlay">
    <div class="modal-content" style="padding: 20px; width: 350px; position: relative;">
        <span class="close-modal" id="closeModalBtn" style="position: absolute; top: 10px; right: 15px; font-size: 20px; cursor: pointer;">&times;</span>
        <h3 style="margin-bottom: 16px; font-size: 0.95rem; color: #0f172a;">Register New Student</h3>
        <form action="add.php" method="POST">
            <div style="margin-bottom: 12px;"><input type="text" name="first_name" placeholder="First Name" required style="width: 100%; padding: 8px; background: rgba(248, 250, 252, 0.8); border: 1px solid rgba(203, 213, 225, 0.8); border-radius: 4px; font-size: 0.85rem;"></div>
            <div style="margin-bottom: 12px;"><input type="text" name="last_name" placeholder="Last Name" required style="width: 100%; padding: 8px; background: rgba(248, 250, 252, 0.8); border: 1px solid rgba(203, 213, 225, 0.8); border-radius: 4px; font-size: 0.85rem;"></div>
            
            <!-- ADDED: Parent Number Field to determine which parent to message -->
            <div style="margin-bottom: 12px;"><input type="text" name="parent_number" placeholder="Parent Contact Number (e.g., 09123456789)" required style="width: 100%; padding: 8px; background: rgba(248, 250, 252, 0.8); border: 1px solid rgba(203, 213, 225, 0.8); border-radius: 4px; font-size: 0.85rem;"></div>

            <div style="margin-bottom: 16px;"><input type="text" name="section" value="<?php echo htmlspecialchars($current_section); ?>" readonly style="width: 100%; padding: 8px; background: rgba(226, 232, 240, 0.8); border: 1px solid rgba(203, 213, 225, 0.8); border-radius: 4px; font-size: 0.85rem;"></div>
            <button type="submit" name="save_student" style="width: 100%; padding: 10px; background: #0284c7; color: white; border: none; border-radius: 4px; font-weight:600; cursor: pointer; font-size: 0.85rem;">Save Student Record</button>
        </form>
    </div>
</div>
<div id="qrDisplayModal" class="cal-modal">
    <div class="cal-box" style="padding: 20px; max-width: 300px; text-align: center; position: relative;">
        <span id="closeQrModal" style="position: absolute; top: 10px; right: 15px; font-size: 20px; cursor: pointer;">&times;</span>
        <h3 id="qrModalName" style="margin-bottom: 16px; font-size: 0.95rem; color: #0f172a;">Student Name</h3>
        <div id="qrcode" style="background: white; padding: 12px; display: inline-block; border-radius: 4px; margin-bottom: 16px; border: 1px solid #e2e8f0;"></div>
        <button onclick="window.print()" style="width: 100%; padding: 10px; background: #0284c7; color: white; border: none; border-radius: 4px; font-weight: 600; cursor: pointer; font-size: 0.85rem;">Print Official Badge</button>
    </div>
</div>

<script>
    const appSidebar = document.getElementById('appSidebar');
    document.getElementById('closeSidebarBtn').addEventListener('click', () => appSidebar.classList.add('collapsed'));
    document.getElementById('openSidebarBtn').addEventListener('click', () => appSidebar.classList.remove('collapsed'));

    const secBtn = document.getElementById('secDropdownBtn');
    const secMenu = document.getElementById('secDropdownMenu');
    secBtn.addEventListener('click', (e) => { 
        e.stopPropagation(); 
        secMenu.classList.toggle('show'); 
        dotsMenu.classList.remove('show');
    });

    const dotsBtn = document.getElementById('dotsMenuBtn');
    const dotsMenu = document.getElementById('dotsDropdownMenu');
    dotsBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        dotsMenu.classList.toggle('show');
        secMenu.classList.remove('show');
    });

    document.getElementById('closeModalBtn').addEventListener('click', () => document.getElementById('studentModal').style.display = 'none');
    document.getElementById('closeQrModal').addEventListener('click', () => document.getElementById('qrDisplayModal').style.display = 'none');

    const qrDisplayModal = document.getElementById('qrDisplayModal');
    const qrContainer = document.getElementById('qrcode');

    document.body.addEventListener('click', function(e) {
        const trigger = e.target.closest('.view-qr-btn');
        if (trigger) {
            e.preventDefault();
            const studentId = trigger.getAttribute('data-id');
            const studentName = trigger.getAttribute('data-name');
            document.getElementById('qrModalName').innerText = studentName;
            qrContainer.innerHTML = ""; 
            new QRCode(qrContainer, { text: studentId, width: 160, height: 160 });
            qrDisplayModal.style.display = 'flex';
        }
    });

    function filterRosterTable() {
        const input = document.getElementById('rosterSearchInput');
        const filter = input.value.toLowerCase();
        const table = document.getElementById('rosterTable');
        const tr = table.getElementsByTagName('tr');

        for (let i = 1; i < tr.length; i++) {
            const tdName = tr[i].getElementsByTagName('td')[0];
            const tdId = tr[i].getElementsByTagName('td')[1];
            if (tdName && tdId) {
                const nameText = tdName.textContent || tdName.innerText;
                const idText = tdId.textContent || tdId.innerText;
                if (nameText.toLowerCase().indexOf(filter) > -1 || idText.toLowerCase().indexOf(filter) > -1) {
                    tr[i].style.display = "";
                } else {
                    tr[i].style.display = "none";
                }
            }
        }
    }

    function calculateDuration() {
        const startVal = document.getElementById('sec_start_time').value;
        const endVal = document.getElementById('sec_end_time').value;
        const previewBox = document.getElementById('durationPreview');
        const durationText = document.getElementById('durationText');

        if (startVal && endVal) {
            const [startHour, startMin] = startVal.split(':').map(Number);
            const [endHour, endMin] = endVal.split(':').map(Number);
            
            let startTotalMins = startHour * 60 + startMin;
            let endTotalMins = endHour * 60 + endMin;
            
            let diffMins = endTotalMins - startTotalMins;
            if (diffMins > 0) {
                const hours = Math.floor(diffMins / 60);
                const mins = diffMins % 60;
                let resultStr = 'Session Duration: ';
                if (hours > 0) resultStr += `${hours} hr${hours > 1 ? 's' : ''} `;
                if (mins > 0) resultStr += `${mins} min${mins > 1 ? 's' : ''}`;
                
                durationText.innerText = resultStr;
                previewBox.style.display = 'flex';
            } else {
                previewBox.style.display = 'none';
            }
        }
    }

    function validateSectionForm() {
        const startTime = document.getElementById('sec_start_time').value;
        const endTime = document.getElementById('sec_end_time').value;
        const sectionName = document.getElementById('new_section_name').value.trim();

        if (!sectionName) {
            showTopToast('Section name cannot be empty.');
            return false;
        }

        if (startTime >= endTime) {
            showTopToast('Error: End time must be later than start time.');
            return false;
        }

        return true;
    }

    function switchReportTab(tabType) {
        const presentSec = document.getElementById('presentReportSection');
        const lateSec = document.getElementById('lateReportSection');
        const absentSec = document.getElementById('absentReportSection');
        
        const presentBtn = document.getElementById('presentTabBtn');
        const lateBtn = document.getElementById('lateTabBtn');
        const absentBtn = document.getElementById('absentTabBtn');

        presentSec.style.display = 'none';
        lateSec.style.display = 'none';
        absentSec.style.display = 'none';

        [presentBtn, lateBtn, absentBtn].forEach(btn => {
            btn.classList.remove('active-tab');
        });

        if (tabType === 'present') {
            presentSec.style.display = 'block';
            presentBtn.classList.add('active-tab');
        } else if (tabType === 'late') {
            lateSec.style.display = 'block';
            lateBtn.classList.add('active-tab');
        } else if (tabType === 'absent') {
            absentSec.style.display = 'block';
            absentBtn.classList.add('active-tab');
        }
    }

    function promptDeleteSection(secName) {
        if(confirm('Are you sure you want to delete section ' + secName + '?')) {
            window.location.href = '?delete_section=' + encodeURIComponent(secName);
        }
    }

    function openEditSectionModal(secName, startTime, endTime) {
        document.getElementById('sectionModalTitle').innerText = 'Edit Section & Schedule';
        document.getElementById('original_section_name').value = secName;
        document.getElementById('new_section_name').value = secName;
        document.getElementById('sec_start_time').value = startTime;
        document.getElementById('sec_end_time').value = endTime;
        document.getElementById('addSectionModal').style.display = 'flex';
        document.getElementById('secDropdownMenu').classList.remove('show');
        calculateDuration();
    }

    function showTopToast(message) {
        const toast = document.getElementById('topNotificationToast');
        const textSpan = document.getElementById('topNotificationText');
        textSpan.innerText = message;
        toast.classList.add('show');
        
        setTimeout(() => {
            toast.classList.remove('show');
        }, 4000);
    }

    window.addEventListener('DOMContentLoaded', () => {
        calculateDuration();
        const urlParams = new URLSearchParams(window.location.search);
        const secError = urlParams.get('sec_error');
        const studentAdded = urlParams.get('student_added');
        const studentError = urlParams.get('student_error');
        
        if (secError) {
            document.getElementById('addSectionModal').style.display = 'flex';
            if (urlParams.get('sec_name')) {
                document.getElementById('new_section_name').value = urlParams.get('sec_name');
            }
            if (urlParams.get('st')) {
                document.getElementById('sec_start_time').value = urlParams.get('st');
            }
            if (urlParams.get('et')) {
                document.getElementById('sec_end_time').value = urlParams.get('et');
            }

            if (secError === 'exists') {
                showTopToast('Schedule or Section already exists! Please use a unique section name.');
            } else if (secError === 'time_exists') {
                showTopToast('This time slot conflicts with an existing schedule.');
            }
            
            window.history.replaceState({}, document.title, window.location.pathname);
        }

        if (studentAdded === 'success') {
            showTopToast('Student successfully added!');
            window.history.replaceState({}, document.title, window.location.pathname);
        }

        if (studentError === 'db_error') {
            document.getElementById('studentModal').style.display = 'flex';
            showTopToast('Database Error: Could not save student record.');
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    });

    let qrScannerInstance = null;
    const scannerModal = document.getElementById('qrScannerModal');
    
    document.getElementById('scanQrDirectBtn').addEventListener('click', function() {
        dotsMenu.classList.remove('show');
        scannerModal.style.display = 'flex';
        setTimeout(() => {
            try {
                if(qrScannerInstance) {
                    qrScannerInstance.clear().catch(err => console.log(err));
                }
                qrScannerInstance = new Html5QrcodeScanner("qrReader", { fps: 15, qrbox: { width: 220, height: 220 } }, false);
                qrScannerInstance.render((decodedText) => {
                    scannerModal.style.display = 'none';
                    if(qrScannerInstance) {
                        qrScannerInstance.clear().catch(err => console.log(err));
                    }
                    fetch('process_qr.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'student_id=' + encodeURIComponent(decodedText.trim())
                    }).then(res => res.json()).then(data => {
                        showTopToast(data.message);
                        setTimeout(() => window.location.reload(), 1500);
                    }).catch(err => {
                        showTopToast('Failed to process QR code server response.');
                    });
                }, error => {});
            } catch(e) {
                showTopToast('Could not initialize camera. Please check permissions.');
            }
        }, 100);
    });

    document.getElementById('closeScannerModalBtn').addEventListener('click', () => {
        scannerModal.style.display = 'none';
        if(qrScannerInstance) { 
            try { 
                qrScannerInstance.clear().catch(err => console.log(err)); 
            } catch(e){} 
        }
    });

    window.addEventListener('click', (e) => {
        if (secMenu && !secBtn.contains(e.target) && !secMenu.contains(e.target)) {
            secMenu.classList.remove('show');
        }
        if (dotsMenu && !dotsBtn.contains(e.target) && !dotsMenu.contains(e.target)) {
            dotsMenu.classList.remove('show');
        }
    });
    
</script>

<script>
(function startAttendanceAutoFinalizer() {
    let isChecking = false;

    async function finalizeIncompleteAttendance() {
        if (isChecking || document.hidden) return;

        isChecking = true;

        try {
            const formData = new FormData();
            formData.append('action', 'finalize');

            const response = await fetch('process_qr.php', {
                method: 'POST',
                body: formData,
                cache: 'no-store'
            });

            if (!response.ok) return;

            const result = await response.json();

            if (
                result.status === 'success' &&
                typeof result.message === 'string' &&
                !result.message.includes('Removed 0 incomplete attendance record(s).')
            ) {
                window.location.reload();
            }
        } catch (error) {
            console.warn('Attendance auto-finalizer:', error);
        } finally {
            isChecking = false;
        }
    }

    finalizeIncompleteAttendance();
    setInterval(finalizeIncompleteAttendance, 5000);
})();
</script>

</body>
</html>