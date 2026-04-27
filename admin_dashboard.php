<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] != "admin") {
    header("Location: login.php");
    exit();
}

include "db.php";

/* COUNTS */
$users = $conn->query("SELECT COUNT(*) as total FROM users")->fetch_assoc()['total'];
$books = $conn->query("SELECT COUNT(*) as total FROM books")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html>
<head>
<title>Admin Dashboard</title>

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

.sidebar h2{
    text-align:center;
    margin-bottom:25px;
}

.sidebar a{
    display:block;
    color:white;
    padding:14px 20px;
    text-decoration:none;
    transition:0.3s;
    cursor:pointer;
}

.sidebar a:hover{
    background:rgba(255,255,255,0.15);
    transform:translateX(5px);
}

/* MAIN */
.main{
    margin-left:230px;
    padding:25px;
}

/* TOPBAR */
.topbar{
    background:white;
    padding:20px;
    border-radius:14px;
    box-shadow:0 8px 20px rgba(0,0,0,0.08);
    display:flex;
    justify-content:space-between;
    align-items:center;
}

.topbar h2{
    margin:0;
    color:#0047ab;
}

.welcome{
    font-size:13px;
    color:#666;
}

/* LOGOUT */
.logout{
    background:#ff3b3b;
    color:white;
    padding:10px 15px;
    border-radius:8px;
    text-decoration:none;
}

.logout:hover{
    background:#c40000;
}

/* OVERVIEW */
.overview{
    margin-top:20px;
    padding:25px;
    border-radius:16px;
    background:linear-gradient(135deg,#0047ab,#00c6ff);
    color:white;
    box-shadow:0 10px 25px rgba(0,0,0,0.15);
}

.overview h2{
    margin:0;
}

.overview p{
    margin-top:10px;
    font-size:14px;
}

.tags{
    margin-top:15px;
    display:flex;
    flex-wrap:wrap;
    gap:10px;
}

.tags span{
    background:rgba(255,255,255,0.2);
    padding:8px 12px;
    border-radius:20px;
    font-size:12px;
}

/* CARDS */
.cards{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
    gap:20px;
    margin-top:20px;
}

/* CARD */
.card{
    padding:25px;
    border-radius:14px;
    color:white;
    box-shadow:0 10px 25px rgba(0,0,0,0.15);
    transition:0.3s;
}

.card:hover{
    transform:translateY(-6px);
}

.c1{ background:linear-gradient(135deg,#667eea,#764ba2); }
.c2{ background:linear-gradient(135deg,#43cea2,#185a9d); }
.c3{ background:linear-gradient(135deg,#ff758c,#ff7eb3); }

.icon{
    font-size:28px;
    margin-bottom:10px;
}

.card h3{
    margin:0;
    font-size:16px;
}

.card p{
    font-size:28px;
    font-weight:bold;
    margin-top:10px;
}

</style>
</head>

<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <h2>📚 Admin Panel</h2>

    <a href="admin_dashboard.php">🏠 Dashboard</a>
    <a href="users.php">👤 Users</a>
    <a href="books.php">📚 Books</a>
    <a href="#">🔄 Borrow</a>
    <a href="#">💰 Fines</a>
    <a href="logout.php">🚪 Logout</a>
</div>

<!-- MAIN -->
<div class="main">

    <!-- TOPBAR -->
    <div class="topbar">
        <div>
            <h2>Welcome Admin, <?php echo $_SESSION['username']; ?> 👋</h2>
            <div class="welcome">Library Management Control Panel</div>
        </div>

        <a class="logout" href="logout.php">Logout</a>
    </div>

    <!-- OVERVIEW -->
    <div class="overview">

        <h2>📊 Library System Overview</h2>

        <p>
            Manage books, users, borrowing activities, and track all library operations
            from this central dashboard.
        </p>

        <div class="tags">
            <span>📚 Books</span>
            <span>👤 Users</span>
            <span>🔄 Borrow</span>
            <span>💰 Fines</span>
        </div>

    </div>

    <!-- CARDS -->
    <div class="cards">

        <div class="card c1">
            <div class="icon">👤</div>
            <h3>Total Users</h3>
            <p><?php echo $users; ?></p>
        </div>

        <div class="card c2">
            <div class="icon">📚</div>
            <h3>Total Books</h3>
            <p><?php echo $books; ?></p>
        </div>

        <div class="card c3">
            <div class="icon">📖</div>
            <h3>Borrowed Books</h3>
            <p>0</p>
        </div>

    </div>

</div>

</body>
</html>