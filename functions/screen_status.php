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

// Game settings (started status + draw/reveal settings + drawn numbers)
$stmt = $pdo->prepare("
    SELECT started, draw_mode, draw_interval_seconds, reveal_duration_ms, drawn_numbers
    FROM game
    WHERE id = ?
");
$stmt->execute([$gameId]);
$game = $stmt->fetch();

$started = (int) ($game['started'] ?? 0);
$drawMode = $game['draw_mode'] ?? 'auto';
$drawIntervalSeconds = (int) ($game['draw_interval_seconds'] ?? 5);
$revealDurationMs = (int) ($game['reveal_duration_ms'] ?? 1200);

// How many numbers have been drawn — lets every open screen notice when
// another screen has drawn a number it hasn't displayed yet.
$drawnNumbers = array_map('intval', json_decode($game['drawn_numbers'] ?? '[]', true) ?: []);
$drawnCount = count($drawnNumbers);

// Claimed winners count
$claimedStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM game_winner_queue 
    WHERE game_id = ? AND claimed = 1
");
$claimedStmt->execute([$gameId]);
$claimed = (int) $claimedStmt->fetchColumn();

// Claimed winner names — lets a screen that only learned about a draw
// through polling still show the same winners list as the drawing screen.
$winnersStmt = $pdo->prepare("
    SELECT u.name
    FROM game_winner_queue gwq
    JOIN user_cards uc ON gwq.card_id = uc.id
    JOIN users u ON uc.user_id = u.id
    WHERE gwq.game_id = ? AND gwq.claimed = 1
    ORDER BY gwq.level ASC
");
$winnersStmt->execute([$gameId]);
$winnerNames = $winnersStmt->fetchAll(PDO::FETCH_COLUMN);

echo json_encode([
    'count'                 => $count,
    'started'               => $started,
    'claimed'               => $claimed,
    'draw_mode'             => $drawMode,
    'draw_interval_seconds' => $drawIntervalSeconds,
    'reveal_duration_ms'    => $revealDurationMs,
    'drawn_count'           => $drawnCount,
    'drawn_numbers'         => $drawnNumbers,
    'winner_names'          => $winnerNames
]);