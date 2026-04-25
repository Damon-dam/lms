<?php
session_start();
require_once "../../config/db.php";

// Authentication check
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

// Handle Category Operations
$category_message = '';
$category_error = '';

// Add Category
if (isset($_POST['add_category'])) {
    $category_name = mysqli_real_escape_string($conn, trim($_POST['category_name']));
    
    if (!empty($category_name)) {
        $check_query = "SELECT id FROM categories WHERE name = ?";
        $check_stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($check_stmt, "s", $category_name);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        
        if (mysqli_num_rows($check_result) > 0) {
            $category_error = "Category already exists!";
        } else {
            $insert_query = "INSERT INTO categories (name) VALUES (?)";
            $insert_stmt = mysqli_prepare($conn, $insert_query);
            mysqli_stmt_bind_param($insert_stmt, "s", $category_name);
            
            if (mysqli_stmt_execute($insert_stmt)) {
                $category_message = "Category added successfully!";
            } else {
                $category_error = "Failed to add category.";
            }
        }
    } else {
        $category_error = "Category name cannot be empty.";
    }
}

// Edit Category
if (isset($_POST['edit_category'])) {
    $category_id = (int)$_POST['category_id'];
    $category_name = mysqli_real_escape_string($conn, trim($_POST['edit_category_name']));
    
    if (!empty($category_name)) {
        $update_query = "UPDATE categories SET name = ? WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($update_stmt, "si", $category_name, $category_id);
        
        if (mysqli_stmt_execute($update_stmt)) {
            $category_message = "Category updated successfully!";
        } else {
            $category_error = "Failed to update category.";
        }
    } else {
        $category_error = "Category name cannot be empty.";
    }
}

// Delete Category
if (isset($_GET['delete_category']) && is_numeric($_GET['delete_category'])) {
    $category_id = (int)$_GET['delete_category'];
    
    // Check if category has books
    $check_query = "SELECT COUNT(*) as count FROM books WHERE category_id = ?";
    $check_stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($check_stmt, "i", $category_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    $book_count = mysqli_fetch_assoc($check_result)['count'];
    
    if ($book_count > 0) {
        $category_error = "Cannot delete category. It has $book_count book(s) associated with it.";
    } else {
        $delete_query = "DELETE FROM categories WHERE id = ?";
        $delete_stmt = mysqli_prepare($conn, $delete_query);
        mysqli_stmt_bind_param($delete_stmt, "i", $category_id);
        
        if (mysqli_stmt_execute($delete_stmt)) {
            $category_message = "Category deleted successfully!";
        } else {
            $category_error = "Failed to delete category.";
        }
    }
}

// Handle Book Delete Request with re-indexing
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
        $error_message = "Cannot delete this book because it has active borrowings history.";
    } else {
        // Get book title for message
        $title_query = "SELECT title FROM books WHERE id = ?";
        $title_stmt = mysqli_prepare($conn, $title_query);
        mysqli_stmt_bind_param($title_stmt, "i", $book_id);
        mysqli_stmt_execute($title_stmt);
        $title_result = mysqli_stmt_get_result($title_stmt);
        $book_title = mysqli_fetch_assoc($title_result)['title'];
        
        // Delete the book
        $delete_query = "DELETE FROM books WHERE id = ?";
        $delete_stmt = mysqli_prepare($conn, $delete_query);
        mysqli_stmt_bind_param($delete_stmt, "i", $book_id);
        
        if (mysqli_stmt_execute($delete_stmt)) {
            // Re-index IDs (optional - reorganize AUTO_INCREMENT)
            // This resets the AUTO_INCREMENT to the next available number
            $reset_ai_query = "SET @count = 0;
                               UPDATE books SET id = @count:= @count + 1;
                               ALTER TABLE books AUTO_INCREMENT = 1;";
            
            // Execute re-indexing (uncomment if you want to renumber IDs)
            // mysqli_multi_query($conn, $reset_ai_query);
            
            $success_message = "Book \"$book_title\" has been deleted successfully!";
            
            // Redirect to refresh the page and reset pagination
            $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $redirect_url = "view.php?page=" . $current_page;
            if (isset($_GET['search'])) $redirect_url .= "&search=" . urlencode($_GET['search']);
            if (isset($_GET['category'])) $redirect_url .= "&category=" . (int)$_GET['category'];
            if (isset($_GET['status'])) $redirect_url .= "&status=" . urlencode($_GET['status']);
            
            header("Location: " . $redirect_url);
            exit();
        } else {
            $error_message = "Failed to delete book.";
        }
    }
}

