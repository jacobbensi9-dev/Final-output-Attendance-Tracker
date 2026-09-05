<?php
include 'connection.php';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if(isset($_POST['update_student'])) {
    $first = mysqli_real_escape_string($conn, $_POST['first_name']);
    $last = mysqli_real_escape_string($conn, $_POST['last_name']);
    $parent_number = mysqli_real_escape_string($conn, $_POST['parent_number']);
    
    mysqli_query($conn, "UPDATE student SET first_name='$first', last_name='$last', parent_number='$parent_number' WHERE id=$id");
    
    // Redirects back to the dashboard and triggers the roster modal view
    header("Location: dashboard.php?view=roster");
    exit();
}

$result = mysqli_query($conn, "SELECT * FROM student WHERE id=$id");
$student = mysqli_fetch_assoc($result);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Student</title>
    <style>
        body { 
            background: rgba(15, 23, 42, 0.75); 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            margin: 0; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            height: 100vh; 
            overflow: hidden;
        }
        .modal-card { 
            background: #ffffff; 
            color: #1e293b; 
            width: 450px; 
            border-radius: 12px; 
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.2); 
            overflow: hidden; 
            border: 1px solid #e2e8f0;
            animation: fadeIn 0.2s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: #0f172a;
        }
        .close-btn {
            background: none;
            border: none;
            font-size: 18px;
            color: #64748b;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
        }
        .close-btn:hover {
            color: #0f172a;
        }
        .modal-body {
            padding: 24px;
        }
        label { 
            display: block; 
            font-size: 13px; 
            font-weight: 600; 
            color: #475569; 
            margin-bottom: 6px; 
        }
        input { 
            width: 100%; 
            padding: 10px 14px; 
            margin-bottom: 18px; 
            border-radius: 6px; 
            border: 1px solid #cbd5e1; 
            background: #f8fafc; 
            color: #0f172a; 
            box-sizing: border-box; 
            font-size: 14px;
        }
        input:focus {
            outline: none;
            border-color: #2563eb;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 10px;
        }
        .btn-cancel {
            padding: 10px 16px;
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        .btn-cancel:hover {
            background: #e2e8f0;
        }
        .btn-save { 
            padding: 10px 18px; 
            background: #2563eb; 
            color: white; 
            border: none; 
            border-radius: 6px; 
            cursor: pointer; 
            font-weight: 600; 
            font-size: 14px;
        } 
        .btn-save:hover { 
            background: #1d4ed8; 
        }
    </style>
</head>
<body>

<div class="modal-card">
    <div class="modal-header">
        <h3>Edit Student Information</h3>
        <!-- Returns to the dashboard with the roster view enabled -->
        <a href="dashboard.php?view=roster" class="close-btn">&times;</a>
    </div>
    
    <div class="modal-body">
        <form method="POST">
            <label>First Name</label>
            <input type="text" name="first_name" value="<?php echo htmlspecialchars($student['first_name'] ?? ''); ?>" required>
            
            <label>Last Name</label>
            <input type="text" name="last_name" value="<?php echo htmlspecialchars($student['last_name'] ?? ''); ?>" required>
            
            <label>Parent's Number</label>
            <input type="text" name="parent_number" value="<?php echo htmlspecialchars($student['parent_number'] ?? ''); ?>" required>

            <div class="form-actions">
                <a href="dashboard.php?view=roster" class="btn-cancel">Cancel</a>
                <button type="submit" name="update_student" class="btn-save">Save Changes</button>
            </div>
        </form>
    </div>
</div>

</body>
</html>