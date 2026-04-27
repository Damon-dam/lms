<?php
include "db.php";
?>

<!DOCTYPE html>
<html>
<head>
<title>Library System Home</title>


<style>
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body{
    font-family:Arial, sans-serif;
    background:url("booksonly.png") no-repeat center center fixed;
    background-size:cover;
    color:white;
}

/* OVERLAY */
body::before{
    content:"";
    position:fixed;
    top:0;
    left:0;
    width:100%;
    height:100%;
    background:rgba(0,0,0,0.55);
    z-index:-1;
}

/* HEADER */
.header{
    background:linear-gradient(90deg,#003b99,#005eff);
    padding:18px 30px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    box-shadow:0 4px 15px rgba(0,0,0,0.3);
}

.header h2{
    font-size:28px;
}

.header a{
    color:white;
    text-decoration:none;
    margin-left:20px;
    font-weight:bold;
    transition:0.3s;
}

.header a:hover{
    color:#d9e8ff;
}

/* HERO */
.hero{
    text-align:center;
    padding:60px 20px;
}

.hero h1{
    font-size:52px;
    margin-bottom:15px;
    text-shadow:2px 2px 10px rgba(0,0,0,0.5);
}

.hero p{
    font-size:20px;
    max-width:700px;
    margin:auto;
}

/* CARDS */
.cards{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(250px,1fr));
    gap:25px;
    padding:40px;
}

.card{
    background:rgba(255,255,255,0.95);
    border-radius:18px;
    padding:30px 20px;
    text-align:center;
    color:#003b99;
    box-shadow:0 10px 25px rgba(0,0,0,0.25);
    cursor:pointer;
    transition:all 0.3s ease;
}

.card:hover{
    transform:translateY(-10px) scale(1.03);
}

.icon{
    font-size:42px;
    margin-bottom:15px;
}

.card h3{
    margin-bottom:10px;
    font-size:24px;
}

.card p{
    color:#444;
    font-size:16px;
}

/* INFO BOX */
.info-box{
    width:85%;
    margin:20px auto 50px;
    background:rgba(255,255,255,0.96);
    color:#222;
    border-radius:18px;
    padding:30px;
    text-align:center;
    box-shadow:0 10px 25px rgba(0,0,0,0.3);
    transition:0.4s;
}

.info-box h2{
    color:#0047ab;
    margin-bottom:15px;
    font-size:30px;
}

.info-box p{
    font-size:18px;
    line-height:1.6;
}

/* FOOTER */
.footer{
    background:linear-gradient(90deg,#003b99,#005eff);
    text-align:center;
    padding:18px;
    font-size:15px;
    box-shadow:0 -4px 15px rgba(0,0,0,0.3);
}

</style>
</head>

<body>

<!-- HEADER -->
<div class="header">
    <h2>📚 Library System</h2>
    <div>
        <a href="login.php">Login</a>
        <a href="contact.php">Contact Us</a>
    </div>
</div>

<!-- HERO -->
<div class="hero">
    <h1>Welcome to Library System</h1>
    <p>Manage books, users, borrowing & fines easily</p>
</div>

<!-- CARDS -->
<div class="cards">

    <div class="card" onclick="showDescription('books')">
        <div class="icon">📚</div>
        <h3>Book Management</h3>
        <p>Organize and track books</p>
    </div>

    <div class="card" onclick="showDescription('users')">
        <div class="icon">👤</div>
        <h3>User Management</h3>
        <p>Manage members & staff</p>
    </div>

    <div class="card" onclick="showDescription('borrow')">
        <div class="icon">🔄</div>
        <h3>Borrow System</h3>
        <p>Track borrowing process</p>
    </div>

    <div class="card" onclick="showDescription('fine')">
        <div class="icon">💰</div>
        <h3>Fine System</h3>
        <p>Handle overdue penalties</p>
    </div>

</div>

<!-- DESCRIPTION BOX -->
<div id="infoBox" class="info-box">
    <h2 id="infoTitle">Select a Feature</h2>
    <p id="infoText">Click any card above to see detailed information about that module.</p>
</div>

<!-- FOOTER -->
<div class="footer">
    © 2026 Library System
</div>

<script>
function showDescription(type){

    let title = "";
    let text = "";

    if(type === "books"){
        title = "Book Management";
        text = "This module allows librarians to add, update, categorize, and monitor all books in the system for easy organization.";
    }

    if(type === "users"){
        title = "User Management";
        text = "This feature manages library members and staff accounts, ensuring proper access levels and secure records.";
    }

    if(type === "borrow"){
        title = "Borrow System";
        text = "Track all borrowing transactions, monitor due dates, and maintain accurate records of issued books.";
    }

    if(type === "fine"){
        title = "Fine System";
        text = "Automatically calculate overdue fines and keep payment details for books returned late.";
    }

    document.getElementById("infoTitle").innerText = title;
    document.getElementById("infoText").innerText = text;
}
</script>

</body>
</html>