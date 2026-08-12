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
function calculatePriorityWeight($wins, $department, $role) {
    $weight = 100;
    $weight -= ($wins * 10); // more wins = lower priority
    if (in_array(strtolower($department), ['softdev','soft dev','software development','soft developer','institutional'])) {
        $weight -= 100;
    }
    if (in_array(strtolower($role), ['priority'])) {
        $weight += 50;
    }
    return max($weight, 10);
}

function weightedRandomPick(&$items) {
    if (empty($items)) {
        return null;
    }
    $totalWeight = array_sum(array_column($items, 'weight'));
    if ($totalWeight <= 0) {
        return null;
    }
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
   HANDLE NUMBER REVEAL DURATION UPDATE
============================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_reveal_duration'])) {
    $seconds = (float) ($_POST['reveal_duration'] ?? 1.2);

    // Hard clamp to 1–5 seconds regardless of what the client sends.
    if ($seconds < 1) $seconds = 1;
    if ($seconds > 5) $seconds = 5;

    $revealDurationMs = (int) round($seconds * 1000);

    $update = $pdo->prepare("UPDATE game SET reveal_duration_ms = ? WHERE id = ?");
    $update->execute([$revealDurationMs, $gameId]);

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

    // 🚫 Guard: never allow a game to start with no joined players.
    // Passed back via query param instead of $_SESSION so this can't
    // interfere with the app's existing session/auth handling.
    if (empty($players)) {
        header("Location: manage_game.php?game_id=".$gameId."&error=no_players");
        exit;
    }

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

            $weight = calculatePriorityWeight((int)($player['wins'] ?? 0),$player['department'] ?? '', $player['role'] ?? '');
            $cardsWithWeights[]=['card_id'=>$cardId,'weight'=>$weight];
            $allCardIds[] = $cardId;
        }
    }

    // 🚫 Guard: if for some reason no cards were generated, bail out cleanly
    // instead of letting weightedRandomPick blow up on an empty array.
    if (empty($cardsWithWeights)) {
        header("Location: manage_game.php?game_id=".$gameId."&error=no_cards");
        exit;
    }

    // Never try to pick more winners than there are cards available.
    $maxWinners = min($maxWinners, count($cardsWithWeights));

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
   FETCH GAME AFTER POST
============================== */
$stmt = $pdo->prepare("SELECT * FROM game WHERE id=?");
$stmt->execute([$gameId]);
$game = $stmt->fetch();
if(!$game){ echo "<p class='text-danger'>Game does not exist.</p>"; exit; }

// Used only to know whether the Start Game button should be enabled/disabled
// and for the initial players-count badge. The actual table rows are now
// loaded by the server-side DataTable via functions/game_players_list.php.
$playerCountStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE current_game=?");
$playerCountStmt->execute([$gameId]);
$playerCount = (int) $playerCountStmt->fetchColumn();

// Reveal duration is stored in ms; the UI edits it in seconds (1–5).
$revealDurationSeconds = round((($game['reveal_duration_ms'] ?? 1200)) / 1000, 1);

// Error message for the Start Game guard, passed via query param
// (no session usage — avoids any interference with app auth/session).
$gameError = null;
if (isset($_GET['error'])) {
    $errorMessages = [
        'no_players' => 'Cannot start the game: no players have joined yet.',
        'no_cards'   => 'Cannot start the game: no cards could be generated for the joined players.',
    ];
    $gameError = $errorMessages[$_GET['error']] ?? null;
}

/* ==============================
   INCLUDE HEADER & SIDEBAR AFTER POST LOGIC
============================== */
include 'partials/header.php';
include 'partials/sidebar.php';
?>

<div class="col-md-10 p-4" id="manage-game-root" data-game-id="<?= $gameId ?>" data-player-count="<?= $playerCount ?>">

    <h3 class="mb-4">Manage Game</h3>

    <?php if ($gameError): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($gameError) ?>
        </div>
    <?php endif; ?>

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

            <hr>

            <p class="mb-2"><strong>Number Reveal Animation</strong></p>
            <form method="POST" class="d-flex align-items-center gap-2 flex-wrap">
                <input type="number"
                       name="reveal_duration"
                       class="form-control form-control-sm"
                       style="max-width:100px;"
                       min="1"
                       max="5"
                       step="0.1"
                       value="<?= htmlspecialchars($revealDurationSeconds) ?>"
                       required>
                <span class="text-muted small">seconds</span>
                <button type="submit" name="update_reveal_duration" class="btn btn-sm btn-outline-primary">
                    Save
                </button>
            </form>
            <p class="text-muted small mt-2 mb-0">
                How long the number "spins" on the Game Screen before the real drawn number is revealed. Must be between 1 and 5 seconds.
            </p>
        </div>
    </div>

    <!-- Players -->
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white">
            Joined Players (<span id="players-count"><?= $playerCount ?></span>)
        </div>

        <div class="card-body">

            <div class="table-responsive">
                <table id="playersTable" class="table table-bordered align-middle w-100">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Mode</th>
                            <th>Cards</th>
                        </tr>
                    </thead>
                </table>
            </div>

            <?php if (!$game['started']): ?>
                <?php if ($playerCount === 0): ?>
                    <div class="mt-3">
                        <button type="button" class="btn btn-lg btn-secondary w-100" disabled>
                            🚀 Start Game (waiting for players to join)
                        </button>
                    </div>
                <?php else: ?>
                    <div class="mt-3">
                        <form method="POST">
                            <button type="submit" name="start_game" class="btn btn-lg btn-primary w-100">
                                🚀 Start Game
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="mt-3">
                    <span class="text-success fw-bold">Game Started ✅</span>
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
<link rel="stylesheet" href="css/datatables.min.css">
<script src="js/jquery-4.0.0.min.js"></script>
<script src="js/datatables.min.js"></script>

<script src="js/game/manage_game.js"></script>
</body>
</html>