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
$registerUrl = "https://192.168.40.14/bingo/index.php?game_code=" . urlencode($game['game_code']);
$qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($registerUrl);

$started = (int)$game['started'] === 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['draw_number'])) {

    // 1️⃣ Next winner
    $queueStmt = $pdo->prepare("
        SELECT * FROM game_winner_queue
        WHERE game_id = ? AND claimed = 0
        ORDER BY level ASC
        LIMIT 1
    ");
    $queueStmt->execute([$gameId]);
    $queuedWinner = $queueStmt->fetch();
    if (!$queuedWinner) die("No queued winner found.");

    $cardId = $queuedWinner['card_id'];

    // 2️⃣ Get winner's card
    $cardStmt = $pdo->prepare("
        SELECT card_data 
        FROM user_cards 
        WHERE id = ?
    ");
    $cardStmt->execute([$cardId]);
    $cardData = json_decode($cardStmt->fetchColumn(), true);

    // 3️⃣ Get pattern
    $pattern = json_decode($game['pattern'], true);

    // 4️⃣ Already drawn numbers (create if missing)
    $drawnNumbers = json_decode($game['drawn_numbers'] ?? '[]', true);

    // 5️⃣ Map pattern to actual numbers
    $letters = ['B','I','N','G','O'];
    $neededNumbers = [];
    foreach ($pattern as $row => $cols) {
        foreach ($cols as $col => $val) {
            if ($val == 1) {
                $number = $cardData[$letters[$col]][$row] ?? null;
                if ($number !== null && $number !== "FREE" && !in_array($number, $drawnNumbers)) {
                    $neededNumbers[] = $number;
                }
            }
        }
    }

    // 6️⃣ Available numbers for filler
    $allNumbers = range(1,75);
    $availableNumbers = array_diff($allNumbers, $drawnNumbers);

    // 7️⃣ Controlled randomness
    $drawCount = (int) ($game['draw_count'] ?? 0);
    $progressChance = min(20 + ($drawCount * 5), 80);

    if (!empty($neededNumbers) && rand(1,100) <= $progressChance) {
        $number = $neededNumbers[array_rand($neededNumbers)]; // 🎯 needed
    } else {
        $number = $availableNumbers[array_rand($availableNumbers)]; // 🎲 filler
    }

    $drawnNumbers[] = $number;

    // 8️⃣ Update game table (make sure columns exist!)
    $pdo->prepare("
        UPDATE game
        SET drawn_numbers = ?, draw_count = draw_count + 1
        WHERE id = ?
    ")->execute([
        json_encode($drawnNumbers),
        $gameId
    ]);

    // 9️⃣ Check if winner completed pattern
    $patternNumbers = [];
    foreach ($pattern as $row => $cols) {
        foreach ($cols as $col => $val) {
            if ($val == 1) {
                $n = $cardData[$letters[$col]][$row] ?? null;
                if ($n !== null && $n !== "FREE") $patternNumbers[] = $n;
            }
        }
    }

    if (empty(array_diff($patternNumbers, $drawnNumbers))) {
        // Mark as claimed
        $pdo->prepare("
            UPDATE game_winner_queue 
            SET claimed = 1
            WHERE id = ?
        ")->execute([$queuedWinner['id']]);

        // Increment game_winners
        $pdo->prepare("
            UPDATE game
            SET game_winners = game_winners + 1
            WHERE id = ?
        ")->execute([$gameId]);
    }

    header("Location: screen.php?game_id=" . $gameId);
    exit;
}
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
         LOBBY SCREEN (UNCHANGED STYLE)
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

        <div class="card bg-dark text-white border-0 shadow-lg mx-auto" style="max-width:350px;">
            <div class="card-body text-center">
                <h5 class="mb-3">Scan to Join</h5>
                <img src="<?= $qrUrl ?>" class="img-fluid">
            </div>
        </div>

    </div>

<?php else: ?>

    <!-- ===============================
         LIVE GAME SCREEN
    ================================ -->

    <div class="container-fluid py-5">
        <div class="row">

            <!-- MAIN GAME CONTENT -->
            <div class="col-lg-9 text-center">

                <h1 class="display-2 text-success mb-4">
                    🎮 Game Started!
                </h1>

                <p class="lead">
                    Live game display will go here.
                </p>

                <h4 class="mt-4">
                    Potential Winners: <?= $game['winners'] ?>
                </h4>

                <form method="POST" class="mt-4">
                    <button type="submit" name="draw_number" class="btn btn-lg btn-success px-5">
                        🎱 Draw Number
                    </button>
                </form>
                <?php
                $drawnNumbers = json_decode($game['drawn_numbers'] ?? '[]', true);
                $lastNumber = end($drawnNumbers); // Get the most recent drawn number
                ?>
                <?php if ($lastNumber): ?>
                    <div class="my-4">
                        <h2 class="display-1 text-warning">
                            🎱 <?= $lastNumber ?>
                        </h2>
                        <p class="lead">Last number drawn</p>
                    </div>
                <?php endif; ?>

            </div>

            <!-- SMALL REJOIN PANEL (SIDE) -->
            <div class="col-lg-3">

                <div class="card bg-dark text-white border-0 shadow-sm">
                    <div class="card-body text-center">

                        <h6 class="mb-2">Rejoin Game</h6>

                        <div class="fw-bold text-warning mb-2">
                            <?= htmlspecialchars($game['game_code']) ?>
                        </div>

                        <img src="<?= $qrUrl ?>" class="img-fluid mb-2" style="max-width:150px;">

                        <p class="small text-muted mb-0">
                            Scan if disconnected
                        </p>

                    </div>
                </div>

                <?php
                $pattern = json_decode($game['pattern'], true);
                $letters = ['B','I','N','G','O'];
                ?>

                <div class="mt-5 text-center w-100">
                    <h3 class="text-info mb-3">Winning Pattern</h3>

                    <div class="mx-auto bg-dark p-3 rounded shadow" style="width:max-content;">

                        <!-- B I N G O Header -->
                        <div class="d-flex mb-2">
                            <?php foreach ($letters as $letter): ?>
                                <div class="text-center fw-bold text-warning" style="width:60px;">
                                    <?= $letter ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Pattern Grid -->
                        <?php foreach ($pattern as $rowIndex => $row): ?>
                            <div class="d-flex">
                                <?php foreach ($row as $colIndex => $cell): ?>

                                    <?php
                                    $isCenter = ($rowIndex == 2 && $colIndex == 2);
                                    ?>

                                    <div class="border text-center"
                                        style="
                                            width:60px;
                                            height:60px;
                                            line-height:60px;
                                            font-size:1.2rem;
                                            font-weight:bold;
                                            <?php if ($isCenter): ?>
                                                background:#ffc107;
                                                color:black;
                                            <?php elseif ($cell == 1): ?>
                                                background:#28a745;
                                                color:white;
                                            <?php else: ?>
                                                background:#222;
                                                color:#555;
                                            <?php endif; ?>
                                        ">

                                        <?php
                                        if ($isCenter) {
                                            echo "FREE";
                                        } elseif ($cell == 1) {
                                            echo "✔";
                                        } else {
                                            echo "";
                                        }
                                        ?>

                                    </div>

                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>

                    </div>
                </div>

            </div>

        </div>
    </div>

<?php endif; ?>

<script>
let currentCount = <?= $playerCount ?>;
let gameStarted = <?= $started ? 1 : 0 ?>;

function checkScreenChanges() {
    fetch('screen_status.php?game_id=<?= $gameId ?>')
        .then(res => res.json())
        .then(data => {

            let newCount = parseInt(data.count);
            let newStarted = parseInt(data.started);

            // If new player joined (Lobby)
            if (!gameStarted && newCount !== currentCount) {
                location.reload();
            }

            // If game started
            if (newStarted !== gameStarted) {
                location.reload();
            }

        })
        .catch(err => console.error("Polling error:", err));
}

// Check every 3 seconds
setInterval(checkScreenChanges, 3000);
</script>

</body>
</html>