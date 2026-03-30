<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../db/db.php";

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

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

if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(["error" => "You must be logged in to vote"]);
    exit;
}

$body = json_decode(file_get_contents("php://input"), true);

if (!isset($body["post_id"]) || !isset($body["vote_type"])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required fields: post_id, vote_type"]);
    exit;
}

$postId = (int) $body["post_id"];
$voteType = (string) $body["vote_type"];
$userId = (int) $_SESSION["user_id"];

if ($postId <= 0 || ($voteType !== "like" && $voteType !== "dislike")) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid post_id or vote_type"]);
    exit;
}

$postIdColumn = columnExists($db, "posts", "post_id") ? "post_id" : "id";
$hasDeletedAt = columnExists($db, "posts", "deleted_at");
$postCheckSql = "SELECT {$postIdColumn} FROM posts WHERE {$postIdColumn} = :post_id";
if ($hasDeletedAt) {
    $postCheckSql .= " AND deleted_at IS NULL";
}
$postCheckSql .= " LIMIT 1";

$postStatement = $db->prepare($postCheckSql);
$postStatement->execute(["post_id" => $postId]);
if (!$postStatement->fetch()) {
    http_response_code(404);
    echo json_encode(["error" => "Post not found"]);
    exit;
}

$db->beginTransaction();

try {
    $existingVoteStatement = $db->prepare("SELECT id, vote_type FROM post_votes WHERE post_id = :post_id AND user_id = :user_id LIMIT 1");
    $existingVoteStatement->execute([
        "post_id" => $postId,
        "user_id" => $userId,
    ]);
    $existingVote = $existingVoteStatement->fetch();

    $newUserVote = $voteType;

    if (!$existingVote) {
        $insertVote = $db->prepare("INSERT INTO post_votes (post_id, user_id, vote_type) VALUES (:post_id, :user_id, :vote_type)");
        $insertVote->execute([
            "post_id" => $postId,
            "user_id" => $userId,
            "vote_type" => $voteType,
        ]);

        if ($voteType === "like") {
            $db->prepare("UPDATE posts SET likes = likes + 1 WHERE {$postIdColumn} = :post_id")->execute(["post_id" => $postId]);
        } else {
            $db->prepare("UPDATE posts SET dislikes = dislikes + 1 WHERE {$postIdColumn} = :post_id")->execute(["post_id" => $postId]);
        }
    } elseif ((string) $existingVote["vote_type"] === $voteType) {
        $deleteVote = $db->prepare("DELETE FROM post_votes WHERE id = :id");
        $deleteVote->execute(["id" => (int) $existingVote["id"]]);

        if ($voteType === "like") {
            $db->prepare("UPDATE posts SET likes = GREATEST(likes - 1, 0) WHERE {$postIdColumn} = :post_id")->execute(["post_id" => $postId]);
        } else {
            $db->prepare("UPDATE posts SET dislikes = GREATEST(dislikes - 1, 0) WHERE {$postIdColumn} = :post_id")->execute(["post_id" => $postId]);
        }

        $newUserVote = null;
    } else {
        $updateVote = $db->prepare("UPDATE post_votes SET vote_type = :vote_type WHERE id = :id");
        $updateVote->execute([
            "vote_type" => $voteType,
            "id" => (int) $existingVote["id"],
        ]);

        if ((string) $existingVote["vote_type"] === "like") {
            $db->prepare("UPDATE posts SET likes = GREATEST(likes - 1, 0), dislikes = dislikes + 1 WHERE {$postIdColumn} = :post_id")->execute(["post_id" => $postId]);
        } else {
            $db->prepare("UPDATE posts SET dislikes = GREATEST(dislikes - 1, 0), likes = likes + 1 WHERE {$postIdColumn} = :post_id")->execute(["post_id" => $postId]);
        }
    }

    $latestPostStatement = $db->prepare("SELECT likes, dislikes FROM posts WHERE {$postIdColumn} = :post_id LIMIT 1");
    $latestPostStatement->execute(["post_id" => $postId]);
    $latestPost = $latestPostStatement->fetch();

    $db->commit();

    echo json_encode([
        "message" => "Vote saved",
        "likes" => (int) $latestPost["likes"],
        "dislikes" => (int) $latestPost["dislikes"],
        "user_vote" => $newUserVote,
    ]);
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    http_response_code(500);
    echo json_encode(["error" => "Could not save vote"]);
}
