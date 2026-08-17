<?php
require_once 'config/db.php';

$success = '';
$error = '';

function generateGameCode($length = 5) {
    return strtoupper(substr(bin2hex(random_bytes(5)), 0, $length));
}

// ======= PROCESS FORM BEFORE HEADER/HTML ======
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pattern_json   = $_POST['pattern_json'] ?? '';
    $winners        = (int) ($_POST['winners'] ?? 1);
    $start_mode     = $_POST['start_mode'] ?? 'manual';
    $timer_minutes  = isset($_POST['timer_minutes']) ? (int) $_POST['timer_minutes'] : null;

    if (empty($pattern_json) || $winners <= 0) {
        $error = "All fields are required.";
    } elseif ($start_mode === 'timer' && (!$timer_minutes || $timer_minutes <= 0)) {
        $error = "Please enter a valid timer duration.";
    } else {
        $gameCode = generateGameCode();

        $scheduledStart = null;
        if ($start_mode === 'timer') {
            $scheduledStart = date('Y-m-d H:i:s', strtotime("+{$timer_minutes} minutes"));
        } else {
            $timer_minutes = null;
        }

        $insert = $pdo->prepare("
            INSERT INTO game (pattern, winners, game_winners, game_code, started, start_mode, timer_minutes, scheduled_start)
            VALUES (?, ?, 0, ?, 0, ?, ?, ?)
        ");
        $insert->execute([
            $pattern_json,
            $winners,
            $gameCode,
            $start_mode,
            $timer_minutes,
            $scheduledStart
        ]);

        $gameId = $pdo->lastInsertId();

        // ✅ Redirect before any HTML
        header("Location: manage_game.php?game_id=" . $gameId);
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

            <form method="POST">

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

</div>
</body>
</html>