<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once '../db/db.php';

$statement = $db->query("SELECT p.post_id, p.title, COALESCE(u.username, p.author, 'Unknown') AS author, p.content_json, p.likes, p.dislikes, p.share_count, p.created_at FROM posts p LEFT JOIN users u ON u.user_id = p.author_id WHERE p.deleted_at IS NULL ORDER BY p.created_at DESC");
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
