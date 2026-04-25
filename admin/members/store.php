<?php
session_start();
require_once "../../config/db.php";

$name = $_POST['name'];
$email = $_POST['email'];
$password = password_hash($_POST['password'], PASSWORD_DEFAULT);

$sql = "INSERT INTO users (name, email, password, role)
        VALUES ('$name', '$email', '$password', 'member')";

mysqli_query($conn, $sql);

header("Location: view.php");
exit();
?>