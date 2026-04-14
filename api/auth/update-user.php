<?php

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../db/db.php";
require_once __DIR__ . "/guards.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

$body = json_decode(file_get_contents("php://input"), true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid JSON payload"]);
    exit;
}

$action = $body["action"] ?? "";

if ($action === "update_profile") {
    updateProfile($db, $body);
} elseif ($action === "change_password") {
    changePassword($db, $body);
} else {
    http_response_code(400);
    echo json_encode(["error" => "Invalid action"]);
    exit;
}

function updateProfile(PDO $db, array $body): void
{
    startSessionIfNeeded();
    
    if (!isset($_SESSION["user_id"])) {
        http_response_code(401);
        echo json_encode(["error" => "Authentication required"]);
        exit;
    }

    $username = trim((string) ($body["username"] ?? ""));
    $email = isset($body["email"]) ? trim((string) $body["email"]) : "";
    $showEmail = !empty($body["show_email"]) ? 1 : 0;

    if ($username === "") {
        http_response_code(400);
        echo json_encode(["error" => "Username is required"]);
        exit;
    }

    if (strlen($username) < 3 || strlen($username) > 50) {
        http_response_code(400);
        echo json_encode(["error" => "Username must be between 3 and 50 characters"]);
        exit;
    }

    if ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(["error" => "Email is not valid"]);
        exit;
    }

    $statement = $db->prepare("SELECT COUNT(*) FROM users WHERE username = :username AND id != :id");
    $statement->execute([
        "username" => $username,
        "id" => (int) $_SESSION["user_id"]
    ]);
    if ((int) $statement->fetchColumn() > 0) {
        http_response_code(409);
        echo json_encode(["error" => "Username is already taken"]);
        exit;
    }

    $update = $db->prepare(
        "UPDATE users SET username = :username, email = :email, show_email = :show_email WHERE id = :id"
    );
    $update->execute([
        "username" => $username,
        "email" => $email !== "" ? $email : null,
        "show_email" => $showEmail,
        "id" => (int) $_SESSION["user_id"]
    ]);

    $_SESSION["username"] = $username;

    echo json_encode([
        "message" => "Profile updated successfully",
        "user" => [
            "id" => (int) $_SESSION["user_id"],
            "username" => $username,
            "email" => $email !== "" ? $email : null,
            "show_email" => (bool) $showEmail,
        ],
    ]);
}

function changePassword(PDO $db, array $body): void
{
    startSessionIfNeeded();
    
    if (!isset($_SESSION["user_id"])) {
        http_response_code(401);
        echo json_encode(["error" => "Authentication required"]);
        exit;
    }

    $currentPassword = (string) ($body["current_password"] ?? "");
    $newPassword = (string) ($body["new_password"] ?? "");
    $confirmPassword = (string) ($body["confirm_password"] ?? "");

    if ($currentPassword === "" || $newPassword === "" || $confirmPassword === "") {
        http_response_code(400);
        echo json_encode(["error" => "All password fields are required"]);
        exit;
    }

    if (strlen($newPassword) < 6) {
        http_response_code(400);
        echo json_encode(["error" => "New password must be at least 6 characters"]);
        exit;
    }

    if ($newPassword !== $confirmPassword) {
        http_response_code(400);
        echo json_encode(["error" => "New passwords do not match"]);
        exit;
    }

    $statement = $db->prepare("SELECT password_hash FROM users WHERE id = :id LIMIT 1");
    $statement->execute(["id" => (int) $_SESSION["user_id"]]);
    $user = $statement->fetch();

    if (!$user) {
        http_response_code(401);
        echo json_encode(["error" => "User not found"]);
        exit;
    }

    if (!password_verify($currentPassword, $user["password_hash"])) {
        http_response_code(401);
        echo json_encode(["error" => "Current password is incorrect"]);
        exit;
    }

    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    $update = $db->prepare("UPDATE users SET password_hash = :password_hash WHERE id = :id");
    $update->execute([
        "password_hash" => $hashedPassword,
        "id" => (int) $_SESSION["user_id"]
    ]);

    echo json_encode([
        "message" => "Password changed successfully"
    ]);
}
