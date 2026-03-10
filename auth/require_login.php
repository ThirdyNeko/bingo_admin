<?php
define('BASE_URL', '/bingo_admin/');
define('SESSION_TIMEOUT', 3600);

// Not logged in
if (empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'auth/login.php');
    exit;
}

// Role protection
if (!in_array($_SESSION['role'], ['admin','gamemaster'])) {
    session_destroy();
    header('Location: ' . BASE_URL . 'auth/login.php');
    exit;
}

// Timeout
if (isset($_SESSION['LAST_ACTIVITY']) &&
    (time() - $_SESSION['LAST_ACTIVITY'] > SESSION_TIMEOUT)) {

    $_SESSION = [];
    session_destroy();

    header('Location: ' . BASE_URL . 'auth/login.php');
    exit;
}

$_SESSION['LAST_ACTIVITY'] = time();