// Pagination
$limit = 10;
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
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total books count
$count_query = "SELECT COUNT(*) as total FROM books b LEFT JOIN categories c ON b.category_id = c.id $where_clause";
$count_stmt = mysqli_prepare($conn, $count_query);

if (!empty($params)) {
    mysqli_stmt_bind_param($count_stmt, $types, ...$params);
}
mysqli_stmt_execute($count_stmt);
$count_result = mysqli_stmt_get_result($count_stmt);
$total_books = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_books / $limit);

// Adjust page if it exceeds total pages (important for when last item is deleted)
if ($page > $total_pages && $total_pages > 0) {
    header("Location: view.php?page=" . $total_pages . 
           (isset($_GET['search']) ? "&search=" . urlencode($_GET['search']) : "") .
           (isset($_GET['category']) ? "&category=" . (int)$_GET['category'] : "") .
           (isset($_GET['status']) ? "&status=" . urlencode($_GET['status']) : ""));
    exit();
}

// Fetch books with category name and row number
$query = "SELECT b.*, c.name as category_name,
          @row_number := @row_number + 1 as row_num
          FROM books b 
          LEFT JOIN categories c ON b.category_id = c.id 
          CROSS JOIN (SELECT @row_number := 0) as rn
          $where_clause 
          ORDER BY b.id ASC 
          LIMIT ?, ?";

$params[] = $offset;
$params[] = $limit;
$types .= "ii";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$books_result = mysqli_stmt_get_result($stmt);

