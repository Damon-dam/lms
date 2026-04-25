<?php
session_start();
require_once "../../config/db.php";

$id = $_POST['id'];
$title = $_POST['title'];
$author = $_POST['author'];
$category = $_POST['category'];
$quantity = $_POST['quantity'];

$sql = "UPDATE books 
        SET title='$title', author='$author', category='$category', quantity='$quantity'
        WHERE id=$id";

mysqli_query($conn, $sql);

header("Location: view.php");
exit();
?>