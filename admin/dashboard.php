<?php
session_start();
require_once "../config/db.php";

// Authentication check
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

// Get current date for welcome message
$currentHour = date('H');
$welcomeMessage = ($currentHour < 12) ? 'Good Morning' : (($currentHour < 18) ? 'Good Afternoon' : 'Good Evening');

/* ===== OPTIMIZED STATS QUERIES (FIXED FOR YOUR SCHEMA) ===== */
// Get all counts in separate queries (safer and avoids complex subqueries)
$total_books_query = "SELECT COUNT(*) as total FROM books";
$total_books_result = mysqli_query($conn, $total_books_query);
$total_books_row = mysqli_fetch_assoc($total_books_result);
$total_books = $total_books_row['total'];

$total_members_query = "SELECT COUNT(*) as total FROM users WHERE role='member'";
$total_members_result = mysqli_query($conn, $total_members_query);
$total_members_row = mysqli_fetch_assoc($total_members_result);
$total_members = $total_members_row['total'];

$total_admins_query = "SELECT COUNT(*) as total FROM users WHERE role='admin'";
$total_admins_result = mysqli_query($conn, $total_admins_query);
$total_admins_row = mysqli_fetch_assoc($total_admins_result);
$total_admins = $total_admins_row['total'];

$total_borrowings_query = "SELECT COUNT(*) as total FROM borrowings";
$total_borrowings_result = mysqli_query($conn, $total_borrowings_query);
$total_borrowings_row = mysqli_fetch_assoc($total_borrowings_result);
$total_borrowings = $total_borrowings_row['total'];

$total_fines_query = "SELECT COUNT(*) as total FROM fines";
$total_fines_result = mysqli_query($conn, $total_fines_query);
$total_fines_row = mysqli_fetch_assoc($total_fines_result);
$total_fines = $total_fines_row['total'];

// Monthly stats
$books_this_month_query = "SELECT COUNT(*) as total FROM books WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
$books_this_month_result = mysqli_query($conn, $books_this_month_query);
$books_this_month_row = mysqli_fetch_assoc($books_this_month_result);
$books_this_month = $books_this_month_row['total'];

$members_this_month_query = "SELECT COUNT(*) as total FROM users WHERE role='member' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
$members_this_month_result = mysqli_query($conn, $members_this_month_query);
$members_this_month_row = mysqli_fetch_assoc($members_this_month_result);
$members_this_month = $members_this_month_row['total'];

$borrowings_this_month_query = "SELECT COUNT(*) as total FROM borrowings WHERE borrow_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
$borrowings_this_month_result = mysqli_query($conn, $borrowings_this_month_query);
$borrowings_this_month_row = mysqli_fetch_assoc($borrowings_this_month_result);
$borrowings_this_month = $borrowings_this_month_row['total'];

// Today's stats
$today_borrowings_query = "SELECT COUNT(*) as total FROM borrowings WHERE borrow_date = CURDATE()";
$today_borrowings_result = mysqli_query($conn, $today_borrowings_query);
$today_borrowings_row = mysqli_fetch_assoc($today_borrowings_result);
$today_borrowings = $today_borrowings_row['total'];

$overdue_books_query = "SELECT COUNT(*) as total FROM borrowings WHERE return_date < CURDATE() AND status = 'borrowed'";
$overdue_books_result = mysqli_query($conn, $overdue_books_query);
$overdue_books_row = mysqli_fetch_assoc($overdue_books_result);
$overdue_books = $overdue_books_row['total'];

// FIXED: Active borrowings query (previously had incorrect logic)
$active_borrowings_query = "SELECT COUNT(*) as total FROM borrowings WHERE status = 'borrowed' AND return_date >= CURDATE()";
$active_borrowings_result = mysqli_query($conn, $active_borrowings_query);
$active_borrowings_row = mysqli_fetch_assoc($active_borrowings_result);
$active_borrowings = $active_borrowings_row['total'];

$collected_fines_query = "SELECT COALESCE(SUM(fine_amount), 0) as total FROM fines";
$collected_fines_result = mysqli_query($conn, $collected_fines_query);
$collected_fines_row = mysqli_fetch_assoc($collected_fines_result);
$collected_fines = $collected_fines_row['total'];

/* ===== PAGINATION ===== */
$limit = 5;
$book_page = isset($_GET['book_page']) ? max(1, (int)$_GET['book_page']) : 1;
$member_page = isset($_GET['member_page']) ? max(1, (int)$_GET['member_page']) : 1;
$book_offset = ($book_page - 1) * $limit;
$member_offset = ($member_page - 1) * $limit;

