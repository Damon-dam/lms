<?php
session_start();
session_destroy(); // destroy login session

header("Location: index.php"); // go back to homepage
exit();
?>