<?php
$host = "localhost";
$dbname = "bingo";
$username = "root";
$password = "Bimbim101602";
$charset = "utf8mb4";

$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,

    // Important for high-request apps
    PDO::ATTR_PERSISTENT => true,

    // Prevent long hanging connections
    PDO::ATTR_TIMEOUT => 5
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}