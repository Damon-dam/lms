<?php
session_start();
require_once "../../config/db.php";

// Authentication check
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

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

// Get member statistics
$stats_query = "SELECT 
                (SELECT COUNT(*) FROM borrowings WHERE user_id = ?) as total_borrowings,
                (SELECT COUNT(*) FROM borrowings WHERE user_id = ? AND status = 'borrowed') as active_borrowings,
                (SELECT COUNT(*) FROM borrowings WHERE user_id = ? AND return_date < CURDATE() AND status = 'borrowed') as overdue_borrowings,
                (SELECT COALESCE(SUM(fine_amount), 0) FROM fines WHERE borrowing_id IN (SELECT id FROM borrowings WHERE user_id = ?)) as total_fines,
                (SELECT COUNT(*) FROM fines WHERE borrowing_id IN (SELECT id FROM borrowings WHERE user_id = ?) AND status = 'unpaid') as unpaid_fines_count";
$stats_stmt = mysqli_prepare($conn, $stats_query);
mysqli_stmt_bind_param($stats_stmt, "iiiii", $member_id, $member_id, $member_id, $member_id, $member_id);
mysqli_stmt_execute($stats_stmt);
$stats_result = mysqli_stmt_get_result($stats_stmt);
$stats = mysqli_fetch_assoc($stats_result);

// Get current borrowings
$current_borrowings_query = "SELECT b.id, bk.title, bk.author, b.borrow_date, b.return_date, b.status,
                              DATEDIFF(CURDATE(), b.return_date) as days_overdue
                              FROM borrowings b
                              JOIN books bk ON b.book_id = bk.id
                              WHERE b.user_id = ? AND b.status = 'borrowed'
                              ORDER BY b.borrow_date DESC";
$current_stmt = mysqli_prepare($conn, $current_borrowings_query);
mysqli_stmt_bind_param($current_stmt, "i", $member_id);
mysqli_stmt_execute($current_stmt);
$current_borrowings = mysqli_stmt_get_result($current_stmt);

// Get borrowing history
$history_query = "SELECT b.id, bk.title, bk.author, b.borrow_date, b.return_date, b.status,
                  (SELECT COALESCE(fine_amount, 0) FROM fines WHERE borrowing_id = b.id) as fine_amount
                  FROM borrowings b
                  JOIN books bk ON b.book_id = bk.id
                  WHERE b.user_id = ? AND b.status = 'returned'
                  ORDER BY b.return_date DESC
                  LIMIT 10";
$history_stmt = mysqli_prepare($conn, $history_query);
mysqli_stmt_bind_param($history_stmt, "i", $member_id);
mysqli_stmt_execute($history_stmt);
$history_borrowings = mysqli_stmt_get_result($history_stmt);

