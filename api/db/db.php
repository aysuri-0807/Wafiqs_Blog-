<?php

$servername = "127.0.0.1";
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
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
