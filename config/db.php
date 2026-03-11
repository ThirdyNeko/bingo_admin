<?php

date_default_timezone_set('Asia/Manila');

/* ===============================
   ERROR LOGGER
================================ */
function errorHandler($errno, $errstr, $errfile, $errline)
{
    $logMessage = "[" . date("Y-m-d H:i:s") . "] Error [$errno]: $errstr in $errfile on line $errline" . PHP_EOL;
    file_put_contents(__DIR__ . '/error_log.txt', $logMessage, FILE_APPEND);
}

set_error_handler("errorHandler");


/* ===============================
   DATABASE CONFIG
================================ */
$servername = "192.168.101.68";
$database   = "BINGO";
$username   = "sa";
$password   = "SB1Admin";


/* ===============================
   PDO OPTIONS
================================ */
$options = [
    PDO::SQLSRV_ATTR_ENCODING => PDO::SQLSRV_ENCODING_UTF8,
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_PERSISTENT => true,
    PDO::ATTR_TIMEOUT => 5
];


/* ===============================
   CONNECT TO SQL SERVER
================================ */
try {

    $pdo = new PDO(
        "sqlsrv:Server=$servername;Database=$database;TrustServerCertificate=true",
        $username,
        $password,
        $options
    );

} catch (PDOException $e) {

    errorHandler(E_WARNING, $e->getMessage(), $e->getFile(), $e->getLine());
    die("Database connection failed.");

}


/* ===============================
   GLOBAL ACCESS FUNCTION
================================ */
function qa_db(): PDO
{
    global $pdo;
    return $pdo;
}