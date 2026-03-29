<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

require_once "../db/db.php";

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

$body = json_decode(file_get_contents("php://input"), true);

if (!isset($body["user_id"]) || !isset($body["post_id"]) || !isset($body["content"])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required fields: user_id, post_id, content"]);
    exit;
}

$userId  = (int) $body["user_id"];
$postId  = (int) $body["post_id"];
$content = trim((string) $body["content"]);

if ($userId <= 0 || $postId <= 0 || $content === "") {
    http_response_code(400);
    echo json_encode(["error" => "Invalid user_id, post_id, or empty content"]);
    exit;
}

// Verify the user exists
$userStmt = $db->prepare("SELECT id FROM users WHERE id = :id LIMIT 1");
$userStmt->execute(["id" => $userId]);
if (!$userStmt->fetch()) {
    http_response_code(404);
    echo json_encode(["error" => "User not found"]);
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