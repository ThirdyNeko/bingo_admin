<?php
require_once '../config/db.php';

/* ==============================
   VALIDATE GAME ID
============================== */
if (!isset($_GET['game_id'])) {
    echo "<p class='text-danger'>Game not found.</p>";
    exit;
}

$gameId = (int) $_GET['game_id'];

/* ==============================
   FETCH GAME
============================== */
$stmt = $pdo->prepare("SELECT * FROM game WHERE id=?");
$stmt->execute([$gameId]);
$game = $stmt->fetch();
if (!$game) { echo "<p class='text-danger'>Game does not exist.</p>"; exit; }

/* ==============================
   PLAYER COUNT (for polling baseline)
============================== */
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE current_game=?");
$countStmt->execute([$gameId]);
$playerCount = (int) $countStmt->fetchColumn();

/* ==============================
   INCLUDE HEADER & SIDEBAR
============================== */
include '../partials/header.php';
include '../partials/sidebar.php';
?>

<div class="col-md-10 p-4" id="manage-game-root"
     data-game-id="<?= $gameId ?>"
     data-player-count="<?= $playerCount ?>">

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
            Joined Players (<span id="players-count"><?= $playerCount ?></span>)
        </div>

        <div class="card-body">
            <div id="players-container">

                <div class="table-responsive">
                    <table id="playersTable" class="table table-bordered align-middle" style="width:100%">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Mode</th>
                                <th>Cards</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>

                <?php if (!$game['started']): ?>
                    <div class="mt-3">
                        <form method="POST" action="functions/start_game.php">
                            <input type="hidden" name="game_id" value="<?= $gameId ?>">
                            <button type="submit" class="btn btn-lg btn-primary w-100">
                                🚀 Start Game
                            </button>
                        </form>
                    </div>
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

</div>

<link rel="stylesheet" href="../css/datatables.min.css">
<script src="../js/jquery-4.0.0.min.js"></script>
<script src="../js/datatables.min.js"></script>
<script src="../js/game/manage_game.js"></script>
</body>
</html>