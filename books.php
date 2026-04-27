<?php
session_start();
include "db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != "admin") {
    header("Location: login.php");
    exit();
}

/* DELETE BOOK */
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM books WHERE book_id=$id");
    header("Location: books.php");
    exit();
}

/* ADD BOOK */
if (isset($_POST['add_book'])) {
    $title = $_POST['title'];
    $author = $_POST['author'];
    $category = $_POST['category'];
    $quantity = $_POST['quantity'];

    $conn->query("INSERT INTO books(title, author, category, quantity, available)
                  VALUES('$title', '$author', '$category', '$quantity', '$quantity')");
}

/* SEARCH */
$search = "";
if (isset($_GET['search'])) {
    $search = $_GET['search'];
    $result = $conn->query("SELECT * FROM books 
        WHERE title LIKE '%$search%' 
        OR author LIKE '%$search%' 
        OR category LIKE '%$search%'");
} else {
    $result = $conn->query("SELECT * FROM books");
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Books Management</title>

<style>

body{
    margin:0;
    font-family:Segoe UI;
    background:#f4f8ff;
}

/* SIDEBAR */
.sidebar{
    width:230px;
    height:100vh;
    background:linear-gradient(180deg,#0047ab,#002c70);
    position:fixed;
    padding-top:20px;
    color:white;
}

.sidebar a{
    display:block;
    color:white;
    padding:14px 20px;
    text-decoration:none;
}

.sidebar a:hover{
    background:rgba(255,255,255,0.15);
}

/* MAIN */
.main{
    margin-left:230px;
    padding:25px;
}

/* HEADER */
.header{
    background:white;
    padding:20px;
    border-radius:12px;
    box-shadow:0 6px 15px rgba(0,0,0,0.08);
    display:flex;
    justify-content:space-between;
    align-items:center;
}

.header h2{
    margin:0;
    color:#0047ab;
}

/* TOP SECTION */
.top{
    display:flex;
    gap:20px;
    margin-top:20px;
}

/* ADD BOOK */
.add-box{
    width:280px;
    background:white;
    padding:15px;
    border-radius:12px;
    box-shadow:0 6px 15px rgba(0,0,0,0.08);
}

.add-box h3{
    margin-top:0;
    color:#0047ab;
}

input{
    width:100%;
    padding:8px;
    margin-top:8px;
    border:1px solid #ddd;
    border-radius:6px;
}

button{
    width:100%;
    padding:10px;
    margin-top:10px;
    background:#0047ab;
    color:white;
    border:none;
    border-radius:6px;
    cursor:pointer;
}

button:hover{
    background:#002c70;
}

/* SEARCH + TABLE */
.content{
    flex:1;
    background:white;
    padding:20px;
    border-radius:12px;
    box-shadow:0 6px 15px rgba(0,0,0,0.08);
}

/* TABLE */
table{
    width:100%;
    border-collapse:collapse;
    margin-top:15px;
}

th{
    background:#0047ab;
    color:white;
    padding:12px;
}

td{
    padding:12px;
    border-bottom:1px solid #eee;
}

tr:hover{
    background:#f0f6ff;
}

/* DELETE */
.delete{
    background:red;
    color:white;
    padding:6px 10px;
    border-radius:6px;
    text-decoration:none;
    font-size:12px;
}

</style>
</head>

<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <a href="admin_dashboard.php">🏠 Dashboard</a>
    <a href="users.php">👤 Users</a>
    <a href="books.php">📚 Books</a>
    <a href="#">🔄 Borrow</a>
    <a href="#">💰 Fines</a>
    <a href="logout.php">🚪 Logout</a>
</div>

<div class="main">

    <!-- HEADER -->
    <div class="header">
        <h2>📚 Books Management</h2>
    </div>

    <!-- TOP SECTION -->
    <div class="top">

        <!-- ADD BOOK -->
        <div class="add-box">
            <h3>➕ Add Book</h3>

            <form method="POST">
                <input type="text" name="title" placeholder="Title" required>
                <input type="text" name="author" placeholder="Author" required>
                <input type="text" name="category" placeholder="Category" required>
                <input type="number" name="quantity" placeholder="Quantity" required>
                <button name="add_book">Add Book</button>
            </form>
        </div>

        <!-- SEARCH + TABLE -->
        <div class="content">

            <form method="GET">
                <input type="text" name="search" placeholder="Search books..." value="<?php echo $search; ?>">
            </form>

            <table>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Author</th>
                    <th>Category</th>
                    <th>Qty</th>
                    <th>Available</th>
                    <th>Action</th>
                </tr>

                <?php while ($row = $result->fetch_assoc()) { ?>

                <tr>
                    <td><?php echo $row['book_id']; ?></td>
                    <td><?php echo $row['title']; ?></td>
                    <td><?php echo $row['author']; ?></td>
                    <td><?php echo $row['category']; ?></td>
                    <td><?php echo $row['quantity']; ?></td>
                    <td><?php echo $row['available']; ?></td>
                    <td>
                        <a class="delete"
                           onclick="return confirm('Delete this book?')"
                           href="books.php?delete=<?php echo $row['book_id']; ?>">
                           Delete
                        </a>
                    </td>
                </tr>

                <?php } ?>

            </table>

        </div>

    </div>

</div>

</body>
</html>