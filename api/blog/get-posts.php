<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../db/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}

$currentUserId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;

function columnExists(PDO $db, string $table, string $column): bool
{
	$statement = $db->prepare("SHOW COLUMNS FROM `{$table}` LIKE :column_name");
	$statement->execute(["column_name" => $column]);
	return $statement->fetch() !== false;
}

$postIdColumn = columnExists($db, 'posts', 'post_id') ? 'post_id' : 'id';
$userIdColumn = columnExists($db, 'users', 'id') ? 'id' : 'user_id';

$hasTitle = columnExists($db, 'posts', 'title');
$hasAuthor = columnExists($db, 'posts', 'author');
$hasContentJson = columnExists($db, 'posts', 'content_json');
$hasContent = columnExists($db, 'posts', 'content');
$hasAuthorId = columnExists($db, 'posts', 'author_id');
$hasLikes = columnExists($db, 'posts', 'likes');
$hasDislikes = columnExists($db, 'posts', 'dislikes');
$hasShareCount = columnExists($db, 'posts', 'share_count');
$hasCreatedAt = columnExists($db, 'posts', 'created_at');
$hasDeletedAt = columnExists($db, 'posts', 'deleted_at');
$hasUsername = columnExists($db, 'users', 'username');
$hasPostVotes = columnExists($db, 'post_votes', 'vote_type') && columnExists($db, 'post_votes', 'post_id') && columnExists($db, 'post_votes', 'user_id');
$hasCommentsTable = false;
try {
	$commentsTableStatement = $db->query("SHOW TABLES LIKE 'comments'");
	$hasCommentsTable = $commentsTableStatement !== false && $commentsTableStatement->fetch() !== false;
} catch (Throwable $error) {
	$hasCommentsTable = false;
}

$authorFallbackExpr = $hasAuthor ? 'p.author' : 'NULL';
$authorExpr = $hasUsername
	? "COALESCE(u.username, {$authorFallbackExpr}, 'Unknown') AS author"
	: "COALESCE({$authorFallbackExpr}, 'Unknown') AS author";

$select = [
	"p.{$postIdColumn} AS post_id",
	$hasAuthorId ? 'p.author_id' : '0 AS author_id',
	$hasTitle ? 'p.title' : "'' AS title",
	$authorExpr,
	$hasContentJson
		? 'p.content_json'
		: ($hasContent ? 'p.content AS content_json' : "'' AS content_json"),
	$hasLikes ? 'p.likes' : '0 AS likes',
	$hasDislikes ? 'p.dislikes' : '0 AS dislikes',
	$hasShareCount ? 'p.share_count' : '0 AS share_count',
	$hasCreatedAt ? 'p.created_at' : 'NULL AS created_at',
	$hasCommentsTable ? "(SELECT COUNT(*) FROM comments c WHERE c.post_id = p.{$postIdColumn}) AS comment_count" : '0 AS comment_count',
	$hasPostVotes && $currentUserId > 0 ? 'pv.vote_type AS user_vote' : 'NULL AS user_vote',
];

$sql = "SELECT " . implode(', ', $select) . " FROM posts p";
if ($hasUsername) {
	$sql .= " LEFT JOIN users u ON u.{$userIdColumn} = p.author_id";
}
if ($hasPostVotes && $currentUserId > 0) {
	$sql .= " LEFT JOIN post_votes pv ON pv.post_id = p.{$postIdColumn} AND pv.user_id = :current_user_id";
}
if ($hasDeletedAt) {
	$sql .= " WHERE p.deleted_at IS NULL";
}
if ($hasCreatedAt) {
	$sql .= " ORDER BY p.created_at DESC";
} else {
	$sql .= " ORDER BY p.{$postIdColumn} DESC";
}
$statement = $db->prepare($sql);
if ($hasPostVotes && $currentUserId > 0) {
	$statement->execute(['current_user_id' => $currentUserId]);
} else {
	$statement->execute();
}
$posts = $statement->fetchAll();

foreach ($posts as &$post) {
	$contentJson = json_decode((string) $post['content_json'], true);
	$bodyText = '';

	if (is_array($contentJson)) {
		if (isset($contentJson['blocks']) && is_array($contentJson['blocks'])) {
			foreach ($contentJson['blocks'] as $block) {
				if (
					is_array($block)
					&& isset($block['type'])
					&& $block['type'] === 'text'
					&& isset($block['data'])
					&& is_array($block['data'])
					&& isset($block['data']['text'])
				) {
					$bodyText = (string) $block['data']['text'];
					break;
				}
			}
		} elseif (isset($contentJson['text'])) {
			$bodyText = (string) $contentJson['text'];
		} elseif (isset($contentJson['content'])) {
			$bodyText = (string) $contentJson['content'];
		} elseif (isset($contentJson['body'])) {
			$bodyText = (string) $contentJson['body'];
		}
	} elseif (is_string($contentJson)) {
		$bodyText = $contentJson;
	}

	$post['body_text'] = $bodyText;
}
unset($post);

echo json_encode(['posts' => $posts]);
