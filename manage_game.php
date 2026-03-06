<?php
require_once 'config/db.php';
include 'partials/header.php';
include 'partials/sidebar.php';

if (!isset($_GET['game_id'])) {
    die("Game not found.");
}

$gameId = (int) $_GET['game_id'];

/* ==============================
   HANDLE PLAYER UPDATE
============================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_player'])) {

    $userId = (int) $_POST['user_id'];
    $autoMode = isset($_POST['auto_mode']) ? 1 : 0;
    $cardCount = max(1, (int) $_POST['card_count']);

    $update = $pdo->prepare("
        UPDATE users
        SET auto_mode = ?, card_count = ?
        WHERE id = ? AND current_game = ?
    ");
    $update->execute([$autoMode, $cardCount, $userId, $gameId]);

    header("Location: manage_game.php?game_id=" . $gameId);
    exit;
}

/* ==============================
   GET GAME
============================== */
$stmt = $pdo->prepare("SELECT * FROM game WHERE id = ?");
$stmt->execute([$gameId]);
$game = $stmt->fetch();

if (!$game) {
    die("Game does not exist.");
}

/* ==============================
   GET PLAYERS
============================== */
$playersStmt = $pdo->prepare("
    SELECT *
    FROM users
    WHERE current_game = ?
");
$playersStmt->execute([$gameId]);
$players = $playersStmt->fetchAll();

function calculatePriorityWeight($wins, $department) {

    // Base weight
    $weight = 100;

    // More wins = lower priority
    $weight -= ($wins * 10);   // Each win reduces chance

    if (strtolower($department) === 'softdev'|| strtolower($department) === 'soft dev') {
        $weight += 30; 
    }

    // Never allow zero or negative
    return max($weight, 10);
}

function weightedRandomPick(&$items) {

    $totalWeight = array_sum(array_column($items, 'weight'));
    $rand = mt_rand(1, $totalWeight);

    foreach ($items as $index => $item) {

        $rand -= $item['weight'];

        if ($rand <= 0) {
            $picked = $item;

            // remove so it can't repeat
            unset($items[$index]);
            $items = array_values($items);

            return $picked['card_id'];
        }
    }

    return null;
}

/* ==============================
   START GAME HANDLER
============================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_game'])) {

    // Fetch players
    $playersStmt = $pdo->prepare("SELECT * FROM users WHERE current_game = ?");
    $playersStmt->execute([$gameId]);
    $players = $playersStmt->fetchAll();

    $letters = ['B','I','N','G','O'];
    $pattern = json_decode($game['pattern'], true);
    $maxWinners = $game['winners'] ?? 2; // number of winners for shared number

    $cardsWithWeights = [];
    $allCardIds = [];

    /* ==============================
       1️⃣ GENERATE CARDS FOR ALL PLAYERS
    ============================== */
    foreach ($players as $player) {
        $userId = $player['id'];
        $cardCount = max(1, $player['card_count'] ?? 1);

        for ($i = 0; $i < $cardCount; $i++) {
            $randomCard = generateRandomBingoCard(); // uses correct B/I/N/G/O ranges
            $stmt = $pdo->prepare("INSERT INTO user_cards (user_id, game_id, card_data) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $gameId, json_encode($randomCard)]);
            $cardId = $pdo->lastInsertId();

            $weight = calculatePriorityWeight((int)$player['wins'] ?? 0, $player['department'] ?? '');
            $cardsWithWeights[] = [
                'card_id' => $cardId,
                'weight'  => $weight
            ];
            $allCardIds[] = $cardId;
        }
    }

    /* ==============================
       2️⃣ PICK WINNER CARDS FOR SHARED NUMBER
    ============================== */
    $winnerCardIds = [];
    for ($i = 0; $i < $maxWinners; $i++) {
        $picked = weightedRandomPick($cardsWithWeights);
        if ($picked) $winnerCardIds[] = $picked;
    }

    /* ==============================
       3️⃣ ASSIGN SHARED NUMBER INSIDE PATTERN
    ============================== */
    if (!empty($winnerCardIds)) {
        $sharedNumber = null;

        // Pick a number between 1-75
        do {
            $sharedNumber = rand(1,75);
            $fits = true;

            foreach ($winnerCardIds as $cardId) {
                $stmt = $pdo->prepare("SELECT card_data FROM user_cards WHERE id = ?");
                $stmt->execute([$cardId]);
                $cardData = json_decode($stmt->fetchColumn(), true);

                // Find a valid pattern cell
                $placed = false;
                foreach ($pattern as $r => $cols) {
                    foreach ($cols as $c => $val) {
                        if ($val == 1) {
                            $letter = $letters[$c];

                            if ($letter === 'N' && $r === 2) continue; // skip FREE

                            // Make sure the shared number is in correct column range
                            $validRange = [
                                'B'=>range(1,15),
                                'I'=>range(16,30),
                                'N'=>range(31,45),
                                'G'=>range(46,60),
                                'O'=>range(61,75)
                            ];

                            if (!in_array($sharedNumber, $validRange[$letter])) continue;

                            $cardData[$letter][$r] = $sharedNumber;
                            $placed = true;
                            break 2;
                        }
                    }
                }

                if (!$placed) {
                    $fits = false;
                    break;
                }

                // Save back
                $stmt = $pdo->prepare("UPDATE user_cards SET card_data = ?, shared_number = ? WHERE id = ?");
                $stmt->execute([json_encode($cardData), $sharedNumber, $cardId]);
            }

        } while (!$fits);
    }

    /* ==============================
    4️⃣ BUILD WINNER QUEUE WITH WINNERS PRIORITIZED
    ============================= */

    // $winnerCardIds = first N winner cards (shared number)
    // $allCardIds = all cards in the game

    // Remove winner cards from all cards so we can append the rest
    $otherCards = array_diff($allCardIds, $winnerCardIds);

    // Queue order: winners first, then the rest
    $queueOrder = array_merge($winnerCardIds, $otherCards);

    $winnerQueue = [];
    $queueLevel = 1;

    while (!empty($queueOrder)) {
        // Take next $queueLevel cards for this level
        $levelCards = array_splice($queueOrder, 0, $queueLevel);
        $winnerQueue[] = $levelCards;
        $queueLevel++;
    }

    // Insert into game_winner_queue table
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

    /* ==============================
       5️⃣ MARK GAME AS STARTED
    ============================== */
    $stmt = $pdo->prepare("UPDATE game SET started = 1 WHERE id = ?");
    $stmt->execute([$gameId]);

    header("Location: manage_game.php?game_id=" . $gameId);
    exit;
}

/* ==============================
   HELPER: Generate Bingo Card With Correct Column Ranges
============================== */
function generateRandomBingoCard() {
    $card = [];
    $columns = [
        'B'=>range(1,15),
        'I'=>range(16,30),
        'N'=>range(31,45),
        'G'=>range(46,60),
        'O'=>range(61,75)
    ];

    foreach ($columns as $letter => $range) {
        shuffle($range);
        $card[$letter] = array_slice($range, 0, 5);
    }

    $card['N'][2] = 'FREE'; // center free space
    return $card;
}

?>

<div class="col-md-10 p-4">

    <h3 class="mb-4">Manage Game</h3>

    <!-- Game Info -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-dark text-white">
            Game Information
        </div>
        <div class="card-body">
            <p><strong>Game Code:</strong> <?= htmlspecialchars($game['game_code']) ?></p>
            <p><strong>Winners Required:</strong> <?= $game['winners'] ?></p>
            <p><strong>Current Winners:</strong> <?= $game['game_winners'] ?></p>
        </div>
    </div>

    <!-- Players -->
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white">
            Joined Players (<?= count($players) ?>)
        </div>

        <div class="card-body">
            <div id="players-container">

                <?php if (empty($players)): ?>
                    <p class="text-muted">No players joined yet.</p>
                <?php else: ?>

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Name</th>
                                    <th>Mode</th>
                                    <th>Cards</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($players as $index => $player): ?>
                                    <tr>
                                        <td style="width:50px; text-align:center;">
                                            <?= $index + 1 ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($player['name']) ?>
                                        </td>

                                        <!-- READ ONLY MODE -->
                                        <td style="width:120px; text-align:center;">
                                            <span class="badge <?= $player['auto_mode'] ? 'bg-primary' : 'bg-secondary' ?>">
                                                <?= $player['auto_mode'] ? 'Auto' : 'Manual' ?>
                                            </span>
                                        </td>

                                        <!-- READ ONLY CARD COUNT -->
                                        <td style="width:120px; text-align:center;">
                                            <?= $player['card_count'] ?? 1 ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php if (!$game['started']): ?>
                            <div class="mt-3">
                                <form method="POST">
                                    <button type="submit" name="start_game" class="btn btn-lg btn-primary w-100">
                                        🚀 Start Game
                                    </button>
                                </form>
                            </div>
                        <?php else: ?>
                            <div class="mt-3">
                                <span class="text-success fw-bold">Game Started ✅</span>
                            </div>
                        <?php endif; ?>
                    </div>

                <?php endif; ?>
                <div class="mt-3">
                    <a href="screen.php?game_id=<?= $gameId ?>" target="_blank" 
                    class="btn btn-lg btn-dark w-100">
                        🎬 Open Game Screen
                    </a>
                </div>
            </div>
        </div>
    </div>

</div>
<script>
let currentCount = <?= count($players) ?>;

function checkForNewPlayers() {
    fetch('player_count.php?game_id=<?= $gameId ?>')
        .then(res => res.text())
        .then(count => {
            count = parseInt(count);

            if (count !== currentCount) {
                location.reload(); // reload only if count changed
            }
        });
}

// Check every 3 seconds
setInterval(checkForNewPlayers, 3000);
</script>
</body>
</html>