// Calculate starting row number for display
$start_row = $offset + 1;

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
    <title>Manage Books & Categories - Library Management System</title>
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

        /* CATEGORY SECTION */
        .category-section-full {
            background: #111827;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.05);
        }

        .section-title {
            font-size: 20px;
            margin-bottom: 20px;
            color: #f1f5f9;
            border-bottom: 2px solid #3b82f6;
            padding-bottom: 10px;
            display: inline-block;
        }

        .category-management {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
        }

        .add-category-box {
            flex: 1;
            min-width: 250px;
        }

        .categories-list-box {
            flex: 2;
            min-width: 300px;
        }

        .input-group {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .input-group input {
            flex: 1;
            padding: 10px 14px;
            background: #1f2937;
            border: 2px solid #374151;
            border-radius: 10px;
            color: #f1f5f9;
            font-size: 14px;
        }

        .input-group input:focus {
            outline: none;
            border-color: #60a5fa;
        }

        .btn-primary-small {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-primary-small:hover {
            background: #2563eb;
        }

        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
            max-height: 250px;
            overflow-y: auto;
            padding-right: 10px;
        }

        .category-card {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 15px;
            background: #1f2937;
            border-radius: 10px;
            transition: all 0.3s;
        }

        .category-card:hover {
            background: #2d3a52;
            transform: translateX(5px);
        }

        .category-name {
            font-weight: 600;
            color: #f1f5f9;
            font-size: 14px;
        }

        .category-stats {
            font-size: 11px;
            color: #94a3b8;
            margin-top: 3px;
        }

        .category-actions {
            display: flex;
            gap: 5px;
        }

        .edit-cat-btn, .delete-cat-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 14px;
            padding: 5px;
            border-radius: 6px;
            transition: all 0.3s;
            width: 28px;
            height: 28px;
        }

        .edit-cat-btn {
            color: #60a5fa;
        }

        .edit-cat-btn:hover {
            background: rgba(96,165,250,0.2);
        }

        .delete-cat-btn {
            color: #f87171;
        }

        .delete-cat-btn:hover {
            background: rgba(248,113,113,0.2);
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

        .row-number {
            color: #60a5fa;
            font-weight: 600;
            background: rgba(96,165,250,0.1);
            display: inline-block;
            width: 35px;
            text-align: center;
            border-radius: 6px;
            padding: 2px;
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

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .btn-view, .btn-edit, .btn-delete {
            background: none;
            border: none;
            cursor: pointer;
            padding: 5px;
            border-radius: 6px;
            transition: all 0.3s;
            width: 28px;
            height: 28px;
        }

        .btn-view { color: #34d399; }
        .btn-view:hover { background: rgba(16,185,129,0.2); }
        .btn-edit { color: #60a5fa; }
        .btn-edit:hover { background: rgba(96,165,250,0.2); }
        .btn-delete { color: #f87171; }
        .btn-delete:hover { background: rgba(248,113,113,0.2); }

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

        /* STATS BAR */
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
            color: #cbd5e1;
        }

        .stat-chip i {
            margin-right: 6px;
            color: #60a5fa;
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
            .category-management {
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
        <div class="header-top">
            <div>
                <h1><i class="fas fa-book"></i> Manage Books & Categories</h1>
                <p>Manage your library collection and organize books by categories</p>
            </div>
            <a href="add.php" class="btn-add">
                <i class="fas fa-plus"></i>
                Add New Book
            </a>
        </div>
    </div>

    <!-- Category Messages -->
    <?php if (isset($category_message) && $category_message): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <span><?php echo $category_message; ?></span>
            <i class="fas fa-times close-alert" onclick="this.parentElement.remove()"></i>
        </div>
    <?php endif; ?>

    <?php if (isset($category_error) && $category_error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-triangle"></i>
            <span><?php echo $category_error; ?></span>
            <i class="fas fa-times close-alert" onclick="this.parentElement.remove()"></i>
        </div>
    <?php endif; ?>

    <!-- Book Messages -->
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

    <!-- CATEGORY MANAGEMENT SECTION -->
    <div class="category-section-full">
        <h3 class="section-title">
            <i class="fas fa-tags"></i> Category Management
        </h3>
        
        <div class="category-management">
            <div class="add-category-box">
                <label style="display: block; margin-bottom: 8px; font-size: 13px; font-weight: 600; color: #94a3b8;">
                    <i class="fas fa-plus-circle"></i> Add New Category
                </label>
                <form method="POST" action="">
                    <div class="input-group">
                        <input type="text" name="category_name" placeholder="Enter category name..." required>
                        <button type="submit" name="add_category" class="btn-primary-small">
                            <i class="fas fa-save"></i> Add
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="categories-list-box">
                <label style="display: block; margin-bottom: 8px; font-size: 13px; font-weight: 600; color: #94a3b8;">
                    <i class="fas fa-list"></i> Existing Categories (<?php echo mysqli_num_rows($categories_result); ?>)
                </label>
                <div class="categories-grid">
                    <?php 
                    mysqli_data_seek($categories_result, 0);
                    if(mysqli_num_rows($categories_result) > 0):
                        while($category = mysqli_fetch_assoc($categories_result)): 
                    ?>
                        <div class="category-card">
                            <div>
                                <div class="category-name">
                                    <i class="fas fa-folder"></i> <?php echo htmlspecialchars($category['name']); ?>
                                </div>
                                <div class="category-stats">
                                    <i class="fas fa-book"></i> <?php echo $category['book_count']; ?> books
                                </div>
                            </div>
                            <div class="category-actions">
                                <button class="edit-cat-btn" onclick="openEditModal(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>')">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if($category['book_count'] == 0): ?>
                                    <a href="?delete_category=<?php echo $category['id']; ?>" 
                                       class="delete-cat-btn" 
                                       onclick="return confirm('Are you sure you want to delete this category?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                <?php else: ?>
                                    <button class="delete-cat-btn" disabled style="opacity: 0.5; cursor: not-allowed;" title="Cannot delete category with books">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 20px; color: #6b7280;">
                            <i class="fas fa-folder-open" style="font-size: 48px; margin-bottom: 10px; display: block;"></i>
                            No categories yet. Add your first category!
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- FILTER SECTION -->
    <div class="filter-section">
        <form method="GET" class="filter-form">
            <div class="filter-group">
                <label><i class="fas fa-search"></i> Search Books</label>
                <input type="text" name="search" placeholder="Search by title or author..." 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <div class="filter-group">
                <label><i class="fas fa-tag"></i> Filter by Category</label>
                <select name="category">
                    <option value="0">All Categories</option>
                    <?php 
                    mysqli_data_seek($categories_result, 0);
                    while($cat = mysqli_fetch_assoc($categories_result)): 
                    ?>
                        <option value="<?php echo $cat['id']; ?>" 
                            <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label><i class="fas fa-info-circle"></i> Filter by Status</label>
                <select name="status">
                    <option value="">All Status</option>
                    <option value="available" <?php echo $status_filter == 'available' ? 'selected' : ''; ?>>Available</option>
                    <option value="unavailable" <?php echo $status_filter == 'unavailable' ? 'selected' : ''; ?>>Out of Stock</option>
                </select>
            </div>
            
            <div class="filter-group" style="display: flex; gap: 10px; align-items: flex-end;">
                <button type="submit" class="btn-filter">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
                <a href="view.php" class="btn-reset">
                    <i class="fas fa-undo"></i> Reset
                </a>
            </div>
        </form>
    </div>

    <!-- STATS BAR -->
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
        <div class="stat-chip">
            <i class="fas fa-times-circle"></i> Out of Stock: 
            <?php 
                $unavailable_query = "SELECT COUNT(*) as count FROM books WHERE quantity = 0";
                $unavailable_result = mysqli_query($conn, $unavailable_query);
                $unavailable = mysqli_fetch_assoc($unavailable_result);
                echo $unavailable['count'];
            ?>
        </div>
        <div class="stat-chip">
            <i class="fas fa-chart-line"></i> Page <?php echo $page; ?> of <?php echo $total_pages; ?>
        </div>
    </div>

    <!-- BOOKS TABLE -->
    <div class="table-container">
        <?php if (mysqli_num_rows($books_result) > 0): ?>
        
            <table>
                <thead>
                    <tr>
                        <th style="width: 60px;">#</th>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Author</th>
                        <th>Category</th>
                        <th>Qty</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $current_row = $start_row;
                    while($book = mysqli_fetch_assoc($books_result)): 
                    ?>
                        <tr>
                            <td>
                                <span class="row-number"><?php echo $current_row; ?></span>
                            </td>
                            <td>#<?php echo $book['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($book['title']); ?></strong></td>
                            <td><?php echo htmlspecialchars($book['author']); ?></td>
                            <td>
                                <span style="background: rgba(96,165,250,0.1); padding: 4px 10px; border-radius: 8px; font-size: 12px;">
                                    <?php echo htmlspecialchars($book['category_name'] ?? 'Uncategorized'); ?>
                                </span>
                            </td>
                            <td><?php echo $book['quantity']; ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $book['quantity'] > 0 ? 'available' : 'unavailable'; ?>">
                                    <i class="fas fa-<?php echo $book['quantity'] > 0 ? 'check-circle' : 'times-circle'; ?>"></i>
                                    <?php echo $book['quantity'] > 0 ? 'Available' : 'Out of Stock'; ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-view" onclick="viewBook(<?php echo $book['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn-edit" onclick="editBook(<?php echo $book['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-delete" onclick="confirmDelete(<?php echo $book['id']; ?>, '<?php echo htmlspecialchars($book['title']); ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php 
                    $current_row++;
                    endwhile; 
                    ?>
                </tbody>
            </table>

            <!-- PAGINATION -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>&status=<?php echo $status_filter; ?>" class="page-link">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>
                    
                    <?php 
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    if($start_page > 1): ?>
                        <a href="?page=1&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>&status=<?php echo $status_filter; ?>" class="page-link">1</a>
                        <?php if($start_page > 2): ?><span class="page-link">...</span><?php endif; ?>
                    <?php endif; ?>
                    
                    <?php for($i = $start_page; $i <= $end_page; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>&status=<?php echo $status_filter; ?>" 
                           class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if($end_page < $total_pages): ?>
                        <?php if($end_page < $total_pages - 1): ?><span class="page-link">...</span><?php endif; ?>
                        <a href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>&status=<?php echo $status_filter; ?>" class="page-link"><?php echo $total_pages; ?></a>
                    <?php endif; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>&status=<?php echo $status_filter; ?>" class="page-link">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div style="margin-top: 20px; text-align: center; color: #6b7280; font-size: 13px;">
                Showing entries <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_books); ?> of <?php echo $total_books; ?> total books
                <br>
                <small><i class="fas fa-sync-alt"></i> Row numbers are continuously maintained from 1 to <?php echo $total_books; ?></small>
            </div>
            
        <?php else: ?>
            <div style="text-align: center; padding: 60px 20px;">
                <i class="fas fa-book" style="font-size: 64px; color: #4b5563; margin-bottom: 20px; display: block;"></i>
                <h3 style="color: #f1f5f9; margin-bottom: 10px;">No Books Found</h3>
                <p style="color: #94a3b8; margin-bottom: 20px;">Try adjusting your search or filter criteria</p>
                <a href="add.php" class="btn-add" style="display: inline-flex; width: auto;">
                    <i class="fas fa-plus"></i>
                    Add Your First Book
                </a>
            </div>
        <?php endif; ?>
    </div>

    <div class="footer">
        <p>© 2025 Library Management System | All rights reserved.</p>
    </div>
</div>

<!-- Edit Category Modal -->
<div id="editCategoryModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <i class="fas fa-edit"></i> Edit Category
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <input type="hidden" name="category_id" id="edit_category_id">
                <input type="text" name="edit_category_name" id="edit_category_name" placeholder="Category name" required>
            </div>
            <div class="modal-buttons">
                <button type="button" class="btn-reset" onclick="closeEditModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" name="edit_category" class="btn-primary-small">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <i class="fas fa-exclamation-triangle" style="color: #f87171;"></i> Confirm Delete
        </div>
        <div class="modal-body" id="deleteModalBody"></div>
        <div class="modal-buttons">
            <button class="btn-reset" onclick="closeDeleteModal()">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button class="btn-primary-small" onclick="deleteBook()" style="background: #ef4444;">
                <i class="fas fa-trash"></i> Delete
            </button>
        </div>
    </div>
</div>

<script>
let bookToDelete = null;

function viewBook(bookId) {
    window.location.href = 'view_details.php?id=' + bookId;
}

function editBook(bookId) {
    window.location.href = 'edit.php?id=' + bookId;
}

function confirmDelete(bookId, bookTitle) {
    bookToDelete = bookId;
    document.getElementById('deleteModalBody').innerHTML = `Are you sure you want to delete "<strong>${bookTitle}</strong>"? This action cannot be undone.`;
    document.getElementById('deleteModal').style.display = 'flex';
}

function deleteBook() {
    if (bookToDelete) {
        // Preserve all current filters and page when redirecting
        const currentUrl = new URL(window.location.href);
        const params = new URLSearchParams(currentUrl.search);
        params.set('delete', bookToDelete);
        window.location.href = `view.php?${params.toString()}`;
    }
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
    bookToDelete = null;
}

function openEditModal(categoryId, categoryName) {
    document.getElementById('edit_category_id').value = categoryId;
    document.getElementById('edit_category_name').value = categoryName;
    document.getElementById('editCategoryModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editCategoryModal').style.display = 'none';
}

// Close modals when clicking outside
window.onclick = function(event) {
    const editModal = document.getElementById('editCategoryModal');
    const deleteModal = document.getElementById('deleteModal');
    if (event.target == editModal) {
        closeEditModal();
    }
    if (event.target == deleteModal) {
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