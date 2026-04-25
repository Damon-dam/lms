<?php
session_start();
require_once "../../config/db.php";

// Authentication check
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

// Handle single book deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $book_id = (int)$_GET['delete'];
    
    // Check if book has any borrowings
    $check_query = "SELECT COUNT(*) as count FROM borrowings WHERE book_id = ?";
    $check_stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($check_stmt, "i", $book_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    $borrowings = mysqli_fetch_assoc($check_result);
    
    if ($borrowings['count'] > 0) {
        $error_message = "Cannot delete this book because it has {$borrowings['count']} active borrowing record(s).";
    } else {
        // Get book title for message
        $title_query = "SELECT title FROM books WHERE id = ?";
        $title_stmt = mysqli_prepare($conn, $title_query);
        mysqli_stmt_bind_param($title_stmt, "i", $book_id);
        mysqli_stmt_execute($title_stmt);
        $title_result = mysqli_stmt_get_result($title_stmt);
        $book_title = mysqli_fetch_assoc($title_result)['title'];
        
        $delete_query = "DELETE FROM books WHERE id = ?";
        $delete_stmt = mysqli_prepare($conn, $delete_query);
        mysqli_stmt_bind_param($delete_stmt, "i", $book_id);
        
        if (mysqli_stmt_execute($delete_stmt)) {
            $success_message = "Book \"$book_title\" has been deleted successfully!";
        } else {
            $error_message = "Failed to delete book. Please try again.";
        }
    }
}

