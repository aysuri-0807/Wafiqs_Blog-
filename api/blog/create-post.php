<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

require_once "../db/db.php";
require_once "../auth/guards.php";

// Change this later when role policy is finalized.
const REQUIRED_POST_ROLE = "admin";

function columnExists(PDO $db, string $table, string $column): bool
{
    $statement = $db->prepare("SHOW COLUMNS FROM `{$table}` LIKE :column_name");
    $statement->execute(["column_name" => $column]);
    return $statement->fetch() !== false;
}

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

if (!isset($body["title"]) || !isset($body["content_json"])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing one of required fields: title, content_json"]);
    exit;
}

$title = trim((string) $body["title"]);
$contentJsonInput = $body["content_json"];

if ($title === "") {
    http_response_code(400);
    echo json_encode(["error" => "Invalid title"]);
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

$user = requireRole($db, REQUIRED_POST_ROLE);

$contentJson = json_encode($contentJsonInput, JSON_UNESCAPED_UNICODE);

$postColumns = ["author_id"];
$postParams = [
    "author_id" => (int) $user["id"],
];

if (columnExists($db, "posts", "title")) {
    $postColumns[] = "title";
    $postParams["title"] = $title;
}

if (columnExists($db, "posts", "author")) {
    $postColumns[] = "author";
    $postParams["author"] = (string) $user["username"];
}

if (columnExists($db, "posts", "content_json")) {
    $postColumns[] = "content_json";
    $postParams["content_json"] = $contentJson;
} elseif (columnExists($db, "posts", "content")) {
    $postColumns[] = "content";
    $postParams["content"] = json_encode([
        "title" => $title,
        "text" => $contentJsonInput["blocks"][0]["data"]["text"] ?? "",
        "blocks" => $contentJsonInput["blocks"],
        "comments" => $contentJsonInput["comments"],
    ], JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Posts table is missing both content_json and content columns"]);
    exit;
}

$placeholders = array_map(static fn(string $column): string => ":{$column}", $postColumns);
$insertSql = "INSERT INTO posts (" . implode(", ", $postColumns) . ") VALUES (" . implode(", ", $placeholders) . ")";
$insert = $db->prepare($insertSql);
$insert->execute($postParams);

echo json_encode([
    "message" => "Successfully created post",
    "required_role" => REQUIRED_POST_ROLE,
]);
