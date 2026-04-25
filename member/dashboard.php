<?php
session_start();
require_once "../config/db.php";

// Protect member access
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'member') {
    header("Location: ../auth/login.php");
    exit();
}
$user_id = $_SESSION['user_id'];

// Books borrowed by this member
$my_borrows = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total FROM borrowings WHERE user_id='$user_id'"
))['total'];

// Active borrowings
$active = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total FROM borrowings WHERE user_id='$user_id' AND status='borrowed'"
))['total'];

// Returned books
$returned = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total FROM borrowings WHERE user_id='$user_id' AND status='returned'"
))['total'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Member Dashboard - LMS</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>

<!-- LOGO -->
<div class="logo">
    <i class="fa-solid fa-book-open"></i>
    <span>LMS Member</span>
</div>

<h2>Member Dashboard</h2>

<p style="text-align:center;">
    Welcome, <?php echo $_SESSION['name']; ?>
</p>

<!-- DASHBOARD CARDS -->
<div class="dashboard">

    <div class="card">
        <i class="fa-solid fa-book"></i>
        <h3><?php echo $my_borrows; ?></h3>
        <p>My Borrowings</p>
    </div>

    <div class="card">
        <i class="fa-solid fa-hourglass-half"></i>
        <h3><?php echo $active; ?></h3>
        <p>Active</p>
    </div>

    <div class="card">
        <i class="fa-solid fa-check"></i>
        <h3><?php echo $returned; ?></h3>
        <p>Returned</p>
    </div>

</div>
<!-- LOGOUT -->
<div style="text-align:center; margin-top:30px;">
    <a href="../auth/logout.php" style="color:#ff4d4d;">
        <i class="fa-solid fa-right-from-bracket"></i> Logout
    </a>
</div>

</body>
</html>