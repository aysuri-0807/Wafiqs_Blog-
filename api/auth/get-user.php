<?php

require_once "../db/db.php";
require_once "./guards.php";

header("Content-Type: application/json; charset=UTF-8");

startSessionIfNeeded();

if (!isset($_SESSION["user_id"])) {
    echo json_encode([
        "authenticated" => false,
        "user" => null,
    ]);
    exit;
}

$user = getAuthUser($db);

echo json_encode([
    "authenticated" => true,
    "user" => [
        "id" => (int) $user["id"],
        "username" => (string) $user["username"],
        "email" => $user["email"] ?: null,
        "show_email" => (bool) $user["show_email"],
        "role" => (string) $user["user_role"],
    ],
]);