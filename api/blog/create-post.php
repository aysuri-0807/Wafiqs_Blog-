<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once "../db/db.php";

$body = json_decode(file_get_contents("php://input"), true);

if (!isset($body["title"]) || !isset($body["content_json"])) {
    echo json_encode(['error' => 'Did not enter title or content_json']);
    exit;
}

$title = $body['title'];
$content_json = $body['content_json'];

$insert = $db->prepare("INSERT INTO posts (title, author, content_json) VALUES (:title, :author, :content_json)");
$insert->execute([
    'title' => $title,
    'author' => "Wafig",
    'content_json' => json_encode($content_json),
]);
echo json_encode(['message' => 'Successfully created post']);
