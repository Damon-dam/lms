<?php
session_start();
require_once "../../config/db.php";

$id = $_POST['id'];
$name = $_POST['name'];
$email = $_POST['email'];

$sql = "UPDATE users 
        SET name='$name', email='$email'
        WHERE id=$id AND role='member'";

mysqli_query($conn, $sql);

header("Location: view.php");
exit();
?>