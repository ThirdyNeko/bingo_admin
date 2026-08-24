<?php
require_once '../config/db.php';

header('Content-Type: application/json');

$sessionId = $_GET['session_id'] ?? '';
if ($sessionId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'session_id is required']);
    exit;
}

/* ==============================
   GET GAME ROUNDS FOR SESSION
============================== */
$gamesStmt = $pdo->prepare("
    SELECT id, game_code, drawn_numbers
    FROM game
    WHERE session_id = ?
    ORDER BY id ASC
");
$gamesStmt->execute([$sessionId]);
$games = $gamesStmt->fetchAll();

$result = [];

foreach ($games as $game) {
    $gameId = $game['id'];

    /* ==============================
       GET WINNERS (same source as winner.php)
    ============================== */
    $winnersStmt = $pdo->prepare("
        SELECT u.name, gwq.level
        FROM game_winner_queue gwq
        JOIN user_cards uc ON gwq.card_id = uc.id
        JOIN users u ON uc.user_id = u.id
        WHERE gwq.game_id = ? AND gwq.claimed = 1
        ORDER BY gwq.level ASC
    ");
    $winnersStmt->execute([$gameId]);
    $winners = $winnersStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($winners)) {
        // No claimed winners for this round — skip it
        continue;
    }

    /* ==============================
       GET PRIZE
    ============================== */
    $prizeStmt = $pdo->prepare("
        SELECT TOP 1 name, description, picture
        FROM game_prize
        WHERE game_id = ?
        ORDER BY id ASC
    ");
    $prizeStmt->execute([$gameId]);
    $prize = $prizeStmt->fetch();

    $prizePicture = null;
    if ($prize && !empty($prize['picture'])) {
        $prizePicture = base64_encode($prize['picture']);
    }

    $totalDrawn = count(json_decode($game['drawn_numbers'] ?? '[]', true));

    $result[] = [
        'game_id'     => $gameId,
        'game_code'   => $game['game_code'],
        'total_drawn' => $totalDrawn,
        'prize'       => $prize ? [
            'name'        => $prize['name'],
            'description' => $prize['description'],
            'picture'     => $prizePicture,
        ] : null,
        'winners'     => array_map(function ($w) {
            return [
                'name'  => $w['name'],
                'level' => $w['level'],
            ];
        }, $winners),
    ];
}

echo json_encode($result);