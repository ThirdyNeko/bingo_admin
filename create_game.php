<?php
require_once 'config/db.php';

$success = '';
$error = '';

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

    // ==========================================
    // CARD CHANGE WINDOW (how long players may
    // still swap their bingo card after creation)
    // ==========================================
    $card_change_enabled = isset($_POST['enable_card_change_limit'])
        && $_POST['enable_card_change_limit'] === '1';

    $card_change_minutes = $card_change_enabled
        ? (int) ($_POST['card_change_minutes'] ?? 0)
        : null;

    // Enforce max lengths server-side as well (defense in depth)
    if (mb_strlen($prize_name) > 50) {
        $prize_name = mb_substr($prize_name, 0, 50);
    }
    if (mb_strlen($prize_description) > 100) {
        $prize_description = mb_substr($prize_description, 0, 100);
    }

    if (empty($pattern_json) || $winners <= 0) {

        $error = "All fields are required.";

    } elseif (
        $start_mode === 'timer' &&
        (!$timer_minutes || $timer_minutes <= 0)
    ) {

        $error = "Please enter a valid timer duration.";

    } elseif (
        $card_change_enabled &&
        (!$card_change_minutes || $card_change_minutes <= 0)
    ) {

        $error = "Please enter a valid card change window in minutes.";

    } elseif (mb_strlen($prize_name) > 50) {

        $error = "Prize name must be 50 characters or fewer.";

    } elseif (mb_strlen($prize_description) > 100) {

        $error = "Prize description must be 100 characters or fewer.";

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
                card_change_minutes
            )
            VALUES (?, ?, 0, ?, ?, 0, ?, ?, ?, ?)
        ");

        $insert->execute([
            $pattern_json,
            $winners,
            $gameCode,
            $sessionId,
            $start_mode,
            $timer_minutes,
            $scheduledStart,
            $card_change_minutes
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
                           class="form-control"
                           min="1"
                           value="1"
                           required>
                </div>

                <!-- Start Mode -->
                <div class="mb-4">
                    <label class="form-label fw-bold d-block">Game Start</label>

                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="start_mode"
                               id="mode_manual" value="manual" checked>
                        <label class="btn btn-outline-primary" for="mode_manual">
                            🖐️ Manual
                        </label>

                        <input type="radio" class="btn-check" name="start_mode"
                               id="mode_timer" value="timer">
                        <label class="btn btn-outline-primary" for="mode_timer">
                            ⏱️ Timer
                        </label>
                    </div>

                    <div id="timerInputWrap" class="mt-3 d-none">
                        <label class="form-label">Auto-start after (minutes)</label>
                        <input type="number" name="timer_minutes"
                               id="timer_minutes"
                               class="form-control"
                               min="1" value="5">
                        <div class="form-text">
                            Game will automatically start this many minutes after creation.
                        </div>
                    </div>
                </div>

                <!-- Card Change Window -->
                <div class="mb-4">
                    <label class="form-label fw-bold d-block">Card Change Window</label>

                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="enable_card_change_limit" name="enable_card_change_limit" value="1">
                        <label class="form-check-label" for="enable_card_change_limit">
                            Limit how long players can change their cards
                        </label>
                    </div>

                    <div id="cardChangeInputWrap" class="d-none">
                        <label class="form-label">Allow card changes for (minutes)</label>
                        <input type="number" name="card_change_minutes"
                               id="card_change_minutes"
                               class="form-control"
                               min="1" value="10">
                        <div class="form-text">
                            Players won't be able to change their bingo card after this many
                            minutes from when the game starts (not from creation). Leave the
                            switch off to allow changes at any time.
                        </div>
                    </div>
                </div>

                <!-- Game Prize -->
                <div class="mb-4">
                    <label class="form-label fw-bold d-block">Game Prize (optional)</label>

                    <input type="text" name="prize_name"
                           id="prize_name"
                           class="form-control mb-1"
                           maxlength="50"
                           placeholder="Prize Name">
                    <div class="form-text text-end mb-2">
                        <span id="prize_name_count">0</span>/50
                    </div>

                    <textarea name="prize_description"
                              id="prize_description"
                              class="form-control mb-1"
                              rows="2"
                              maxlength="100"
                              placeholder="Prize Description"></textarea>
                    <div class="form-text text-end mb-2">
                        <span id="prize_description_count">0</span>/100
                    </div>

                    <input type="file" name="prize_picture"
                           class="form-control"
                           accept="image/*">
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
        });
    }
});
</script>

</div>
</body>
</html>