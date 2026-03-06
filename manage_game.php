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

    // Fetch all players for this game
    $playersStmt = $pdo->prepare("SELECT * FROM users WHERE current_game = ?");
    $playersStmt->execute([$gameId]);
    $players = $playersStmt->fetchAll();

    /* ==============================
       2️⃣ GENERATE & INSERT CARDS
    ============================== */

    foreach ($players as $player) {
        $userId = $player['id'];
        $cardCount = max(1, $player['card_count'] ?? 1);

        // Assign random bingo cards
        // Assuming you have a `bingo_cards` table to store cards per user
        // If not, you can generate them and store in JSON in a `cards` column in `users`
        for ($i = 0; $i < $cardCount; $i++) {
            $randomCard = json_encode(generateRandomBingoCard()); // function to generate card
            $stmt = $pdo->prepare("
                INSERT INTO user_cards (user_id, game_id, card_data)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$userId, $gameId, $randomCard]);
        }

        // Set auto_mode and card_count frozen (optional)
        $stmt = $pdo->prepare("
            UPDATE users
            SET auto_mode = auto_mode, card_count = card_count
            WHERE id = ? AND current_game = ?
        ");
        $stmt->execute([$userId, $gameId]);
    }

    /* ==============================
    3️⃣ BUILD UNIQUE WEIGHTED CARD LIST
    ============================== */

    $cardsWithWeights = [];

    foreach ($players as $player) {

        $userId = $player['id'];
        $wins = (int)($player['wins'] ?? 0);
        $department = $player['department'] ?? '';

        $weight = calculatePriorityWeight($wins, $department);

        $cardsStmt = $pdo->prepare("
            SELECT id FROM user_cards
            WHERE user_id = ? AND game_id = ?
        ");
        $cardsStmt->execute([$userId, $gameId]);
        $cards = $cardsStmt->fetchAll();

        foreach ($cards as $card) {
            $cardsWithWeights[] = [
                'card_id' => $card['id'],
                'weight'  => $weight
            ];
        }
    }

    /* ==============================
    4️⃣ GENERATE TRIANGLE WINNER QUEUE
    ============================== */

    $winnerQueue = [];
    $maxWinners = 10;

    for ($level = 1; $level <= $maxWinners; $level++) {

        $levelCards = [];

        for ($i = 0; $i < $level; $i++) {

            if (empty($cardsWithWeights)) {
                break;
            }

            $pickedCard = weightedRandomPick($cardsWithWeights);

            if ($pickedCard) {
                $levelCards[] = $pickedCard;
            }
        }

        if (!empty($levelCards)) {
            $winnerQueue[] = $levelCards;
        }
    }

    /* ==============================
       5️⃣ STORE WINNER QUEUE
    ============================== */

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
    6️⃣ SET PRIMARY WINNER IN GAME
    ============================== */

    $primaryCardId = $winnerQueue[0][0] ?? null;

    if ($primaryCardId) {

        // Get user of that card
        $stmt = $pdo->prepare("
            SELECT user_id 
            FROM user_cards 
            WHERE id = ?
        ");
        $stmt->execute([$primaryCardId]);
        $cardOwner = $stmt->fetch();

        if ($cardOwner) {

            $primaryUserId = $cardOwner['user_id'];

            // Store winner user_id in game table
            $stmt = $pdo->prepare("
                UPDATE game 
                SET game_winners = ? 
                WHERE id = ?
            ");
            $stmt->execute([$primaryUserId, $gameId]);
        }
    }

    /* ==============================
       7️⃣ MARK GAME AS STARTED
    ============================== */

    // Mark game as started (optional)
    $stmt = $pdo->prepare("UPDATE game SET started = 1 WHERE id = ?");
    $stmt->execute([$gameId]);

    header("Location: manage_game.php?game_id=" . $gameId);
    exit;
}



function generateRandomBingoCard() {
    $card = [];
    $columns = ['B'=>1,'I'=>16,'N'=>31,'G'=>46,'O'=>61];
    foreach ($columns as $letter => $start) {
        $nums = range($start, $start+14);
        shuffle($nums);
        $card[$letter] = array_slice($nums, 0, 5);
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