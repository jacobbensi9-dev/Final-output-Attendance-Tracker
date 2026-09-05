<?php
include "db.php";

$sql = "SELECT COUNT(*) AS total FROM students WHERE DATE(time_in)=CURDATE()";

$result = mysqli_query($conn, $sql);

if(!$result){
    die(mysqli_error($conn));
}

$row = mysqli_fetch_assoc($result);

echo $row['total'];
?>