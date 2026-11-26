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

    if ($seconds < 1) $seconds = 1;
    if ($seconds > 5) $seconds = 5;

    $revealDurationMs = (int) round($seconds * 1000);

    $update = $pdo->prepare("UPDATE game SET reveal_duration_ms = ? WHERE id = ?");
    $update->execute([$revealDurationMs, $gameId]);

    header("Location: manage_game.php?game_id=".$gameId);
    exit;
}

/* ==============================
   HANDLE DRAW MODE UPDATE
============================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_draw_mode'])) {
    $drawMode = ($_POST['draw_mode'] ?? 'auto') === 'manual' ? 'manual' : 'auto';

    $drawInterval = (int) ($_POST['draw_interval_seconds'] ?? 5);
    if ($drawInterval < 1) $drawInterval = 1;
    if ($drawInterval > 60) $drawInterval = 60;

    $update = $pdo->prepare("UPDATE game SET draw_mode = ?, draw_interval_seconds = ? WHERE id = ?");
    $update->execute([$drawMode, $drawInterval, $gameId]);

    header("Location: manage_game.php?game_id=".$gameId);
    exit;
}

/* ==============================
   FETCH GAME AFTER POST
   (start_game itself is now handled entirely by functions/start_game.php,
   which is also the endpoint the auto-start timer fetch()es below.)
============================== */
$stmt = $pdo->prepare("SELECT * FROM game WHERE id=?");
$stmt->execute([$gameId]);
$game = $stmt->fetch();
if(!$game){ echo "<p class='text-danger'>Game does not exist.</p>"; exit; }

/* ==============================
   HANDLE RESTRICT / UNRESTRICT (pre-start only)
   restrict   = wins + 1
   unrestrict = wins reset to 0
============================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_restriction'])) {
    if (!$game['started']) {
        $userId = (int) ($_POST['user_id'] ?? 0);

        if ($_POST['set_restriction'] === 'restrict') {
            $sql = "UPDATE users SET wins = wins + 1 WHERE id = ? AND current_game = ?";
        } else {
            $sql = "UPDATE users SET wins = 0 WHERE id = ? AND current_game = ?";
        }

        $restrictStmt = $pdo->prepare($sql);
        $restrictStmt->execute([$userId, $gameId]);
    }

    header("Location: manage_game.php?game_id=".$gameId);
    exit;
}

$playerCountStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE current_game=?");
$playerCountStmt->execute([$gameId]);
$playerCount = (int) $playerCountStmt->fetchColumn();

$revealDurationSeconds = round((($game['reveal_duration_ms'] ?? 1200)) / 1000, 1);
$drawMode = $game['draw_mode'] ?? 'auto';
$drawIntervalSeconds = (int) ($game['draw_interval_seconds'] ?? 5);

$gameError = null;
if (isset($_GET['error'])) {
    $errorMessages = [
        'no_players' => 'Cannot start the game: no players have joined yet.',
        'no_cards'   => 'Cannot start the game: no cards could be generated for the joined players.',
    ];
    $gameError = $errorMessages[$_GET['error']] ?? null;
}

include 'partials/header.php';
include 'partials/sidebar.php';
?>

<div class="col-md-10 p-4" id="manage-game-root"
     data-game-id="<?= $gameId ?>"
     data-player-count="<?= $playerCount ?>"
     data-game-started="<?= $game['started'] ? '1' : '0' ?>"
     data-draw-mode="<?= htmlspecialchars($drawMode) ?>"
     data-draw-interval-seconds="<?= $drawIntervalSeconds ?>"
     data-start-mode="<?= htmlspecialchars($game['start_mode'] ?? 'manual') ?>"
     data-scheduled-start="<?= $game['scheduled_start'] ? date('c', strtotime($game['scheduled_start'])) : '' ?>">

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

            <hr>

            <p class="mb-2"><strong>Number Draw Mode</strong></p>
            <form method="POST" class="d-flex align-items-center gap-3 flex-wrap" id="drawModeForm">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="draw_mode" id="drawModeAuto"
                           value="auto" <?= $drawMode === 'auto' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="drawModeAuto">Automatic</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="draw_mode" id="drawModeManual"
                           value="manual" <?= $drawMode === 'manual' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="drawModeManual">Manual</label>
                </div>

                <div class="d-flex align-items-center gap-2" id="drawIntervalWrap"
                     style="<?= $drawMode === 'manual' ? 'display:none;' : '' ?>">
                    <input type="number"
                           name="draw_interval_seconds"
                           class="form-control form-control-sm"
                           style="max-width:100px;"
                           min="1"
                           max="60"
                           step="1"
                           value="<?= $drawIntervalSeconds ?>">
                    <span class="text-muted small">sec between draws</span>
                </div>

                <button type="submit" name="update_draw_mode" class="btn btn-sm btn-outline-primary">
                    Save
                </button>
            </form>
            <p class="text-muted small mt-2 mb-0">
                Automatic: numbers draw on their own every N seconds on the Game Screen.
                Manual: the operator clicks "Draw Next Number" on the Game Screen to draw each ball.
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
                            <th>Department</th>
                            <th>Mode</th>
                            <th>Cards</th>
                            <th>Restriction</th>
                        </tr>
                    </thead>
                </table>
            </div>

            <?php if (!$game['started']): ?>

                <?php if ($game['start_mode'] === 'timer' && $game['scheduled_start']): ?>
                    <div class="mt-3 alert alert-info mb-2">
                        Game starts automatically at
                        <strong><?= date('h:i A', strtotime($game['scheduled_start'])) ?></strong>
                        — <span id="countdown"></span>
                    </div>
                <?php endif; ?>

                <?php if ($playerCount === 0): ?>
                    <div class="mt-3">
                        <button type="button" class="btn btn-lg btn-secondary w-100" disabled>
                            🚀 Start Game (waiting for players to join)
                        </button>
                    </div>
                <?php else: ?>
                    <div class="mt-3">
                        <form method="POST" action="functions/start_game.php">
                            <input type="hidden" name="game_id" value="<?= $gameId ?>">
                            <button type="submit" class="btn btn-lg btn-primary w-100">
                                🚀 Start Game<?= $game['start_mode'] === 'timer' ? ' Now' : '' ?>
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