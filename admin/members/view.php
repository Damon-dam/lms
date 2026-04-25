<?php
session_start();
require_once "../../config/db.php";

// Authentication check
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

// Handle Member Delete Request
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $member_id = (int)$_GET['delete'];
    
    // Check if member has any active borrowings
    $check_query = "SELECT COUNT(*) as count FROM borrowings WHERE user_id = ? AND status = 'borrowed'";
    $check_stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($check_stmt, "i", $member_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    $active_borrowings = mysqli_fetch_assoc($check_result);
    
    if ($active_borrowings['count'] > 0) {
        $error_message = "Cannot delete this member because they have {$active_borrowings['count']} active borrowing(s).";
    } else {
        // Get member name for message
        $name_query = "SELECT name FROM users WHERE id = ?";
        $name_stmt = mysqli_prepare($conn, $name_query);
        mysqli_stmt_bind_param($name_stmt, "i", $member_id);
        mysqli_stmt_execute($name_stmt);
        $name_result = mysqli_stmt_get_result($name_stmt);
        $member = mysqli_fetch_assoc($name_result);
        
        // Delete member
        $delete_query = "DELETE FROM users WHERE id = ? AND role = 'member'";
        $delete_stmt = mysqli_prepare($conn, $delete_query);
        mysqli_stmt_bind_param($delete_stmt, "i", $member_id);
        
        if (mysqli_stmt_execute($delete_stmt)) {
            $success_message = "Member \"{$member['name']}\" has been deleted successfully!";
        } else {
            $error_message = "Failed to delete member.";
        }
    }
    
    // Redirect to refresh the page
    $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $redirect_url = "view.php?page=" . $current_page;
    if (isset($_GET['search'])) $redirect_url .= "&search=" . urlencode($_GET['search']);
    
    header("Location: " . $redirect_url);
    exit();
}

// Pagination
$limit = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Search
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

// Build WHERE clause
$where_conditions = ["role = 'member'"];
$params = [];
$types = "";

if (!empty($search)) {
    $where_conditions[] = "(name LIKE ? OR email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Get total members count
$count_query = "SELECT COUNT(*) as total FROM users $where_clause";
$count_stmt = mysqli_prepare($conn, $count_query);

if (!empty($params)) {
    mysqli_stmt_bind_param($count_stmt, $types, ...$params);
}

// Fix: Check if prepare succeeded
if ($count_stmt) {
    mysqli_stmt_execute($count_stmt);
    $count_result = mysqli_stmt_get_result($count_stmt);
    $total_members = mysqli_fetch_assoc($count_result)['total'];
} else {
    $total_members = 0;
}
$total_pages = ceil($total_members / $limit);

// Adjust page if it exceeds total pages
if ($page > $total_pages && $total_pages > 0) {
    header("Location: view.php?page=" . $total_pages . 
           (isset($_GET['search']) ? "&search=" . urlencode($_GET['search']) : ""));
    exit();
}

// Fetch members with borrowings count - SIMPLIFIED QUERY
$query = "SELECT u.*, 
          (SELECT COUNT(*) FROM borrowings WHERE user_id = u.id) as total_borrowings,
          (SELECT COUNT(*) FROM borrowings WHERE user_id = u.id AND status = 'borrowed') as active_borrowings
          FROM users u
          $where_clause 
          ORDER BY u.id DESC 
          LIMIT ?, ?";

$params[] = $offset;
$params[] = $limit;
$types .= "ii";

$stmt = mysqli_prepare($conn, $query);

// Fix: Check if prepare succeeded
if ($stmt) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $members_result = mysqli_stmt_get_result($stmt);
} else {
    $members_result = false;
    $error_message = "Database query failed: " . mysqli_error($conn);
}

// Calculate statistics - FIXED with error checking
$total_active = $total_members; // Since no status column, assume all are active

$new_this_month_query = "SELECT COUNT(*) as count FROM users WHERE role = 'member' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
$new_this_month_result = mysqli_query($conn, $new_this_month_query);
if ($new_this_month_result) {
    $new_this_month = mysqli_fetch_assoc($new_this_month_result)['count'];
} else {
    $new_this_month = 0;
}

