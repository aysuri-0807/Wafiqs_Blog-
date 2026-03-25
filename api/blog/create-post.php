<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

require_once "../db/db.php";

// Change this later when role policy is finalized.
const REQUIRED_POST_ROLE = "admin";

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

if (!isset($body["user_id"]) || !isset($body["title"]) || !isset($body["content_json"])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing one of required fields: user_id, title, content_json"]);
    exit;
}

$userId = (int) $body["user_id"];
$title = trim((string) $body["title"]);
$contentJsonInput = $body["content_json"];

if ($userId <= 0 || $title === "") {
    http_response_code(400);
    echo json_encode(["error" => "Invalid user_id or title"]);
    exit;
}

if (!is_array($contentJsonInput) || !isset($contentJsonInput["blocks"]) || !is_array($contentJsonInput["blocks"])) {
    http_response_code(400);
    echo json_encode(["error" => "content_json must include a blocks array"]);
    exit;
}

if (!isset($contentJsonInput["comments"]) || !is_array($contentJsonInput["comments"])) {
    $contentJsonInput["comments"] = [];
}

$userStatement = $db->prepare("SELECT user_id, username, user_role FROM users WHERE user_id = :user_id LIMIT 1");
$userStatement->execute(["user_id" => $userId]);
$user = $userStatement->fetch();

if (!$user) {
    http_response_code(404);
    echo json_encode(["error" => "User not found"]);
    exit;
}

if ((string) $user["user_role"] !== REQUIRED_POST_ROLE) {
    http_response_code(403);
    echo json_encode([
        "error" => "You do not have permission to create posts",
        "required_role" => REQUIRED_POST_ROLE,
        "current_role" => (string) $user["user_role"],
    ]);
    exit;
}

$contentJson = json_encode($contentJsonInput, JSON_UNESCAPED_UNICODE);

$insert = $db->prepare("INSERT INTO posts (author_id, title, author, content_json) VALUES (:author_id, :title, :author, :content_json)");
$insert->execute([
    "author_id" => (int) $user["user_id"],
    "title" => $title,
    "author" => (string) $user["username"],
    "content_json" => $contentJson,
]);

echo json_encode([
    "message" => "Successfully created post",
    "required_role" => REQUIRED_POST_ROLE,
]);
