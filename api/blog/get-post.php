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
    SELECT p.id AS post_id, p.title, p.content, p.likes, p.dislikes, p.share_count, p.created_at,
           u.username AS author
    FROM posts p
    LEFT JOIN users u ON u.id = p.author_id
    WHERE p.id = :post_id AND p.deleted_at IS NULL
    LIMIT 1
");
$statement->execute(['post_id' => $postId]);
$post = $statement->fetch();

if (!$post) {
    http_response_code(404);
    echo json_encode(['error' => 'Post not found']);
    exit;
}

// Extract plain text from content JSON
$contentJson = json_decode((string) $post['content'], true);
$bodyText = '';

if (is_array($contentJson)) {
    if (isset($contentJson['blocks']) && is_array($contentJson['blocks'])) {
        foreach ($contentJson['blocks'] as $block) {
            if (isset($block['type']) && $block['type'] === 'text' && isset($block['data']['text'])) {
                $bodyText = (string) $block['data']['text'];
                break;
            }
        }
    } elseif (isset($contentJson['text'])) {
        $bodyText = (string) $contentJson['text'];
    }
} elseif (is_string($contentJson)) {
    $bodyText = $contentJson;
}

$post['body_text'] = $bodyText;

echo json_encode(['post' => $post]);