<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../db/db.php";

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
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

if (!isset($body["comment_id"]) || !isset($body["vote_type"])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required fields: comment_id, vote_type"]);
    exit;
}

$commentId = (int) $body["comment_id"];
$voteType = (string) $body["vote_type"];
$userId = (int) $_SESSION["user_id"];

if ($commentId <= 0 || ($voteType !== "like" && $voteType !== "dislike")) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid comment_id or vote_type"]);
    exit;
}

$commentStatement = $db->prepare("SELECT id, likes, dislikes FROM comments WHERE id = :comment_id LIMIT 1");
$commentStatement->execute(["comment_id" => $commentId]);
$comment = $commentStatement->fetch();

if (!$comment) {
    http_response_code(404);
    echo json_encode(["error" => "Comment not found"]);
    exit;
}

$db->beginTransaction();

try {
    $existingVoteStatement = $db->prepare("SELECT id, vote_type FROM comment_votes WHERE comment_id = :comment_id AND user_id = :user_id LIMIT 1");
    $existingVoteStatement->execute([
        "comment_id" => $commentId,
        "user_id" => $userId,
    ]);
    $existingVote = $existingVoteStatement->fetch();

    $newUserVote = $voteType;

    if (!$existingVote) {
        $insertVote = $db->prepare("INSERT INTO comment_votes (comment_id, user_id, vote_type) VALUES (:comment_id, :user_id, :vote_type)");
        $insertVote->execute([
            "comment_id" => $commentId,
            "user_id" => $userId,
            "vote_type" => $voteType,
        ]);

        if ($voteType === "like") {
            $db->prepare("UPDATE comments SET likes = likes + 1 WHERE id = :comment_id")->execute(["comment_id" => $commentId]);
        } else {
            $db->prepare("UPDATE comments SET dislikes = dislikes + 1 WHERE id = :comment_id")->execute(["comment_id" => $commentId]);
        }
    } elseif ((string) $existingVote["vote_type"] === $voteType) {
        // Toggle off when pressing the same vote again.
        $deleteVote = $db->prepare("DELETE FROM comment_votes WHERE id = :id");
        $deleteVote->execute(["id" => (int) $existingVote["id"]]);

        if ($voteType === "like") {
            $db->prepare("UPDATE comments SET likes = GREATEST(likes - 1, 0) WHERE id = :comment_id")->execute(["comment_id" => $commentId]);
        } else {
            $db->prepare("UPDATE comments SET dislikes = GREATEST(dislikes - 1, 0) WHERE id = :comment_id")->execute(["comment_id" => $commentId]);
        }

        $newUserVote = null;
    } else {
        $updateVote = $db->prepare("UPDATE comment_votes SET vote_type = :vote_type WHERE id = :id");
        $updateVote->execute([
            "vote_type" => $voteType,
            "id" => (int) $existingVote["id"],
        ]);

        if ((string) $existingVote["vote_type"] === "like") {
            $db->prepare("UPDATE comments SET likes = GREATEST(likes - 1, 0), dislikes = dislikes + 1 WHERE id = :comment_id")->execute(["comment_id" => $commentId]);
        } else {
            $db->prepare("UPDATE comments SET dislikes = GREATEST(dislikes - 1, 0), likes = likes + 1 WHERE id = :comment_id")->execute(["comment_id" => $commentId]);
        }
    }

    $latestCommentStatement = $db->prepare("SELECT likes, dislikes FROM comments WHERE id = :comment_id LIMIT 1");
    $latestCommentStatement->execute(["comment_id" => $commentId]);
    $latestComment = $latestCommentStatement->fetch();

    $db->commit();

    echo json_encode([
        "message" => "Vote saved",
        "likes" => (int) $latestComment["likes"],
        "dislikes" => (int) $latestComment["dislikes"],
        "user_vote" => $newUserVote,
    ]);
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    http_response_code(500);
    echo json_encode(["error" => "Could not save vote"]);
}
