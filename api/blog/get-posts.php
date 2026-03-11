<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once '../db/db.php';

$sql = "SELECT * FROM posts";
$result = $conn->query($sql);
$result = [];

while ($row = $result->fetch_assoc()) {
  $users[] = $row;
}

$result["posts"] = $users;

//sends the data to the user
echo json_encode($result);