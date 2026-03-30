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

function columnExists(PDO $db, string $table, string $column): bool
{
    $statement = $db->prepare("SHOW COLUMNS FROM `{$table}` LIKE :column_name");
    $statement->execute(["column_name" => $column]);
    return $statement->fetch() !== false;
}

$body = json_decode(file_get_contents("php://input"), true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid JSON payload"]);
    exit;
}

$identifier = trim((string) ($body["identifier"] ?? ""));
$username = trim((string) ($body["username"] ?? $identifier));
$password = (string) ($body["password"] ?? "");

if ($username === "" || $password === "") {
    http_response_code(400);
    echo json_encode(["error" => "Username or password is required"]);
    exit;
}

// Support both newer schema (id) and older schema (user_id).
$userIdColumn = "id";
$idColumnStatement = $db->query("SHOW COLUMNS FROM users LIKE 'id'");
if ($idColumnStatement->fetch() === false) {
    $userIdColumn = "user_id";
}

$hasEmailColumn = columnExists($db, "users", "email");
$whereClause = "username = :identifier";
if ($hasEmailColumn) {
    $whereClause .= " OR email = :identifier";
}

$statement = $db->prepare("SELECT {$userIdColumn} AS id, username, password_hash, email, show_email FROM users WHERE {$whereClause} LIMIT 1");
$statement->execute(["identifier" => $username]);
$user = $statement->fetch();

if (!$user || !password_verify($password, (string) $user["password_hash"])) {
    http_response_code(401);
    echo json_encode(["error" => "Invalid username or password"]);
    exit;
}

$_SESSION["user_id"] = (int) $user["id"];
$_SESSION["username"] = (string) $user["username"];

echo json_encode([
    "message" => "Login successful",
    "user" => [
        "id" => (int) $user["id"],
        "username" => (string) $user["username"],
        "email" => $user["email"] ?: null,
        "show_email" => (bool) $user["show_email"],
    ],
]);