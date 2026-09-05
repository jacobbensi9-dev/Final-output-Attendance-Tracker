<?php
session_start();

if(!isset($_SESSION['admin']) || !isset($_SESSION['adviser_id'])){
    header("Location: index.php");
    exit();
}

include 'connection.php'; 
$current_adviser_id = (int)$_SESSION['adviser_id'];

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
$current_section = isset($_SESSION['section']) ? htmlspecialchars($_SESSION['section']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asian School - Home</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        body { display: flex; margin: 0; background-color: #0b132b; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; overflow-x: hidden; color: white; }
        
        /* SIDEBAR */
        .app-sidebar {
            width: 280px;
            background: #121a2f;
            border-right: 1px solid rgba(255, 255, 255, 0.08);
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
            transition: transform 0.3s ease, width 0.3s ease, padding 0.3s ease;
            overflow-y: auto;
        }
        .app-sidebar.collapsed { transform: translateX(-100%); width: 0; padding: 0; border-right: none; overflow: hidden; }
        .sidebar-top-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
        .sidebar-brand h3 { color: #38bdf8; margin: 0; font-size: 1.2rem; }
        .sidebar-brand p { color: rgba(255,255,255,0.4); margin: 0; font-size: 0.75rem; }
        
        .toggle-sidebar-btn {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white; padding: 6px 10px; border-radius: 6px; cursor: pointer; font-size: 0.9rem; transition: background 0.2s;
        }
        .toggle-sidebar-btn:hover { background: rgba(56, 189, 248, 0.2); color: #38bdf8; }

        /* MAIN WRAPPER WITH SMOOTH ANIMATION */
        .main-wrapper {
            flex-grow: 1; margin-left: 280px; transition: margin-left 0.3s ease; padding: 40px; box-sizing: border-box; width: calc(100% - 280px);
            animation: fadeInSlide 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        .app-sidebar.collapsed ~ .main-wrapper { margin-left: 0; width: 100%; }
        .external-open-sidebar { position: fixed; top: 20px; left: 20px; z-index: 999; display: none; }
        .app-sidebar.collapsed ~ .external-open-sidebar { display: block; }

        @keyframes fadeInSlide {
            0% {
                opacity: 0;
                transform: translateY(20px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .nav-view-btn { background: #0f172a; border: 1px solid rgba(255,255,255,0.12); color: rgba(255,255,255,0.6); padding: 10px 14px; font-weight: 600; font-size: 0.85rem; border-radius: 10px; cursor: pointer; transition: all 0.2s ease; text-align: left; display: block; width: 100%; box-sizing: border-box;}
        .nav-view-btn:hover { border-color: rgba(56, 189, 248, 0.5); color: #ffffff; background: rgba(56, 189, 248, 0.1); }
        .nav-view-btn.active-nav-btn { background: linear-gradient(135deg, #00bfff, #0284c7); color: white; border-color: transparent; box-shadow: 0 2px 8px rgba(0, 191, 255, 0.3); }

        /* MULTI-COLUMN CARDS CONTAINER */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }

        .home-hero-card {
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 25px;
            box-sizing: border-box;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: transform 0.3s ease, border-color 0.3s ease;
        }
        .home-hero-card:hover {
            transform: translateY(-5px);
            border-color: rgba(56, 189, 248, 0.4);
        }
        .home-hero-card img {
            width: 100%;
            height: 220px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 15px;
            border: 1px solid rgba(255,255,255,0.15);
        }
        .home-hero-card h2 { color: #38bdf8; margin-top: 0; font-size: 1.5rem; }
        .home-hero-card p { color: rgba(255,255,255,0.8); line-height: 1.6; font-size: 0.95rem; }
    </style>
</head>
<body>

<!-- LEFT SIDEBAR -->
<div class="app-sidebar" id="appSidebar">
    <div>
        <div class="sidebar-top-header">
            <div class="sidebar-brand">
                <h3>Asian School</h3>
                <p>Attendance Portal</p>
            </div>
            <button type="button" class="toggle-sidebar-btn" id="closeSidebarBtn" title="Close Sidebar">✕</button>
        </div>

        <div style="display: flex; flex-direction: column; gap: 8px; margin-top: 15px;">
            <a href="attendance_graph.php" class="nav-view-btn" style="text-decoration: none;">📈 Monthly Attendance</a>

        <a href="dashboard.php" class="nav-view-btn" style="text-decoration: none;">📊 Main Dashboard</a>
            <a href="logout.php" class="nav-view-btn" style="text-decoration: none; color: #ef4444; border-color: rgba(239,68,68,0.3);">🚪 Logout</a>
        </div>
    </div>
    
    <div style="font-size: 0.75rem; color: rgba(255,255,255,0.3); text-align: center; margin-top: 15px;">
        Asian School &copy; 2026
    </div>
</div>

<button type="button" class="toggle-sidebar-btn external-open-sidebar" id="openSidebarBtn" title="Open Sidebar">☰ Menu</button>

<div class="main-wrapper">
    <h1 style="color: white; margin-bottom: 10px; font-size: 2rem;">Welcome to Asian School Portal</h1>
    <p style="color: rgba(255,255,255,0.6); margin-bottom: 25px;">Explore our campus highlights, academic structure, and daily attendance portal system features below.</p>
    
    <!-- GRID LAYOUT UTILIZING RIGHT-SIDE SPACE -->
    <div class="cards-grid">
        
        <!-- CARD 1: Campus & Facilities -->
        <div class="home-hero-card">
            <div>
                <img src="asians.jpg" alt="Asian School Campus" onerror="this.src='https://images.unsplash.com/photo-1562774053-701939374585?auto=format&fit=crop&w=800&q=80'">
                <h2>Asian learning Center CAMPUS</h2>
                <p>Asian Learning Center (ALC), located in Pajo, Lapu-Lapu City, Cebu, is a private educational institution founded in 1991 by spouses Gregorio Magdadaro and Louella Duroya Magdadaro. It began as a preparatory school offering Nursery and Kindergarten classes in a rented house along R. de la Serna Street with approximately 120 pupils. As enrollment grew, the school expanded to include elementary education and later transferred to its permanent campus in Pajo in 1995, where a four-story building was constructed to accommodate its increasing student population. In 1998, ALC opened its High School Department, allowing students to complete both elementary and secondary education within the institution. Today, Asian Learning Center continues to provide quality education by promoting academic excellence, good character, leadership, and holistic development for its students.</p>
            </div>
            <div style="margin-top: 20px;">
            </div>
        </div>

        <!-- CARD 2: Academic Excellence & Mission -->
        <div class="home-hero-card">
            <div>
                <img src="ICT.jpg" alt="Academic Excellence">
                <h2>Academic Excellence</h2>
                <p>The Information and Communications Technology (ICT) Strand at Asian Learning Center (ALC), Pajo, is offered under the Technical-Vocational-Livelihood (TVL) track of the Senior High School program. It provides students with knowledge and practical skills in computer systems, programming, networking, and digital technologies. The strand includes the <strong>Computer Systems Servicing (CSS)</strong> specialization, where students are trained in computer hardware and software installation, computer maintenance, network setup, troubleshooting, and basic technical support. The curriculum also includes ICT-related subjects and work immersion to help students develop the competencies needed for higher education, industry certifications, and employment in the field of information and communications technology.</p>
            </div>
            <div style="margin-top: 20px;">
            </div>
        </div>

    </div>
</div>

<script>
    const appSidebar = document.getElementById('appSidebar');
    const closeSidebarBtn = document.getElementById('closeSidebarBtn');
    const openSidebarBtn = document.getElementById('openSidebarBtn');

    closeSidebarBtn.addEventListener('click', () => { appSidebar.classList.add('collapsed'); });
    openSidebarBtn.addEventListener('click', () => { appSidebar.classList.remove('collapsed'); });
</script>
</body>
</html>