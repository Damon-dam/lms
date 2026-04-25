<?php
session_start();
require_once "../../config/db.php";

// Authentication check
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

// Function to log activities
function logActivity($conn, $user_id, $action, $details) {
    $query = "INSERT INTO activity_logs (user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())";
    $stmt = mysqli_prepare($conn, $query);
    $ip = $_SERVER['REMOTE_ADDR'];
    mysqli_stmt_bind_param($stmt, "isss", $user_id, $action, $details, $ip);
    return mysqli_stmt_execute($stmt);
}

// Create activity_logs table if not exists
$create_logs_table = "CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `action` varchar(50) NOT NULL,
    `details` text NOT NULL,
    `ip_address` varchar(45) NOT NULL,
    `created_at` datetime NOT NULL,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
mysqli_query($conn, $create_logs_table);

// Get member ID
$member_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($member_id <= 0) {
    header("Location: view.php");
    exit();
}

// Fetch member details
$member_query = "SELECT id, name, email, role, created_at FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $member_query);
mysqli_stmt_bind_param($stmt, "i", $member_id);
mysqli_stmt_execute($stmt);
$member_result = mysqli_stmt_get_result($stmt);
$member = mysqli_fetch_assoc($member_result);

if (!$member) {
    header("Location: view.php");
    exit();
}

// Prevent editing the last admin
if ($member['role'] == 'admin') {
    $admin_count_query = "SELECT COUNT(*) as count FROM users WHERE role = 'admin'";
    $admin_count_result = mysqli_query($conn, $admin_count_query);
    $admin_count = mysqli_fetch_assoc($admin_count_result);
    
    if ($admin_count['count'] <= 1 && $member['id'] == $_SESSION['user_id']) {
        $error_message = "Cannot edit the last admin account. You cannot remove your own admin privileges or change your role.";
    }
}

// Get member statistics
$stats_query = "SELECT 
                (SELECT COUNT(*) FROM borrowings WHERE user_id = ?) as total_borrowings,
                (SELECT COUNT(*) FROM borrowings WHERE user_id = ? AND status = 'borrowed') as active_borrowings,
                (SELECT COALESCE(SUM(fine_amount), 0) FROM fines WHERE borrowing_id IN (SELECT id FROM borrowings WHERE user_id = ?)) as total_fines";
$stats_stmt = mysqli_prepare($conn, $stats_query);
mysqli_stmt_bind_param($stats_stmt, "iii", $member_id, $member_id, $member_id);
mysqli_stmt_execute($stats_stmt);
$stats_result = mysqli_stmt_get_result($stats_stmt);
$stats = mysqli_fetch_assoc($stats_result);

