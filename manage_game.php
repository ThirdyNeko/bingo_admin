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

<div class="col-md-10 p-4 manage-game-content" id="manage-game-root"
     data-game-id="<?= $gameId ?>"
     data-player-count="<?= $playerCount ?>"
     data-game-started="<?= $game['started'] ? '1' : '0' ?>"
     data-draw-mode="<?= htmlspecialchars($drawMode) ?>"
     data-draw-interval-seconds="<?= $drawIntervalSeconds ?>"
     data-start-mode="<?= htmlspecialchars($game['start_mode'] ?? 'manual') ?>"
     data-scheduled-start="<?= $game['scheduled_start'] ? date('c', strtotime($game['scheduled_start'])) : '' ?>">

    <!-- PAGE HEADER -->
    <div class="mg-page-header">
        <div>
            <span class="mg-eyebrow">GAME CONTROL CENTER</span>
            <h2>Manage Game</h2>
            <p>Monitor players, configure game settings, and control your game session.</p>
        </div>

        <a href="manage_games.php" class="mg-back-btn">
            <i class="bi bi-arrow-left"></i>
            Back to Games
        </a>
    </div>

    <?php if ($gameError): ?>
        <div class="mg-alert-error">
            <i class="bi bi-exclamation-circle-fill"></i>
            <?= htmlspecialchars($gameError) ?>
        </div>
    <?php endif; ?>

    <!-- GAME OVERVIEW -->
    <div class="mg-overview-grid">

        <div class="mg-overview-card">
            <div class="mg-overview-icon purple">
                <i class="bi bi-controller"></i>
            </div>
            <div class="mg-overview-label">Game Code</div>
            <div class="mg-overview-value mg-game-code">
                <?= htmlspecialchars($game['game_code']) ?>
            </div>
        </div>

        <div class="mg-overview-card">
            <div class="mg-overview-icon blue">
                <i class="bi bi-trophy"></i>
            </div>
            <div class="mg-overview-label">Winners Needed</div>
            <div class="mg-overview-value">
                <?= (int)$game['winners'] ?>
            </div>
        </div>

        <div class="mg-overview-card">
            <div class="mg-overview-icon green">
                <i class="bi bi-award"></i>
            </div>
            <div class="mg-overview-label">Winners Declared</div>
            <div class="mg-overview-value">
                <?php
                $winners = json_decode($game['game_winners'], true);
                echo !empty($winners) ? count($winners) : 0;
                ?>
                <span class="mg-overview-muted">/ <?= (int)$game['winners'] ?></span>
            </div>
        </div>

        <div class="mg-overview-card">
            <div class="mg-overview-icon orange">
                <i class="bi bi-people"></i>
            </div>
            <div class="mg-overview-label">Joined Players</div>
            <div class="mg-overview-value" id="players-count">
                <?= $playerCount ?>
            </div>
        </div>

    </div>

    <!-- GAME SETTINGS -->
    <div class="mg-settings-card">

        <div class="mg-section-header">
            <div class="mg-section-heading">
                <div class="mg-section-icon">
                    <i class="bi bi-sliders"></i>
                </div>
                <div>
                    <h5>Game Settings</h5>
                    <p>Configure how numbers are revealed and drawn.</p>
                </div>
            </div>
        </div>

        <div class="mg-settings-grid">

            <!-- REVEAL DURATION -->
            <div class="mg-setting-box">
                <div class="mg-setting-title">
                    <i class="bi bi-hourglass-split"></i>
                    Number Reveal Animation
                </div>

                <p class="mg-setting-description">
                    Set how long the number spins on the game screen before the actual number is revealed.
                </p>

                <form method="POST" class="mg-setting-form">
                    <div class="mg-input-with-unit">
                        <input type="number"
                               name="reveal_duration"
                               class="mg-input"
                               min="1"
                               max="5"
                               step="0.1"
                               value="<?= htmlspecialchars((string)$revealDurationSeconds) ?>"
                               required>
                        <span>seconds</span>
                    </div>

                    <button type="submit" name="update_reveal_duration" class="mg-save-btn">
                        <i class="bi bi-check2"></i>
                        Save
                    </button>
                </form>

                <div class="mg-setting-hint">
                    <i class="bi bi-info-circle"></i>
                    Allowed duration: 1–5 seconds.
                </div>
            </div>

            <!-- DRAW MODE -->
            <div class="mg-setting-box">
                <div class="mg-setting-title">
                    <i class="bi bi-arrow-repeat"></i>
                    Number Draw Mode
                </div>

                <p class="mg-setting-description">
                    Choose whether numbers are drawn automatically or by the operator.
                </p>

                <form method="POST" id="drawModeForm">

                    <div class="mg-draw-options">

                        <label class="mg-draw-option" for="drawModeAuto">
                            <input class="mg-draw-radio"
                                   type="radio"
                                   name="draw_mode"
                                   id="drawModeAuto"
                                   value="auto"
                                   <?= $drawMode === 'auto' ? 'checked' : '' ?>>

                            <div class="mg-draw-option-content">
                                <i class="bi bi-play-circle"></i>
                                <div>
                                    <strong>Automatic</strong>
                                    <span>Draw numbers automatically</span>
                                </div>
                            </div>
                        </label>

                        <label class="mg-draw-option" for="drawModeManual">
                            <input class="mg-draw-radio"
                                   type="radio"
                                   name="draw_mode"
                                   id="drawModeManual"
                                   value="manual"
                                   <?= $drawMode === 'manual' ? 'checked' : '' ?>>

                            <div class="mg-draw-option-content">
                                <i class="bi bi-hand-index-thumb"></i>
                                <div>
                                    <strong>Manual</strong>
                                    <span>Operator draws each number</span>
                                </div>
                            </div>
                        </label>

                    </div>

                    <div class="mg-interval-wrap" id="drawIntervalWrap"
                         style="<?= $drawMode === 'manual' ? 'display:none;' : '' ?>">

                        <div class="mg-input-with-unit">
                            <input type="number"
                                   name="draw_interval_seconds"
                                   class="mg-input"
                                   min="1"
                                   max="60"
                                   step="1"
                                   value="<?= $drawIntervalSeconds ?>">
                            <span>seconds</span>
                        </div>

                        <small>Interval between automatic draws.</small>
                    </div>

                    <button type="submit" name="update_draw_mode" class="mg-save-btn">
                        <i class="bi bi-check2"></i>
                        Save Draw Settings
                    </button>

                </form>

                <div class="mg-setting-hint">
                    <i class="bi bi-info-circle"></i>
                    Automatic draws follow the configured interval. Manual draws require operator action.
                </div>
            </div>

        </div>
    </div>

    <!-- PLAYER MANAGEMENT -->
    <div class="mg-players-card">

        <div class="mg-players-header">
            <div class="mg-section-heading">
                <div class="mg-section-icon">
                    <i class="bi bi-people"></i>
                </div>
                <div>
                    <h5>Joined Players</h5>
                    <p>Manage player settings and restrictions.</p>
                </div>
            </div>

            <div class="mg-player-counter">
                <i class="bi bi-person-check"></i>
                <span id="players-count-badge"><?= $playerCount ?></span>
                <span>Players</span>
            </div>
        </div>

        <div class="mg-table-container">
            <table id="playersTable" class="table align-middle w-100">
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

        <!-- START STATUS -->
        <?php if (!$game['started']): ?>

            <?php if ($game['start_mode'] === 'timer' && $game['scheduled_start']): ?>
                <div class="mg-countdown-panel">
                    <div class="mg-countdown-icon">
                        <i class="bi bi-clock"></i>
                    </div>
                    <div>
                        <strong>Scheduled Game Start</strong>
                        <p>
                            Scheduled at
                            <?= date('h:i A', strtotime($game['scheduled_start'])) ?>
                        </p>
                        <div id="countdown" class="mg-countdown-value">
                            Waiting for countdown...
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($playerCount === 0): ?>

                <div class="mg-start-panel waiting">
                    <div class="mg-start-icon">
                        <i class="bi bi-hourglass"></i>
                    </div>
                    <div class="mg-start-text">
                        <strong>Waiting for Players</strong>
                        <p>Players need to join before the game can begin.</p>
                    </div>
                    <button type="button" class="mg-start-btn disabled" disabled>
                        <i class="bi bi-play-fill"></i>
                        Start Game
                    </button>
                </div>

            <?php else: ?>

                <div class="mg-start-panel ready">
                    <div class="mg-start-icon">
                        <i class="bi bi-play-circle"></i>
                    </div>
                    <div class="mg-start-text">
                        <strong>Game Ready</strong>
                        <p><?= $playerCount ?> player<?= $playerCount !== 1 ? 's' : '' ?> joined. You can start the game now.</p>
                    </div>

                    <form method="POST" action="functions/start_game.php">
                        <input type="hidden" name="game_id" value="<?= $gameId ?>">
                        <button type="submit" class="mg-start-btn">
                            <i class="bi bi-play-fill"></i>
                            Start Game<?= $game['start_mode'] === 'timer' ? ' Now' : '' ?>
                        </button>
                    </form>
                </div>

            <?php endif; ?>

        <?php else: ?>

            <div class="mg-start-panel started">
                <div class="mg-start-icon">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <div class="mg-start-text">
                    <strong>Game is Running</strong>
                    <p>The game has already started.</p>
                </div>
                <span class="mg-started-status">
                    <i class="bi bi-check-circle"></i>
                    Started
                </span>
            </div>

        <?php endif; ?>

        <!-- GAME SCREEN BUTTON -->
        <a href="screen.php?game_id=<?= $gameId ?>"
           target="_blank"
           class="mg-screen-btn">
            <i class="bi bi-display"></i>
            Open Game Screen
            <i class="bi bi-box-arrow-up-right mg-screen-arrow"></i>
        </a>

    </div>

</div>

<link rel="stylesheet" href="css/datatables.min.css">
<link rel="stylesheet" href="css/manage_game.css">

<script src="js/jquery-4.0.0.min.js"></script>
<script src="js/datatables.min.js"></script>
<script src="js/game/manage_game.js"></script>

</body>
</html>
