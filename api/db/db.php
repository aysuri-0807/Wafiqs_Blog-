<?php

$servername = "127.0.0.1";
$username   = "root";
$password   = "";
$dbname     = "blog_db";

// First connect without database to check if it exists
$conn = new mysqli($servername, $username, $password);

if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// Check if database exists, create if not
$result = $conn->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$dbname'");
if ($result->num_rows === 0) {
  if ($conn->query("CREATE DATABASE $dbname") !== TRUE) {
    die("Error creating database: " . $conn->error);
  }
}

$conn->close();
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}
