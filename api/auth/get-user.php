<?php

require_once "../db/db.php";

header("Content-Type: application/json; charset=UTF-8");

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION["user_id"])) {
    echo json_encode([
        "authenticated" => false,
        "user" => null,
    ]);
    exit;
}

// Support both newer schema (id) and older schema (user_id).
$userIdColumn = "id";
$idColumnStatement = $db->query("SHOW COLUMNS FROM users LIKE 'id'");
if ($idColumnStatement->fetch() === false) {
    $userIdColumn = "user_id";
}

$statement = $db->prepare("SELECT {$userIdColumn} AS id, username, email, show_email FROM users WHERE {$userIdColumn} = :id LIMIT 1");
$statement->execute(["id" => (int) $_SESSION["user_id"]]);
$user = $statement->fetch();

if (!$user) {
    session_unset();
    session_destroy();

    echo json_encode([
        "authenticated" => false,
        "user" => null,
    ]);
    exit;
}

echo json_encode([
    "authenticated" => true,
    "user" => [
        "id" => (int) $user["id"],
        "username" => (string) $user["username"],
        "email" => $user["email"] ?: null,
        "show_email" => (bool) $user["show_email"],
    ],
]);