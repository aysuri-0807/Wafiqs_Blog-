<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../db/db.php";
require_once __DIR__ . "/../auth/guards.php";

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

$user = getAuthUser($db);
$userId = (int) $user["id"];
$userRole = (string) ($user["user_role"] ?? "user");

$data = json_decode(file_get_contents("php://input"), true);
$commentId = $data['comment_id'] ?? null;

try {
    if (!is_numeric($commentId) || (int) $commentId <= 0) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid comment_id"]);
        exit;
    }

    $commentId = (int) $commentId;

    $commentStmt = $db->prepare("SELECT id, user_id, deleted_at FROM comments WHERE id = :id LIMIT 1");
    $commentStmt->execute(["id" => $commentId]);
    $comment = $commentStmt->fetch();

    if (!$comment || !empty($comment["deleted_at"])) {
        http_response_code(404);
        echo json_encode(["error" => "Comment not found"]);
        exit;
    }

    $isOwner = (int) $comment["user_id"] === $userId;
    $isAdmin = $userRole === "admin";

    if (!$isOwner && !$isAdmin) {
        http_response_code(403);
        echo json_encode(["error" => "You do not have permission to delete this comment"]);
        exit;
    }

    $deleteStmt = $db->prepare("UPDATE comments SET deleted_at = NOW() WHERE id = :id");
    $deleteStmt->execute(["id" => $commentId]);

    http_response_code(200);
    echo json_encode(["message" => "Comment deleted successfully"]);
} catch (Throwable $error) {
    http_response_code(500);
    echo json_encode(["error" => "Database query failed"]);
}