// Handle bulk deletion
if (isset($_POST['bulk_delete']) && isset($_POST['selected_books'])) {
    $selected_books = $_POST['selected_books'];
    $deleted_count = 0;
    $failed_books = [];
    
    foreach ($selected_books as $book_id) {
        $book_id = (int)$book_id;
        
        // Check if book has borrowings
        $check_query = "SELECT COUNT(*) as count, title FROM borrowings b 
                        JOIN books bk ON b.book_id = bk.id 
                        WHERE book_id = ?";
        $check_stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($check_stmt, "i", $book_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        $borrowings = mysqli_fetch_assoc($check_result);
        
        if ($borrowings['count'] == 0) {
            $delete_query = "DELETE FROM books WHERE id = ?";
            $delete_stmt = mysqli_prepare($conn, $delete_query);
            mysqli_stmt_bind_param($delete_stmt, "i", $book_id);
            
            if (mysqli_stmt_execute($delete_stmt)) {
                $deleted_count++;
            }
        } else {
            // Get book title
            $title_query = "SELECT title FROM books WHERE id = ?";
            $title_stmt = mysqli_prepare($conn, $title_query);
            mysqli_stmt_bind_param($title_stmt, "i", $book_id);
            mysqli_stmt_execute($title_stmt);
            $title_result = mysqli_stmt_get_result($title_stmt);
            $book = mysqli_fetch_assoc($title_result);
            $failed_books[] = $book['title'];
        }
    }
    
    if ($deleted_count > 0) {
        $success_message = "Successfully deleted $deleted_count book(s)!";
    }
    if (!empty($failed_books)) {
        $error_message = "Could not delete " . count($failed_books) . " book(s) due to existing borrowings: " . implode(", ", $failed_books);
    }
}

// Pagination
$limit = 15;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Search and Filter
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Build WHERE clause
$where_conditions = [];
$params = [];
$types = "";

if (!empty($search)) {
    $where_conditions[] = "(b.title LIKE ? OR b.author LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

if ($category_filter > 0) {
    $where_conditions[] = "b.category_id = ?";
    $params[] = $category_filter;
    $types .= "i";
}

if ($status_filter == 'available') {
    $where_conditions[] = "b.quantity > 0";
} elseif ($status_filter == 'unavailable') {
    $where_conditions[] = "b.quantity = 0";
} elseif ($status_filter == 'deletable') {
    // Books that can be deleted (no borrowings)
    $where_conditions[] = "b.id NOT IN (SELECT DISTINCT book_id FROM borrowings)";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total books count
$count_query = "SELECT COUNT(*) as total FROM books b $where_clause";
$count_stmt = mysqli_prepare($conn, $count_query);

if (!empty($params)) {
    mysqli_stmt_bind_param($count_stmt, $types, ...$params);
}
mysqli_stmt_execute($count_stmt);
$count_result = mysqli_stmt_get_result($count_stmt);
$total_books = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_books / $limit);

// Fetch books with category name and borrowing status
$query = "SELECT b.*, c.name as category_name,
          (SELECT COUNT(*) FROM borrowings WHERE book_id = b.id) as borrowings_count
          FROM books b 
          LEFT JOIN categories c ON b.category_id = c.id 
          $where_clause 
          ORDER BY b.id DESC 
          LIMIT ?, ?";

$params[] = $offset;
$params[] = $limit;
$types .= "ii";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$books_result = mysqli_stmt_get_result($stmt);

// Fetch categories for filter dropdown
$categories_query = "SELECT id, name, 
                    (SELECT COUNT(*) FROM books WHERE category_id = categories.id) as book_count 
                    FROM categories 
                    ORDER BY name";
$categories_result = mysqli_query($conn, $categories_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Books - Library Management System</title>
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
            color: #f1f5f9;
        }

        .header h1 i {
            color: #ef4444;
            margin-right: 10px;
        }

        .header p {
            color: #94a3b8;
            font-size: 14px;
            margin-top: 5px;
        }

        /* WARNING BANNER */
        .warning-banner {
            background: linear-gradient(135deg, rgba(239,68,68,0.1) 0%, rgba(239,68,68,0.05) 100%);
            border-left: 4px solid #ef4444;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .warning-banner i {
            font-size: 24px;
            color: #ef4444;
        }

        .warning-banner p {
            color: #fca5a5;
            font-size: 14px;
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
            min-width: 180px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 13px;
            font-weight: 600;
            color: #94a3b8;
        }

        .filter-group input, .filter-group select {
            width: 100%;
            padding: 10px 14px;
            background: #1f2937;
            border: 2px solid #374151;
            border-radius: 10px;
            color: #f1f5f9;
            font-size: 14px;
        }

        .btn-filter, .btn-reset {
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            border: none;
        }

        .btn-filter {
            background: #3b82f6;
            color: white;
        }

        .btn-reset {
            background: #1f2937;
            color: #cbd5e1;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        /* BULK ACTIONS */
        .bulk-actions {
            background: #111827;
            border-radius: 20px;
            padding: 15px 20px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .selection-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-select-all, .btn-deselect-all {
            background: #1f2937;
            color: #cbd5e1;
            border: 1px solid #374151;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.3s;
        }

        .btn-select-all:hover, .btn-deselect-all:hover {
            background: #374151;
        }

        .btn-bulk-delete {
            background: #ef4444;
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-bulk-delete:hover:not(:disabled) {
            background: #dc2626;
            transform: translateY(-2px);
        }

        .btn-bulk-delete:disabled {
            opacity: 0.5;
            cursor: not-allowed;
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

        .checkbox-col {
            width: 40px;
            text-align: center;
        }

        .checkbox-col input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #ef4444;
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
        }

        .status-available {
            background: rgba(16,185,129,0.2);
            color: #34d399;
        }

        .status-unavailable {
            background: rgba(239,68,68,0.2);
            color: #f87171;
        }

        .delete-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 5px;
            border-radius: 6px;
            transition: all 0.3s;
            width: 28px;
            height: 28px;
            color: #f87171;
        }

        .delete-btn:hover:not(:disabled) {
            background: rgba(248,113,113,0.2);
            transform: scale(1.1);
        }

        .delete-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .cannot-delete {
            color: #f87171;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 5px;
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

        /* STATS */
        .stats-bar {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .stat-chip {
            background: #1f2937;
            padding: 8px 16px;
            border-radius: 10px;
            font-size: 13px;
        }

        .stat-chip i {
            margin-right: 6px;
        }

        .stat-chip.warning {
            background: rgba(239,68,68,0.1);
            border: 1px solid rgba(239,68,68,0.3);
        }

        /* MODAL */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: #111827;
            border-radius: 20px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .modal-header {
            font-size: 24px;
            margin-bottom: 20px;
            color: #f1f5f9;
        }

        .modal-body {
            margin-bottom: 25px;
            color: #cbd5e1;
        }

        .modal-buttons {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
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
            .bulk-actions {
                flex-direction: column;
                align-items: stretch;
            }
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
        <a href="view.php" class="nav-item active">
            <i class="fas fa-book"></i>
            <span>Books</span>
        </a>
        <a href="../members/view.php" class="nav-item">
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
            Back to Books
        </a>
        <h1><i class="fas fa-trash-alt"></i> Delete Books</h1>
        <p>Permanently remove books from the library collection</p>
    </div>

    <!-- Warning Banner -->
    <div class="warning-banner">
        <i class="fas fa-exclamation-triangle"></i>
        <p><strong>Warning:</strong> Books with active borrowing history cannot be deleted. Please ensure books are returned before deletion.</p>
    </div>

    <?php if (isset($success_message)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <span><?php echo $success_message; ?></span>
            <i class="fas fa-times close-alert" onclick="this.parentElement.remove()"></i>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-triangle"></i>
            <span><?php echo $error_message; ?></span>
            <i class="fas fa-times close-alert" onclick="this.parentElement.remove()"></i>
        </div>
    <?php endif; ?>

    <!-- Filter Section -->
    <div class="filter-section">
        <form method="GET" class="filter-form">
            <div class="filter-group">
                <label><i class="fas fa-search"></i> Search</label>
                <input type="text" name="search" placeholder="Search by title or author..." 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <div class="filter-group">
                <label><i class="fas fa-tag"></i> Category</label>
                <select name="category">
                    <option value="0">All Categories</option>
                    <?php 
                    mysqli_data_seek($categories_result, 0);
                    while($cat = mysqli_fetch_assoc($categories_result)): 
                    ?>
                        <option value="<?php echo $cat['id']; ?>" 
                            <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?> (<?php echo $cat['book_count']; ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label><i class="fas fa-info-circle"></i> Status</label>
                <select name="status">
                    <option value="">All Status</option>
                    <option value="available" <?php echo $status_filter == 'available' ? 'selected' : ''; ?>>Available</option>
                    <option value="unavailable" <?php echo $status_filter == 'unavailable' ? 'selected' : ''; ?>>Out of Stock</option>
                    <option value="deletable" <?php echo $status_filter == 'deletable' ? 'selected' : ''; ?>>Deletable (No Borrowings)</option>
                </select>
            </div>
            
            <div class="filter-group" style="display: flex; gap: 10px; align-items: flex-end;">
                <button type="submit" class="btn-filter">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
                <a href="delete.php" class="btn-reset">
                    <i class="fas fa-undo"></i> Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Stats Bar -->
    <div class="stats-bar">
        <div class="stat-chip">
            <i class="fas fa-database"></i> Total Books: <?php echo $total_books; ?>
        </div>
        <div class="stat-chip">
            <i class="fas fa-check-circle"></i> Available: 
            <?php 
                $available_query = "SELECT COUNT(*) as count FROM books WHERE quantity > 0";
                $available_result = mysqli_query($conn, $available_query);
                $available = mysqli_fetch_assoc($available_result);
                echo $available['count'];
            ?>
        </div>
        <div class="stat-chip warning">
            <i class="fas fa-trash"></i> Deletable: 
            <?php 
                $deletable_query = "SELECT COUNT(*) as count FROM books WHERE id NOT IN (SELECT DISTINCT book_id FROM borrowings)";
                $deletable_result = mysqli_query($conn, $deletable_query);
                $deletable = mysqli_fetch_assoc($deletable_result);
                echo $deletable['count'];
            ?>
        </div>
    </div>

    <!-- Bulk Actions -->
    <?php if (mysqli_num_rows($books_result) > 0): ?>
    <form method="POST" action="" id="bulkDeleteForm">
        <div class="bulk-actions">
            <div class="selection-info">
                <button type="button" class="btn-select-all" onclick="selectAll()">
                    <i class="fas fa-check-double"></i> Select All
                </button>
                <button type="button" class="btn-deselect-all" onclick="deselectAll()">
                    <i class="fas fa-times"></i> Deselect All
                </button>
                <span id="selectedCount" style="color: #94a3b8; font-size: 13px;">0 selected</span>
            </div>
            <button type="submit" name="bulk_delete" class="btn-bulk-delete" id="bulkDeleteBtn" disabled>
                <i class="fas fa-trash"></i> Delete Selected
            </button>
        </div>

        <!-- Books Table -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th class="checkbox-col">
                            <input type="checkbox" id="selectAllCheckbox" onclick="toggleSelectAll()">
                        </th>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Author</th>
                        <th>Category</th>
                        <th>Quantity</th>
                        <th>Status</th>
                        <th>Borrowings</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($book = mysqli_fetch_assoc($books_result)): 
                        $can_delete = ($book['borrowings_count'] == 0);
                    ?>
                        <tr>
                            <td class="checkbox-col">
                                <input type="checkbox" name="selected_books[]" value="<?php echo $book['id']; ?>" 
                                       class="book-checkbox" <?php echo !$can_delete ? 'disabled' : ''; ?>
                                       onchange="updateSelectedCount()">
                            </td>
                            <td>#<?php echo $book['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($book['title']); ?></strong></td>
                            <td><?php echo htmlspecialchars($book['author']); ?></td>
                            <td><?php echo htmlspecialchars($book['category_name'] ?? 'Uncategorized'); ?></td>
                            <td><?php echo $book['quantity']; ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $book['quantity'] > 0 ? 'available' : 'unavailable'; ?>">
                                    <i class="fas fa-<?php echo $book['quantity'] > 0 ? 'check-circle' : 'times-circle'; ?>"></i>
                                    <?php echo $book['quantity'] > 0 ? 'Available' : 'Out of Stock'; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($book['borrowings_count'] > 0): ?>
                                    <span class="cannot-delete">
                                        <i class="fas fa-ban"></i> <?php echo $book['borrowings_count']; ?> borrowing(s)
                                    </span>
                                <?php else: ?>
                                    <span style="color: #34d399;">
                                        <i class="fas fa-check-circle"></i> No borrowings
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="delete-btn" 
                                        onclick="deleteSingle(<?php echo $book['id']; ?>, '<?php echo htmlspecialchars($book['title']); ?>')"
                                        <?php echo !$can_delete ? 'disabled' : ''; ?>>
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>&status=<?php echo $status_filter; ?>" class="page-link">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php 
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    for($i = $start_page; $i <= $end_page; $i++): 
                    ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>&status=<?php echo $status_filter; ?>" 
                           class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>&status=<?php echo $status_filter; ?>" class="page-link">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div style="margin-top: 20px; text-align: center; color: #6b7280; font-size: 13px;">
                Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_books); ?> of <?php echo $total_books; ?> books
            </div>
        </div>
    </form>
    <?php else: ?>
        <div class="table-container">
            <div style="text-align: center; padding: 60px 20px;">
                <i class="fas fa-book" style="font-size: 64px; color: #4b5563; margin-bottom: 20px; display: block;"></i>
                <h3 style="color: #f1f5f9; margin-bottom: 10px;">No Books Found</h3>
                <p style="color: #94a3b8;">Try adjusting your search or filter criteria</p>
            </div>
        </div>
    <?php endif; ?>

    <div class="footer">
        <p>© 2025 Library Management System | All rights reserved.</p>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <i class="fas fa-exclamation-triangle" style="color: #f87171;"></i> Confirm Deletion
        </div>
        <div class="modal-body" id="deleteModalBody"></div>
        <div class="modal-buttons">
            <button class="btn-reset" onclick="closeModal()">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button class="btn-bulk-delete" onclick="proceedDelete()" style="background: #dc2626;">
                <i class="fas fa-trash"></i> Delete
            </button>
        </div>
    </div>
</div>

<script>
let bookToDelete = null;
let isBulkDelete = false;

function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('.book-checkbox:checked');
    const count = checkboxes.length;
    document.getElementById('selectedCount').innerHTML = `${count} selected`;
    const bulkBtn = document.getElementById('bulkDeleteBtn');
    if (count > 0) {
        bulkBtn.disabled = false;
    } else {
        bulkBtn.disabled = true;
    }
}

function toggleSelectAll() {
    const selectAll = document.getElementById('selectAllCheckbox');
    const checkboxes = document.querySelectorAll('.book-checkbox:not(:disabled)');
    checkboxes.forEach(cb => {
        cb.checked = selectAll.checked;
    });
    updateSelectedCount();
}

function selectAll() {
    const checkboxes = document.querySelectorAll('.book-checkbox:not(:disabled)');
    checkboxes.forEach(cb => {
        cb.checked = true;
    });
    document.getElementById('selectAllCheckbox').checked = true;
    updateSelectedCount();
}

function deselectAll() {
    const checkboxes = document.querySelectorAll('.book-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = false;
    });
    document.getElementById('selectAllCheckbox').checked = false;
    updateSelectedCount();
}

function deleteSingle(bookId, bookTitle) {
    bookToDelete = bookId;
    isBulkDelete = false;
    document.getElementById('deleteModalBody').innerHTML = `Are you sure you want to delete "<strong>${bookTitle}</strong>"? This action cannot be undone.`;
    document.getElementById('deleteModal').style.display = 'flex';
}

function proceedDelete() {
    if (isBulkDelete) {
        document.getElementById('bulkDeleteForm').submit();
    } else if (bookToDelete) {
        window.location.href = `delete.php?delete=${bookToDelete}&page=<?php echo $page; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>&status=<?php echo $status_filter; ?>`;
    }
}

function closeModal() {
    document.getElementById('deleteModal').style.display = 'none';
    bookToDelete = null;
    isBulkDelete = false;
}

// Handle bulk delete confirmation
document.getElementById('bulkDeleteForm')?.addEventListener('submit', function(e) {
    const selectedCount = document.querySelectorAll('.book-checkbox:checked').length;
    if (selectedCount > 0) {
        e.preventDefault();
        isBulkDelete = true;
        document.getElementById('deleteModalBody').innerHTML = `Are you sure you want to delete <strong>${selectedCount}</strong> selected book(s)? This action cannot be undone.`;
        document.getElementById('deleteModal').style.display = 'flex';
    }
});

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('deleteModal');
    if (event.target == modal) {
        closeModal();
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