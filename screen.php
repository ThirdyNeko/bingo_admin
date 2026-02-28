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
   PLAYER COUNT
============================== */
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE current_game = ?");
$countStmt->execute([$gameId]);
$playerCount = $countStmt->fetchColumn();

/* ==============================
   QR CODE LINK
============================== */
$registerUrl = "https://192.168.40.14/join_game.php?game_code=" . urlencode($game['game_code']);
$qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($registerUrl);

$started = (int)$game['started'] === 1;
?>

<!DOCTYPE html>
<html>
<head>
    <title>Game Screen</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #111;
            color: white;
        }
        .big-number {
            font-size: 5rem;
            font-weight: bold;
        }
    </style>
</head>
<body>

<?php if (!$started): ?>

    <!-- ===============================
         LOBBY SCREEN
    ================================ -->

    <div class="container text-center py-5">

        <h1 class="display-3 mb-4">🎉 Bingo Game Lobby</h1>

        <h3 class="mb-3">Game Code:</h3>
        <div class="big-number text-warning mb-4">
            <?= htmlspecialchars($game['game_code']) ?>
        </div>

        <h4 class="mb-3">👥 Players Joined</h4>
        <div class="display-1 text-success mb-4">
            <?= $playerCount ?>
        </div>

        <div class="card bg-dark border-0 shadow-lg mx-auto" style="max-width:350px;">
            <div class="card-body">
                <h5 class="mb-3">Scan to Join</h5>
                <img src="<?= $qrUrl ?>" class="img-fluid">
            </div>
        </div>

    </div>

    <script>
    let currentCount = <?= $playerCount ?>;
    let gameStarted = <?= $started ? 1 : 0 ?>;

    function checkLobbyChanges() {
        fetch('screen_status.php?game_id=<?= $gameId ?>')
            .then(res => res.json())
            .then(data => {

                let newCount = parseInt(data.count);
                let newStarted = parseInt(data.started);

                // If new player joined
                if (newCount !== currentCount) {
                    location.reload();
                }

                // If game started
                if (newStarted !== gameStarted) {
                    location.reload();
                }
            })
            .catch(err => console.error(err));
    }

    // Check every 3 seconds
    setInterval(checkLobbyChanges, 3000);
    </script>

<?php else: ?>

    <!-- ===============================
         LIVE GAME SCREEN
    ================================ -->

    <div class="container text-center py-5">

        <h1 class="display-2 text-success mb-4">
            🎮 Game Started!
        </h1>

        <p class="lead">
            Live game display will go here.
        </p>

        <h4 class="mt-4">
            Winners Required: <?= $game['winners'] ?>
        </h4>

        <h4>
            Current Winners: <?= $game['game_winners'] ?>
        </h4>

    </div>

<?php endif; ?>

</body>
</html>