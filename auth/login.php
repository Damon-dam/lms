<?php session_start(); ?>

<!DOCTYPE html>
<html>
<head>
    <title>Login - LMS</title>

    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>

<!-- 🔷 LOGO -->
<div class="logo">
    <i class="fa-solid fa-book-open"></i>
    <span>LMS Library</span>
</div>

<h2>Login</h2>

<!-- Messages -->
<?php if (isset($_SESSION['success'])): ?>
    <div class="alert success">
        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert error">
        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
    </div>
<?php endif; ?>

<form action="login_process.php" method="POST">

    <!-- Email -->
    <label><i class="fa-solid fa-envelope"></i> Email</label>
    <input type="email" name="email" placeholder="Enter email" required>

    <!-- Password -->
    <label><i class="fa-solid fa-lock"></i> Password</label>
    <input type="password" name="password" placeholder="Enter password" required>

    <button type="submit">
        <i class="fa-solid fa-right-to-bracket"></i> Login
    </button>

    <p style="text-align:center; margin-top:10px;">
        Don't have an account? <a href="register.php">Register</a>
    </p>

</form>

</body>
</html>