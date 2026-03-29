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

if (!isset($body["post_id"])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing post_id"]);
    exit;
}

$postId = (int) $body["post_id"];
if ($postId <= 0) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid post_id"]);
    exit;
}

$commandCheckPost = "SELECT `id` FROM `posts` WHERE `id` = :id AND `deleted_at` IS NULL LIMIT 1";
$postStmt = $db->prepare($commandCheckPost);
$argsCheckPost = ["id" => $postId];
$success = $postStmt->execute($argsCheckPost);

if (!$success) {
    http_response_code(500);
    echo json_encode(["error" => "Database query failed"]);
    exit;
} else if (!$postStmt->fetch()) {
    http_response_code(404);
    echo json_encode(["error" => "Post not found or already deleted."]);
    exit;
}

$commandDeletePost = "UPDATE `posts` SET `deleted_at` = NOW() WHERE id = :id";
$deleteStmt = $db->prepare($commandDeletePost);
$argsDeletePost = ["id" => $postId];
$success = $deleteStmt->execute($argsDeletePost);
if (!$success) {
    http_response_code(500);
    echo json_encode(["error" => "Database query failed"]);
    exit;
}

http_response_code(200);
echo json_encode(["message" => "Post deleted successfully"]);