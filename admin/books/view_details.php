<?php
session_start();
require_once "../../config/db.php";

// Authentication check
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

// Get book ID
$book_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($book_id <= 0) {
    header("Location: ../books/view.php");
    exit();
}

// Fetch book details with category
$book_query = "SELECT b.*, c.name as category_name 
               FROM books b 
               LEFT JOIN categories c ON b.category_id = c.id 
               WHERE b.id = ?";
$stmt = mysqli_prepare($conn, $book_query);
mysqli_stmt_bind_param($stmt, "i", $book_id);
mysqli_stmt_execute($stmt);
$book_result = mysqli_stmt_get_result($stmt);
$book = mysqli_fetch_assoc($book_result);

if (!$book) {
    header("Location: ../books/view.php");
    exit();
}

// Get book statistics
$stats_query = "SELECT 
                (SELECT COUNT(*) FROM borrowings WHERE book_id = ?) as total_borrowings,
                (SELECT COUNT(*) FROM borrowings WHERE book_id = ? AND status = 'borrowed') as currently_borrowed,
                (SELECT COUNT(*) FROM borrowings WHERE book_id = ? AND status = 'borrowed' AND return_date < CURDATE()) as overdue_count,
                (SELECT COUNT(*) FROM borrowings WHERE book_id = ? AND status = 'returned') as returned_count";
$stats_stmt = mysqli_prepare($conn, $stats_query);
mysqli_stmt_bind_param($stats_stmt, "iiii", $book_id, $book_id, $book_id, $book_id);
mysqli_stmt_execute($stats_stmt);
$stats_result = mysqli_stmt_get_result($stats_stmt);
$stats = mysqli_fetch_assoc($stats_result);

// Get current borrowers (who has this book now)
$current_borrowers_query = "SELECT u.name, u.email, b.borrow_date, b.return_date, 
                            DATEDIFF(CURDATE(), b.return_date) as days_overdue
                            FROM borrowings b
                            JOIN users u ON b.user_id = u.id
                            WHERE b.book_id = ? AND b.status = 'borrowed'
                            ORDER BY b.borrow_date DESC";
$current_stmt = mysqli_prepare($conn, $current_borrowers_query);
mysqli_stmt_bind_param($current_stmt, "i", $book_id);
mysqli_stmt_execute($current_stmt);
$current_borrowers = mysqli_stmt_get_result($current_stmt);

// Get borrowing history
$history_query = "SELECT u.name, u.email, b.borrow_date, b.return_date, b.status,
                  (SELECT COALESCE(fine_amount, 0) FROM fines WHERE borrowing_id = b.id) as fine_amount
                  FROM borrowings b
                  JOIN users u ON b.user_id = u.id
                  WHERE b.book_id = ? AND b.status = 'returned'
                  ORDER BY b.return_date DESC
                  LIMIT 10";
$history_stmt = mysqli_prepare($conn, $history_query);
mysqli_stmt_bind_param($history_stmt, "i", $book_id);
mysqli_stmt_execute($history_stmt);
$history_borrowings = mysqli_stmt_get_result($history_stmt);

// Calculate availability status
$available_copies = $book['quantity'] - $stats['currently_borrowed'];
$status_class = $available_copies > 0 ? 'available' : 'unavailable';
$status_text = $available_copies > 0 ? 'Available' : 'Out of Stock';
$status_icon = $available_copies > 0 ? 'fa-check-circle' : 'fa-times-circle';
$status_color = $available_copies > 0 ? '#34d399' : '#f87171';

