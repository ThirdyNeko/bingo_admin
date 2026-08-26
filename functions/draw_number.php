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

// 🚫 Don't draw while the game is still in its card-change window —
// players are allowed to be swapping cards right now. Mirrors the
// check in screen.php and change_card.php.
$cardChangeDeadline = $game['card_change_deadline'] ?? null;
$inCardChangeWindow = (int) $game['started'] === 1
    && $cardChangeDeadline
    && strtotime($cardChangeDeadline) > time();

if ($inCardChangeWindow) {
    http_response_code(409);
    echo json_encode([
        'error' => 'Cannot draw yet — card change window is still open.',
        'cardChangeDeadline' => date('c', strtotime($cardChangeDeadline)),
    ]);
    exit;
}

$totalWinners = (int) $game['winners'];

$pattern = json_decode($game['pattern'], true);
$drawnNumbers = array_map('intval', json_decode($game['drawn_numbers'] ?? '[]', true));
$letters = ['B','I','N','G','O'];

/* ACTIVE-WINNER RESOLUTION
   Pull the whole unclaimed queue in level order, then walk down it and
   treat the first NOT-YET-COMPLETE entry as the active target. Any entry
   that's already fully drawn (needed numbers + shared number all drawn)
   but not yet claimed is skipped over — it just sits there waiting for
   the player to claim it — without blocking the next level from being
   fed winner numbers. This is what lets the game "move on" to level 2
   if level 1 finishes but never claims. */

