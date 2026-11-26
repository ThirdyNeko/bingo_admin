<?php
require_once 'config/db.php';

$success = '';
$error = '';
$error_field = ''; // which input to highlight red on reload, if any
$card_change_enabled = false; // default for GET requests (no form submitted yet)
$prize_missing = []; // which prize fields are missing when the "all or nothing" rule trips

function generateGameCode($length = 6) {
    return strtoupper(substr(bin2hex(random_bytes(6)), 0, $length));
}

// ======= PROCESS FORM BEFORE HEADER/HTML ======
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pattern_json  = $_POST['pattern_json'] ?? '';
    $winners       = (int) ($_POST['winners'] ?? 1);
    $start_mode    = $_POST['start_mode'] ?? 'manual';
    $timer_minutes = isset($_POST['timer_minutes'])
        ? (int) $_POST['timer_minutes']
        : null;

    $prize_name        = trim($_POST['prize_name'] ?? '');
    $prize_description = trim($_POST['prize_description'] ?? '');

    $prize_picture_uploaded = isset($_FILES['prize_picture'])
        && $_FILES['prize_picture']['error'] === UPLOAD_ERR_OK;

    // ==========================================
    // CARD CHANGE WINDOW (how long players may
    // still swap their bingo card after creation)
    // ==========================================
    $card_change_enabled = isset($_POST['enable_card_change_limit'])
        && $_POST['enable_card_change_limit'] === '1';

    $card_change_minutes = $card_change_enabled
        ? (int) ($_POST['card_change_minutes'] ?? 0)
        : null;

    // How many times a player may change a given card within the window
    // above. Independent of the window duration; defaults to 2 when the
    // window is enabled but the field wasn't posted for some reason.
    $card_change_limit = $card_change_enabled
        ? (int) ($_POST['card_change_limit'] ?? 2)
        : null;

    // Enforce max lengths server-side as well (defense in depth)
    if (mb_strlen($prize_name) > 50) {
        $prize_name = mb_substr($prize_name, 0, 50);
    }
    if (mb_strlen($prize_description) > 100) {
        $prize_description = mb_substr($prize_description, 0, 100);
    }

    // ==========================================
    // PRIZE FIELDS ARE ALL-OR-NOTHING
    // Prize is optional as a whole, but if any one of
    // name/description/picture is filled in, the other two
    // become required too.
    // ==========================================
    $prize_any_filled = ($prize_name !== '')
        || ($prize_description !== '')
        || $prize_picture_uploaded;

    if ($prize_any_filled) {
        if ($prize_name === '') {
            $prize_missing[] = 'prize_name';
        }
        if ($prize_description === '') {
            $prize_missing[] = 'prize_description';
        }
        if (!$prize_picture_uploaded) {
            $prize_missing[] = 'prize_picture';
        }
    }

    if (empty($pattern_json)) {

        $error = "All fields are required.";

    } elseif (
        !$winners || $winners <= 0
    ) {

        $error = "Please enter a number of winners between 1 and 5.";
        $error_field = 'winners';

    } elseif (
        $winners > 5
    ) {

        $error = "Number of winners must be between 1 and 5.";
        $error_field = 'winners';

    } elseif (
        $start_mode === 'timer' &&
        (!$timer_minutes || $timer_minutes <= 0)
    ) {

        $error = "Please enter a timer duration between 1 and 10 minutes.";
        $error_field = 'timer_minutes';

    } elseif (
        $start_mode === 'timer' &&
        $timer_minutes > 10
    ) {

        $error = "Timer duration must be between 1 and 10 minutes.";
        $error_field = 'timer_minutes';

    } elseif (
        $card_change_enabled &&
        (!$card_change_minutes || $card_change_minutes <= 0)
    ) {

        $error = "Please enter a card change window between 1 and 5 minutes.";
        $error_field = 'card_change_minutes';

    } elseif (
        $card_change_enabled &&
        $card_change_minutes > 5
    ) {

        $error = "Card change window must be between 1 and 5 minutes.";
        $error_field = 'card_change_minutes';

    } elseif (
        $card_change_enabled &&
        (!$card_change_limit || $card_change_limit <= 0)
    ) {

        $error = "Please enter a card change limit between 1 and 5.";
        $error_field = 'card_change_limit';

    } elseif (
        $card_change_enabled &&
        $card_change_limit > 5
    ) {

        $error = "Card change limit must be between 1 and 5.";
        $error_field = 'card_change_limit';

    } elseif (mb_strlen($prize_name) > 50) {

        $error = "Prize name must be 50 characters or fewer.";

    } elseif (mb_strlen($prize_description) > 100) {

        $error = "Prize description must be 100 characters or fewer.";

    } elseif (!empty($prize_missing)) {

        $error = "If you're adding a prize, please fill in the name, description, and picture.";
        $error_field = 'prize';

    } else {

        // ==========================================
        // CALCULATE SCHEDULED START
        // ==========================================
        $scheduledStart = null;

        if ($start_mode === 'timer') {
            $scheduledStart = date(
                'Y-m-d H:i:s',
                strtotime("+{$timer_minutes} minutes")
            );
        } else {
            $timer_minutes = null;
        }

        // ==========================================
        // CARD CHANGE WINDOW
        // ==========================================
        // Only the duration is known at creation time. The window
        // starts when the game actually starts (started = 1), which
        // for manual-start games isn't known yet — so card_change_deadline
        // is NOT set here. It must be calculated and written wherever
        // the "start game" action flips started to 1.
        if (!$card_change_enabled) {
            $card_change_minutes = null;
            $card_change_limit = null;
        }

        // ==========================================
        // GENERATE UNIQUE GAME CODE + SESSION ID
        // ==========================================
        do {
            $gameCode = generateGameCode();

            $checkCode = $pdo->prepare("
                SELECT COUNT(*)
                FROM game
                WHERE game_code = ?
            ");

            $checkCode->execute([$gameCode]);

            $codeExists = (int) $checkCode->fetchColumn() > 0;

        } while ($codeExists);

        // session_id is date-based, so all games created the same
        // day automatically share the same session_id
        $sessionId = date('Ymd');

        // ==========================================
        // INSERT GAME
        // ==========================================
        $insert = $pdo->prepare("
            INSERT INTO game (
                pattern,
                winners,
                game_winners,
                game_code,
                session_id,
                started,
                start_mode,
                timer_minutes,
                scheduled_start,
                card_change_minutes,
                card_change_limit
            )
            VALUES (?, ?, 0, ?, ?, 0, ?, ?, ?, ?, ?)
        ");

        $insert->execute([
            $pattern_json,
            $winners,
            $gameCode,
            $sessionId,
            $start_mode,
            $timer_minutes,
            $scheduledStart,
            $card_change_minutes,
            $card_change_limit
        ]);

        $gameId = $pdo->lastInsertId();

        // ==========================================
        // INSERT GAME PRIZE (optional)
        // ==========================================
        if ($prize_name !== '') {

            $picture_binary = null;

            if (
                isset($_FILES['prize_picture']) &&
                $_FILES['prize_picture']['error'] === UPLOAD_ERR_OK
            ) {
                $picture_binary = file_get_contents(
                    $_FILES['prize_picture']['tmp_name']
                );
            }

            $insertPrize = $pdo->prepare("
                INSERT INTO game_prize (
                    game_id,
                    name,
                    description,
                    picture
                )
                VALUES (?, ?, ?, ?)
            ");

            $insertPrize->bindValue(1, $gameId);
            $insertPrize->bindValue(2, $prize_name);
            $insertPrize->bindValue(3, $prize_description !== '' ? $prize_description : null);

            $pictureStream = null;

            if ($picture_binary !== null) {
                // pdo_sqlsrv needs a stream (not a raw string) to treat the
                // param as binary instead of trying to convert it as UCS-2 text
                $pictureStream = fopen('php://memory', 'r+');
                fwrite($pictureStream, $picture_binary);
                rewind($pictureStream);

                $insertPrize->bindParam(
                    4,
                    $pictureStream,
                    PDO::PARAM_LOB,
                    0,
                    PDO::SQLSRV_ENCODING_BINARY
                );
            } else {
                $insertPrize->bindValue(4, null, PDO::PARAM_NULL);
            }

            $insertPrize->execute();

            if ($pictureStream !== null) {
                fclose($pictureStream);
            }
        }

        // ==========================================
        // REDIRECT
        // ==========================================
        header(
            "Location: manage_game.php?game_id=" .
            urlencode($gameId)
        );
        exit;
    }
}

// ======= NOW INCLUDE HEADER & SIDEBAR ======
include 'partials/header.php';
include 'partials/sidebar.php';
?>

<link rel="stylesheet" href="css/create_game.css">

<div class="col-md-10 create-game-content">

    <!-- HEADER -->
    <div class="cg-page-header">
        <div>
            <span class="cg-eyebrow">GAME MANAGEMENT</span>
            <h2>Create Game</h2>
            <p>Configure your game, winning pattern, and optional prize.</p>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger cg-alert">
            <i class="bi bi-exclamation-circle me-2"></i>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success cg-alert">
            <i class="bi bi-check-circle me-2"></i>
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="createGameForm">

        <!-- GAME CONFIGURATION -->
        <div class="cg-card">

            <div class="cg-card-header">
                <div class="cg-section-icon cg-icon-blue">
                    <i class="bi bi-sliders"></i>
                </div>

                <div>
                    <h5>Game Configuration</h5>
                    <p>Set the basic rules for your Bingo game.</p>
                </div>
            </div>

            <div class="cg-card-body">

                <!-- WINNERS -->
                <div class="cg-form-group">
                    <label for="winners" class="cg-label">
                        Number of Winners
                    </label>

                    <input type="number"
                           name="winners"
                           id="winners"
                           class="form-control cg-input<?= $error_field === 'winners' ? ' is-invalid' : '' ?>"
                           min="1"
                           max="5"
                           value="<?= isset($_POST['winners']) ? (int) $_POST['winners'] : 1 ?>"
                           required>

                    <div class="invalid-feedback" id="winners_feedback">
                        <?= $error_field === 'winners' ? htmlspecialchars($error) : '' ?>
                    </div>

                    <div class="cg-help">
                        <i class="bi bi-info-circle"></i>
                        You can set between 1 and 5 winners.
                    </div>
                </div>

                <!-- START MODE -->
                <div class="cg-form-group">

                    <label class="cg-label d-block">Game Start Mode</label>

                    <?php $start_mode_posted = $_POST['start_mode'] ?? 'manual'; ?>

                    <div class="cg-mode-selection">

                        <div class="cg-mode-option">
                            <input type="radio"
                                   class="btn-check"
                                   name="start_mode"
                                   id="mode_manual"
                                   value="manual"
                                   <?= $start_mode_posted !== 'timer' ? 'checked' : '' ?>>

                            <label for="mode_manual" class="cg-mode-label">
                                <div class="cg-mode-icon">
                                    <i class="bi bi-hand-index-thumb"></i>
                                </div>

                                <div>
                                    <strong>Manual Start</strong>
                                    <small>Start the game when ready.</small>
                                </div>

                                <i class="bi bi-check-circle cg-mode-check"></i>
                            </label>
                        </div>

                        <div class="cg-mode-option">
                            <input type="radio"
                                   class="btn-check"
                                   name="start_mode"
                                   id="mode_timer"
                                   value="timer"
                                   <?= $start_mode_posted === 'timer' ? 'checked' : '' ?>>

                            <label for="mode_timer" class="cg-mode-label">
                                <div class="cg-mode-icon cg-mode-icon-purple">
                                    <i class="bi bi-clock"></i>
                                </div>

                                <div>
                                    <strong>Timer Start</strong>
                                    <small>Automatically start after a delay.</small>
                                </div>

                                <i class="bi bi-check-circle cg-mode-check"></i>
                            </label>
                        </div>

                    </div>

                    <!-- TIMER INPUT -->
                    <div id="timerInputWrap"
                         class="cg-sub-settings mt-3<?= $start_mode_posted === 'timer' ? '' : ' d-none' ?>">

                        <label for="timer_minutes" class="cg-label">
                            Auto-start after (minutes)
                        </label>

                        <input type="number"
                               name="timer_minutes"
                               id="timer_minutes"
                               class="form-control cg-input<?= $error_field === 'timer_minutes' ? ' is-invalid' : '' ?>"
                               min="1"
                               max="10"
                               value="<?= isset($_POST['timer_minutes']) ? (int) $_POST['timer_minutes'] : 2 ?>">

                        <div class="invalid-feedback" id="timer_minutes_feedback">
                            <?= $error_field === 'timer_minutes' ? htmlspecialchars($error) : '' ?>
                        </div>

                        <div class="cg-help">
                            <i class="bi bi-clock-history"></i>
                            Automatically starts 1–10 minutes after creation.
                        </div>
                    </div>

                </div>

            </div>
        </div>


        <!-- CARD CHANGE SETTINGS -->
        <div class="cg-card">

            <div class="cg-card-header">
                <div class="cg-section-icon cg-icon-purple">
                    <i class="bi bi-arrow-repeat"></i>
                </div>

                <div>
                    <h5>Card Change Settings</h5>
                    <p>Control when and how players may change their cards.</p>
                </div>
            </div>

            <div class="cg-card-body">

                <div class="cg-toggle-panel">

                    <div class="cg-toggle-text">
                        <strong>Enable Card Change Limit</strong>
                        <small>
                            Restrict card changes after the game begins.
                        </small>
                    </div>

                    <div class="form-check form-switch cg-switch">
                        <input class="form-check-input"
                               type="checkbox"
                               role="switch"
                               id="enable_card_change_limit"
                               name="enable_card_change_limit"
                               value="1"
                               <?= $card_change_enabled ? 'checked' : '' ?>>
                    </div>

                </div>

                <div id="cardChangeInputWrap"
                     class="<?= $card_change_enabled ? '' : 'd-none' ?>">

                    <div class="cg-sub-settings">

                        <div class="cg-form-group">
                            <label for="card_change_minutes" class="cg-label">
                                Card Change Window
                            </label>

                            <div class="cg-input-unit">
                                <input type="number"
                                       name="card_change_minutes"
                                       id="card_change_minutes"
                                       class="form-control cg-input<?= $error_field === 'card_change_minutes' ? ' is-invalid' : '' ?>"
                                       min="1"
                                       max="5"
                                       value="<?= isset($_POST['card_change_minutes']) ? (int) $_POST['card_change_minutes'] : 2 ?>">

                                <span>minutes</span>
                            </div>

                            <div class="invalid-feedback" id="card_change_minutes_feedback">
                                <?= $error_field === 'card_change_minutes' ? htmlspecialchars($error) : '' ?>
                            </div>

                            <div class="cg-help">
                                <i class="bi bi-hourglass-split"></i>
                                The countdown begins when the game actually starts.
                            </div>
                        </div>

                        <div class="cg-form-group mb-0">
                            <label for="card_change_limit" class="cg-label">
                                Maximum Changes per Card
                            </label>

                            <input type="number"
                                   name="card_change_limit"
                                   id="card_change_limit"
                                   class="form-control cg-input<?= $error_field === 'card_change_limit' ? ' is-invalid' : '' ?>"
                                   min="1"
                                   max="5"
                                   value="<?= isset($_POST['card_change_limit']) ? (int) $_POST['card_change_limit'] : 2 ?>">

                            <div class="invalid-feedback" id="card_change_limit_feedback">
                                <?= $error_field === 'card_change_limit' ? htmlspecialchars($error) : '' ?>
                            </div>

                            <div class="cg-help">
                                <i class="bi bi-arrow-repeat"></i>
                                Maximum of 1–5 card changes per player.
                            </div>
                        </div>

                    </div>

                </div>

            </div>
        </div>


        <!-- PRIZE -->
        <div class="cg-card">

            <div class="cg-card-header">
                <div class="cg-section-icon cg-icon-green">
                    <i class="bi bi-gift"></i>
                </div>

                <div>
                    <h5>Game Prize</h5>
                    <p>Attach a prize to this game if applicable.</p>
                </div>

                <span class="cg-optional-tag">OPTIONAL</span>
            </div>

            <div class="cg-card-body">

                <div class="cg-prize-note">
                    <i class="bi bi-info-circle"></i>
                    <span>
                        Leave all fields empty to skip. If you add a prize,
                        the name, description, and picture are all required.
                    </span>
                </div>

                <?php if ($error_field === 'prize'): ?>
                    <div class="alert alert-danger py-2 px-3">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <div class="cg-form-group">
                    <label for="prize_name" class="cg-label">
                        Prize Name
                    </label>

                    <input type="text"
                           name="prize_name"
                           id="prize_name"
                           maxlength="50"
                           class="form-control cg-input mb-1<?= in_array('prize_name', $prize_missing) ? ' is-invalid' : '' ?>"
                           value="<?= isset($_POST['prize_name']) ? htmlspecialchars($_POST['prize_name']) : '' ?>"
                           placeholder="Enter prize name">

                    <div class="form-text text-end cg-counter">
                        <span id="prize_name_count">0</span>/50
                    </div>
                </div>

                <div class="cg-form-group">
                    <label for="prize_description" class="cg-label">
                        Prize Description
                    </label>

                    <textarea name="prize_description"
                              id="prize_description"
                              class="form-control cg-input mb-1<?= in_array('prize_description', $prize_missing) ? ' is-invalid' : '' ?>"
                              rows="3"
                              maxlength="100"
                              placeholder="Describe the prize"><?= isset($_POST['prize_description']) ? htmlspecialchars($_POST['prize_description']) : '' ?></textarea>

                    <div class="form-text text-end cg-counter">
                        <span id="prize_description_count">0</span>/100
                    </div>
                </div>

                <div class="cg-form-group mb-0">
                    <label for="prize_picture" class="cg-label">
                        Prize Picture
                    </label>

                    <input type="file"
                           name="prize_picture"
                           id="prize_picture"
                           class="form-control cg-input<?= in_array('prize_picture', $prize_missing) ? ' is-invalid' : '' ?>"
                           accept="image/*">

                    <?php if (in_array('prize_picture', $prize_missing)): ?>
                        <div class="invalid-feedback d-block">
                            A prize picture is required.
                        </div>
                    <?php endif; ?>

                    <div class="cg-help">
                        <i class="bi bi-image"></i>
                        Upload an image of the prize.
                    </div>
                </div>

            </div>
        </div>


        <!-- WINNING PATTERN -->
        <div class="cg-card">

            <div class="cg-card-header">
                <div class="cg-section-icon cg-icon-orange">
                    <i class="bi bi-grid-3x3"></i>
                </div>

                <div>
                    <h5>Winning Pattern</h5>
                    <p>Select the squares required to win.</p>
                </div>
            </div>

            <div class="cg-card-body">

                <div class="cg-pattern-instructions">
                    <i class="bi bi-cursor"></i>
                    Click the squares to select your winning pattern.
                </div>

                <div class="cg-pattern-container">

                    <table class="table bingo-table m-0">
                        <thead>
                            <tr>
                                <th>B</th>
                                <th>I</th>
                                <th>N</th>
                                <th>G</th>
                                <th>O</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php for ($r = 0; $r < 5; $r++): ?>
                                <tr>
                                    <?php for ($c = 0; $c < 5; $c++): ?>

                                        <?php if ($r == 2 && $c == 2): ?>
                                            <td class="free">
                                                <i class="bi bi-star-fill"></i>
                                                <span>FREE</span>
                                            </td>
                                        <?php else: ?>
                                            <td class="pattern-cell"
                                                data-row="<?= $r ?>"
                                                data-col="<?= $c ?>">
                                                <span class="pattern-check">
                                                    <i class="bi bi-check-lg"></i>
                                                </span>
                                            </td>
                                        <?php endif; ?>

                                    <?php endfor; ?>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>

                </div>

                <input type="hidden" name="pattern_json" id="pattern_json" required>

                <div class="cg-pattern-footer">
                    <div class="cg-pattern-hint">
                        <i class="bi bi-lightbulb"></i>
                        <span>Select multiple squares to create a custom pattern.</span>
                    </div>

                    <button type="button" id="resetPattern" class="cg-reset-btn">
                        <i class="bi bi-arrow-counterclockwise"></i>
                        Reset Pattern
                    </button>
                </div>

            </div>
        </div>


        <!-- ACTION BUTTONS -->
        <div class="cg-form-actions">

            <button type="button"
                    id="resetForm"
                    class="cg-reset-btn cg-reset-main"
                    onclick="document.getElementById('createGameForm').reset();">
                Reset Form
            </button>

            <button type="submit" class="cg-submit-btn">
                <i class="bi bi-bullseye"></i>
                Create Game
            </button>

        </div>

    </form>

</div>

<script src="js/game/create_game.js"></script>
<script src="js/game/create_game_form.js"></script>

</div>
</body>
</html>