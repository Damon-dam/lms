<?php
session_start();
include "db.php";

if ($_SESSION['role'] != "admin") {
    die("Access denied");
}

if (isset($_POST['create'])) {

    $full_name = $_POST['full_name'];
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];

    $sql = "INSERT INTO users (full_name, username, password, role)
            VALUES ('$full_name', '$username', '$password', '$role')";

    $conn->query($sql);

    $success = "User created successfully!";
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Add User</title>

<style>
body{font-family:Arial;background:#eef4ff;display:flex;justify-content:center;align-items:center;height:100vh;}
.box{width:400px;background:white;padding:20px;border-radius:10px;border-top:5px solid #0047ab;}
input,select{width:100%;padding:10px;margin-top:10px;}
button{width:100%;padding:10px;background:#0047ab;color:white;border:none;margin-top:10px;}
.success{color:green;text-align:center;}
</style>
</head>

<body>

<div class="box">
<h2>Create User</h2>

<form method="POST">
<input name="full_name" placeholder="Full Name" required>
<input name="username" placeholder="Username" required>
<input name="password" placeholder="Password" required>

<select name="role">
<option value="user">👤 Users</option>
<option value="admin">Admin</option>
</select>

<button name="create">Create</button>
</form>

<?php
if (isset($success)) echo "<div class='success'>$success</div>";
?>
</div>

</body>
</html>