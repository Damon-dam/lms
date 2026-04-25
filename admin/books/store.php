<?php
session_start();
require_once "../../config/db.php";

$title = $_POST['title'];
$author = $_POST['author'];
$category = $_POST['category'];
$quantity = $_POST['quantity'];

$sql = "INSERT INTO books (title, author, category, quantity)
        VALUES ('$title', '$author', '$category', '$quantity')";

mysqli_query($conn, $sql);

header("Location: view.php");
exit();
?>