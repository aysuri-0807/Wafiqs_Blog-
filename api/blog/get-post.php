<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../db/db.php';

function columnExists(PDO $db, string $table, string $column): bool
{
    $statement = $db->prepare("SHOW COLUMNS FROM `{$table}` LIKE :column_name");
    $statement->execute(["column_name" => $column]);
    return $statement->fetch() !== false;
}

$postId = isset($_GET['post_id']) ? (int) $_GET['post_id'] : 0;

if ($postId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing or invalid post_id']);
    exit;
}

try {
    $postIdColumn = columnExists($db, 'posts', 'post_id') ? 'post_id' : 'id';
    $userIdColumn = columnExists($db, 'users', 'id') ? 'id' : 'user_id';

    $hasTitle = columnExists($db, 'posts', 'title');
    $hasAuthor = columnExists($db, 'posts', 'author');
    $hasContentJson = columnExists($db, 'posts', 'content_json');
    $hasContent = columnExists($db, 'posts', 'content');
    $hasLikes = columnExists($db, 'posts', 'likes');
    $hasDislikes = columnExists($db, 'posts', 'dislikes');
    $hasShareCount = columnExists($db, 'posts', 'share_count');
    $hasCreatedAt = columnExists($db, 'posts', 'created_at');
    $hasDeletedAt = columnExists($db, 'posts', 'deleted_at');
    $hasUsername = columnExists($db, 'users', 'username');

    $authorFallbackExpr = $hasAuthor ? 'p.author' : 'NULL';
    $authorExpr = $hasUsername
        ? "COALESCE(u.username, {$authorFallbackExpr}, 'Unknown') AS author"
        : "COALESCE({$authorFallbackExpr}, 'Unknown') AS author";

    $select = [
        "p.{$postIdColumn} AS post_id",
        $hasTitle ? 'p.title' : "'' AS title",
        $authorExpr,
        $hasContentJson
            ? 'p.content_json AS content_payload'
            : ($hasContent ? 'p.content AS content_payload' : "'' AS content_payload"),
        $hasLikes ? 'p.likes' : '0 AS likes',
        $hasDislikes ? 'p.dislikes' : '0 AS dislikes',
        $hasShareCount ? 'p.share_count' : '0 AS share_count',
        $hasCreatedAt ? 'p.created_at' : 'NULL AS created_at',
    ];

    $sql = "SELECT " . implode(', ', $select) . " FROM posts p";
    if ($hasUsername) {
        $sql .= " LEFT JOIN users u ON u.{$userIdColumn} = p.author_id";
    }
    $sql .= " WHERE p.{$postIdColumn} = :post_id";
    if ($hasDeletedAt) {
        $sql .= " AND p.deleted_at IS NULL";
    }
    $sql .= " LIMIT 1";

    $statement = $db->prepare($sql);
    $statement->execute(['post_id' => $postId]);
    $post = $statement->fetch();
} catch (Throwable $error) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not query post']);
    exit;
}

if (!$post) {
    http_response_code(404);
    echo json_encode(['error' => 'Post not found']);
    exit;
}

// Extract plain text from content JSON
$contentJson = json_decode((string) $post['content_payload'], true);
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
unset($post['content_payload']);

echo json_encode(['post' => $post]);