<?php
session_start();
require_once "../../config/db.php";

// Authentication check
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

// Fetch all categories for dropdown
$categories_query = "SELECT id, name FROM categories ORDER BY name";
$categories_result = mysqli_query($conn, $categories_query);

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = mysqli_real_escape_string($conn, trim($_POST['title']));
    $author = mysqli_real_escape_string($conn, trim($_POST['author']));
    $category_id = (int)$_POST['category_id'];
    $quantity = (int)$_POST['quantity'];
    
    // Validation
    $errors = [];
    
    if (empty($title)) {
        $errors[] = "Book title is required";
    }
    
    if (empty($author)) {
        $errors[] = "Author name is required";
    }
    
    if ($category_id <= 0) {
        $errors[] = "Please select a category";
    }
    
    if ($quantity < 1) {
        $errors[] = "Quantity must be at least 1";
    }
    
    // Check if book already exists
    $check_query = "SELECT id, quantity FROM books WHERE title = ? AND author = ? AND category_id = ?";
    $check_stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($check_stmt, "ssi", $title, $author, $category_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    
    if (mysqli_num_rows($check_result) > 0) {
        // Book exists, update quantity
        $existing_book = mysqli_fetch_assoc($check_result);
        $new_quantity = $existing_book['quantity'] + $quantity;
        
        $update_query = "UPDATE books SET quantity = ? WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($update_stmt, "ii", $new_quantity, $existing_book['id']);
        
        if (mysqli_stmt_execute($update_stmt)) {
            $success_message = "Book already exists! Quantity updated successfully.";
        } else {
            $error_message = "Failed to update book quantity.";
        }
    } else {
        // Insert new book
        $insert_query = "INSERT INTO books (title, author, category_id, quantity) VALUES (?, ?, ?, ?)";
        $insert_stmt = mysqli_prepare($conn, $insert_query);
        mysqli_stmt_bind_param($insert_stmt, "ssii", $title, $author, $category_id, $quantity);
        
        if (mysqli_stmt_execute($insert_stmt)) {
            $success_message = "Book added successfully!";
            // Clear form fields
            $_POST = array();
        } else {
            $error_message = "Failed to add book. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Books - Library Management System</title>
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
            font-size: 32px;
            margin-bottom: 8px;
            color: #f1f5f9;
        }

        .header p {
            color: #94a3b8;
            font-size: 16px;
        }

        /* FORM CONTAINER */
        .form-container {
            background: #111827;
            border-radius: 20px;
            padding: 35px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.05);
            max-width: 800px;
            margin: 0 auto;
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

        select.form-control option {
            background: #1f2937;
            color: #f1f5f9;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
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

        .alert i {
            font-size: 20px;
        }

        .close-alert {
            margin-left: auto;
            cursor: pointer;
            font-size: 18px;
            transition: all 0.3s;
        }

        .close-alert:hover {
            opacity: 0.7;
        }

        /* INFO BOX */
        .info-box {
            background: rgba(96,165,250,0.05);
            border: 1px solid rgba(96,165,250,0.2);
            border-radius: 12px;
            padding: 15px;
            margin-top: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .info-box i {
            font-size: 24px;
            color: #60a5fa;
        }

        .info-box p {
            color: #94a3b8;
            font-size: 13px;
            line-height: 1.5;
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
            .form-container {
                padding: 20px;
            }
            .button-group {
                flex-direction: column;
            }
        }

        /* Scrollbar */
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
        <a href="../dashboard.php" class="nav-item">
            <i class="fas fa-chart-line"></i>
            <span>Dashboard</span>
        </a>
        <a href="books/view.php" class="nav-item active">
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
        <a href="view.php" class="back-button">
            <i class="fas fa-arrow-left"></i>
            Back to Books
        </a>
        <h1><i class="fas fa-plus-circle"></i> Add New Book</h1>
        <p>Add a new book to the library collection</p>
    </div>

    <div class="form-container">
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

        <form method="POST" action="" id="addBookForm">
            <div class="form-group">
                <label>
                    <i class="fas fa-book"></i>
                    Book Title
                    <span class="required">*</span>
                </label>
                <input type="text" name="title" class="form-control" 
                       value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>"
                       placeholder="Enter book title" required>
            </div>

            <div class="form-group">
                <label>
                    <i class="fas fa-user-edit"></i>
                    Author
                    <span class="required">*</span>
                </label>
                <input type="text" name="author" class="form-control" 
                       value="<?php echo isset($_POST['author']) ? htmlspecialchars($_POST['author']) : ''; ?>"
                       placeholder="Enter author name" required>
            </div>

            <div class="form-group">
                <label>
                    <i class="fas fa-tag"></i>
                    Category
                    <span class="required">*</span>
                </label>
                <select name="category_id" class="form-control" required>
                    <option value="">Select Category</option>
                    <?php while($category = mysqli_fetch_assoc($categories_result)): ?>
                        <option value="<?php echo $category['id']; ?>" 
                            <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label>
                    <i class="fas fa-cubes"></i>
                    Quantity
                    <span class="required">*</span>
                </label>
                <input type="number" name="quantity" class="form-control" 
                       value="<?php echo isset($_POST['quantity']) ? $_POST['quantity'] : '1'; ?>"
                       min="1" required>
            </div>

            <div class="button-group">
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i>
                    Add Book
                </button>
                <button type="reset" class="btn-secondary" onclick="resetForm()">
                    <i class="fas fa-undo"></i>
                    Reset
                </button>
            </div>
        </form>

        <div class="info-box">
            <i class="fas fa-info-circle"></i>
            <div>
                <p><strong>Note:</strong> If a book with the same title, author, and category already exists, the quantity will be updated instead of creating a duplicate entry.</p>
            </div>
        </div>
    </div>

    <div class="footer">
        <p>© 2025 Library Management System | All rights reserved.</p>
    </div>
</div>

<script>
function resetForm() {
    if (confirm('Are you sure you want to reset the form?')) {
        document.getElementById('addBookForm').reset();
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