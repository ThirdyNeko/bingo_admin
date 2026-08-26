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

        $error = "Please enter a timer duration between 1 and 5 minutes.";
        $error_field = 'timer_minutes';

    } elseif (
        $start_mode === 'timer' &&
        $timer_minutes > 5
    ) {

        $error = "Timer duration must be between 1 and 5 minutes.";
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

<div class="col-md-10 p-4 d-flex justify-content-center">
    <div style="width:100%; max-width:600px;">
    <h3 class="mb-4">Create Game</h3>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white">
            Define Winning Pattern
        </div>
        <div class="card-body">

            <form method="POST" enctype="multipart/form-data">

                <!-- Number of Winners -->
                <div class="mb-4">
                    <label class="form-label fw-bold">Number of Winners</label>
                    <input type="number" name="winners"
                           id="winners"
                           class="form-control<?= $error_field === 'winners' ? ' is-invalid' : '' ?>"
                           min="1" max="5"
                           value="<?= isset($_POST['winners']) ? (int) $_POST['winners'] : 1 ?>"
                           required>
                    <div class="invalid-feedback" id="winners_feedback"><?= $error_field === 'winners' ? htmlspecialchars($error) : '' ?></div>
                    <div class="form-text">
                        Must be between 1 and 5.
                    </div>
                </div>

                <!-- Start Mode -->
                <div class="mb-4">
                    <label class="form-label fw-bold d-block">Game Start</label>

                    <?php $start_mode_posted = $_POST['start_mode'] ?? 'manual'; ?>
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="start_mode"
                               id="mode_manual" value="manual"
                               <?= $start_mode_posted !== 'timer' ? 'checked' : '' ?>>
                        <label class="btn btn-outline-primary" for="mode_manual">
                            🖐️ Manual
                        </label>

                        <input type="radio" class="btn-check" name="start_mode"
                               id="mode_timer" value="timer"
                               <?= $start_mode_posted === 'timer' ? 'checked' : '' ?>>
                        <label class="btn btn-outline-primary" for="mode_timer">
                            ⏱️ Timer
                        </label>
                    </div>

                    <div id="timerInputWrap" class="mt-3<?= $start_mode_posted === 'timer' ? '' : ' d-none' ?>">
                        <label class="form-label">Auto-start after (minutes)</label>
                        <input type="number" name="timer_minutes"
                               id="timer_minutes"
                               class="form-control<?= $error_field === 'timer_minutes' ? ' is-invalid' : '' ?>"
                               min="1" max="5"
                               value="<?= isset($_POST['timer_minutes']) ? (int) $_POST['timer_minutes'] : 2 ?>">
                        <div class="invalid-feedback" id="timer_minutes_feedback"><?= $error_field === 'timer_minutes' ? htmlspecialchars($error) : '' ?></div>
                        <div class="form-text">
                            Game will automatically start this many minutes after creation.
                            Must be between 1 and 5 minutes.
                        </div>
                    </div>
                </div>

                <!-- Card Change Window -->
                <div class="mb-4">
                    <label class="form-label fw-bold d-block">Card Change Window</label>

                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="enable_card_change_limit" name="enable_card_change_limit" value="1"
                               <?= $card_change_enabled ? 'checked' : '' ?>>
                        <label class="form-check-label" for="enable_card_change_limit">
                            Limit how long players can change their cards
                        </label>
                    </div>

                    <div id="cardChangeInputWrap" class="<?= $card_change_enabled ? '' : 'd-none' ?>">
                        <label class="form-label">Allow card changes for (minutes)</label>
                        <input type="number" name="card_change_minutes"
                               id="card_change_minutes"
                               class="form-control<?= $error_field === 'card_change_minutes' ? ' is-invalid' : '' ?>"
                               min="1" max="5"
                               value="<?= isset($_POST['card_change_minutes']) ? (int) $_POST['card_change_minutes'] : 2 ?>">
                        <div class="invalid-feedback" id="card_change_minutes_feedback"><?= $error_field === 'card_change_minutes' ? htmlspecialchars($error) : '' ?></div>
                        <div class="form-text">
                            Players won't be able to change their bingo card after this many
                            minutes from when the game starts (not from creation). Leave the
                            switch off to allow changes at any time. Must be between 1 and 5 minutes.
                        </div>

                        <label class="form-label mt-3">Max card changes per card</label>
                        <input type="number" name="card_change_limit"
                               id="card_change_limit"
                               class="form-control<?= $error_field === 'card_change_limit' ? ' is-invalid' : '' ?>"
                               min="1" max="5"
                               value="<?= isset($_POST['card_change_limit']) ? (int) $_POST['card_change_limit'] : 2 ?>">
                        <div class="invalid-feedback" id="card_change_limit_feedback"><?= $error_field === 'card_change_limit' ? htmlspecialchars($error) : '' ?></div>
                        <div class="form-text">
                            How many times a player may change a given card within the window above.
                            Must be between 1 and 5.
                        </div>
                    </div>
                </div>

                <!-- Game Prize -->
                <div class="mb-4">
                    <label class="form-label fw-bold d-block">Game Prize (optional)</label>
                    <div class="form-text mb-2">
                        Leave all three blank to skip a prize, or fill in name, description,
                        and picture together — if you fill in one, the others are required too.
                    </div>

                    <?php if ($error_field === 'prize'): ?>
                        <div class="alert alert-danger py-2 px-3 mb-2"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <input type="text" name="prize_name"
                           id="prize_name"
                           class="form-control mb-1<?= in_array('prize_name', $prize_missing) ? ' is-invalid' : '' ?>"
                           maxlength="50"
                           value="<?= isset($_POST['prize_name']) ? htmlspecialchars($_POST['prize_name']) : '' ?>"
                           placeholder="Prize Name">
                    <div class="form-text text-end mb-2">
                        <span id="prize_name_count">0</span>/50
                    </div>

                    <textarea name="prize_description"
                              id="prize_description"
                              class="form-control mb-1<?= in_array('prize_description', $prize_missing) ? ' is-invalid' : '' ?>"
                              rows="2"
                              maxlength="100"
                              placeholder="Prize Description"><?= isset($_POST['prize_description']) ? htmlspecialchars($_POST['prize_description']) : '' ?></textarea>
                    <div class="form-text text-end mb-2">
                        <span id="prize_description_count">0</span>/100
                    </div>

                    <input type="file" name="prize_picture"
                           id="prize_picture"
                           class="form-control<?= in_array('prize_picture', $prize_missing) ? ' is-invalid' : '' ?>"
                           accept="image/*">
                    <?php if (in_array('prize_picture', $prize_missing)): ?>
                        <div class="invalid-feedback d-block">A prize picture is required.</div>
                    <?php endif; ?>
                </div>

                <!-- Bingo Pattern Grid -->
                <div class="text-center mb-4">
                    <table class="table table-bordered table-dark m-auto bingo-table">
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
                            <?php for($r=0;$r<5;$r++): ?>
                                <tr>
                                    <?php for($c=0;$c<5;$c++): ?>
                                        <?php if($r==2 && $c==2): ?>
                                            <td class="free">FREE</td>
                                        <?php else: ?>
                                            <td class="pattern-cell"
                                                data-row="<?= $r ?>"
                                                data-col="<?= $c ?>">
                                            </td>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>

                <input type="hidden" name="pattern_json" id="pattern_json" required>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100">
                        🎯 Create Game
                    </button>
                    <button type="button" id="resetPattern"
                            class="btn btn-outline-secondary w-100">
                        Reset Pattern
                    </button>
                </div>

            </form>

        </div>
    </div>
    </div>
</div>

<script src="js/game/create_game.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    function bindCounter(inputId, countId, max) {
        const input = document.getElementById(inputId);
        const count = document.getElementById(countId);
        if (!input || !count) return;

        function update() {
            count.textContent = input.value.length;
            count.classList.toggle('text-danger', input.value.length >= max);
        }

        input.addEventListener('input', update);
        update();
    }

    bindCounter('prize_name', 'prize_name_count', 50);
    bindCounter('prize_description', 'prize_description_count', 100);

    // Toggle the card change minutes input based on the switch
    const cardChangeSwitch = document.getElementById('enable_card_change_limit');
    const cardChangeWrap = document.getElementById('cardChangeInputWrap');

    if (cardChangeSwitch && cardChangeWrap) {
        cardChangeSwitch.addEventListener('change', function () {
            cardChangeWrap.classList.toggle('d-none', !cardChangeSwitch.checked);
            validateMinutesInput(document.getElementById('card_change_minutes'));
            validateMinutesInput(document.getElementById('card_change_limit'));
        });
    }

    // Highlight winners/timer/card-change inputs red when out of range,
    // and say whether the value is too high or too low.
    function validateMinutesInput(input) {
        if (!input) return true;

        const feedback = document.getElementById(input.id + '_feedback');
        const min = parseInt(input.min, 10);
        const max = parseInt(input.max, 10);
        const value = parseInt(input.value, 10);

        let message = '';

        if (isNaN(value)) {
            message = 'Enter a value.';
        } else if (value < min) {
            message = `Too low — minimum is ${min}.`;
        } else if (value > max) {
            message = `Too high — maximum is ${max}.`;
        }

        const isInvalid = message !== '';
        input.classList.toggle('is-invalid', isInvalid);
        if (feedback) feedback.textContent = message;

        return !isInvalid;
    }

    const winnersInput = document.getElementById('winners');
    const timerMinutesInput = document.getElementById('timer_minutes');
    const cardChangeMinutesInput = document.getElementById('card_change_minutes');
    const cardChangeLimitInput = document.getElementById('card_change_limit');

    [winnersInput, timerMinutesInput, cardChangeMinutesInput, cardChangeLimitInput].forEach(function (input) {
        if (!input) return;
        input.addEventListener('input', function () { validateMinutesInput(input); });
        input.addEventListener('blur', function () { validateMinutesInput(input); });
    });

    // Prize fields: optional as a whole, but if any one is filled the
    // other two become required — checked live and again on submit.
    const prizeNameInput = document.getElementById('prize_name');
    const prizeDescInput = document.getElementById('prize_description');
    const prizePicInput = document.getElementById('prize_picture');

    function validatePrizeFields() {
        if (!prizeNameInput || !prizeDescInput || !prizePicInput) return true;

        const nameFilled = prizeNameInput.value.trim() !== '';
        const descFilled = prizeDescInput.value.trim() !== '';
        const picFilled = prizePicInput.files && prizePicInput.files.length > 0;

        const anyFilled = nameFilled || descFilled || picFilled;

        const nameMissing = anyFilled && !nameFilled;
        const descMissing = anyFilled && !descFilled;
        const picMissing = anyFilled && !picFilled;

        prizeNameInput.classList.toggle('is-invalid', nameMissing);
        prizeDescInput.classList.toggle('is-invalid', descMissing);
        prizePicInput.classList.toggle('is-invalid', picMissing);

        let picFeedback = prizePicInput.parentElement.querySelector('.js-prize-picture-feedback');
        if (picMissing) {
            if (!picFeedback) {
                picFeedback = document.createElement('div');
                picFeedback.className = 'invalid-feedback d-block js-prize-picture-feedback';
                picFeedback.textContent = 'A prize picture is required.';
                prizePicInput.insertAdjacentElement('afterend', picFeedback);
            }
        } else if (picFeedback) {
            picFeedback.remove();
        }

        return !(nameMissing || descMissing || picMissing);
    }

    if (prizeNameInput && prizeDescInput && prizePicInput) {
        [prizeNameInput, prizeDescInput].forEach(function (input) {
            input.addEventListener('input', validatePrizeFields);
            input.addEventListener('blur', validatePrizeFields);
        });
        prizePicInput.addEventListener('change', validatePrizeFields);
    }

    // Only enforce validation on submit for whichever mode/switch is active
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function (e) {
            let valid = true;

            if (!validateMinutesInput(winnersInput)) valid = false;

            const timerMode = document.getElementById('mode_timer');
            if (timerMode && timerMode.checked) {
                if (!validateMinutesInput(timerMinutesInput)) valid = false;
            }

            if (cardChangeSwitch && cardChangeSwitch.checked) {
                if (!validateMinutesInput(cardChangeMinutesInput)) valid = false;
                if (!validateMinutesInput(cardChangeLimitInput)) valid = false;
            }

            if (!validatePrizeFields()) valid = false;

            if (!valid) e.preventDefault();
        });
    }
});
</script>

</div>
</body>
</html>