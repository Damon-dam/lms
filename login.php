<?php
session_start();
include "db.php";
?>

<!DOCTYPE html>
<html>
<head>
<title>Library Login System</title>

<style>

body{
    margin:0;
    font-family:Arial, sans-serif;

    background: url("openbook.png") no-repeat center center fixed;
    background-size: cover;

    display:flex;
    justify-content:center;
    align-items:center;
    height:100vh;
    position:relative;
}

/* DARK OVERLAY */
body::before{
    content:"";
    position:absolute;
    top:0;
    left:0;
    width:100%;
    height:100%;
    background:rgba(0,0,0,0.5);
}

/* LOGIN BOX (GLASS EFFECT) */
.box{
    width:380px;
    padding:35px;

    background:rgba(255,255,255,0.15);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);

    border-radius:16px;
    box-shadow:0 10px 30px rgba(0,0,0,0.3);
    text-align:center;
    border:1px solid rgba(255,255,255,0.3);

    position:relative;
}

/* LOGO ANIMATION */
.logo{
    width:75px;
    height:75px;
    margin-bottom:10px;
    animation: float 3s ease-in-out infinite;
}

@keyframes float{
    0%{transform:translateY(0px);}
    50%{transform:translateY(-8px);}
    100%{transform:translateY(0px);}
}

/* TITLE */
h2{
    color:white;
    margin:10px 0 5px;
}

p{
    color:#eee;
    font-size:14px;
    margin-bottom:20px;
}

/* INPUTS */
input{
    width:100%;
    padding:12px;
    margin-top:12px;
    border:none;
    border-radius:8px;
    outline:none;
}

input:focus{
    transform:scale(1.02);
    transition:0.3s;
}

/* BUTTON */
button{
    width:100%;
    padding:12px;
    margin-top:18px;
    background:#0047ab;
    color:white;
    border:none;
    border-radius:8px;
    font-size:16px;
    cursor:pointer;
    transition:0.3s;
}

button:hover{
    background:#002f6c;
    transform:scale(1.05);
}

/* ERROR */
.error{
    color:#ff4d4d;
    margin-top:12px;
    font-size:14px;
}

/* SMALL TEXT */
.small{
    font-size:12px;
    color:#ddd;
    margin-top:10px;
}

/* SHOW PASSWORD */
.show-pass{
    font-size:12px;
    color:white;
    margin-top:8px;
    cursor:pointer;
}

</style>
</head>

<body>

<div class="box">

    <!-- LOGO -->
    <svg class="logo" viewBox="0 0 64 64">
        <rect width="64" height="64" rx="12" fill="#0047ab"/>
        <path d="M16 18H48V50H16V18Z" fill="white"/>
        <path d="M20 24H44V28H20V24Z" fill="#0047ab"/>
        <path d="M20 32H44V36H20V32Z" fill="#0047ab"/>
        <path d="M20 40H36V44H20V40Z" fill="#0047ab"/>
    </svg>

    <h2>📚 Library System</h2>
    <p>Login to access books & dashboard</p>

    <form method="POST">

        <input type="text" name="username" placeholder="Username" required>

        <input type="password" name="password" id="password" placeholder="Password" required>

        <div class="show-pass" onclick="togglePassword()">
            👁 Show Password
        </div>

        <button type="submit" name="login">Login</button>
    </form>

<?php
if (isset($_POST['login'])) {

    $username = $_POST['username'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE username='$username'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {

        $row = $result->fetch_assoc();

        if (password_verify($password, $row['password'])) {

            $_SESSION['id'] = $row['id'];
            $_SESSION['role'] = $row['role'];
            $_SESSION['username'] = $row['username'];

            if ($row['role'] == "admin") {
                header("Location: admin_dashboard.php");
                exit();
            } else {
                header("Location: user_dashboard.php");
                exit();
            }

        } else {
            echo "<div class='error'>❌ Invalid password</div>";
        }

    } else {
        echo "<div class='error'>❌ User not found</div>";
    }
}
?>

<div class="small">
    © Library Management System
</div>

</div>

<script>
function togglePassword(){
    var pass = document.getElementById("password");
    if(pass.type === "password"){
        pass.type = "text";
    } else {
        pass.type = "password";
    }
}
</script>

</body>
</html>