$total_borrowings_query = "SELECT COUNT(*) as count FROM borrowings";
$total_borrowings_result = mysqli_query($conn, $total_borrowings_query);
if ($total_borrowings_result) {
    $total_borrowings = mysqli_fetch_assoc($total_borrowings_result)['count'];
} else {
    $total_borrowings = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Members - Library Management System</title>
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

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header h1 {
            font-size: 28px;
            color: #f1f5f9;
        }

        .header h1 i {
            color: #60a5fa;
            margin-right: 10px;
        }

        .header p {
            color: #94a3b8;
            font-size: 14px;
            margin-top: 5px;
        }

        .btn-add {
            background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
            color: white;
            padding: 12px 24px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(59,130,246,0.4);
        }

        /* STATS CARDS */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 24px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #111827;
            border-radius: 20px;
            padding: 20px;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.05);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            background: #1a2332;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
        }

        .stat-icon.purple { background: rgba(139,92,246,0.2); color: #a78bfa; }
        .stat-icon.green { background: rgba(16,185,129,0.2); color: #34d399; }
        .stat-icon.blue { background: rgba(59,130,246,0.2); color: #60a5fa; }
        .stat-icon.orange { background: rgba(249,115,22,0.2); color: #fb923c; }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #f1f5f9;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #94a3b8;
            font-size: 13px;
        }

        /* FILTER SECTION */
        .filter-section {
            background: #111827;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.05);
        }

        .filter-form {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 13px;
            font-weight: 600;
            color: #94a3b8;
        }

        .filter-group label i {
            margin-right: 6px;
        }

        .filter-group input {
            width: 100%;
            padding: 10px 14px;
            background: #1f2937;
            border: 2px solid #374151;
            border-radius: 10px;
            color: #f1f5f9;
            font-size: 14px;
        }

        .filter-group input:focus {
            outline: none;
            border-color: #60a5fa;
        }

        .btn-filter, .btn-reset {
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
        }

        .btn-filter {
            background: #3b82f6;
            color: white;
        }

        .btn-filter:hover {
            background: #2563eb;
        }

        .btn-reset {
            background: #1f2937;
            color: #cbd5e1;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-reset:hover {
            background: #374151;
            color: white;
        }

        /* TABLE */
        .table-container {
            background: #111827;
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.05);
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
            font-size: 13px;
        }

        tr:hover {
            background: #1a2332;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            width: fit-content;
            background: rgba(16,185,129,0.2);
            color: #34d399;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-view, .btn-edit, .btn-delete {
            background: none;
            border: none;
            cursor: pointer;
            padding: 6px;
            border-radius: 8px;
            transition: all 0.3s;
            width: 32px;
            height: 32px;
            font-size: 14px;
        }

        .btn-view { color: #34d399; }
        .btn-view:hover { background: rgba(16,185,129,0.2); transform: scale(1.1); }
        .btn-edit { color: #60a5fa; }
        .btn-edit:hover { background: rgba(96,165,250,0.2); transform: scale(1.1); }
        .btn-delete { color: #f87171; }
        .btn-delete:hover { background: rgba(248,113,113,0.2); transform: scale(1.1); }
        .btn-delete:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* PAGINATION */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
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
            font-size: 14px;
        }

        .page-link:hover, .page-link.active {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }

        /* ALERTS */
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
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
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            .filter-form {
                flex-direction: column;
            }
            .stats-grid {
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
        <div class="header-top">
            <div>
                <h1><i class="fas fa-users"></i> Manage Members</h1>
                <p>View, search, and manage library members</p>
            </div>
            <a href="add.php" class="btn-add">
                <i class="fas fa-plus"></i>
                Add New Member
            </a>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if (isset($success_message) && $success_message): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <span><?php echo $success_message; ?></span>
            <i class="fas fa-times close-alert" onclick="this.parentElement.remove()"></i>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message) && $error_message): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-triangle"></i>
            <span><?php echo $error_message; ?></span>
            <i class="fas fa-times close-alert" onclick="this.parentElement.remove()"></i>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon purple">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-value"><?php echo number_format($total_members); ?></div>
            <div class="stat-label">Total Members</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon green">
                <i class="fas fa-user-check"></i>
            </div>
            <div class="stat-value"><?php echo number_format($total_active); ?></div>
            <div class="stat-label">Active Members</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon blue">
                <i class="fas fa-calendar-plus"></i>
            </div>
            <div class="stat-value">+<?php echo number_format($new_this_month); ?></div>
            <div class="stat-label">New This Month</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon orange">
                <i class="fas fa-book-reader"></i>
            </div>
            <div class="stat-value"><?php echo number_format($total_borrowings); ?></div>
            <div class="stat-label">Total Borrowings</div>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
        <form method="GET" class="filter-form">
            <div class="filter-group">
                <label><i class="fas fa-search"></i> Search Members</label>
                <input type="text" name="search" placeholder="Search by name or email..." 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <div class="filter-group" style="display: flex; gap: 10px; align-items: flex-end;">
                <button type="submit" class="btn-filter">
                    <i class="fas fa-search"></i> Search
                </button>
                <a href="view.php" class="btn-reset">
                    <i class="fas fa-undo"></i> Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Members Table -->
    <div class="table-container">
        <?php if ($members_result && mysqli_num_rows($members_result) > 0): ?>
            <div style="margin-bottom: 15px; padding: 10px; background: #1a2332; border-radius: 10px;">
                <i class="fas fa-info-circle"></i> 
                Showing members <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_members); ?> of <?php echo $total_members; ?> total members
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Member Since</th>
                        <th>Borrowings</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($member = mysqli_fetch_assoc($members_result)): ?>
                        <tr>
                            <td>#<?php echo $member['id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($member['name']); ?></strong>
                            </td>
                            <td>
                                <i class="fas fa-envelope" style="color: #6b7280; font-size: 11px;"></i>
                                <?php echo htmlspecialchars($member['email']); ?>
                            </td>
                            <td>
                                <span class="status-badge">
                                    <i class="fas fa-check-circle"></i>
                                    Active
                                </span>
                            </td>
                            <td>
                                <i class="fas fa-calendar" style="color: #6b7280; font-size: 11px;"></i>
                                <?php echo date('M d, Y', strtotime($member['created_at'])); ?>
                            </td>
                            <td>
                                <span style="display: flex; gap: 5px; align-items: center;">
                                    <span style="color: #60a5fa;"><?php echo $member['total_borrowings']; ?></span>
                                    <?php if($member['active_borrowings'] > 0): ?>
                                        <span style="color: #34d399; font-size: 11px;">
                                            <i class="fas fa-book"></i> (<?php echo $member['active_borrowings']; ?> active)
                                        </span>
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-view" onclick="viewMember(<?php echo $member['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn-edit" onclick="editMember(<?php echo $member['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if($member['active_borrowings'] == 0): ?>
                                        <button class="btn-delete" onclick="confirmDelete(<?php echo $member['id']; ?>, '<?php echo htmlspecialchars($member['name']); ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php else: ?>
                                        <button class="btn-delete" disabled title="Cannot delete member with active borrowings">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>" class="page-link">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>
                    
                    <?php 
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    for($i = $start_page; $i <= $end_page; $i++): 
                    ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" 
                           class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>" class="page-link">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div style="text-align: center; padding: 60px 20px;">
                <i class="fas fa-users" style="font-size: 64px; color: #4b5563; margin-bottom: 20px; display: block;"></i>
                <h3 style="color: #f1f5f9; margin-bottom: 10px;">No Members Found</h3>
                <p style="color: #94a3b8; margin-bottom: 20px;">Try adjusting your search or add a new member</p>
                <a href="add.php" class="btn-add" style="display: inline-flex; width: auto;">
                    <i class="fas fa-plus"></i>
                    Add Your First Member
                </a>
            </div>
        <?php endif; ?>
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
let memberToDelete = null;

function viewMember(memberId) {
    window.location.href = 'view_details.php?id=' + memberId;
}

function editMember(memberId) {
    window.location.href = 'edit.php?id=' + memberId;
}

function confirmDelete(memberId, memberName) {
    memberToDelete = memberId;
    document.getElementById('deleteModalBody').innerHTML = `Are you sure you want to delete "<strong>${memberName}</strong>"? This action cannot be undone.`;
    document.getElementById('deleteModal').style.display = 'flex';
}

function deleteMember() {
    if (memberToDelete) {
        const currentUrl = new URL(window.location.href);
        const params = new URLSearchParams(currentUrl.search);
        params.set('delete', memberToDelete);
        window.location.href = `view.php?${params.toString()}`;
    }
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
    memberToDelete = null;
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
</script>

</body>
</html>