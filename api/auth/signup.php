<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once "../db/db.php";

$body = json_decode(file_get_contents("php://input"), true);

if (!isset($body["username"]) || !isset($body["password"])) {
    echo json_encode(['error' => 'Did not enter username or password']);
    exit;
}

$username = $body['username'];
$password = $body['password'];

$statement = $db->prepare("SELECT 1 FROM users WHERE username = :username");
$statement->execute(['username' => $username]);

if ($statement->rowCount() > 0) {
    echo json_encode(['error' => 'User already exists']);
    exit;
}

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
$insert = $db->prepare("INSERT INTO users (username, password_hash) VALUES (:username, :password_hash)");
$insert->execute([
    'username' => $username,
    'password_hash' => $hashedPassword,
]);

echo json_encode(['message' => 'Successfully signed up']);
