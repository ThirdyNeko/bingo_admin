<?php
require_once '../config/db.php';
require_once 'bingo_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['game_id'])) {
    header("Location: ../manage_games.php");
    exit;
}

$gameId = (int) $_POST['game_id'];

// Fetch game & players
$stmt = $pdo->prepare("SELECT * FROM game WHERE id=?");
$stmt->execute([$gameId]);
$game = $stmt->fetch();
if (!$game) { echo "<p class='text-danger'>Game does not exist.</p>"; exit; }

$playersStmt = $pdo->prepare("SELECT * FROM users WHERE current_game=?");
$playersStmt->execute([$gameId]);
$players = $playersStmt->fetchAll();

$letters = ['B','I','N','G','O'];
$pattern = json_decode($game['pattern'], true);
$maxWinners = $game['winners'] ?? 2;

$cardsWithWeights = [];
$allCardIds = [];

// 1️⃣ Generate cards for players
foreach ($players as $player) {
    $userId = $player['id'];
    $cardCount = max(1, $player['card_count'] ?? 1);
    for ($i = 0; $i < $cardCount; $i++) {
        $randomCard = generateRandomBingoCard();
        $stmt = $pdo->prepare("INSERT INTO user_cards (user_id, game_id, card_data) VALUES (?,?,?)");
        $stmt->execute([$userId, $gameId, json_encode($randomCard)]);
        $cardId = $pdo->lastInsertId();

        $weight = calculatePriorityWeight((int)($player['wins'] ?? 0), $player['department'] ?? '', $player['role'] ?? '');
        $cardsWithWeights[] = ['card_id' => $cardId, 'weight' => $weight];
        $allCardIds[] = $cardId;
    }
}

// 2️⃣ Pick winner cards
$winnerCardIds = [];
for ($i = 0; $i < $maxWinners; $i++) {
    $picked = weightedRandomPick($cardsWithWeights);
    if ($picked) $winnerCardIds[] = $picked;
}

// 3️⃣ Assign shared number inside pattern
if (!empty($winnerCardIds)) {
    do {
        $sharedNumber = rand(1, 75); // pick a random number
        $fits = true;

        foreach ($winnerCardIds as $cardId) {
            $stmt = $pdo->prepare("SELECT card_data FROM user_cards WHERE id=?");
            $stmt->execute([$cardId]);
            $cardData = json_decode($stmt->fetchColumn(), true);

            $placed = false;
            foreach ($pattern as $r => $cols) {
                foreach ($cols as $c => $val) {
                    if ($val == 1) {
                        $letter = $letters[$c];
                        if ($letter === 'N' && $r === 2) continue;

                        $validRange = ['B'=>range(1,15),'I'=>range(16,30),'N'=>range(31,45),'G'=>range(46,60),'O'=>range(61,75)];
                        if (!in_array($sharedNumber, $validRange[$letter])) continue;

                        // ✅ Force integer
                        $cardData[$letter][$r] = (int) $sharedNumber;
                        $placed = true;
                        break 2;
                    }
                }
            }

            if (!$placed) {
                $fits = false;
                break;
            }

            // ✅ Force integer in DB too
            $stmt = $pdo->prepare("UPDATE user_cards SET card_data=?, shared_number=? WHERE id=?");
            $stmt->execute([json_encode($cardData), (int) $sharedNumber, $cardId]);
        }
    } while (!$fits);
}

// 4️⃣ Build winner queue

$otherCards = array_values(array_diff($allCardIds, $winnerCardIds));

$winnerQueue = [];

/* Level 1 = all winners */
$winnerQueue[] = $winnerCardIds;

/* Remaining cards */
$queueLevel = count($winnerCardIds) + 1;

while (!empty($otherCards)) {
    $levelCards = array_splice($otherCards, 0, $queueLevel);

    if (!empty($levelCards)) {
        $winnerQueue[] = $levelCards;
    }

    $queueLevel++;
}

/* Insert into DB */
foreach ($winnerQueue as $levelIndex => $cards) {
    $level = $levelIndex + 1;

    foreach ($cards as $cardId) {
        $stmt = $pdo->prepare("
            INSERT INTO game_winner_queue (game_id, level, card_id)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$gameId, $level, $cardId]);
    }
}

// 5️⃣ Mark game as started
$stmt = $pdo->prepare("UPDATE game SET started=1 WHERE id=?");
$stmt->execute([$gameId]);

header("Location: ../manage_game.php?game_id=" . $gameId);
exit;