<?php
session_start();
include "db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != "admin") {
    header("Location: login.php");
    exit();
}

/* DELETE USER */
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $conn->query("DELETE FROM users WHERE id=$id");
    header("Location: users.php");
    exit();
}

/* SEARCH */
$search = "";
if (isset($_GET['search'])) {
    $search = $_GET['search'];
    $sql = "SELECT * FROM users 
            WHERE username LIKE '%$search%' 
            OR full_name LIKE '%$search%'";
} else {
    $sql = "SELECT * FROM users";
}

$result = $conn->query($sql);
$totalUsers = $conn->query("SELECT COUNT(*) as total FROM users")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html>
<head>
<title>Users Management</title>

<style>

body{
    margin:0;
    font-family:'Segoe UI', sans-serif;
    background:#f5f7fb;
}

/* SIDEBAR */
.sidebar{
    width:240px;
    height:100vh;
    position:fixed;
    background:linear-gradient(180deg,#1a56db,#0b2f6b);
    padding-top:20px;
}

.sidebar a{
    display:block;
    color:white;
    padding:14px 20px;
    text-decoration:none;
}

.sidebar a:hover{
    background:rgba(255,255,255,0.12);
}

/* MAIN */
.main{
    margin-left:240px;
    padding:25px;
}

/* HEADER */
.header{
    background:white;
    padding:20px;
    border-radius:12px;
    box-shadow:0 6px 15px rgba(0,0,0,0.05);
    display:flex;
    justify-content:space-between;
}

.header h2{
    margin:0;
    color:#1a56db;
}

.stat{
    font-size:14px;
    color:#777;
}

/* LAYOUT */
.top{
    display:flex;
    gap:20px;
    margin-top:20px;
}

/* SMALL ADD USER CARD */
.small-card{
    width:260px;
    background:white;
    padding:15px;
    border-radius:12px;
    box-shadow:0 8px 20px rgba(0,0,0,0.05);
}

.small-card h3{
    margin-top:0;
    font-size:16px;
    color:#1a56db;
}

.small-card input,
.small-card select{
    width:100%;
    padding:8px;
    margin-top:8px;
    border:1px solid #ddd;
    border-radius:6px;
    font-size:13px;
}

.small-card button{
    width:100%;
    padding:10px;
    margin-top:10px;
    background:#1a56db;
    color:white;
    border:none;
    border-radius:6px;
    cursor:pointer;
}

.small-card button:hover{
    background:#0b2f6b;
}

/* SEARCH BOX */
.search-box{
    flex:1;
    background:white;
    padding:20px;
    border-radius:12px;
    box-shadow:0 8px 20px rgba(0,0,0,0.05);
}

.search-box input{
    width:100%;
    padding:10px;
    border:1px solid #ddd;
    border-radius:8px;
}

/* TABLE */
table{
    width:100%;
    border-collapse:collapse;
    margin-top:20px;
}

th{
    background:#1a56db;
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

/* BADGE */
.badge{
    padding:5px 10px;
    border-radius:20px;
    font-size:12px;
    color:white;
}

.admin{ background:#ef4444; }
.user{ background:#22c55e; }

/* DELETE */
.delete{
    background:#ff3b3b;
    padding:6px 10px;
    border-radius:6px;
    color:white;
    text-decoration:none;
    font-size:12px;
}

.delete:hover{
    background:#b80000;
}

</style>
</head>

<body>

<div class="sidebar">
    <a href="admin_dashboard.php">🏠 Dashboard</a>
    <a href="users.php">👤 Users</a>
    <a href="#">📚 Books</a>
    <a href="#">🔄 Borrow</a>
    <a href="#">💰 Fines</a>
</div>

<div class="main">

    <!-- HEADER -->
    <div class="header">
        <div>
            <h2>Users Management</h2>
            <div class="stat">Total Users: <?php echo $totalUsers; ?></div>
        </div>
    </div>

    <!-- TOP SECTION -->
    <div class="top">

        <!-- SMALL ADD USER -->
        <div class="small-card">
            <h3>➕ Add User</h3>

            <form method="POST">
                <input type="text" name="full_name" placeholder="Full Name" required>
                <input type="text" name="username" placeholder="Username" required>
                <input type="password" name="password" placeholder="Password" required>

                <select name="role">
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                </select>

                <button name="add_user">Add</button>
            </form>

            <?php
            if (isset($_POST['add_user'])) {

                $full_name = $_POST['full_name'];
                $username = $_POST['username'];
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $role = $_POST['role'];

                $conn->query("INSERT INTO users (full_name, username, password, role)
                              VALUES ('$full_name', '$username', '$password', '$role')");

                echo "<p style='color:green;font-size:12px;'>✔ User added</p>";
            }
            ?>
        </div>

        <!-- SEARCH -->
        <div class="search-box">
            <h3>🔍 Search Users</h3>

            <form method="GET">
                <input type="text" name="search" placeholder="Search user..." value="<?php echo $search; ?>">
            </form>

            <!-- TABLE -->
            <table>
                <tr>
                    <th>ID</th>
                    <th>Full Name</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Action</th>
                </tr>

                <?php while ($row = $result->fetch_assoc()) { ?>

                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo $row['full_name']; ?></td>
                    <td><?php echo $row['username']; ?></td>
                    <td>
                        <span class="badge <?php echo $row['role']; ?>">
                            <?php echo $row['role']; ?>
                        </span>
                    </td>
                    <td>
                        <a class="delete"
                           onclick="return confirm('Delete user?')"
                           href="users.php?delete=<?php echo $row['id']; ?>">
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