/* ===== BOOK SEARCH WITH PAGINATION ===== */
$book_search = isset($_GET['book_search']) ? mysqli_real_escape_string($conn, $_GET['book_search']) : '';
$search_param = "%$book_search%";

// Get total books for pagination (searching by title, author, or category name)
$total_books_search_query = "SELECT COUNT(*) as total 
                      FROM books b 
                      LEFT JOIN categories c ON b.category_id = c.id 
                      WHERE b.title LIKE ? OR b.author LIKE ? OR c.name LIKE ?";
$total_stmt = mysqli_prepare($conn, $total_books_search_query);
if ($total_stmt) {
    mysqli_stmt_bind_param($total_stmt, "sss", $search_param, $search_param, $search_param);
    mysqli_stmt_execute($total_stmt);
    $total_result = mysqli_stmt_get_result($total_stmt);
    $total_books_search_row = mysqli_fetch_assoc($total_result);
    $total_books_search = $total_books_search_row['total'];
    $total_book_pages = ceil($total_books_search / $limit);
} else {
    $total_books_search = 0;
    $total_book_pages = 0;
}

// Get paginated books with category name
$book_query = "SELECT b.id, b.title, b.author, b.quantity, c.name as category 
               FROM books b 
               LEFT JOIN categories c ON b.category_id = c.id 
               WHERE b.title LIKE ? OR b.author LIKE ? OR c.name LIKE ?
               ORDER BY b.id DESC
               LIMIT ?, ?";

$stmt = mysqli_prepare($conn, $book_query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "sssii", $search_param, $search_param, $search_param, $book_offset, $limit);
    mysqli_stmt_execute($stmt);
    $book_result = mysqli_stmt_get_result($stmt);
} else {
    $book_result = false;
}

/* ===== MEMBER SEARCH WITH PAGINATION ===== */
$member_search = isset($_GET['member_search']) ? mysqli_real_escape_string($conn, $_GET['member_search']) : '';
$member_param = "%$member_search%";

// Get total members for pagination
$total_members_search_query = "SELECT COUNT(*) as total FROM users WHERE role='member' AND (name LIKE ? OR email LIKE ?)";
$total_members_stmt = mysqli_prepare($conn, $total_members_search_query);
if ($total_members_stmt) {
    mysqli_stmt_bind_param($total_members_stmt, "ss", $member_param, $member_param);
    mysqli_stmt_execute($total_members_stmt);
    $total_members_result = mysqli_stmt_get_result($total_members_stmt);
    $total_members_search_row = mysqli_fetch_assoc($total_members_result);
    $total_members_search = $total_members_search_row['total'];
    $total_member_pages = ceil($total_members_search / $limit);
} else {
    $total_members_search = 0;
    $total_member_pages = 0;
}

// Get paginated members
$member_query = "SELECT id, name, email, created_at as membership_date 
                 FROM users 
                 WHERE role='member' 
                 AND (name LIKE ? OR email LIKE ?)
                 ORDER BY id DESC
                 LIMIT ?, ?";

$stmt2 = mysqli_prepare($conn, $member_query);
if ($stmt2) {
    mysqli_stmt_bind_param($stmt2, "ssii", $member_param, $member_param, $member_offset, $limit);
    mysqli_stmt_execute($stmt2);
    $member_result = mysqli_stmt_get_result($stmt2);
} else {
    $member_result = false;
}

// Get recent activities for dashboard
$recent_activities_query = "SELECT 'book_added' as type, title as description, created_at 
                            FROM books 
                            ORDER BY created_at DESC 
                            LIMIT 3
                            UNION ALL
                            SELECT 'member_joined' as type, name as description, created_at 
                            FROM users 
                            WHERE role='member' 
                            ORDER BY created_at DESC 
                            LIMIT 3
                            UNION ALL
                            SELECT 'book_borrowed' as type, CONCAT('Book ID: ', book_id) as description, borrow_date as created_at 
                            FROM borrowings 
                            ORDER BY borrow_date DESC 
                            LIMIT 3
                            ORDER BY created_at DESC 
                            LIMIT 5";
