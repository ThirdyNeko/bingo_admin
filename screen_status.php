<?php
require_once 'config/db.php';

if (!isset($_GET['game_id'])) {
    exit;
}

$gameId = (int) $_GET['game_id'];

// Get player count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE current_game = ?");
$countStmt->execute([$gameId]);
$playerCount = (int)$countStmt->fetchColumn();

// Get game started status
$stmt = $pdo->prepare("SELECT started FROM game WHERE id = ?");
$stmt->execute([$gameId]);
$started = (int)$stmt->fetchColumn();

echo json_encode([
    'count' => $playerCount,
    'started' => $started
]);