<?php

$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "blog_db";

function addColumnIfNotExists($db, $table, $column, $definition)
{
    $result = $db->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if ($result->rowCount() === 0) {
        $db->exec("ALTER TABLE `$table` ADD COLUMN $column $definition");
    }
}

try {
    $db = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $result = $db->query("SHOW TABLES LIKE 'users'");
    $hasUsersTable = $result->rowCount() > 0;

    if (!$hasUsersTable) {
        $seedSql = file_get_contents(__DIR__ . '/../seed.sql');
        $db->exec($seedSql);
    }

    // Users
    addColumnIfNotExists($db, 'users', 'email', 'VARCHAR(255) NULL');
    addColumnIfNotExists($db, 'users', 'show_email', 'BOOLEAN NOT NULL DEFAULT FALSE');
    addColumnIfNotExists($db, 'users', 'profile_picture', 'VARCHAR(255) NULL');
    addColumnIfNotExists($db, 'users', 'updated_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
    addColumnIfNotExists($db, 'users', 'user_role', "VARCHAR(32) NOT NULL DEFAULT 'user'");

    // Posts
    addColumnIfNotExists($db, 'posts', 'author_id', 'INT NOT NULL DEFAULT 1');
    addColumnIfNotExists($db, 'posts', 'likes', 'INT NOT NULL DEFAULT 0');
    addColumnIfNotExists($db, 'posts', 'dislikes', 'INT NOT NULL DEFAULT 0');
    addColumnIfNotExists($db, 'posts', 'share_count', 'INT NOT NULL DEFAULT 0');
    addColumnIfNotExists($db, 'posts', 'updated_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
    addColumnIfNotExists($db, 'posts', 'deleted_at', 'DATETIME NULL');

    // Comments
    addColumnIfNotExists($db, 'comments', 'likes', 'INT NOT NULL DEFAULT 0');
    addColumnIfNotExists($db, 'comments', 'dislikes', 'INT NOT NULL DEFAULT 0');
    addColumnIfNotExists($db, 'comments', 'updated_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
    addColumnIfNotExists($db, 'comments', 'deleted_at', 'DATETIME NULL');

    // Votes
    $db->exec("CREATE TABLE IF NOT EXISTS post_votes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        user_id INT NOT NULL,
        vote_type ENUM('like', 'dislike') NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_post_vote (post_id, user_id)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS comment_votes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        comment_id INT NOT NULL,
        user_id INT NOT NULL,
        vote_type ENUM('like', 'dislike') NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_comment_vote (comment_id, user_id)
    )");
} catch (PDOException $e) {
    die(json_encode(["error" => $e->getMessage()]));
}