$recent_activities_result = mysqli_query($conn, $recent_activities_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Management System - Admin Dashboard</title>
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

        /* DARK MODE SIDEBAR */
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

        .nav-item:hover, .nav-item.active {
            background: rgba(255,255,255,0.08);
            color: #60a5fa;
            transform: translateX(5px);
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

        .welcome-badge {
            display: inline-block;
            background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
            color: white;
            padding: 8px 20px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .header h1 {
            font-size: 32px;
            margin-bottom: 8px;
            color: #f1f5f9;
        }

        .header p {
            color: #94a3b8;
            font-size: 16px;
        }

        /* STATS CARDS - DARK MODE */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: #111827;
            border-radius: 20px;
            padding: 20px;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            cursor: pointer;
            border: 1px solid rgba(255,255,255,0.05);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.4);
            background: #1a2332;
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .stat-icon.blue { background: rgba(59,130,246,0.2); color: #60a5fa; }
        .stat-icon.green { background: rgba(16,185,129,0.2); color: #34d399; }
        .stat-icon.purple { background: rgba(139,92,246,0.2); color: #a78bfa; }
        .stat-icon.orange { background: rgba(249,115,22,0.2); color: #fb923c; }
        .stat-icon.red { background: rgba(239,68,68,0.2); color: #f87171; }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #f1f5f9;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #94a3b8;
            font-size: 14px;
            font-weight: 500;
        }

        .stat-change {
            font-size: 13px;
            margin-top: 10px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .change-up { color: #34d399; }
        .change-down { color: #f87171; }

        /* SEARCH SECTIONS - DARK MODE */
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

        .section-header h2 {
            font-size: 24px;
            color: #f1f5f9;
        }

        .view-all {
            color: #60a5fa;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .view-all:hover {
            color: #93c5fd;
            transform: translateX(3px);
        }

        .search-box {
            display: flex;
            gap: 12px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .search-box input {
            flex: 1;
            padding: 12px 18px;
            background: #1f2937;
            border: 2px solid #374151;
            border-radius: 12px;
            font-size: 14px;
            color: #f1f5f9;
            transition: all 0.3s;
        }

        .search-box input:focus {
            outline: none;
            border-color: #60a5fa;
            box-shadow: 0 0 0 3px rgba(96,165,250,0.2);
            background: #1f2a3e;
        }

        .search-box input::placeholder {
            color: #6b7280;
        }

        .search-box button {
            padding: 12px 30px;
            background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
            color: white;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }

        .search-box button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(59,130,246,0.4);
        }

        .clear-search {
            padding: 12px 20px;
            background: #374151;
            color: #cbd5e1;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .clear-search:hover {
            background: #4b5563;
            color: white;
        }

        /* TABLES - DARK MODE */
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
            padding: 15px;
            background: #1a2332;
            color: #cbd5e1;
            font-weight: 600;
            font-size: 14px;
            border-bottom: 2px solid #374151;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #1f2937;
            color: #cbd5e1;
        }

        tr:hover {
            background: #1a2332;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-available {
            background: rgba(16,185,129,0.2);
            color: #34d399;
            border: 1px solid rgba(16,185,129,0.3);
        }

        .status-borrowed {
            background: rgba(239,68,68,0.2);
            color: #f87171;
            border: 1px solid rgba(239,68,68,0.3);
        }

        .action-btn {
            background: none;
            border: none;
            color: #60a5fa;
            cursor: pointer;
            font-size: 18px;
            transition: all 0.3s;
            padding: 5px 10px;
        }

        .action-btn:hover {
            color: #93c5fd;
            transform: scale(1.1);
        }

        /* PAGINATION - DARK MODE */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 25px;
            flex-wrap: wrap;
        }

        .page-link {
            padding: 8px 14px;
            background: #1f2937;
            border: 1px solid #374151;
            border-radius: 8px;
            color: #cbd5e1;
            text-decoration: none;
            transition: all 0.3s;
        }

        .page-link:hover, .page-link.active {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }

        /* QUICK SUMMARY - DARK MODE */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-top: 30px;
            margin-bottom: 30px;
        }

        .summary-item {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            color: white;
            padding: 20px;
            border-radius: 20px;
            text-align: center;
            transition: all 0.3s;
            border: 1px solid rgba(255,255,255,0.05);
        }

        .summary-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
            background: linear-gradient(135deg, #2d3a52 0%, #1a2332 100%);
        }

        .summary-value {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
            background: linear-gradient(135deg, #60a5fa, #a78bfa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .summary-label {
            font-size: 14px;
            opacity: 0.8;
        }

        /* RECENT ACTIVITIES */
        .activities-list {
            margin-top: 20px;
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            border-bottom: 1px solid #1f2937;
            transition: all 0.3s;
        }

        .activity-item:hover {
            background: #1a2332;
            border-radius: 12px;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .activity-icon.book_added { background: rgba(59,130,246,0.2); color: #60a5fa; }
        .activity-icon.member_joined { background: rgba(16,185,129,0.2); color: #34d399; }
        .activity-icon.book_borrowed { background: rgba(245,158,11,0.2); color: #fbbf24; }

        .activity-details {
            flex: 1;
        }

        .activity-title {
            font-weight: 600;
            margin-bottom: 4px;
        }

        .activity-time {
            font-size: 12px;
            color: #6b7280;
        }

        /* FOOTER */
        .footer {
            text-align: center;
            padding: 30px;
            color: #6b7280;
            font-size: 14px;
            border-top: 1px solid #1f2937;
            margin-top: 20px;
        }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #6b7280;
        }
        
        .error-message {
            background: rgba(239,68,68,0.1);
            color: #f87171;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            border: 1px solid rgba(239,68,68,0.3);
        }

        /* Scrollbar Styling */
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

        ::-webkit-scrollbar-thumb:hover {
            background: #6b7280;
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
        <a href="#" class="nav-item active">
            <i class="fas fa-chart-line"></i>
            <span>Dashboard</span>
        </a>
        <a href="books/view.php" class="nav-item">
            <i class="fas fa-book"></i>
            <span>Books</span>
        </a>
        <a href="members/view.php" class="nav-item">
            <i class="fas fa-users"></i>
            <span>Members</span>
        </a>
        <a href="borrowings/manage.php" class="nav-item">
            <i class="fas fa-exchange-alt"></i>
            <span>Borrowings</span>
        </a>
        <a href="fines/manage.php" class="nav-item">
            <i class="fas fa-money-bill-wave"></i>
            <span>Fines</span>
        </a>
        <a href="../auth/logout.php" class="nav-item logout-item">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</div>

<div class="main-content">
    <div class="header">
        <div class="welcome-badge">
            <i class="fas fa-hand-peace"></i> <?php echo htmlspecialchars($welcomeMessage); ?>
        </div>
        <h1>Welcome back, <?php echo htmlspecialchars($_SESSION['name']); ?>! 🎉</h1>
        <p>Here's what's happening in your library today.</p>
    </div>

    <!-- STATISTICS CARDS -->
    <div class="stats-grid">
        <div class="stat-card" onclick="window.location.href='books/view.php'">
            <div class="stat-header">
                <div class="stat-icon blue">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-change <?php echo $books_this_month > 0 ? 'change-up' : 'change-down'; ?>">
                    <i class="fas fa-arrow-<?php echo $books_this_month > 0 ? 'up' : 'down'; ?>"></i>
                    +<?php echo $books_this_month; ?> this month
                </div>
            </div>
            <div class="stat-value"><?php echo number_format($total_books); ?></div>
            <div class="stat-label">Total Books</div>
        </div>

        <div class="stat-card" onclick="window.location.href='members/view.php'">
            <div class="stat-header">
                <div class="stat-icon green">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="stat-change change-up">
                    <i class="fas fa-arrow-up"></i>
                    +<?php echo $members_this_month; ?> this month
                </div>
            </div>
            <div class="stat-value"><?php echo number_format($total_members); ?></div>
            <div class="stat-label">Members</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon purple">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="stat-change">
                    No change
                </div>
            </div>
            <div class="stat-value"><?php echo number_format($total_admins); ?></div>
            <div class="stat-label">Admins</div>
        </div>

        <div class="stat-card" onclick="window.location.href='borrowings/manage.php'">
            <div class="stat-header">
                <div class="stat-icon orange">
                    <i class="fas fa-hand-holding-heart"></i>
                </div>
                <div class="stat-change change-up">
                    <i class="fas fa-arrow-up"></i>
                    +<?php echo $borrowings_this_month; ?> this month
                </div>
            </div>
            <div class="stat-value"><?php echo number_format($total_borrowings); ?></div>
            <div class="stat-label">Total Borrowings</div>
        </div>

        <div class="stat-card" onclick="window.location.href='fines/manage.php'">
            <div class="stat-header">
                <div class="stat-icon red">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-change <?php echo $total_fines > 0 ? 'change-up' : 'change-down'; ?>">
                    <i class="fas fa-arrow-<?php echo $total_fines > 0 ? 'up' : 'down'; ?>"></i>
                    <?php echo $total_fines; ?> total fines
                </div>
            </div>
            <div class="stat-value"><?php echo number_format($total_fines); ?></div>
            <div class="stat-label">Total Fines</div>
        </div>
    </div>

    <!-- BOOK EXPLORER SECTION -->
    <div class="section-card">
        <div class="section-header">
            <h2><i class="fas fa-book-open"></i> Book Explorer</h2>
            <a href="books/view.php" class="view-all">View All Books <i class="fas fa-arrow-right"></i></a>
        </div>
        
        <form method="GET" class="search-box">
            <input type="text" name="book_search" placeholder="Search by title, author or category..." value="<?php echo htmlspecialchars($book_search); ?>">
            <input type="hidden" name="member_search" value="<?php echo htmlspecialchars($member_search); ?>">
            <input type="hidden" name="member_page" value="<?php echo $member_page; ?>">
            <button type="submit"><i class="fas fa-search"></i> Search Books</button>
            <?php if($book_search): ?>
                <a href="?member_search=<?php echo urlencode($member_search); ?>&member_page=<?php echo $member_page; ?>" class="clear-search">
                    <i class="fas fa-times"></i> Clear
                </a>
            <?php endif; ?>
        </form>

        <div class="data-table">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Title</th>
                        <th>Author</th>
                        <th>Category</th>
                        <th>Quantity</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($book_result && mysqli_num_rows($book_result) > 0): ?>
                        <?php $counter = $book_offset + 1; ?>
                        <?php while($book = mysqli_fetch_assoc($book_result)): ?>
                            <tr>
                                <td><?php echo $counter++; ?></td>
                                <td><strong><?php echo htmlspecialchars($book['title']); ?></strong></td>
                                <td><?php echo htmlspecialchars($book['author']); ?></td>
                                <td><?php echo htmlspecialchars($book['category'] ?? 'Uncategorized'); ?></td>
                                <td><?php echo $book['quantity']; ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $book['quantity'] > 0 ? 'available' : 'borrowed'; ?>">
                                        <i class="fas fa-<?php echo $book['quantity'] > 0 ? 'check-circle' : 'times-circle'; ?>"></i>
                                        <?php echo $book['quantity'] > 0 ? 'Available' : 'Out of Stock'; ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="action-btn" onclick="viewBook(<?php echo $book['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="no-data">
                                <i class="fas fa-search" style="font-size: 48px; margin-bottom: 10px; display: block;"></i>
                                <p>No books found</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if (isset($total_book_pages) && $total_book_pages > 1): ?>
        <div class="pagination">
            <?php for($i = 1; $i <= $total_book_pages; $i++): ?>
                <a href="?book_page=<?php echo $i; ?>&book_search=<?php echo urlencode($book_search); ?>&member_search=<?php echo urlencode($member_search); ?>&member_page=<?php echo $member_page; ?>" 
                   class="page-link <?php echo $i == $book_page ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        
        <?php if (isset($total_books_search)): ?>
        <div style="margin-top: 15px; color: #6b7280; font-size: 13px; text-align: center;">
            Showing <?php echo $book_offset + 1; ?> to <?php echo min($book_offset + $limit, $total_books_search); ?> of <?php echo number_format($total_books_search); ?> books
        </div>
        <?php endif; ?>
    </div>

    <!-- MEMBERS SECTION (UNDER BOOK TABLE) -->
    <div class="section-card">
        <div class="section-header">
            <h2><i class="fas fa-users"></i> Member Directory</h2>
            <a href="members/view.php" class="view-all">View All Members <i class="fas fa-arrow-right"></i></a>
        </div>
        
        <form method="GET" class="search-box">
            <input type="text" name="member_search" placeholder="Search by name or email..." value="<?php echo htmlspecialchars($member_search); ?>">
            <input type="hidden" name="book_search" value="<?php echo htmlspecialchars($book_search); ?>">
            <input type="hidden" name="book_page" value="<?php echo $book_page; ?>">
            <button type="submit"><i class="fas fa-search"></i> Search Members</button>
            <?php if($member_search): ?>
                <a href="?book_search=<?php echo urlencode($book_search); ?>&book_page=<?php echo $book_page; ?>" class="clear-search">
                    <i class="fas fa-times"></i> Clear
                </a>
            <?php endif; ?>
        </form>

        <div class="data-table">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Member Since</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($member_result && mysqli_num_rows($member_result) > 0): ?>
                        <?php while($member = mysqli_fetch_assoc($member_result)): ?>
                            <tr>
                                <td><?php echo $member['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($member['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($member['email']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($member['membership_date'])); ?></td>
                                <td>
                                    <button class="action-btn" onclick="viewMember(<?php echo $member['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="no-data">
                                <i class="fas fa-search" style="font-size: 48px; margin-bottom: 10px; display: block;"></i>
                                <p>No members found</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if (isset($total_member_pages) && $total_member_pages > 1): ?>
        <div class="pagination">
            <?php for($i = 1; $i <= $total_member_pages; $i++): ?>
                <a href="?member_page=<?php echo $i; ?>&member_search=<?php echo urlencode($member_search); ?>&book_search=<?php echo urlencode($book_search); ?>&book_page=<?php echo $book_page; ?>" 
                   class="page-link <?php echo $i == $member_page ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        
        <?php if (isset($total_members_search)): ?>
        <div style="margin-top: 15px; color: #6b7280; font-size: 13px; text-align: center;">
            Showing <?php echo $member_offset + 1; ?> to <?php echo min($member_offset + $limit, $total_members_search); ?> of <?php echo number_format($total_members_search); ?> members
        </div>
        <?php endif; ?>
    </div>

    <!-- QUICK SUMMARY -->
    <div class="summary-grid">
        <div class="summary-item">
            <div class="summary-value"><?php echo $today_borrowings; ?></div>
            <div class="summary-label">Today's Borrowings</div>
        </div>
        <div class="summary-item">
            <div class="summary-value"><?php echo $overdue_books; ?></div>
            <div class="summary-label">Overdue Books</div>
        </div>
        <div class="summary-item">
            <div class="summary-value"><?php echo $active_borrowings; ?></div>
            <div class="summary-label">Active Borrowings</div>
        </div>
        <div class="summary-item">
            <div class="summary-value">$<?php echo number_format($collected_fines, 2); ?></div>
            <div class="summary-label">Total Fines Collected</div>
        </div>
    </div>

    <!-- RECENT ACTIVITIES SECTION -->
    <div class="section-card">
        <div class="section-header">
            <h2><i class="fas fa-history"></i> Recent Activities</h2>
        </div>
        <div class="activities-list">
            <?php if ($recent_activities_result && mysqli_num_rows($recent_activities_result) > 0): ?>
                <?php while($activity = mysqli_fetch_assoc($recent_activities_result)): ?>
                    <div class="activity-item">
                        <div class="activity-icon <?php echo $activity['type']; ?>">
                            <i class="fas fa-<?php echo $activity['type'] == 'book_added' ? 'book' : ($activity['type'] == 'member_joined' ? 'user-plus' : 'book-reader'); ?>"></i>
                        </div>
                        <div class="activity-details">
                            <div class="activity-title">
                                <?php 
                                if($activity['type'] == 'book_added') {
                                    echo 'New Book Added: ' . htmlspecialchars($activity['description']);
                                } elseif($activity['type'] == 'member_joined') {
                                    echo 'New Member Joined: ' . htmlspecialchars($activity['description']);
                                } else {
                                    echo 'Book Borrowed: ' . htmlspecialchars($activity['description']);
                                }
                                ?>
                            </div>
                            <div class="activity-time">
                                <?php echo date('F j, Y g:i A', strtotime($activity['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-data">
                    <p>No recent activities to display</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="footer">
        <p>© 2025 Library Management System | All rights reserved.</p>
    </div>
</div>

<script>
function viewBook(bookId) {
    window.location.href = 'books/view.php?id=' + bookId;
}

function viewMember(memberId) {
    window.location.href = 'members/view.php?id=' + memberId;
}

// Add keyboard shortcuts for search
document.addEventListener('keydown', function(e) {
    // Ctrl + K to focus on book search
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        const bookSearch = document.querySelector('input[name="book_search"]');
        if (bookSearch) bookSearch.focus();
    }
    // Ctrl + M to focus on member search
    if ((e.ctrlKey || e.metaKey) && e.key === 'm') {
        e.preventDefault();
        const memberSearch = document.querySelector('input[name="member_search"]');
        if (memberSearch) memberSearch.focus();
    }
});
</script>

</body>
</html>