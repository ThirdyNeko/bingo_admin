<?php
require_once 'config/db.php';

if (!isset($_GET['session_id'])) {
    die("Session not found.");
}

$sessionId = $_GET['session_id'];

/* ==============================
   GET ALL ROUNDS IN SESSION
============================== */
$gamesStmt = $pdo->prepare("
    SELECT id, game_code
    FROM game
    WHERE session_id = ?
    ORDER BY id ASC
");
$gamesStmt->execute([$sessionId]);
$games = $gamesStmt->fetchAll();

if (!$games) {
    die("Session does not exist.");
}

$rounds = [];

foreach ($games as $game) {
    $gameId = $game['id'];

    /* ==============================
       GET WINNERS
    ============================== */
    $winnersStmt = $pdo->prepare("
        SELECT u.name, gwq.level
        FROM game_winner_queue gwq
        JOIN user_cards uc ON gwq.card_id = uc.id
        JOIN users u ON uc.user_id = u.id
        WHERE gwq.game_id = ? AND gwq.claimed = 1
        ORDER BY gwq.level ASC
    ");
    $winnersStmt->execute([$gameId]);
    $winners = $winnersStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($winners)) {
        // No claimed winners for this round yet — skip it
        continue;
    }

    /* ==============================
       GET PRIZE NAME
    ============================== */
    $prizeStmt = $pdo->prepare("
        SELECT TOP 1 name
        FROM game_prize
        WHERE game_id = ?
        ORDER BY id ASC
    ");
    $prizeStmt->execute([$gameId]);
    $prize = $prizeStmt->fetch();

    $rounds[] = [
        'game_code'  => $game['game_code'],
        'prize_name' => $prize['name'] ?? '',
        'winners'    => $winners,
    ];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Session Winners</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/winner.css" rel="stylesheet">
    <link href="css/winner_pages.css" rel="stylesheet">
    <script src="sweetalert/dist/sweetalert2.all.min.js"></script>
</head>
<body data-complete-title="Session Complete!">

<div class="container py-5 text-center">

    <h1 class="display-2 text-success mb-4">
        🎉 Session Winners 🎉
    </h1>

    <?php if (empty($rounds)): ?>
        <div class="alert alert-warning">
            No winners recorded for this session.
        </div>
    <?php else: ?>

        <?php foreach ($rounds as $round): ?>

            <h6 class="round-label text-white-50 mt-5 mb-3">
                <?= htmlspecialchars($round['game_code']) ?>
            </h6>

            <ul class="winner-list">
                <?php foreach ($round['winners'] as $index => $winner): ?>

                    <?php
                    $rank = $index + 1;
                    $medal = '';
                    $class = '';

                    if ($rank == 1) {
                        $medal = "🥇";
                        $class = "gold";
                    } elseif ($rank == 2) {
                        $medal = "🥈";
                        $class = "silver";
                    } elseif ($rank == 3) {
                        $medal = "🥉";
                        $class = "bronze";
                    } else {
                        $medal = "🏅";
                    }
                    ?>

                    <li>
                        <span class="rank <?= $class ?>"><?= $medal ?> #<?= $rank ?></span>
                        <span class="name"><?= htmlspecialchars($winner['name']) ?></span>
                        <span class="prize">🏆 <?= htmlspecialchars($round['prize_name']) ?></span>
                    </li>

                <?php endforeach; ?>
            </ul>

        <?php endforeach; ?>

    <?php endif; ?>

</div>

<script src="js/winners/winner_complete.js"></script>

</body>
</html>