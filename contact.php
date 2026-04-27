<?php
include "db.php";

$msg = "";

// WHEN FORM IS SUBMITTED
if(isset($_POST['send'])){
    $name = $_POST['name'];
    $email = $_POST['email'];
    $message = $_POST['message'];

    $sql = "INSERT INTO contact_messages(name, email, message)
            VALUES('$name', '$email', '$message')";

    if($conn->query($sql) === TRUE){
        $msg = "Message sent successfully!";
    } else {
        $msg = "Error: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Contact Us</title>

<style>
body{
    margin:0;
    font-family:Arial;
    background: url("booksonly.png") no-repeat center center fixed;
    background-size:cover;
}

/* overlay */
body::before{
    content:"";
    position:fixed;
    top:0;
    left:0;
    width:100%;
    height:100%;
    background:rgba(0,0,0,0.5);
    z-index:-1;
}

/* header */
.header{
    background:rgba(0,71,171,0.9);
    color:white;
    padding:20px;
    display:flex;
    justify-content:space-between;
}

.header a{
    color:white;
    text-decoration:none;
}

/* container */
.container{
    max-width:600px;
    margin:50px auto;
    background:rgba(255,255,255,0.95);
    padding:30px;
    border-radius:10px;
}

/* inputs */
input, textarea{
    width:100%;
    padding:10px;
    margin:8px 0;
    border:1px solid #ccc;
    border-radius:5px;
}

button{
    background:#0047ab;
    color:white;
    padding:10px 15px;
    border:none;
    border-radius:5px;
    cursor:pointer;
}

button:hover{
    background:#003380;
}

.success{
    color:green;
    font-weight:bold;
}
</style>

</head>

<body>

<div class="header">
    <h2>📞 Contact Us</h2>
    <a href="index.php">Home</a>
</div>

<div class="container">

    <h2>Send Us a Message</h2>

    <?php if($msg != "") echo "<p class='success'>$msg</p>"; ?>

    <form method="POST">
        <input type="text" name="name" placeholder="Your Name" required>

        <input type="email" name="email" placeholder="Your Email" required>

        <textarea name="message" placeholder="Your Message" required></textarea>

        <button type="submit" name="send">Send Message</button>
    </form>

</div>

</body>
</html>