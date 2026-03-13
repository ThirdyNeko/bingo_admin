<?php
require_once 'config/db.php';

/* ==============================
   VALIDATE GAME ID
============================== */
if (!isset($_GET['game_id'])) {
    echo "<p class='text-danger'>Game not found.</p>";
    exit;
}

$gameId = (int) $_GET['game_id'];

/* ==============================
   HELPER FUNCTIONS
============================== */
function calculatePriorityWeight($wins, $department) {
    $weight = 100;
    $weight -= ($wins * 10); // more wins = lower priority
    if (in_array(strtolower($department), ['softdev','soft dev','software development','soft developer','institutional'])) {
        $weight += 30;
    }
    return max($weight, 10);
}

function weightedRandomPick(&$items) {
    $totalWeight = array_sum(array_column($items, 'weight'));
    $rand = mt_rand(1, $totalWeight);
    foreach ($items as $index => $item) {
        $rand -= $item['weight'];
        if ($rand <= 0) {
            $picked = $item;
            unset($items[$index]);
            $items = array_values($items);
            return $picked['card_id'];
        }
    }
    return null;
}

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
    $card['N'][2] = 'FREE';
    return $card;
}

/* ==============================
   HANDLE PLAYER UPDATE
============================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_player'])) {
    $userId = (int)$_POST['user_id'];
    $autoMode = isset($_POST['auto_mode']) ? 1 : 0;
    $cardCount = max(1,(int)$_POST['card_count']);

    $update = $pdo->prepare("
        UPDATE users
        SET auto_mode = ?, card_count = ?
        WHERE id = ? AND current_game = ?
    ");
    $update->execute([$autoMode,$cardCount,$userId,$gameId]);

    header("Location: manage_game.php?game_id=".$gameId);
    exit;
}

/* ==============================
   START GAME HANDLER
============================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_game'])) {

    // Fetch game & players
    $stmt = $pdo->prepare("SELECT * FROM game WHERE id=?");
    $stmt->execute([$gameId]);
    $game = $stmt->fetch();
    if (!$game) { echo "<p class='text-danger'>Game does not exist.</p>"; exit; }

    $playersStmt = $pdo->prepare("SELECT * FROM users WHERE current_game=?");
    $playersStmt->execute([$gameId]);
    $players = $playersStmt->fetchAll();

    $letters = ['B','I','N','G','O'];
    $pattern = json_decode($game['pattern'],true);
    $maxWinners = $game['winners'] ?? 2;

    $cardsWithWeights = [];
    $allCardIds = [];

    // 1️⃣ Generate cards for players
    foreach ($players as $player) {
        $userId = $player['id'];
        $cardCount = max(1,$player['card_count'] ?? 1);
        for ($i=0;$i<$cardCount;$i++){
            $randomCard = generateRandomBingoCard();
            $stmt = $pdo->prepare("INSERT INTO user_cards (user_id, game_id, card_data) VALUES (?,?,?)");
            $stmt->execute([$userId,$gameId,json_encode($randomCard)]);
            $cardId = $pdo->lastInsertId();

            $weight = calculatePriorityWeight((int)($player['wins'] ?? 0),$player['department'] ?? '');
            $cardsWithWeights[]=['card_id'=>$cardId,'weight'=>$weight];
            $allCardIds[] = $cardId;
        }
    }

    // 2️⃣ Pick winner cards
    $winnerCardIds = [];
    for($i=0;$i<$maxWinners;$i++){
        $picked = weightedRandomPick($cardsWithWeights);
        if($picked) $winnerCardIds[]=$picked;
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
                            $cardData[$letter][$r] = (int)$sharedNumber;
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
                $stmt->execute([json_encode($cardData), (int)$sharedNumber, $cardId]);
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

    header("Location: manage_game.php?game_id=".$gameId);
    exit;
}

/* ==============================
   FETCH GAME AND PLAYERS AFTER POST
============================== */
$stmt = $pdo->prepare("SELECT * FROM game WHERE id=?");
$stmt->execute([$gameId]);
$game = $stmt->fetch();
if(!$game){ echo "<p class='text-danger'>Game does not exist.</p>"; exit; }

$playersStmt = $pdo->prepare("SELECT * FROM users WHERE current_game=?");
$playersStmt->execute([$gameId]);
$players = $playersStmt->fetchAll();

/* ==============================
   INCLUDE HEADER & SIDEBAR AFTER POST LOGIC
============================== */
include 'partials/header.php';
include 'partials/sidebar.php';
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
            <p><strong>Current Winners:</strong>
            <?php
            $winners = json_decode($game['game_winners'], true);

            if (!empty($winners)) {
                echo htmlspecialchars(implode(', ', $winners));
            } else {
                echo 'No winners yet';
            }
            ?>
            </p>
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