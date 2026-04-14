<?php

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../db/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$currentUserId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;

$username = trim((string) ($_GET['username'] ?? ''));

if ($username === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Username parameter is required']);
    exit;
}

$userStatement = $db->prepare("SELECT id FROM users WHERE username = :username LIMIT 1");
$userStatement->execute(['username' => $username]);
$user = $userStatement->fetch();

if (!$user) {
    http_response_code(404);
    echo json_encode(['error' => 'User not found']);
    exit;
}

$userId = (int) $user['id'];


$hasCommentVotes = true;
try {
    $db->query("SELECT vote_type FROM comment_votes LIMIT 1");
} catch (Throwable $error) {
    $hasCommentVotes = false;
}

// fetch user's recent comments with post information
$sql = "
    SELECT 
        c.id AS comment_id, 
        c.content, 
        c.likes, 
        c.dislikes, 
        c.created_at,
        c.post_id,
        u.username AS author,
        p.title AS post_title";

if ($hasCommentVotes && $currentUserId > 0) {
    $sql .= ", cv.vote_type AS user_vote";
} else {
    $sql .= ", NULL AS user_vote";
}

$sql .= "
    FROM comments c
    LEFT JOIN users u ON u.id = c.user_id
    LEFT JOIN posts p ON p.id = c.post_id";

if ($hasCommentVotes && $currentUserId > 0) {
    $sql .= " LEFT JOIN comment_votes cv ON cv.comment_id = c.id AND cv.user_id = :current_user_id";
}

$sql .= "
    WHERE c.user_id = :user_id
    ORDER BY c.created_at DESC
    LIMIT 20
";

$statement = $db->prepare($sql);
$params = ['user_id' => $userId];
if ($hasCommentVotes && $currentUserId > 0) {
    $params['current_user_id'] = $currentUserId;
}
$statement->execute($params);
$comments = $statement->fetchAll();

echo json_encode(['comments' => $comments]);
