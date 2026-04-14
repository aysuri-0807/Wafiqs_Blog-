<?php

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../db/db.php";
require_once __DIR__ . "/guards.php";

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

$username = trim((string) ($_GET["username"] ?? ""));

if ($username === "") {
    http_response_code(400);
    echo json_encode(["error" => "Username parameter is required"]);
    exit;
}

$userIdColumn = columnExists($db, "users", "id") ? "id" : "user_id";
$hasEmail = columnExists($db, "users", "email");
$hasShowEmail = columnExists($db, "users", "show_email");

$select = "{$userIdColumn} AS id, username";
if ($hasEmail) {
    $select .= ", email";
}
if ($hasShowEmail) {
    $select .= ", show_email";
}

// Fetch user profile (public information only)
$statement = $db->prepare("SELECT {$select} FROM users WHERE username = :username LIMIT 1");
$statement->execute(["username" => $username]);
$user = $statement->fetch();

if (!$user) {
    http_response_code(404);
    echo json_encode(["error" => "User not found"]);
    exit;
}

$showEmail = $hasShowEmail ? (bool) $user["show_email"] : false;
$email = null;
if ($hasEmail && $showEmail) {
    $email = $user["email"] ?: null;
}

echo json_encode([
    "user" => [
        "id" => (int) $user["id"],
        "username" => (string) $user["username"],
        "email" => $email,
    ],
]);
