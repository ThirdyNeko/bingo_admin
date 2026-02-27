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

/* ==============================
   START GAME HANDLER
============================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_game'])) {

    // Fetch all players for this game
    $playersStmt = $pdo->prepare("SELECT * FROM users WHERE current_game = ?");
    $playersStmt->execute([$gameId]);
    $players = $playersStmt->fetchAll();

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
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($players as $index => $player): ?>
                                    <tr>
                                        <form method="POST">
                                            <td style="width:50px; text-align:center;"><?= $index + 1 ?></td>

                                            <td><?= htmlspecialchars($player['name']) ?></td>

                                            <!-- AUTO / MANUAL SWITCH -->
                                            <td style="width:120px; text-align:center;">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input"
                                                        type="checkbox"
                                                        name="auto_mode"
                                                        <?= $player['auto_mode'] == 1 ? 'checked' : '' ?>
                                                        <?= $game['started'] ? 'disabled' : '' ?>>
                                                    <label class="form-check-label">
                                                        <?= $player['auto_mode'] == 1 ? 'Auto' : 'Manual' ?>
                                                    </label>
                                                </div>
                                            </td>

                                            <!-- MULTIPLE CARDS -->
                                            <td style="width:120px;">
                                                <input type="number"
                                                    name="card_count"
                                                    class="form-control"
                                                    min="1"
                                                    value="<?= $player['card_count'] ?? 1 ?>"
                                                    <?= $game['started'] ? 'disabled' : '' ?>>
                                            </td>

                                            <!-- UPDATE BUTTON -->
                                            <td style="width:120px;">
                                                <?php if (!$game['started']): ?>
                                                    <input type="hidden" name="user_id" value="<?= $player['id'] ?>">
                                                    <button type="submit"
                                                            name="update_player"
                                                            class="btn btn-sm btn-success w-100">
                                                        Update
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-muted">Locked</span>
                                                <?php endif; ?>
                                            </td>
                                        </form>
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
                        <?php if ($game['started']): ?>
                            <div class="mt-3">
                                <a href="screen.php?game_id=<?= $gameId ?>" target="_blank" 
                                class="btn btn-lg btn-primary w-100">
                                    🎬 Show Screen
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                <?php endif; ?>
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