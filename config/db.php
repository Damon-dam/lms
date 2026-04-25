<?php
// Database credentials
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "lms";

// Create connection
$conn = mysqli_connect($host, $user, $pass, $dbname);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>