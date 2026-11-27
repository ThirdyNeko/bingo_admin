<?php
if (session_status() === PHP_SESSION_NONE) {
    session_name('Bingo');
    session_start();
}

require_once 'auth/require_login.php';

?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Panel - Bingo</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="bootstrap-icons/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="css/admin_layout.css" rel="stylesheet">
    <?php foreach (($pageStyles ?? []) as $css): ?>
    <link href="<?= htmlspecialchars($css) ?>" rel="stylesheet">
    <?php endforeach; ?>
    <script src="js/bootstrap.bundle.min.js"></script>
</head>
<body class="<?= htmlspecialchars($bodyClass ?? '') ?>">
<div class="container-fluid">
<div class="row">