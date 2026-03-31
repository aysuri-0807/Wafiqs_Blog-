<?php

function columnExists(PDO $db, string $table, string $column): bool
{
    $statement = $db->prepare("SHOW COLUMNS FROM `{$table}` LIKE :column_name");
    $statement->execute(["column_name" => $column]);
    return $statement->fetch() !== false;
}

function getUsersIdColumn(PDO $db): string
{
    return columnExists($db, "users", "id") ? "id" : "user_id";
}

function startSessionIfNeeded(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function respondJson(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode($payload);
    exit;
}

function requireAuth(): void
{
    startSessionIfNeeded();
    if (!isset($_SESSION["user_id"])) {
        respondJson(401, ["error" => "Authentication required"]);
    }
}

function getAuthUser(PDO $db): array
{
    requireAuth();

    $userIdColumn = getUsersIdColumn($db);
    $hasRole = columnExists($db, "users", "user_role");

    $select = "{$userIdColumn} AS id, username";
    if (columnExists($db, "users", "email")) {
        $select .= ", email";
    }
    if (columnExists($db, "users", "show_email")) {
        $select .= ", show_email";
    }
    if ($hasRole) {
        $select .= ", user_role";
    }

    $statement = $db->prepare("SELECT {$select} FROM users WHERE {$userIdColumn} = :id LIMIT 1");
    $statement->execute(["id" => (int) $_SESSION["user_id"]]);
    $user = $statement->fetch();

    if (!$user) {
        session_unset();
        session_destroy();
        respondJson(401, ["error" => "Authentication required"]);
    }

    if (!$hasRole) {
        $user["user_role"] = "user";
    }

    return $user;
}

function requireRole(PDO $db, string $requiredRole): array
{
    $user = getAuthUser($db);
    $currentRole = (string) ($user["user_role"] ?? "user");
    $roleRank = [
        "user" => 1,
        "admin" => 2,
    ];
    $requiredRank = $roleRank[$requiredRole] ?? PHP_INT_MAX;
    $currentRank = $roleRank[$currentRole] ?? 0;

    if ($currentRank < $requiredRank) {
        respondJson(403, [
            "error" => "You do not have permission to perform this action",
            "required_role" => $requiredRole,
            "current_role" => $currentRole,
        ]);
    }

    return $user;
}

