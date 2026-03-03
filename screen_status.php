<?php
require_once 'config/db.php';

if (!isset($_GET['game_id'])) {
    echo json_encode([]);
    exit;
}

$gameId = (int) $_GET['game_id'];

// Player count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE current_game = ?");
$countStmt->execute([$gameId]);
$count = (int) $countStmt->fetchColumn();

// Started status
$stmt = $pdo->prepare("SELECT started FROM game WHERE id = ?");
$stmt->execute([$gameId]);
$started = (int) $stmt->fetchColumn();

// Claimed winners count
$claimedStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM game_winner_queue 
    WHERE game_id = ? AND claimed = 1
");
$claimedStmt->execute([$gameId]);
$claimed = (int) $claimedStmt->fetchColumn();

echo json_encode([
    'count'   => $count,
    'started' => $started,
    'claimed' => $claimed
]);