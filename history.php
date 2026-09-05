<?php
include 'db.php'; // Ensure this connects to your database

// Get the date from the URL, default to today
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
?>

<!DOCTYPE html>
<html>
<head>
    <title>Attendance History</title>
    <style>
        body { background: #0f172a; color: white; padding: 20px; font-family: sans-serif; }
        table { width: 100%; border-collapse: collapse; background: #1e293b; border-radius: 8px; overflow: hidden; }
        th, td { padding: 15px; border-bottom: 1px solid #334155; text-align: left; }
    </style>
</head>
<body>
  <div class="days-nav">
    <a href="history.php?date=2026-07-06" class="nav-btn">Monday</a>
    <a href="history.php?date=2026-07-07" class="nav-btn">Tuesday</a>
    <a href="history.php?date=2026-07-08" class="nav-btn">Wednesday</a>
    <a href="history.php?date=2026-07-09" class="nav-btn">Thursday</a>
    <a href="history.php?date=2026-07-10" class="nav-btn">Friday</a>
</div>
    <a href="dashboard.php" style="color: #38bdf8;">Back to Dashboard</a>
    
    <table>
        <tr>
            <th>Student Name</th>
            <th>Status</th>
        </tr>
        <?php
        $query = "SELECT s.first_name, s.last_name, a.status 
                  FROM attendance a 
                  JOIN student s ON a.student_id = s.id 
                  WHERE a.date = '$date'";
        $result = mysqli_query($conn, $query);
        
        while($row = mysqli_fetch_assoc($result)) {
            echo "<tr>
                    <td>{$row['last_name']}, {$row['first_name']}</td>
                    <td>{$row['status']}</td>
                  </tr>";
        }
        ?>
    </table>
</body>
</html>