$queueStmt = $pdo->prepare("
    SELECT *
    FROM game_winner_queue
    WHERE game_id = ? AND claimed = 0
    ORDER BY level ASC
");
$queueStmt->execute([$gameId]);
$allQueued = $queueStmt->fetchAll(PDO::FETCH_ASSOC);

if (!$allQueued) {
    http_response_code(422);
    echo json_encode(['error' => 'No queued winner found.']);
    exit;
}

$activeWinner = null;

foreach ($allQueued as $q) {

    $cardStmt = $pdo->prepare("
        SELECT card_data, shared_number
        FROM user_cards
        WHERE id = ?
    ");
    $cardStmt->execute([$q['card_id']]);
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

    $isComplete = empty($neededNumbers) && in_array($sharedNumber, $drawnNumbers, true);

    if ($isComplete) {
        // Fully drawn but not yet claimed — leave them be, move on to
        // whoever's next in the queue.
        continue;
    }

    $activeWinner = [
        'queueId'      => $q['id'],
        'cardId'       => $q['card_id'],
        'level'        => $q['level'],
        'needed'       => $neededNumbers,
        'sharedNumber' => $sharedNumber,
    ];
    break;
}

if (!$activeWinner) {
    http_response_code(422);
    echo json_encode(['error' => 'All queued winners are finished, awaiting claim.']);
    exit;
}

$drawPool = $activeWinner['needed'];

$allAvailableNumbers = array_values(array_diff(range(1,75), $drawnNumbers));

/* SMART DRAW ENGINE */

$winnerNumbers = array_values(array_diff($drawPool, $drawnNumbers));
$neutralPool = array_values(array_diff($allAvailableNumbers, $drawPool));
$dangerPool = [];
$blockedNumbers = [];

// Total number of cells in the pattern — used to scale thresholds so
// small patterns (fast games) don't finish in a handful of draws.
$patternCellCount = 0;
foreach ($pattern as $cols) {
    foreach ($cols as $val) {
        if ($val == 1) $patternCellCount++;
    }
}
$patternCellCount = max($patternCellCount, 1);

// Non-winner cards participate in the danger pool from the very start
// of the pattern (not just the last couple of cells) so they visibly
// progress throughout the game instead of staying flat until the end.
$dangerThreshold = $patternCellCount - 1; // everything except "would complete"

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

    $isActiveWinner = ($card['id'] == $activeWinner['cardId']);

    if (count($missing) == 1 && !$isActiveWinner) {
        // Would complete a non-winner on the next draw — never allow this number.
        $blockedNumbers[] = $missing[0];

    } elseif (!$isActiveWinner && count($missing) >= 2 && count($missing) <= $dangerThreshold) {
        // Weight by closeness: cards nearer completion get more copies in
        // the pool, so they're favored, but every non-winner still gets
        // *some* representation from early in the game.
        $weight = $dangerThreshold - count($missing) + 1;
        for ($w = 0; $w < $weight; $w++) {
            $dangerPool = array_merge($dangerPool, $missing);
        }
    }
}

$dangerPool = array_values(array_unique($dangerPool));
$blockedNumbers = array_unique($blockedNumbers);

$winnerNumbers = array_values(array_diff($winnerNumbers, $blockedNumbers));
$neutralPool = array_values(array_diff($neutralPool, $blockedNumbers));
$dangerPool = array_values(array_diff($dangerPool, $blockedNumbers));
$allAvailableNumbers = array_values(array_diff($allAvailableNumbers, $blockedNumbers));

/* STUCK-STREAK TRACKING
   If at least one non-winner card is sitting blocked at 1-away, count
   consecutive draws that's been true. Past the threshold, stop dragging
   it out — bias hard toward finishing the actual winner instead of
   continuing to hold everyone at "so close". */

$stuckStreak = (int) ($game['stuck_streak'] ?? 0);
$stuckStreakLimit = 8; // draws a block can persist before we speed up the real finish

if (!empty($blockedNumbers)) {
    $stuckStreak++;
} else {
    $stuckStreak = 0;
}

$forceWinnerFinish = $stuckStreak >= $stuckStreakLimit && !empty($winnerNumbers);

/* DRAW PROBABILITY SYSTEM */

$drawCount = (int)($game['draw_count'] ?? 0);

$baseChance = 10;
$maxChance  = 75;

// Aim for the winner-chance ramp to hit $maxChance after roughly
// (patternCellCount * $drawsPerCell) draws — bigger patterns take
// longer to realistically fill, so they get a longer ramp; small
// patterns ramp fast so the game doesn't drag.
$drawsPerCell     = 5;
$targetRampDraws  = max(1, $patternCellCount * $drawsPerCell);
$rampRate         = ($maxChance - $baseChance) / $targetRampDraws;

$winnerChance = min($baseChance + ($drawCount * $rampRate), $maxChance);

// Danger pool airtime scales inversely with pattern size: small
// patterns produce a tiny danger pool on their own, so they need a
// bigger slice of the roll to stay convincing. Larger patterns
// generate their own danger pool naturally, so they need less help.
$dangerChance = (int) max(15, min(40, round(150 / $patternCellCount)));

if ($forceWinnerFinish) {
    // Stop stalling — push straight toward completing the real winner.
    $winnerChance = 100;
}

$roll = rand(1,100);

if (!empty($winnerNumbers) && $roll <= $winnerChance) {

    $number = $winnerNumbers[array_rand($winnerNumbers)];

} elseif ($roll <= ($winnerChance + $dangerChance)) {

    if (!empty($dangerPool)) {
        $number = $dangerPool[array_rand($dangerPool)];
    } elseif (!empty($allAvailableNumbers)) {
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

// Shared number trigger (final number for the active winner)
$sharedNumber = $activeWinner['sharedNumber'];

if ($sharedNumber && !in_array($sharedNumber, $drawnNumbers, true)) {

    $remaining = array_diff($drawPool, $drawnNumbers, [$sharedNumber]);

    if (empty($remaining) || $forceWinnerFinish) {
        $number = $sharedNumber;
    }
}

if (!in_array($number, $drawnNumbers)) {
    $drawnNumbers[] = $number;
}

$pdo->prepare("
    UPDATE game
    SET drawn_numbers = ?, draw_count = draw_count + 1, stuck_streak = ?
    WHERE id = ?
")->execute([
    json_encode($drawnNumbers),
    $stuckStreak,
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