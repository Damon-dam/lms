<?php
session_start();
include "db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != "user") {
    header("Location: login.php");
    exit();
}

/* BORROW ACTION */
if (isset($_GET['book_id'])) {

    $book_id = $_GET['book_id'];
    $user_id = $_SESSION['id'];

    $borrow_date = date("Y-m-d");
    $due_date = date("Y-m-d", strtotime("+7 days"));

    // insert borrow record
    mysqli_query($conn, "INSERT INTO borrowing (user_id, book_id, borrow_date, due_date, status)
    VALUES ('$user_id', '$book_id', '$borrow_date', '$due_date', 'Borrowed')");

    // reduce available books
    mysqli_query($conn, "UPDATE books SET available = available - 1 WHERE id='$book_id'");

    echo "<script>alert('Book borrowed successfully!'); window.location='borrow.php';</script>";
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Borrow Books</title>

<style>
body{
    font-family:Arial;
    background:#f5f7fb;
    margin:0;
}

.header{
    background:#0047ab;
    color:white;
    padding:20px;
    text-align:center;
}

.container{
    padding:20px;
}

.book{
    background:white;
    padding:15px;
    margin-bottom:10px;
    border-radius:10px;
    box-shadow:0 4px 10px rgba(0,0,0,0.1);
    display:flex;
    justify-content:space-between;
    align-items:center;
}

.btn{
    background:#0047ab;
    color:white;
    padding:8px 12px;
    text-decoration:none;
    border-radius:6px;
}

.btn:hover{
    background:#003580;
}
</style>
</head>

<body>

<div class="header">
    <h2>📚 Borrow Books</h2>
</div>

<div class="container">

<?php
$result = mysqli_query($conn, "SELECT * FROM books WHERE available > 0");

while($row = mysqli_fetch_assoc($result)){
?>

<div class="book">
    <div>
        <b><?php echo $row['title']; ?></b><br>
        <small><?php echo $row['author']; ?></small>
    </div>

    <a class="btn" href="borrow.php?book_id=">
        Borrow
    </a>
</div>

<?php } ?>

</div>

</body>
</html>