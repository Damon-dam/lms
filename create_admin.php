<?php
include "db.php";

$full_name = "System Admin";
$username = "admin";
$password = password_hash("admin123", PASSWORD_DEFAULT);
$role = "admin";

// prevent duplicate admin
$check = $conn->query("SELECT * FROM users WHERE username='admin'");
if ($check->num_rows == 0) {

    $sql = "INSERT INTO users (full_name, username, password, role)
            VALUES ('$full_name', '$username', '$password', '$role')";

    $conn->query($sql);

    echo "Admin created successfully!";
} else {
    echo "Admin already exists!";
}
?>