// Handle form submission
$success_message = '';
$error_message = '';
$password_changed = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = mysqli_real_escape_string($conn, trim($_POST['name']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $role = isset($_POST['role']) ? mysqli_real_escape_string($conn, $_POST['role']) : $member['role'];
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $reset_password = isset($_POST['reset_password']) && $_POST['reset_password'] == '1' ? true : false;
    
    // Validation
    $errors = [];
    $changes_made = [];
    $old_data = $member;
    
    if (empty($name)) {
        $errors[] = "Member name is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email address is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    // Check if email already exists for another user
    $check_query = "SELECT id FROM users WHERE email = ? AND id != ?";
    $check_stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($check_stmt, "si", $email, $member_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    
    if (mysqli_num_rows($check_result) > 0) {
        $errors[] = "Email already registered to another user.";
    }
    
    // Role validation - prevent removing last admin
    if ($role != $member['role']) {
        if ($member['role'] == 'admin') {
            $admin_count_query = "SELECT COUNT(*) as count FROM users WHERE role = 'admin'";
            $admin_count_result = mysqli_query($conn, $admin_count_query);
            $admin_count = mysqli_fetch_assoc($admin_count_result);
            
            if ($admin_count['count'] <= 1) {
                $errors[] = "Cannot change role of the last admin account.";
            } elseif ($member['id'] == $_SESSION['user_id']) {
                $errors[] = "You cannot change your own admin role.";
            }
        }
        $changes_made[] = "Role: " . ucfirst($member['role']) . " → " . ucfirst($role);
    }
    
    // Password validation ONLY if reset_password is checked
    if ($reset_password && !empty($new_password)) {
        if (strlen($new_password) < 6) {
            $errors[] = "Password must be at least 6 characters long";
        } elseif ($new_password !== $confirm_password) {
            $errors[] = "Passwords do not match";
        } else {
            $password_changed = true;
        }
    }
    
    // Track other changes
    if ($name != $member['name']) {
        $changes_made[] = "Name: " . htmlspecialchars($member['name']) . " → " . htmlspecialchars($name);
    }
    if ($email != $member['email']) {
        $changes_made[] = "Email: " . htmlspecialchars($member['email']) . " → " . htmlspecialchars($email);
    }
    
    if (empty($errors)) {
        // Build update query
        $update_query = "UPDATE users SET name = ?, email = ?, role = ?";
        $params = [$name, $email, $role];
        $types = "sss";
        
        // Only update password if reset_password is checked and password is provided
        if ($reset_password && $password_changed) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_query .= ", password = ?";
            $params[] = $hashed_password;
            $types .= "s";
        }
        
        $update_query .= " WHERE id = ?";
        $params[] = $member_id;
        $types .= "i";
        
        $update_stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($update_stmt, $types, ...$params);
        
        if (mysqli_stmt_execute($update_stmt)) {
            // Log the activity
            $action = 'member_updated';
            $details = "Updated member ID: $member_id (" . htmlspecialchars($name) . "). Changes: " . implode(", ", $changes_made);
            if ($password_changed) {
                $details .= " | Password was reset";
            }
            logActivity($conn, $_SESSION['user_id'], $action, $details);
            
            if ($password_changed) {
                $success_message = "Member information updated successfully! Password has been reset. The member will need to use their new password to login.";
            } else {
                $success_message = "Member information updated successfully! " . (!empty($changes_made) ? "Changes: " . implode(", ", $changes_made) : "No changes were made.");
            }
            
            // Refresh member data
            $stmt = mysqli_prepare($conn, $member_query);
            mysqli_stmt_bind_param($stmt, "i", $member_id);
            mysqli_stmt_execute($stmt);
            $member_result = mysqli_stmt_get_result($stmt);
            $member = mysqli_fetch_assoc($member_result);
            
            // Refresh stats if role changed
            if ($role != $old_data['role']) {
                $stats_stmt = mysqli_prepare($conn, $stats_query);
                mysqli_stmt_bind_param($stats_stmt, "iii", $member_id, $member_id, $member_id);
                mysqli_stmt_execute($stats_stmt);
                $stats_result = mysqli_stmt_get_result($stats_stmt);
                $stats = mysqli_fetch_assoc($stats_result);
            }
        } else {
            $error_message = "Failed to update member. Please try again.";
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Member - Library Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #0a0e1a;
            color: #e2e8f0;
            overflow-x: hidden;
        }

        /* SIDEBAR */
        .sidebar {
            width: 280px;
            background: linear-gradient(135deg, #0f1419 0%, #1a1f2e 100%);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            padding: 30px 20px;
            box-shadow: 2px 0 20px rgba(0,0,0,0.5);
            transition: all 0.3s;
            z-index: 100;
            overflow-y: auto;
            border-right: 1px solid rgba(255,255,255,0.05);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .logo i {
            font-size: 32px;
            color: #fbbf24;
        }

        .logo h2 {
            color: white;
            font-weight: 700;
            font-size: 22px;
        }

        .logo p {
            color: rgba(255,255,255,0.6);
            font-size: 12px;
            margin-top: 4px;
        }

        .nav-menu {
            margin-top: 30px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            margin: 8px 0;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s;
            font-weight: 500;
        }

        .nav-item i {
            width: 24px;
            font-size: 18px;
        }

        .nav-item:hover {
            background: rgba(255,255,255,0.08);
            color: #60a5fa;
            transform: translateX(5px);
        }

        .nav-item.active {
            background: rgba(96,165,250,0.15);
            color: #60a5fa;
        }

        .logout-item {
            margin-top: 50px;
            color: #f87171;
        }

        .logout-item:hover {
            background: rgba(248,113,113,0.1);
            color: #f87171;
        }

        /* MAIN CONTENT */
        .main-content {
            margin-left: 280px;
            padding: 30px 40px;
            min-height: 100vh;
        }

        /* HEADER */
        .header {
            background: #111827;
            border-radius: 20px;
            padding: 25px 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.05);
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(96,165,250,0.1);
            color: #60a5fa;
            padding: 8px 16px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 15px;
            transition: all 0.3s;
            border: 1px solid rgba(96,165,250,0.2);
        }

        .back-button:hover {
            background: rgba(96,165,250,0.2);
            transform: translateX(-3px);
        }

        .header h1 {
            font-size: 28px;
            margin-bottom: 8px;
            color: #f1f5f9;
        }

        .header h1 i {
            color: #60a5fa;
            margin-right: 10px;
        }

        .header p {
            color: #94a3b8;
            font-size: 14px;
        }

        /* TWO COLUMN LAYOUT */
        .two-columns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        /* FORM CONTAINER */
        .form-container, .stats-container {
            background: #111827;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.05);
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #cbd5e1;
            font-size: 14px;
        }

        .form-group label i {
            margin-right: 8px;
            color: #60a5fa;
        }

        .required {
            color: #f87171;
            margin-left: 4px;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            background: #1f2937;
            border: 2px solid #374151;
            border-radius: 12px;
            font-size: 14px;
            color: #f1f5f9;
            transition: all 0.3s;
            font-family: 'Inter', sans-serif;
        }

        .form-control:focus {
            outline: none;
            border-color: #60a5fa;
            box-shadow: 0 0 0 3px rgba(96,165,250,0.2);
            background: #1f2a3e;
        }

        select.form-control {
            cursor: pointer;
        }

        /* ROLE BADGE */
        .role-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .role-admin {
            background: rgba(139,92,246,0.2);
            color: #a78bfa;
            border: 1px solid rgba(139,92,246,0.3);
        }

        .role-member {
            background: rgba(16,185,129,0.2);
            color: #34d399;
            border: 1px solid rgba(16,185,129,0.3);
        }

        /* PASSWORD SECTION - OPTIONAL */
        .password-section {
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px solid #1f2937;
        }

        .password-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            cursor: pointer;
            padding: 12px 15px;
            background: #1a2332;
            border-radius: 12px;
            transition: all 0.3s;
        }

        .password-header:hover {
            background: #1f2a3e;
        }

        .password-header h3 {
            font-size: 16px;
            color: #f1f5f9;
        }

        .optional-badge {
            font-size: 12px;
            color: #6b7280;
            font-weight: normal;
            margin-left: 10px;
        }

        .password-fields {
            display: none;
            animation: slideDown 0.3s ease-out;
            padding: 0 10px;
        }

        .password-fields.show {
            display: block;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* STATS CARD */
        .stats-card {
            margin-bottom: 25px;
        }

        .stat-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: #1a2332;
            border-radius: 12px;
            margin-bottom: 15px;
        }

        .stat-label {
            color: #94a3b8;
            font-size: 14px;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: #f1f5f9;
        }

        .stat-value.small {
            font-size: 18px;
        }

        .info-box {
            background: rgba(96,165,250,0.05);
            border-radius: 12px;
            padding: 15px;
            margin-top: 20px;
        }

        .info-box p {
            color: #94a3b8;
            font-size: 12px;
            margin: 5px 0;
        }

        .warning-box {
            background: rgba(239,68,68,0.05);
            border: 1px solid rgba(239,68,68,0.2);
        }

        /* BUTTONS */
        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn-primary {
            flex: 1;
            padding: 14px 24px;
            background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(59,130,246,0.4);
        }

        .btn-secondary {
            flex: 1;
            padding: 14px 24px;
            background: #1f2937;
            color: #cbd5e1;
            border: 1px solid #374151;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-secondary:hover {
            background: #374151;
            color: #f1f5f9;
        }

        .btn-danger {
            padding: 10px 20px;
            background: rgba(239,68,68,0.1);
            color: #f87171;
            border: 1px solid rgba(239,68,68,0.3);
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            width: 100%;
            justify-content: center;
        }

        .btn-danger:hover {
            background: rgba(239,68,68,0.2);
        }

        /* ALERTS */
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: rgba(16,185,129,0.1);
            border: 1px solid rgba(16,185,129,0.3);
            color: #34d399;
        }

        .alert-error {
            background: rgba(239,68,68,0.1);
            border: 1px solid rgba(239,68,68,0.3);
            color: #f87171;
        }

        .close-alert {
            margin-left: auto;
            cursor: pointer;
            font-size: 18px;
        }

        /* FOOTER */
        .footer {
            text-align: center;
            padding: 30px;
            color: #6b7280;
            font-size: 14px;
            border-top: 1px solid #1f2937;
            margin-top: 30px;
        }

        /* RESPONSIVE */
        @media (max-width: 1024px) {
            .two-columns {
                grid-template-columns: 1fr;
            }
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .button-group {
                flex-direction: column;
            }
        }

        ::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }

        ::-webkit-scrollbar-track {
            background: #1f2937;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: #4b5563;
            border-radius: 10px;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="logo">
        <i class="fas fa-book-open"></i>
        <div>
            <h2>LIBRARY</h2>
            <p>Management System</p>
        </div>
    </div>
    
    <div class="nav-menu">
        <a href="../dashboard.php" class="nav-item">
            <i class="fas fa-chart-line"></i>
            <span>Dashboard</span>
        </a>
        <a href="../books/view.php" class="nav-item">
            <i class="fas fa-book"></i>
            <span>Books</span>
        </a>
        <a href="view.php" class="nav-item active">
            <i class="fas fa-users"></i>
            <span>Members</span>
        </a>
        <a href="../borrowings/manage.php" class="nav-item">
            <i class="fas fa-exchange-alt"></i>
            <span>Borrowings</span>
        </a>
        <a href="../fines/manage.php" class="nav-item">
            <i class="fas fa-money-bill-wave"></i>
            <span>Fines</span>
        </a>
        <a href="../../auth/logout.php" class="nav-item logout-item">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</div>

<div class="main-content">
    <div class="header">
        <a href="view.php" class="back-button">
            <i class="fas fa-arrow-left"></i>
            Back to Members
        </a>
        <h1><i class="fas fa-user-edit"></i> Edit <?php echo $member['role'] == 'admin' ? 'Admin' : 'Member'; ?></h1>
        <p>Update user information - Password reset is optional</p>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <span><?php echo $success_message; ?></span>
            <i class="fas fa-times close-alert" onclick="this.parentElement.remove()"></i>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-triangle"></i>
            <span><?php echo $error_message; ?></span>
            <i class="fas fa-times close-alert" onclick="this.parentElement.remove()"></i>
        </div>
    <?php endif; ?>

    <div class="two-columns">
        <!-- Edit Form -->
        <div class="form-container">
            <form method="POST" action="" id="editMemberForm">
                <div class="form-group">
                    <label>
                        <i class="fas fa-user"></i>
                        Full Name
                        <span class="required">*</span>
                    </label>
                    <input type="text" name="name" class="form-control" 
                           value="<?php echo htmlspecialchars($member['name']); ?>"
                           placeholder="Enter user's full name" required>
                </div>

                <div class="form-group">
                    <label>
                        <i class="fas fa-envelope"></i>
                        Email Address
                        <span class="required">*</span>
                    </label>
                    <input type="email" name="email" class="form-control" 
                           value="<?php echo htmlspecialchars($member['email']); ?>"
                           placeholder="Enter email address" required>
                </div>

                <div class="form-group">
                    <label>
                        <i class="fas fa-user-tag"></i>
                        User Role
                        <span class="required">*</span>
                    </label>
                    <select name="role" class="form-control" id="roleSelect">
                        <option value="member" <?php echo $member['role'] == 'member' ? 'selected' : ''; ?>>📖 Member</option>
                        <option value="admin" <?php echo $member['role'] == 'admin' ? 'selected' : ''; ?>>⚙️ Admin</option>
                    </select>
                    <small style="color: #6b7280; display: block; margin-top: 5px;">
                        <i class="fas fa-info-circle"></i> 
                        Admins have full access to manage books, members, borrowings, and fines.
                    </small>
                </div>

                <!-- OPTIONAL PASSWORD RESET SECTION -->
                <div class="password-section">
                    <div class="password-header" onclick="togglePasswordFields()">
                        <h3>
                            <i class="fas fa-key"></i> 
                            Reset Password
                            <span class="optional-badge">(Optional - Click to expand)</span>
                        </h3>
                        <i class="fas fa-chevron-down" id="toggleIcon"></i>
                    </div>
                    
                    <div class="password-fields" id="passwordFields">
                        <div class="form-group">
                            <label>
                                <i class="fas fa-lock"></i>
                                New Password
                            </label>
                            <input type="password" name="new_password" class="form-control" 
                                   placeholder="Enter new password (minimum 6 characters)">
                            <small style="color: #6b7280; display: block; margin-top: 5px;">
                                <i class="fas fa-info-circle"></i> 
                                Password must be at least 6 characters long
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <i class="fas fa-lock"></i>
                                Confirm New Password
                            </label>
                            <input type="password" name="confirm_password" class="form-control" 
                                   placeholder="Confirm new password">
                        </div>
                        
                        <input type="hidden" name="reset_password" id="reset_password" value="0">
                        
                        <div class="info-box">
                            <p><i class="fas fa-info-circle"></i> <strong>Note:</strong> Leave password fields empty to keep the current password unchanged.</p>
                            <p><i class="fas fa-shield-alt"></i> Password will be securely hashed before saving to the database.</p>
                        </div>
                    </div>
                </div>

                <div class="button-group">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i>
                        Update User
                    </button>
                    <button type="button" class="btn-secondary" onclick="resetForm()">
                        <i class="fas fa-undo"></i>
                        Reset
                    </button>
                </div>
            </form>
        </div>

        <!-- Statistics & Info -->
        <div class="stats-container">
            <h3 style="margin-bottom: 20px;">
                <i class="fas fa-chart-line"></i> User Statistics
            </h3>
            
            <div class="stats-card">
                <div class="stat-item">
                    <span class="stat-label">
                        <i class="fas fa-id-badge"></i> User ID
                    </span>
                    <span class="stat-value small">#<?php echo $member['id']; ?></span>
                </div>
                
                <div class="stat-item">
                    <span class="stat-label">
                        <i class="fas fa-tag"></i> Current Role
                    </span>
                    <span class="stat-value small">
                        <span class="role-badge role-<?php echo $member['role']; ?>">
                            <i class="fas fa-<?php echo $member['role'] == 'admin' ? 'shield-alt' : 'user'; ?>"></i>
                            <?php echo ucfirst($member['role']); ?>
                        </span>
                    </span>
                </div>
                
                <div class="stat-item">
                    <span class="stat-label">
                        <i class="fas fa-calendar-alt"></i> Member Since
                    </span>
                    <span class="stat-value small">
                        <?php echo date('F d, Y', strtotime($member['created_at'])); ?>
                    </span>
                </div>
                
                <div class="stat-item">
                    <span class="stat-label">
                        <i class="fas fa-book-reader"></i> Total Borrowings
                    </span>
                    <span class="stat-value"><?php echo $stats['total_borrowings']; ?></span>
                </div>
                
                <div class="stat-item">
                    <span class="stat-label">
                        <i class="fas fa-book"></i> Active Borrowings
                    </span>
                    <span class="stat-value" style="color: <?php echo $stats['active_borrowings'] > 0 ? '#f87171' : '#34d399'; ?>">
                        <?php echo $stats['active_borrowings']; ?>
                    </span>
                </div>
                
                <div class="stat-item">
                    <span class="stat-label">
                        <i class="fas fa-money-bill-wave"></i> Total Fines
                    </span>
                    <span class="stat-value" style="color: <?php echo $stats['total_fines'] > 0 ? '#f87171' : '#34d399'; ?>">
                        $<?php echo number_format($stats['total_fines'], 2); ?>
                    </span>
                </div>
            </div>
            
            <div class="info-box <?php echo ($member['role'] == 'admin' && $member['id'] == $_SESSION['user_id']) ? 'warning-box' : ''; ?>">
                <p><i class="fas fa-info-circle" style="color: #60a5fa;"></i> <strong>User Information:</strong></p>
                <p>• Joined: <?php echo date('M d, Y', strtotime($member['created_at'])); ?></p>
                <p>• Current Role: <?php echo ucfirst($member['role']); ?></p>
                <?php if($member['id'] == $_SESSION['user_id']): ?>
                    <p style="color: #fbbf24;">⚠️ You are editing your own account. Be careful with role changes!</p>
                <?php endif; ?>
                <?php if($stats['active_borrowings'] > 0): ?>
                    <p style="color: #f87171;">⚠️ User has <?php echo $stats['active_borrowings']; ?> active borrowing(s)</p>
                <?php endif; ?>
                <?php if($stats['total_fines'] > 0): ?>
                    <p style="color: #f87171;">💰 User has unpaid fines: $<?php echo number_format($stats['total_fines'], 2); ?></p>
                <?php endif; ?>
            </div>
            
            <div style="margin-top: 20px;">
                <button class="btn-danger" onclick="confirmDelete()">
                    <i class="fas fa-trash"></i>
                    Delete User Account
                </button>
            </div>
        </div>
    </div>

    <div class="footer">
        <p>© 2025 Library Management System | All rights reserved.</p>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); align-items: center; justify-content: center;">
    <div style="background: #111827; border-radius: 20px; padding: 30px; max-width: 500px; width: 90%; border: 1px solid rgba(255,255,255,0.1);">
        <div style="font-size: 24px; margin-bottom: 20px; color: #f1f5f9;">
            <i class="fas fa-exclamation-triangle" style="color: #f87171;"></i> Confirm Delete
        </div>
        <div style="margin-bottom: 25px; color: #cbd5e1;" id="deleteModalBody"></div>
        <div style="display: flex; gap: 12px; justify-content: flex-end;">
            <button onclick="closeDeleteModal()" style="padding: 10px 20px; background: #1f2937; color: #cbd5e1; border: none; border-radius: 10px; cursor: pointer;">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button onclick="deleteMember()" style="padding: 10px 20px; background: #ef4444; color: white; border: none; border-radius: 10px; cursor: pointer;">
                <i class="fas fa-trash"></i> Delete
            </button>
        </div>
    </div>
</div>

<script>
let passwordFieldsVisible = false;

function togglePasswordFields() {
    const fields = document.getElementById('passwordFields');
    const icon = document.getElementById('toggleIcon');
    const resetPasswordInput = document.getElementById('reset_password');
    
    passwordFieldsVisible = !passwordFieldsVisible;
    
    if (passwordFieldsVisible) {
        fields.classList.add('show');
        icon.classList.remove('fa-chevron-down');
        icon.classList.add('fa-chevron-up');
        resetPasswordInput.value = '1';
    } else {
        fields.classList.remove('show');
        icon.classList.remove('fa-chevron-up');
        icon.classList.add('fa-chevron-down');
        resetPasswordInput.value = '0';
        
        // Clear password fields when collapsing
        document.querySelector('input[name="new_password"]').value = '';
        document.querySelector('input[name="confirm_password"]').value = '';
    }
}

function resetForm() {
    if (confirm('Are you sure you want to reset the form? Any unsaved changes will be lost.')) {
        location.reload();
    }
}

function confirmDelete() {
    const activeBorrowings = <?php echo $stats['active_borrowings']; ?>;
    const isAdmin = <?php echo $member['role'] == 'admin' ? 'true' : 'false'; ?>;
    const isSelf = <?php echo $member['id'] == $_SESSION['user_id'] ? 'true' : 'false'; ?>;
    
    if (activeBorrowings > 0) {
        alert('Cannot delete user with active borrowings. Please ensure all books are returned first.');
        return;
    }
    
    if (isSelf) {
        alert('You cannot delete your own account while logged in. Please ask another admin to delete your account if needed.');
        return;
    }
    
    let message = `Are you sure you want to delete user "<strong><?php echo htmlspecialchars($member['name']); ?></strong>"? This action cannot be undone.`;
    if (isAdmin) {
        message += `<br><br><strong style="color: #f87171;">WARNING: This user is an ADMIN. Deleting admin accounts may affect system management.</strong>`;
    }
    
    document.getElementById('deleteModalBody').innerHTML = message;
    document.getElementById('deleteModal').style.display = 'flex';
}

function deleteMember() {
    window.location.href = `view.php?delete=<?php echo $member_id; ?>`;
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('deleteModal');
    if (event.target == modal) {
        closeDeleteModal();
    }
}

// Auto-hide alerts after 5 seconds
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 300);
    });
}, 5000);

// Client-side validation for role changes
document.getElementById('roleSelect').addEventListener('change', function() {
    const newRole = this.value;
    const currentRole = '<?php echo $member['role']; ?>';
    const isSelf = <?php echo $member['id'] == $_SESSION['user_id'] ? 'true' : 'false'; ?>;
    
    if (isSelf && newRole !== currentRole) {
        if (!confirm('WARNING: You are changing your own role. If you remove admin privileges from yourself, you may lose access to admin features. Continue?')) {
            this.value = currentRole;
        }
    }
});
</script>

</body>
</html>