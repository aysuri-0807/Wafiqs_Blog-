<?php
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../db/db.php";

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

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

$username = trim((string) ($body["username"] ?? ""));
$password = (string) ($body["password"] ?? "");
$email = trim((string) ($body["email"] ?? ""));
$showEmail = !empty($body["show_email"]) ? 1 : 0;

if ($username === "" || $password === "") {
    http_response_code(400);
    echo json_encode(["error" => "Username and password are required"]);
    exit;
}

if (strlen($username) < 3 || strlen($username) > 50) {
    http_response_code(400);
    echo json_encode(["error" => "Username must be between 3 and 50 characters"]);
    exit;
}

if (strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(["error" => "Password must be at least 6 characters"]);
    exit;
}

if ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["error" => "Email is not valid"]);
    exit;
}

$statement = $db->prepare("SELECT 1 FROM users WHERE username = :username LIMIT 1");
$statement->execute(["username" => $username]);
if ((bool) $statement->fetchColumn()) {
    http_response_code(409);
    echo json_encode(["error" => "Username is already taken"]);
    exit;
}

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
$insert = $db->prepare(
    "INSERT INTO users (username, password_hash, email, show_email) VALUES (:username, :password_hash, :email, :show_email)"
);
$insert->execute([
    "username" => $username,
    "password_hash" => $hashedPassword,
    "email" => $email !== "" ? $email : null,
    "show_email" => $showEmail,
]);

$userId = (int) $db->lastInsertId();
$_SESSION["user_id"] = $userId;
$_SESSION["username"] = $username;

echo json_encode([
    "message" => "Signup successful",
    "user" => [
        "id" => $userId,
        "username" => $username,
        "email" => $email !== "" ? $email : null,
        "show_email" => (bool) $showEmail,
    ],
]);
