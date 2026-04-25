<?php
session_start();
require_once "../../config/db.php";

$id = $_GET['id'];

mysqli_query($conn, "DELETE FROM users WHERE id=$id AND role='member'");

header("Location: view.php");
exit();
?>