// Helper function to safely get book field values
function getBookValue($book, $key, $default = 'Not specified') {
    return isset($book[$key]) && !empty($book[$key]) ? htmlspecialchars($book[$key]) : $default;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Details - Library Management System</title>
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
            cursor: pointer;
            border: none;
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

        /* BOOK COVER SECTION */
        .book-grid {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .book-cover {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            border-radius: 20px;
            padding: 30px;
            text-align: center;
            border: 1px solid rgba(255,255,255,0.05);
            height: fit-content;
        }

        .cover-icon {
            font-size: 120px;
            color: #60a5fa;
            margin-bottom: 20px;
        }

        .status-indicator {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 14px;
            margin-top: 15px;
        }

        .book-info {
            background: #111827;
            border-radius: 20px;
            padding: 30px;
            border: 1px solid rgba(255,255,255,0.05);
        }

        .book-title {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
            color: #f1f5f9;
        }

        .book-meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #1f2937;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #94a3b8;
            font-size: 14px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }

        .info-field {
            margin-bottom: 15px;
        }

        .info-label {
            font-size: 12px;
            color: #94a3b8;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-value {
            font-size: 16px;
            font-weight: 600;
            color: #f1f5f9;
        }

        .description {
            background: #1a2332;
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
        }

        .description h4 {
            margin-bottom: 10px;
            color: #cbd5e1;
        }

        .description p {
            line-height: 1.6;
            color: #94a3b8;
        }

        /* STATS CARDS */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #111827;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            border: 1px solid rgba(255,255,255,0.05);
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            background: #1a2332;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 13px;
            color: #94a3b8;
        }

        /* SECTIONS */
        .section-card {
            background: #111827;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
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

        /* TABLES */
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
            .book-grid {
                grid-template-columns: 1fr;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
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
            .info-grid {
                grid-template-columns: 1fr;
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
        <div style="display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 15px;">
            <div>
                <h1><i class="fas fa-book"></i> Book Details</h1>
                <p>View complete information about this book</p>
            </div>
            <div class="action-buttons">
                <a href="edit.php?id=<?php echo $book_id; ?>" class="btn-edit">
                    <i class="fas fa-edit"></i> Edit Book
                </a>
                <button class="btn-delete" onclick="confirmDelete()">
                    <i class="fas fa-trash"></i> Delete Book
                </button>
            </div>
        </div>
    </div>

    <!-- Book Information -->
    <div class="book-grid">
        <div class="book-cover">
            <div class="cover-icon">
                <i class="fas fa-book-open"></i>
            </div>
            <div style="font-size: 48px; font-weight: 700; margin: 10px 0;">
                <?php echo $available_copies; ?>/<?php echo $book['quantity']; ?>
            </div>
            <div>Copies Available</div>
            <div class="status-indicator" style="background: <?php echo $status_color; ?>20; color: <?php echo $status_color; ?>; border: 1px solid <?php echo $status_color; ?>40;">
                <i class="fas <?php echo $status_icon; ?>"></i> <?php echo $status_text; ?>
            </div>
        </div>

        <div class="book-info">
            <h1 class="book-title"><?php echo htmlspecialchars($book['title']); ?></h1>
            <div class="book-meta">
                <div class="meta-item">
                    <i class="fas fa-user-edit"></i>
                    By <?php echo htmlspecialchars($book['author']); ?>
                </div>
                <?php if(isset($book['isbn']) && !empty($book['isbn'])): ?>
                <div class="meta-item">
                    <i class="fas fa-barcode"></i>
                    ISBN: <?php echo htmlspecialchars($book['isbn']); ?>
                </div>
                <?php endif; ?>
                <div class="meta-item">
                    <i class="fas fa-tag"></i>
                    <?php echo htmlspecialchars($book['category_name'] ?? 'Uncategorized'); ?>
                </div>
            </div>

            <div class="info-grid">
                <div class="info-field">
                    <div class="info-label">Publisher</div>
                    <div class="info-value"><?php echo getBookValue($book, 'publisher'); ?></div>
                </div>
                <div class="info-field">
                    <div class="info-label">Publication Year</div>
                    <div class="info-value"><?php echo getBookValue($book, 'publication_year', 'N/A'); ?></div>
                </div>
                <div class="info-field">
                    <div class="info-label">Edition</div>
                    <div class="info-value"><?php echo getBookValue($book, 'edition', '1st'); ?> Edition</div>
                </div>
                <div class="info-field">
                    <div class="info-label">Total Copies</div>
                    <div class="info-value"><?php echo $book['quantity']; ?> copies</div>
                </div>
            </div>

            <?php if(isset($book['description']) && !empty($book['description'])): ?>
            <div class="description">
                <h4><i class="fas fa-align-left"></i> Description</h4>
                <p><?php echo nl2br(htmlspecialchars($book['description'])); ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?php echo $stats['total_borrowings']; ?></div>
            <div class="stat-label">Total Borrowings</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" style="color: #fbbf24;"><?php echo $stats['currently_borrowed']; ?></div>
            <div class="stat-label">Currently Borrowed</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" style="color: #f87171;"><?php echo $stats['overdue_count']; ?></div>
            <div class="stat-label">Overdue Copies</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" style="color: #34d399;"><?php echo $stats['returned_count']; ?></div>
            <div class="stat-label">Successfully Returned</div>
        </div>
    </div>

    <!-- Current Borrowers -->
    <div class="section-card">
        <div class="section-header">
            <h3><i class="fas fa-users"></i> Who Has This Book Now</h3>
        </div>
        <div class="data-table">
            <?php if (mysqli_num_rows($current_borrowers) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Member Name</th>
                            <th>Email</th>
                            <th>Borrow Date</th>
                            <th>Due Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($borrower = mysqli_fetch_assoc($current_borrowers)): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($borrower['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($borrower['email']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($borrower['borrow_date'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($borrower['return_date'])); ?></td>
                                <td>
                                    <?php if($borrower['days_overdue'] > 0): ?>
                                        <span class="status-badge status-overdue">
                                            <i class="fas fa-exclamation-circle"></i> Overdue (<?php echo $borrower['days_overdue']; ?> days)
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
                    <p>This book is currently available. No one has borrowed it.</p>
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
                            <th>Member Name</th>
                            <th>Email</th>
                            <th>Borrow Date</th>
                            <th>Return Date</th>
                            <th>Fine</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($history = mysqli_fetch_assoc($history_borrowings)): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($history['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($history['email']); ?></td>
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
                    <p>No borrowing history found for this book</p>
                </div>
            <?php endif; ?>
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
        <div style="margin-bottom: 25px; color: #cbd5e1;">
            Are you sure you want to delete the book "<strong><?php echo htmlspecialchars($book['title']); ?></strong>"? 
            This action cannot be undone.
            <?php if($stats['currently_borrowed'] > 0): ?>
                <br><br><strong style="color: #f87171;">Warning: This book has <?php echo $stats['currently_borrowed']; ?> active borrowing(s).</strong>
            <?php endif; ?>
        </div>
        <div style="display: flex; gap: 12px; justify-content: flex-end;">
            <button onclick="closeDeleteModal()" style="padding: 10px 20px; background: #1f2937; color: #cbd5e1; border: none; border-radius: 10px; cursor: pointer;">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button onclick="deleteBook()" style="padding: 10px 20px; background: #ef4444; color: white; border: none; border-radius: 10px; cursor: pointer;">
                <i class="fas fa-trash"></i> Delete
            </button>
        </div>
    </div>
</div>

<script>
function confirmDelete() {
    const currentlyBorrowed = <?php echo $stats['currently_borrowed']; ?>;
    
    if (currentlyBorrowed > 0) {
        if (!confirm('This book is currently borrowed. Deleting it will affect borrowing records. Continue anyway?')) {
            return;
        }
    }
    
    document.getElementById('deleteModal').style.display = 'flex';
}

function deleteBook() {
    window.location.href = `view.php?delete=<?php echo $book_id; ?>`;
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