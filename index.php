<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Attendance - Login</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .error-message {
            color: #dc2626;
            font-size: 12px;
            margin-bottom: 12px;
            margin-top: -4px;
            text-align: center;
            font-weight: 500;
        }
    </style>
</head>
<body>

    <div id="welcomeOverlay" class="welcome-overlay">
        <div class="welcome-content">
            <img src="Logo.jpg" class="welcome-logo" alt="School Logo">
            <h2>Welcome to Asian Learning Center Inc.</h2>
            <p>Digital Attendance Logging System</p>
            <button type="button" class="start-btn" onclick="startAttendance()">Fill Up Form</button>
        </div>
    </div>

    <div class="container">
        <div class="form-header">
            <img src="Logo.jpg" class="logo" alt="School Logo">
            <h1>Attendance</h1>
            <span class="school-year">ALCI Logging Portal</span>
        </div>

        <form action="authenticate.php" method="POST">
            
            <div class="input-box">
                <input type="text" name="teacher_name" placeholder="Teacher's Name" required>
            </div>

            <div class="input-box">
                <input type="text" name="grade" placeholder="Grade Level" required>
            </div>

            <div class="name-row">
                <div class="input-box">
                    <input 
                        type="password" 
                        name="pin" 
                        placeholder="Enter PIN" 
                        inputmode="numeric" 
                        maxlength="6" 
                        pattern="[0-9]{1,6}" 
                        oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);" 
                        required>
                </div>
                <div class="input-box">
                    <input 
                        type="password" 
                        name="re_pin" 
                        placeholder="Re-enter PIN" 
                        inputmode="numeric" 
                        maxlength="6" 
                        pattern="[0-9]{1,6}" 
                        oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);" 
                        required>
                </div>
            </div>

            <?php 
            $hasError = false;
            if (isset($_SESSION['login_error'])): 
                $hasError = true;
            ?>
                <div class="error-message">
                    <?php 
                        echo $_SESSION['login_error']; 
                        unset($_SESSION['login_error']); 
                    ?>
                </div>
            <?php endif; ?>

            <button type="submit" name="login" class="submit-btn">Log In</button>
        </form>

        <div class="form-footer">
            <a href="login.php" class="admin-link">Create account / Sign Up</a>
        </div>
    </div>

    <script>
    const hasLoginError = <?php echo $hasError ? 'true' : 'false'; ?>;

    if (hasLoginError) {
        document.getElementById("welcomeOverlay").style.display = "none";
    }

    function startAttendance() {
        const overlay = document.getElementById('welcomeOverlay');
        overlay.classList.add('slide-up');
        setTimeout(() => { overlay.style.display = "none"; }, 600);
    }
    </script>
</body>
</html>