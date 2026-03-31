<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../db/db.php";
require_once __DIR__ . "/../auth/guards.php";

// Only admins are permissed to create posts. 
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

$postColumns = [];
$postParams = [];

if (columnExists($db, "posts", "author_id")) {
    $postColumns[] = "author_id";
    $postParams["author_id"] = (int) $user["id"];
}

// Backward compatibility for legacy schemas that still use posts.user_id.
if (columnExists($db, "posts", "user_id")) {
    $postColumns[] = "user_id";
    $postParams["user_id"] = (int) $user["id"];
}

if (count($postColumns) === 0) {
    http_response_code(500);
    echo json_encode(["error" => "Posts table is missing both author_id and user_id columns"]);
    exit;
}

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
try {
    $insert = $db->prepare($insertSql);
    $insert->execute($postParams);

    if ((int) $insert->rowCount() !== 1) {
        throw new RuntimeException("Insert did not affect exactly one row");
    }
} catch (Throwable $error) {
    http_response_code(500);
    echo json_encode([
        "error" => "Failed to create post",
        "details" => $error->getMessage(),
    ]);
    exit;
}

$postIdColumn = columnExists($db, "posts", "id") ? "id" : (columnExists($db, "posts", "post_id") ? "post_id" : null);
$createdPostId = (int) $db->lastInsertId();

if ($postIdColumn !== null && $createdPostId <= 0) {
    $lookupWhere = [];
    $lookupParams = [];

    if (isset($postParams["author_id"])) {
        $lookupWhere[] = "author_id = :lookup_author_id";
        $lookupParams["lookup_author_id"] = (int) $postParams["author_id"];
    }
    if (isset($postParams["user_id"])) {
        $lookupWhere[] = "user_id = :lookup_user_id";
        $lookupParams["lookup_user_id"] = (int) $postParams["user_id"];
    }
    if (isset($postParams["title"])) {
        $lookupWhere[] = "title = :lookup_title";
        $lookupParams["lookup_title"] = (string) $postParams["title"];
    }
    if (isset($postParams["content_json"])) {
        $lookupWhere[] = "content_json = :lookup_content_json";
        $lookupParams["lookup_content_json"] = (string) $postParams["content_json"];
    }
    if (isset($postParams["content"])) {
        $lookupWhere[] = "content = :lookup_content";
        $lookupParams["lookup_content"] = (string) $postParams["content"];
    }

    if (count($lookupWhere) > 0) {
        $fallbackLookupSql = "SELECT {$postIdColumn} FROM posts WHERE " . implode(" AND ", $lookupWhere) . " ORDER BY {$postIdColumn} DESC LIMIT 1";
        $fallbackLookup = $db->prepare($fallbackLookupSql);
        $fallbackLookup->execute($lookupParams);
        $matchedPost = $fallbackLookup->fetch();
        if ($matchedPost && isset($matchedPost[$postIdColumn])) {
            $createdPostId = (int) $matchedPost[$postIdColumn];
        }
    }
}

if ($postIdColumn === null || $createdPostId <= 0) {
    http_response_code(500);
    echo json_encode([
        "error" => "Post insert could not be verified",
        "details" => "Primary key column is unavailable and fallback lookup could not identify the new row",
    ]);
    exit;
}

$verify = $db->prepare("SELECT {$postIdColumn} FROM posts WHERE {$postIdColumn} = :post_id LIMIT 1");
$verify->execute(["post_id" => $createdPostId]);
$created = $verify->fetch();

if (!$created) {
    http_response_code(500);
    echo json_encode([
        "error" => "Post insert verification failed",
        "details" => "No persisted row found for created post id",
    ]);
    exit;
}

echo json_encode([
    "message" => "Successfully created post",
    "post_id" => $createdPostId,
    "required_role" => REQUIRED_POST_ROLE,
]);
