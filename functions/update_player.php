<?php
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['user_id'], $_POST['game_id'])) {
    header("Location: ../manage_games.php");
    exit;
}

$gameId    = (int) $_POST['game_id'];
$userId    = (int) $_POST['user_id'];
$autoMode  = isset($_POST['auto_mode']) ? 1 : 0;
$cardCount = max(1, (int) $_POST['card_count']);

$update = $pdo->prepare("
    UPDATE users
    SET auto_mode = ?, card_count = ?
    WHERE id = ? AND current_game = ?
");
$update->execute([$autoMode, $cardCount, $userId, $gameId]);

header("Location: ../manage_game.php?game_id=" . $gameId);
exit;