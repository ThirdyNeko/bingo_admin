<?php
header('Content-Type: application/json');
require_once '../config/db.php';

if (!isset($_POST['game_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Game not found.']);
    exit;
}

$gameId = (int) $_POST['game_id'];

$stmt = $pdo->prepare("SELECT * FROM game WHERE id = ?");
$stmt->execute([$gameId]);
$game = $stmt->fetch();

if (!$game) {
    http_response_code(404);
    echo json_encode(['error' => 'Game does not exist.']);
    exit;
}

$totalWinners = (int) $game['winners'];

$pattern = json_decode($game['pattern'], true);
$drawnNumbers = array_map('intval', json_decode($game['drawn_numbers'] ?? '[]', true));
$letters = ['B','I','N','G','O'];

$limit = (int) $totalWinners;

$queueStmt = $pdo->prepare("
    SELECT TOP $limit *
    FROM game_winner_queue
    WHERE game_id = ? AND claimed = 0
    ORDER BY level ASC
");
$queueStmt->execute([$gameId]);
$queuedWinners = $queueStmt->fetchAll();

if (!$queuedWinners) {
    http_response_code(422);
    echo json_encode(['error' => 'No queued winner found.']);
    exit;
}

$allNeeded = [];
$sharedNumber = null;

foreach ($queuedWinners as $winner) {

    $cardStmt = $pdo->prepare("
        SELECT card_data, shared_number
        FROM user_cards 
        WHERE id = ?
    ");
    $cardStmt->execute([$winner['card_id']]);
    $rowData = $cardStmt->fetch(PDO::FETCH_ASSOC);
    $cardData = json_decode($rowData['card_data'], true);
    $sharedNumber = (int) $rowData['shared_number'];

    $neededNumbers = [];

    foreach ($pattern as $r => $cols) {
        foreach ($cols as $c => $val) {
            if ($val == 1) {
                $num = $cardData[$letters[$c]][$r] ?? null;

                if ($num !== null && $num !== "FREE" && !in_array($num, $drawnNumbers)) {
                    if ($num != $sharedNumber) {
                        $neededNumbers[] = $num;
                    }
                }
            }
        }
    }

    $allNeeded[] = $neededNumbers;
}

$drawPool = !empty($allNeeded)
    ? array_unique(array_merge(...$allNeeded))
    : [];

$allAvailableNumbers = array_values(array_diff(range(1,75), $drawnNumbers));

/* SMART DRAW ENGINE */

$winnerNumbers = array_values(array_diff($drawPool, $drawnNumbers));
$neutralPool = array_values(array_diff($allAvailableNumbers, $drawPool));
$dangerPool = [];
$blockedNumbers = [];

$cardsStmt = $pdo->prepare("
    SELECT id, card_data
    FROM user_cards
    WHERE game_id = ?
");
$cardsStmt->execute([$gameId]);
$cards = $cardsStmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($cards as $card) {

    $cardData = json_decode($card['card_data'], true);
    $missing = [];

    foreach ($pattern as $r => $cols) {
        foreach ($cols as $c => $val) {
            if ($val == 1) {
                $num = $cardData[$letters[$c]][$r] ?? null;

                if ($num !== null && $num !== "FREE" && !in_array($num, $drawnNumbers)) {
                    $missing[] = $num;
                }
            }
        }
    }

    if (count($missing) == 1) {

        $lastNumber = $missing[0];
        $isQueued = false;

        foreach ($queuedWinners as $qw) {
            if ($qw['card_id'] == $card['id']) {
                $isQueued = true;
                break;
            }
        }

        if (!$isQueued) {
            $blockedNumbers[] = $lastNumber;
        }
    }
}

$blockedNumbers = array_unique($blockedNumbers);

$winnerNumbers = array_values(array_diff($winnerNumbers, $blockedNumbers));
$neutralPool = array_values(array_diff($neutralPool, $blockedNumbers));
$allAvailableNumbers = array_values(array_diff($allAvailableNumbers, $blockedNumbers));

/* DRAW PROBABILITY SYSTEM */

$drawCount = (int)($game['draw_count'] ?? 0);
$winnerChance = min(20 + ($drawCount * 3), 75);
$dangerChance = 15;
$roll = rand(1,100);

if (!empty($winnerNumbers) && $roll <= $winnerChance) {

    $number = $winnerNumbers[array_rand($winnerNumbers)];

} elseif ($roll <= ($winnerChance + $dangerChance)) {

    if (!empty($allAvailableNumbers)) {
        $number = $allAvailableNumbers[array_rand($allAvailableNumbers)];
    } else {
        $number = $neutralPool[array_rand($neutralPool)];
    }

} else {

    if (!empty($neutralPool)) {
        $number = $neutralPool[array_rand($neutralPool)];
    } else {
        $number = $allAvailableNumbers[array_rand($allAvailableNumbers)];
    }

}

// Shared number trigger (final number for winners)
foreach ($queuedWinners as $winner) {

    $cardStmt = $pdo->prepare("
        SELECT shared_number
        FROM user_cards
        WHERE id = ?
    ");
    $cardStmt->execute([$winner['card_id']]);
    $sharedNumber = (int) $cardStmt->fetchColumn();

    if ($sharedNumber && !in_array($sharedNumber, $drawnNumbers, true)) {

        $remaining = array_diff($drawPool, $drawnNumbers, [$sharedNumber]);

        if (empty($remaining)) {
            $number = $sharedNumber;
            break;
        }
    }
}

if (!in_array($number, $drawnNumbers)) {
    $drawnNumbers[] = $number;
}

$pdo->prepare("
    UPDATE game
    SET drawn_numbers = ?, draw_count = draw_count + 1
    WHERE id = ?
")->execute([
    json_encode($drawnNumbers),
    $gameId
]);

/* Refresh winners info after the draw */
$claimedStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM game_winner_queue 
    WHERE game_id = ? AND claimed = 1
");
$claimedStmt->execute([$gameId]);
$claimedCount = (int) $claimedStmt->fetchColumn();

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
    'number'       => $number,
    'drawnNumbers' => $drawnNumbers,
    'claimedCount' => $claimedCount,
    'totalWinners' => $totalWinners,
    'winnerNames'  => $winnerNames,
    'finished'     => $claimedCount >= $totalWinners,
]);