<?php session_start(); ?>[cite: 4]
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Attendance - Create Account</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .error-message {
            color: #dc2626;
            font-size: 13px;
            margin-bottom: 14px;
            margin-top: -6px;
            text-align: center;
            font-weight: 500;
        }
        .success-message {
            color: #16a34a;
            font-size: 13px;
            margin-bottom: 14px;
            margin-top: -6px;
            text-align: center;
            font-weight: 500;
        }
    </style>
</head>
<body>

    <div class="container">
        <div class="form-header">
            <img src="Logo.jpg" class="logo" alt="School Logo">
            <h1>Create Account</h1>
            <span class="school-year">ALCI Registration Portal</span>
        </div>

        <form action="register_adviser.php" method="POST" class="register-form">
            
            <div class="input-box">
                <input type="text" name="teacher_name" placeholder="Teacher's Name" required>
            </div>

            <div class="input-box">
                <input type="text" name="grade" placeholder="Grade Level (e.g. Grade 11)" required>
            </div>

            <div class="input-box">
                <input 
                    type="password" 
                    name="pin" 
                    placeholder="Create PIN (Max 6 digits)" 
                    inputmode="numeric" 
                    maxlength="6" 
                    oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);" 
                    required>
            </div>

            <div class="input-box">
                <input 
                    type="password" 
                    name="re_pin" 
                    placeholder="Confirm PIN" 
                    inputmode="numeric" 
                    maxlength="6" 
                    oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);" 
                    required>
            </div>

            <?php if (isset($_SESSION['reg_error'])): ?>
                <div class="error-message">
                    <?php echo $_SESSION['reg_error']; unset($_SESSION['reg_error']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['reg_success'])): ?>
                <div class="success-message">
                    <?php echo $_SESSION['reg_success']; unset($_SESSION['reg_success']); ?>
                </div>
            <?php endif; ?>

            <button type="submit" name="register" class="submit-btn">Register Account</button>
        </form>

        <div class="form-footer">
            <a href="index.php" class="admin-link">← Back to Attendance Login</a>
        </div>
    </div>

</body>
</html>