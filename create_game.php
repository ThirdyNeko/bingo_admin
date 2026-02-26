<?php
require_once 'config/db.php';
include 'partials/header.php';
include 'partials/sidebar.php';

$success = '';
$error = '';

function generateGameCode($length = 5) {
    return 'BINGO-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, $length));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pattern_json = $_POST['pattern_json'] ?? '';
    $winners = (int) ($_POST['winners'] ?? 1);

    if (empty($pattern_json) || $winners <= 0) {
        $error = "All fields are required.";
    } else {
        $gameCode = generateGameCode();
        $insert = $pdo->prepare("
            INSERT INTO game (pattern, winners, game_winners, game_code)
            VALUES (?, ?, 0, ?)
        ");
        $insert->execute([$pattern_json, $winners, $gameCode]);
        $success = "Game Created Successfully! Code: $gameCode";
    }
}
?>

<div class="col-md-10 p-4">
    <h3 class="mb-4">Create Game</h3>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <!-- KEEP YOUR BINGO PATTERN TABLE HERE (same as before) -->
</div>

</div></div>
</body>
</html>