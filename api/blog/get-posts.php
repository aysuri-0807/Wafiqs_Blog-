<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once '../db/db.php';

$statement = $db->query("SELECT * FROM posts");
$posts = $statement->fetchAll();

echo json_encode(['posts' => $posts]);
