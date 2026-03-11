<?php
// Router for PHP built-in server
// If the requested path doesn't match a file, serve index.php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($uri === '/' || $uri === '') {
    require __DIR__ . '/index.php';
    return true;
}

// Let PHP serve static files if they exist
if (file_exists(__DIR__ . $uri)) {
    return false;
}

require __DIR__ . '/index.php';
return true;
