<?php
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_GET['game_id'])) {
    echo json_encode([]);
    exit;
}

$gameId = (int) $_GET['game_id'];

// Player count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE current_game = ?");
$countStmt->execute([$gameId]);
$count = (int) $countStmt->fetchColumn();

// Game settings (started status + draw/reveal settings)
$stmt = $pdo->prepare("
    SELECT started, draw_mode, draw_interval_seconds, reveal_duration_ms
    FROM game
    WHERE id = ?
");
$stmt->execute([$gameId]);
$game = $stmt->fetch();

$started = (int) ($game['started'] ?? 0);
$drawMode = $game['draw_mode'] ?? 'auto';
$drawIntervalSeconds = (int) ($game['draw_interval_seconds'] ?? 5);
$revealDurationMs = (int) ($game['reveal_duration_ms'] ?? 1200);

// Claimed winners count
$claimedStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM game_winner_queue 
    WHERE game_id = ? AND claimed = 1
");
$claimedStmt->execute([$gameId]);
$claimed = (int) $claimedStmt->fetchColumn();

echo json_encode([
    'count'                 => $count,
    'started'               => $started,
    'claimed'               => $claimed,
    'draw_mode'             => $drawMode,
    'draw_interval_seconds' => $drawIntervalSeconds,
    'reveal_duration_ms'    => $revealDurationMs
]);