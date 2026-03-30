<?php

$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "blog_db";

try {
    $db = new PDO("mysql:host=$servername", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Check if database exists, create if not
    $result = $db->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$dbname'");
    $dbExists = $result->rowCount() > 0;

    if (!$dbExists) {
        $db->exec("CREATE DATABASE $dbname");
    }

    // Reconnect with the database
    $db = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Seed database only if tables don't exist
    $result = $db->query("SHOW TABLES LIKE 'users'");
    $hasUsersTable = $result->rowCount() > 0;

    if (!$hasUsersTable) {
        $seedSql = file_get_contents(__DIR__ . '/../seed.sql');
        $db->exec($seedSql);
    }

    // Keep older local databases compatible with newer schema requirements.
    $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS email VARCHAR(255) NULL");
    $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS show_email BOOLEAN NOT NULL DEFAULT FALSE");
    $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_picture VARCHAR(255) NULL");
    $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS user_role VARCHAR(32) NOT NULL DEFAULT 'user'");

    $db->exec("ALTER TABLE posts ADD COLUMN IF NOT EXISTS author_id INT NOT NULL DEFAULT 1");
    $db->exec("ALTER TABLE posts ADD COLUMN IF NOT EXISTS likes INT NOT NULL DEFAULT 0");
    $db->exec("ALTER TABLE posts ADD COLUMN IF NOT EXISTS dislikes INT NOT NULL DEFAULT 0");
    $db->exec("ALTER TABLE posts ADD COLUMN IF NOT EXISTS share_count INT NOT NULL DEFAULT 0");
    $db->exec("ALTER TABLE posts ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    $db->exec("ALTER TABLE posts ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL");

    $db->exec("ALTER TABLE comments ADD COLUMN IF NOT EXISTS likes INT NOT NULL DEFAULT 0");
    $db->exec("ALTER TABLE comments ADD COLUMN IF NOT EXISTS dislikes INT NOT NULL DEFAULT 0");
    $db->exec("ALTER TABLE comments ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");

    $db->exec("CREATE TABLE IF NOT EXISTS post_votes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        user_id INT NOT NULL,
        vote_type ENUM ('like', 'dislike') NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_post_vote (post_id, user_id)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS comment_votes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        comment_id INT NOT NULL,
        user_id INT NOT NULL,
        vote_type ENUM ('like', 'dislike') NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_comment_vote (comment_id, user_id)
    )");
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
