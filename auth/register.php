<?php session_start(); ?>

<!DOCTYPE html>
<html>
<head>
    <title>Register - LMS</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>

<!-- 🔷 LOGO -->
<div class="logo">
    <i class="fa-solid fa-book-open"></i>
    <span>LMS Library</span>
</div>

<h2>Create Account</h2>

<form action="register_process.php" method="POST">

    <!-- Name -->
    <label><i class="fa-solid fa-user"></i> Full Name</label>
    <input type="text" name="name" placeholder="Enter your name" required>

    <!-- Email -->
    <label><i class="fa-solid fa-envelope"></i> Email</label>
    <input type="email" name="email" placeholder="Enter your email" required>

    <!-- Password -->
    <label><i class="fa-solid fa-lock"></i> Password</label>
    <input type="password" name="password" placeholder="Enter password" required>

    <!-- Role -->
    <label><i class="fa-solid fa-user-shield"></i> Role</label>
    <select name="role" required>
        <option value="">- - -Select Role- - -</option>
        <option value="member">Member</option>
        <option value="admin">Admin</option>
    </select>

    <button type="submit">
        <i class="fa-solid fa-user-plus"></i> Register
    </button>

    <p style="text-align:center; margin-top:10px;">
        Already have an account? <a href="login.php">Login</a>
    </p>

</form>

</body>
</html>