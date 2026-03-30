<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../db/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$currentUserId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$hasCommentVotes = true;
try {
    $db->query("SELECT vote_type FROM comment_votes LIMIT 1");
} catch (Throwable $error) {
    $hasCommentVotes = false;
}

$postId = isset($_GET['post_id']) ? (int) $_GET['post_id'] : 0;

if ($postId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing or invalid post_id']);
    exit;
}

$sql = "
    SELECT c.id AS comment_id, c.content, c.likes, c.dislikes, c.created_at,
           u.username AS author";

if ($hasCommentVotes && $currentUserId > 0) {
    $sql .= ", cv.vote_type AS user_vote";
} else {
    $sql .= ", NULL AS user_vote";
}

$sql .= "
    FROM comments c
    LEFT JOIN users u ON u.id = c.user_id";

if ($hasCommentVotes && $currentUserId > 0) {
    $sql .= " LEFT JOIN comment_votes cv ON cv.comment_id = c.id AND cv.user_id = :current_user_id";
}

$sql .= "
    WHERE c.post_id = :post_id
    ORDER BY c.created_at ASC
";

$statement = $db->prepare($sql);
$params = ['post_id' => $postId];
if ($hasCommentVotes && $currentUserId > 0) {
    $params['current_user_id'] = $currentUserId;
}
$statement->execute($params);
$comments = $statement->fetchAll();

echo json_encode(['comments' => $comments]);