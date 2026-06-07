<?php

// 1. Define secure credentials (use env vars in Docker, fallback for local dev)
define('DB_HOST', getenv('MYSQL_HOST') ?: 'localhost');
define('DB_USER', getenv('MYSQL_USER') ?: 'root');
define('DB_PASS', getenv('MYSQL_PASSWORD') ?: 'root');
define('DB_NAME', getenv('MYSQL_DATABASE') ?: 'itec106');

try {
    // 2. Build the connection string
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    
    // 3. Set the absolute strictest security rules
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Kill script on SQL errors
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Return clean arrays
        PDO::ATTR_EMULATE_PREPARES   => false,                  // Force true prepared statements
    ];

    // 4. Create the global $pdo object
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

} catch (PDOException $e) {
    // Secure failure state: Log it, but do NOT echo $e->getMessage() to the browser
    error_log("Database Connection Error: " . $e->getMessage());
    die("Systems Error: Unable to connect to the secure data layer.");
}