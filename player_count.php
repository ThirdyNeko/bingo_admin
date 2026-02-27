<?php
require_once 'config/db.php';

if (!isset($_GET['game_id'])) {
    exit;
}

$gameId = (int) $_GET['game_id'];

$stmt = $pdo->prepare("
    SELECT COUNT(*) as total
    FROM users
    WHERE current_game = ?
");
$stmt->execute([$gameId]);
$count = $stmt->fetch();

echo $count['total'];