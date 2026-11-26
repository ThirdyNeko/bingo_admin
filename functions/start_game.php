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

// 🚫 Don't re-run the whole flow if the game already started
// (e.g. the manual button was clicked right as the timer fired).
if ($game['started']) {
    header("Location: ../manage_game.php?game_id=" . $gameId);
    exit;
}

$playersStmt = $pdo->prepare("SELECT * FROM users WHERE current_game=?");
$playersStmt->execute([$gameId]);
$players = $playersStmt->fetchAll();

// 🚫 Guard: never allow a game to start with no joined players.
if (empty($players)) {
    header("Location: ../manage_game.php?game_id=" . $gameId . "&error=no_players");
    exit;
}

$letters = ['B','I','N','G','O'];
$pattern = json_decode($game['pattern'], true);
$maxWinners = $game['winners'] ?? 2;

// Cards are split into two pools:
//  - $normalCards:     players with wins = 0
//  - $restrictedCards: players with wins > 0 (restricted from the manage page)
// Restricted cards are only ever used as winners if there aren't enough
// normal cards to fill the winner slots, and they always sit at the very
// end of the winner queue.
$normalCards = [];
$restrictedCards = [];
$allCardIds = [];

// 1️⃣ Generate cards for players
foreach ($players as $player) {
    $userId = $player['id'];
    $cardCount = max(1, $player['card_count'] ?? 1);
    $wins = (int) ($player['wins'] ?? 0);
    $isRestricted = $wins > 0;

    for ($i = 0; $i < $cardCount; $i++) {
        $randomCard = generateRandomBingoCard();
        $stmt = $pdo->prepare("INSERT INTO user_cards (user_id, game_id, card_data) VALUES (?,?,?)");
        $stmt->execute([$userId, $gameId, json_encode($randomCard)]);
        $cardId = $pdo->lastInsertId();

        $weight = calculatePriorityWeight($wins, $player['department'] ?? '', $player['role'] ?? '');
        $entry = ['card_id' => $cardId, 'weight' => $weight];

        if ($isRestricted) {
            $restrictedCards[] = $entry;
        } else {
            $normalCards[] = $entry;
        }
        $allCardIds[] = $cardId;
    }
}

// 🚫 Guard: if for some reason no cards were generated, bail out cleanly.
if (empty($allCardIds)) {
    header("Location: ../manage_game.php?game_id=" . $gameId . "&error=no_cards");
    exit;
}

// Never try to pick more winners than there are cards available.
$maxWinners = min($maxWinners, count($allCardIds));

// 2️⃣ Pick winner cards
// Normal pool first. Restricted cards are only drawn once the normal
// pool is empty.
// weightedRandomPick() removes the picked item from the array it is given,
// so whatever is left in each pool afterwards is exactly the "other" cards.
$winnerCardIds = [];
for ($i = 0; $i < $maxWinners; $i++) {
    if (!empty($normalCards)) {
        $picked = weightedRandomPick($normalCards);
    } elseif (!empty($restrictedCards)) {
        $picked = weightedRandomPick($restrictedCards);
    } else {
        break;
    }
    if ($picked) $winnerCardIds[] = $picked;
}

// 3️⃣ Assign shared number inside pattern
if (!empty($winnerCardIds)) {
    do {
        $sharedNumber = rand(1, 75);
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

                        // 🚫 Skip if this column already has the number elsewhere on the card
                        $existingValues = $cardData[$letter];
                        unset($existingValues[$r]); // ignore the cell we're about to overwrite
                        if (in_array((int) $sharedNumber, $existingValues, true)) {
                            continue;
                        }

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

            $stmt = $pdo->prepare("UPDATE user_cards SET card_data=?, shared_number=? WHERE id=?");
            $stmt->execute([json_encode($cardData), (int) $sharedNumber, $cardId]);
        }
    } while (!$fits);
}

// 4️⃣ Build winner queue
// Level 1 = the picked winners. Remaining normal cards fill the next
// levels, and restricted cards come strictly after all of them (they
// never share a level with a normal card).
$normalOthers     = array_column($normalCards, 'card_id');
$restrictedOthers = array_column($restrictedCards, 'card_id');

$winnerQueue = [];
$winnerQueue[] = $winnerCardIds;

$queueLevel = count($winnerCardIds) + 1;

foreach ([$normalOthers, $restrictedOthers] as $group) {
    while (!empty($group)) {
        $levelCards = array_splice($group, 0, $queueLevel);

        if (!empty($levelCards)) {
            $winnerQueue[] = $levelCards;
        }

        $queueLevel++;
    }
}

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
// The card-change window (if any) starts counting from *now*, since
// this is the moment the game actually starts — not from creation.
$cardChangeDeadline = null;

if (!empty($game['card_change_minutes'])) {
    $cardChangeDeadline = date(
        'Y-m-d H:i:s',
        strtotime("+{$game['card_change_minutes']} minutes")
    );
}

$stmt = $pdo->prepare("
    UPDATE game
    SET started = 1, card_change_deadline = ?
    WHERE id = ?
");
$stmt->execute([$cardChangeDeadline, $gameId]);

header("Location: ../manage_game.php?game_id=" . $gameId);
exit;