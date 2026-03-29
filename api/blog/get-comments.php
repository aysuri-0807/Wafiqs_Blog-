<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once '../db/db.php';

$postId = isset($_GET['post_id']) ? (int) $_GET['post_id'] : 0;

if ($postId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing or invalid post_id']);
    exit;
}

$statement = $db->prepare("
    SELECT c.id AS comment_id, c.content, c.likes, c.dislikes, c.created_at,
           u.username AS author
    FROM comments c
    LEFT JOIN users u ON u.id = c.user_id
    WHERE c.post_id = :post_id
    ORDER BY c.created_at ASC
");
$statement->execute(['post_id' => $postId]);
$comments = $statement->fetchAll();

echo json_encode(['comments' => $comments]);