// Get recent activity from activity_logs if table exists
$recent_activity = null;
$table_check = "SHOW TABLES LIKE 'activity_logs'";
$table_result = mysqli_query($conn, $table_check);
if (mysqli_num_rows($table_result) > 0) {
    $activity_query = "SELECT action, details, ip_address, created_at 
                       FROM activity_logs 
                       WHERE user_id = ? 
                       ORDER BY created_at DESC 
                       LIMIT 10";
    $activity_stmt = mysqli_prepare($conn, $activity_query);
    mysqli_stmt_bind_param($activity_stmt, "i", $member_id);
    mysqli_stmt_execute($activity_stmt);
    $recent_activity = mysqli_stmt_get_result($activity_stmt);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Details - Library Management System</title>
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

        .action-buttons {
            display: flex;
            gap: 12px;
            margin-top: 15px;
        }

        .btn-edit, .btn-delete {
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-edit {
            background: rgba(59,130,246,0.2);
            color: #60a5fa;
            border: 1px solid rgba(59,130,246,0.3);
        }

        .btn-edit:hover {
            background: rgba(59,130,246,0.3);
            transform: translateY(-2px);
        }

        .btn-delete {
            background: rgba(239,68,68,0.2);
            color: #f87171;
            border: 1px solid rgba(239,68,68,0.3);
        }

        .btn-delete:hover {
            background: rgba(239,68,68,0.3);
            transform: translateY(-2px);
        }

        /* PROFILE SECTION */
        .profile-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .profile-card {
            background: #111827;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.05);
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #1f2937;
        }

        .profile-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            color: white;
        }

        .profile-info h2 {
            font-size: 24px;
            margin-bottom: 5px;
        }

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

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #1f2937;
        }

        .info-label {
            color: #94a3b8;
            font-size: 14px;
        }

        .info-value {
            font-weight: 600;
            color: #f1f5f9;
        }

        /* STATS CARDS */
        .stats-mini-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-mini-card {
            background: #1a2332;
            border-radius: 15px;
            padding: 15px;
            text-align: center;
        }

        .stat-mini-value {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-mini-label {
            font-size: 12px;
            color: #94a3b8;
        }

        /* TABLES */
        .section-card {
            background: #111827;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.05);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .section-header h3 {
            font-size: 20px;
            color: #f1f5f9;
        }

        .data-table {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 12px;
            background: #1a2332;
            color: #cbd5e1;
            font-weight: 600;
            font-size: 13px;
            border-bottom: 2px solid #374151;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #1f2937;
            color: #cbd5e1;
            font-size: 14px;
        }

        tr:hover {
            background: #1a2332;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .status-borrowed {
            background: rgba(245,158,11,0.2);
            color: #fbbf24;
            border: 1px solid rgba(245,158,11,0.3);
        }

        .status-returned {
            background: rgba(16,185,129,0.2);
            color: #34d399;
            border: 1px solid rgba(16,185,129,0.3);
        }

        .status-overdue {
            background: rgba(239,68,68,0.2);
            color: #f87171;
            border: 1px solid rgba(239,68,68,0.3);
        }

        .fine-amount {
            color: #f87171;
            font-weight: 600;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #6b7280;
        }

        .footer {
            text-align: center;
            padding: 30px;
            color: #6b7280;
            font-size: 14px;
            border-top: 1px solid #1f2937;
            margin-top: 20px;
        }

        @media (max-width: 1024px) {
            .profile-grid {
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
            .stats-mini-grid {
                grid-template-columns: 1fr;
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
        <div style="display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 15px;">
            <div>
                <h1><i class="fas fa-user-circle"></i> Member Details</h1>
                <p>View complete information about <?php echo htmlspecialchars($member['name']); ?></p>
            </div>
            <div class="action-buttons">
                <a href="edit.php?id=<?php echo $member_id; ?>" class="btn-edit">
                    <i class="fas fa-edit"></i> Edit Member
                </a>
                <button class="btn-delete" onclick="confirmDelete()">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </div>
        </div>
    </div>

    <!-- Profile Information -->
    <div class="profile-grid">
        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="profile-info">
                    <h2><?php echo htmlspecialchars($member['name']); ?></h2>
                    <span class="role-badge role-<?php echo $member['role']; ?>">
                        <i class="fas fa-<?php echo $member['role'] == 'admin' ? 'shield-alt' : 'user'; ?>"></i>
                        <?php echo ucfirst($member['role']); ?>
                    </span>
                </div>
            </div>
            
            <div class="info-row">
                <span class="info-label"><i class="fas fa-id-card"></i> Member ID</span>
                <span class="info-value">#<?php echo $member['id']; ?></span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="fas fa-envelope"></i> Email Address</span>
                <span class="info-value"><?php echo htmlspecialchars($member['email']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="fas fa-calendar-alt"></i> Member Since</span>
                <span class="info-value"><?php echo date('F d, Y', strtotime($member['created_at'])); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="fas fa-clock"></i> Membership Duration</span>
                <span class="info-value">
                    <?php 
                    $join_date = new DateTime($member['created_at']);
                    $today = new DateTime();
                    $interval = $join_date->diff($today);
                    echo $interval->y . ' years, ' . $interval->m . ' months';
                    ?>
                </span>
            </div>
        </div>

        <div class="profile-card">
            <h3 style="margin-bottom: 20px;"><i class="fas fa-chart-simple"></i> Library Statistics</h3>
            <div class="stats-mini-grid">
                <div class="stat-mini-card">
                    <div class="stat-mini-value"><?php echo $stats['total_borrowings']; ?></div>
                    <div class="stat-mini-label">Total Books Borrowed</div>
                </div>
                <div class="stat-mini-card">
                    <div class="stat-mini-value" style="color: <?php echo $stats['active_borrowings'] > 0 ? '#fbbf24' : '#34d399'; ?>">
                        <?php echo $stats['active_borrowings']; ?>
                    </div>
                    <div class="stat-mini-label">Currently Borrowed</div>
                </div>
                <div class="stat-mini-card">
                    <div class="stat-mini-value" style="color: #f87171;">
                        <?php echo $stats['overdue_borrowings']; ?>
                    </div>
                    <div class="stat-mini-label">Overdue Books</div>
                </div>
                <div class="stat-mini-card">
                    <div class="stat-mini-value" style="color: #fbbf24;">
                        $<?php echo number_format($stats['total_fines'], 2); ?>
                    </div>
                    <div class="stat-mini-label">Total Fines</div>
                </div>
            </div>
            
            <?php if($stats['unpaid_fines_count'] > 0): ?>
                <div style="background: rgba(239,68,68,0.1); border-radius: 12px; padding: 12px; margin-top: 15px;">
                    <p style="color: #f87171; font-size: 13px;">
                        <i class="fas fa-exclamation-triangle"></i> 
                        This member has <?php echo $stats['unpaid_fines_count']; ?> unpaid fine(s)
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Current Borrowings -->
    <div class="section-card">
        <div class="section-header">
            <h3><i class="fas fa-book-open"></i> Currently Borrowed Books</h3>
        </div>
        <div class="data-table">
            <?php if (mysqli_num_rows($current_borrowings) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Book Title</th>
                            <th>Author</th>
                            <th>Borrow Date</th>
                            <th>Due Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($borrowing = mysqli_fetch_assoc($current_borrowings)): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($borrowing['title']); ?></strong></td>
                                <td><?php echo htmlspecialchars($borrowing['author']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($borrowing['borrow_date'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($borrowing['return_date'])); ?></td>
                                <td>
                                    <?php if($borrowing['days_overdue'] > 0): ?>
                                        <span class="status-badge status-overdue">
                                            <i class="fas fa-exclamation-circle"></i> Overdue (<?php echo $borrowing['days_overdue']; ?> days)
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge status-borrowed">
                                            <i class="fas fa-book"></i> Borrowed
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-check-circle" style="font-size: 48px; margin-bottom: 10px; display: block;"></i>
                    <p>No books currently borrowed</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Borrowing History -->
    <div class="section-card">
        <div class="section-header">
            <h3><i class="fas fa-history"></i> Borrowing History (Last 10)</h3>
        </div>
        <div class="data-table">
            <?php if (mysqli_num_rows($history_borrowings) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Book Title</th>
                            <th>Author</th>
                            <th>Borrow Date</th>
                            <th>Return Date</th>
                            <th>Fine</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($history = mysqli_fetch_assoc($history_borrowings)): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($history['title']); ?></strong></td>
                                <td><?php echo htmlspecialchars($history['author']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($history['borrow_date'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($history['return_date'])); ?></td>
                                <td class="fine-amount">
                                    <?php if($history['fine_amount'] > 0): ?>
                                        $<?php echo number_format($history['fine_amount'], 2); ?>
                                    <?php else: ?>
                                        <span style="color: #34d399;">No fine</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-book" style="font-size: 48px; margin-bottom: 10px; display: block;"></i>
                    <p>No borrowing history found</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Activity Log (if available) -->
    <?php if ($recent_activity && mysqli_num_rows($recent_activity) > 0): ?>
    <div class="section-card">
        <div class="section-header">
            <h3><i class="fas fa-list-alt"></i> Recent Activity Log</h3>
        </div>
        <div class="data-table">
            <table>
                <thead>
                    <tr>
                        <th>Action</th>
                        <th>Details</th>
                        <th>IP Address</th>
                        <th>Date & Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($activity = mysqli_fetch_assoc($recent_activity)): ?>
                        <tr>
                            <td>
                                <?php 
                                $icon = 'fa-info-circle';
                                if(strpos($activity['action'], 'member') !== false) $icon = 'fa-user-edit';
                                if(strpos($activity['action'], 'borrow') !== false) $icon = 'fa-book';
                                if(strpos($activity['action'], 'fine') !== false) $icon = 'fa-money-bill';
                                ?>
                                <i class="fas <?php echo $icon; ?>"></i> 
                                <?php echo str_replace('_', ' ', ucfirst($activity['action'])); ?>
                            </td>
                            <td><?php echo htmlspecialchars($activity['details']); ?></td>
                            <td><code><?php echo $activity['ip_address']; ?></code></td>
                            <td><?php echo date('M d, Y g:i A', strtotime($activity['created_at'])); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

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
        <div style="margin-bottom: 25px; color: #cbd5e1;">
            Are you sure you want to delete member "<strong><?php echo htmlspecialchars($member['name']); ?></strong>"? 
            This action cannot be undone.
            <?php if($stats['active_borrowings'] > 0): ?>
                <br><br><strong style="color: #f87171;">Warning: This member has <?php echo $stats['active_borrowings']; ?> active borrowing(s).</strong>
            <?php endif; ?>
        </div>
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
function confirmDelete() {
    const activeBorrowings = <?php echo $stats['active_borrowings']; ?>;
    
    if (activeBorrowings > 0) {
        if (!confirm('This member has active borrowings. Deleting this account will not remove the borrowing records. Continue anyway?')) {
            return;
        }
    }
    
    document.getElementById('deleteModal').style.display = 'flex';
}

function deleteMember() {
    window.location.href = `view.php?delete=<?php echo $member_id; ?>`;
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}

window.onclick = function(event) {
    const modal = document.getElementById('deleteModal');
    if (event.target == modal) {
        closeDeleteModal();
    }
}
</script>

</body>
</html>