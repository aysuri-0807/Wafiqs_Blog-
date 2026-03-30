<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../db/db.php";
require_once __DIR__ . "/../auth/guards.php";

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

$user = getAuthUser($db);
$userId = (int) $user["id"];

$body = json_decode(file_get_contents("php://input"), true);

if (!isset($body["post_id"]) || !isset($body["content"])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required fields: post_id, content"]);
    exit;
}

$postId  = (int) $body["post_id"];
$content = trim((string) $body["content"]);

if ($postId <= 0 || $content === "") {
    http_response_code(400);
    echo json_encode(["error" => "Invalid post_id, or empty content"]);
    exit;
}

// Verify the post exists and isn't deleted
$postStmt = $db->prepare("SELECT id FROM posts WHERE id = :id AND deleted_at IS NULL LIMIT 1");
$postStmt->execute(["id" => $postId]);
if (!$postStmt->fetch()) {
    http_response_code(404);
    echo json_encode(["error" => "Post not found"]);
    exit;
}

$insert = $db->prepare("INSERT INTO comments (post_id, user_id, content) VALUES (:post_id, :user_id, :content)");
$insert->execute([
    "post_id" => $postId,
    "user_id" => $userId,
    "content" => $content,
]);

http_response_code(201);
echo json_encode(["message" => "Comment posted successfully"]);