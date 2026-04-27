<?php
session_start();
include("db.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'user') {
    header("Location: login.php");
    exit();
}

/* BORROW ACTION */
if (isset($_GET['borrow'])) {
    $book_id = $_GET['borrow'];
    $user_id = $_SESSION['user_id'];

    mysqli_query($conn, "INSERT INTO borrowing (user_id, book_id, borrow_date, return_date, status)
    VALUES ('$user_id', '$book_id', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'Borrowed')");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>User Dashboard</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Segoe UI", sans-serif;
        }

        body {
            display: flex;
            background: #f5f7fb;
            min-height: 100vh;
        }

        .sidebar {
            width: 250px;
            background: linear-gradient(180deg, #1d2671, #0f1642);
            color: white;
            padding: 25px;
            position: fixed;
            height: 100%;
        }

        .sidebar h2 {
            margin-bottom: 40px;
            text-align: center;
            font-size: 24px;
        }

        .sidebar a {
            display: block;
            padding: 14px;
            margin: 12px 0;
            color: white;
            text-decoration: none;
            border-radius: 12px;
            transition: 0.3s;
        }

        .sidebar a:hover {
            background: rgba(255,255,255,0.15);
            transform: translateX(5px);
        }

        .main {
            margin-left: 250px;
            flex: 1;
            padding: 30px;
        }

        .header {
            background: white;
            padding: 25px;
            border-radius: 18px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            padding: 25px;
            border-radius: 18px;
            color: white;
            box-shadow: 0 8px 18px rgba(0,0,0,0.12);
        }

        .c1 { background: linear-gradient(135deg, #667eea, #764ba2); }
        .c2 { background: linear-gradient(135deg, #43cea2, #185a9d); }
        .c3 { background: linear-gradient(135deg, #ff758c, #ff7eb3); }

        .section {
            background: white;
            padding: 25px;
            border-radius: 18px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table th, table td {
            padding: 14px;
            text-align: left;
        }

        table th {
            background: #1d2671;
            color: white;
        }

        table tr:nth-child(even) {
            background: #f8f9fc;
        }

        .status {
            padding: 6px 12px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: bold;
        }

        .active { background: #d4f8e8; color: #0c8b4d; }
        .late { background: #fde2e2; color: #b42318; }

        /* BUTTON */
        .btn {
            background: #1d2671;
            color: white;
            padding: 8px 14px;
            border-radius: 10px;
            text-decoration: none;
            transition: 0.3s;
        }

        .btn:hover {
            background: #c33764;
        }
    </style>
</head>

<body>

<div class="sidebar">
    <h2>📚 My Library</h2>
    <a href="#">🏠 Dashboard</a>
   <a href="search_books.php">📖 Search Books</a>
   <a href="borrow.php" class="btn">📚 Borrow Books</a>
    <a href="#">💰 Fines</a>
    <a href="logout.php">🚪 Logout</a>
</div>

<div class="main">

    <div class="header">
        <h1>Welcome 👋</h1>
        <p>Track your books, due dates, and account activity.</p>
    </div>

    <div class="cards">
        <div class="card c1">
            <h3>Books Borrowed</h3>
            <p>5</p>
        </div>

        <div class="card c2">
            <h3>Books Returned</h3>
            <p>12</p>
        </div>

        <div class="card c3">
            <h3>Outstanding Fine</h3>
            <p>$8</p>
        </div>
    </div>

    <!-- NEW BORROW SECTION -->
    <div class="section">
        <h2>📚 Available Books to Borrow</h2>

        <table>
            <tr>
                <th>Title</th>
                <th>Author</th>
                <th>Category</th>
                <th>Action</th>
            </tr>

            <?php
            $books = mysqli_query($conn, "SELECT * FROM books");

            while($book = mysqli_fetch_assoc($books)) {
            ?>
            <tr>
                <td><?php echo $book['title']; ?></td>
                <td><?php echo $book['author']; ?></td>
                <td><?php echo $book['category']; ?></td>
                <td>
                    <a href="?borrow=" class="btn">Borrow</a>
                </td>
            </tr>
            <?php } ?>
        </table>
    </div>

    <div class="section">
        <h2>Recent Borrowed Books</h2>

        <table>
            <tr>
                <th>Book</th>
                <th>Date Borrowed</th>
                <th>Due Date</th>
                <th>Status</th>
            </tr>

            <tr>
                <td>Introduction to PHP</td>
                <td>2026-04-20</td>
                <td>2026-04-30</td>
                <td><span class="status active">On Time</span></td>
            </tr>

            <tr>
                <td>Database Systems</td>
                <td>2026-04-15</td>
                <td>2026-04-22</td>
                <td><span class="status late">Late</span></td>
            </tr>
        </table>
    </div>

</div>

</body>
</html>