<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Panel - Bingo</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <script src="js/bootstrap.bundle.min.js"></script>

    <style>
        body { background: #f4f6f9; }
        .sidebar {
            min-height: 100vh;
            background: #1a1a1a;
            color: white;
        }
        .sidebar a {
            color: #bbb;
            text-decoration: none;
            display: block;
            padding: 12px 15px;
        }
        .sidebar a:hover {
            background: #333;
            color: #fff;
        }
        .active-link {
            background: #0d6efd;
            color: white !important;
        }
    </style>
</head>
<body>
<div class="container-fluid">
<div class="row">