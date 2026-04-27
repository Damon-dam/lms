<?php
include "db.php";

if(isset($_GET['q'])){
    $q = $_GET['q'];

    $sql = "SELECT title FROM books 
            WHERE title LIKE '%$q%' 
            LIMIT 5";

    $result = $conn->query($sql);

    $data = [];

    while($row = $result->fetch_assoc()){
        $data[] = $row['title'];
    }

    echo json_encode($data);
}
?>