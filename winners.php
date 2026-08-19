<?php
require_once 'config/db.php';

if (!isset($_GET['game_id'])) {
    die("Game not found.");
}

$gameId = (int) $_GET['game_id'];

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

$totalDrawn = count(json_decode($game['drawn_numbers'] ?? '[]', true));

/* ==============================
   GET GAME PRIZE (photo + name)
============================== */
$prizeStmt = $pdo->prepare("
    SELECT TOP 1 name, picture
    FROM game_prize
    WHERE game_id = ?
    ORDER BY id ASC
");
$prizeStmt->execute([$gameId]);
$prize = $prizeStmt->fetch();

$prizePictureData = null;
if ($prize && $prize['picture'] !== null) {
    $prizePictureData = 'data:image/jpeg;base64,' . base64_encode($prize['picture']);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Game Winners</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/winner.css" rel="stylesheet">
    <script src="sweetalert/dist/sweetalert2.all.min.js"></script>

    <style>
        body {
            background: #111;
            color: white;
        }
    </style>
</head>
<body>

<div class="container py-5 text-center">

    <h1 class="display-2 text-success mb-4">
        🎉 Game Winners 🎉
    </h1>

    <h5 class="mb-4">
        Total Numbers Drawn: <?= $totalDrawn ?>
    </h5>

    <?php if ($prize): ?>
        <div class="d-flex justify-content-center mb-5">
            <div class="card bg-dark text-white border-0 shadow-lg" style="max-width:350px;">
                <div class="card-body text-center">
                    <h5 class="mb-3">🏆 Prize</h5>
                    <?php if ($prizePictureData): ?>
                        <img src="<?= $prizePictureData ?>" class="img-fluid rounded mb-3" style="max-height:250px;">
                    <?php endif; ?>
                    <div class="fs-4 fw-bold text-warning">
                        <?= htmlspecialchars($prize['name']) ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (empty($winners)): ?>
        <div class="alert alert-warning">
            No winners recorded.
        </div>
    <?php else: ?>

        <?php foreach ($winners as $index => $winner): ?>

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

            <div class="winner-card shadow">
                <h2 class="<?= $class ?>">
                    <?= $medal ?> #<?= $rank ?>
                </h2>
                <h3>
                    <?= htmlspecialchars($winner['name']) ?>
                </h3>
            </div>

        <?php endforeach; ?>

    <?php endif; ?>

</div>

<script>
Swal.fire({
    icon: 'success',
    title: 'Game Complete!',
    text: 'Congratulations to all winners!',
    timer: 2500,
    showConfirmButton: false
});
</script>

</body>
</html>