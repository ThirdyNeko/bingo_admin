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
   CLAIMED WINNERS COUNT
============================== */
$claimedStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM game_winner_queue 
    WHERE game_id = ? AND claimed = 1
");
$claimedStmt->execute([$gameId]);
$claimedCount = (int) $claimedStmt->fetchColumn();

$totalWinners = (int) $game['winners'];

/* ==============================
   WINNERS LIST
============================== */
$winnersStmt = $pdo->prepare("
    SELECT u.name
    FROM game_winner_queue gwq
    JOIN user_cards uc ON gwq.card_id = uc.id
    JOIN users u ON uc.user_id = u.id
    WHERE gwq.game_id = ? AND gwq.claimed = 1
    ORDER BY gwq.level ASC
");
$winnersStmt->execute([$gameId]);
$winnerNames = $winnersStmt->fetchAll(PDO::FETCH_COLUMN);

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

    // 1️⃣ Get pattern and drawn numbers first
    $pattern = json_decode($game['pattern'], true);
    $drawnNumbers = json_decode($game['drawn_numbers'] ?? '[]', true);
    $letters = ['B','I','N','G','O'];

    // 2️⃣ Get queued winners
    $queueStmt = $pdo->prepare("
        SELECT * FROM game_winner_queue
        WHERE game_id = ? AND claimed = 0
        ORDER BY level ASC
        LIMIT ?
    ");
    $queueStmt->execute([$gameId, $totalWinners]);
    $queuedWinners = $queueStmt->fetchAll();

    if (!$queuedWinners) {
        die("No queued winner found.");
    }

    // 3️⃣ Collect needed numbers for each winner
    $allNeeded = [];
    $sharedNumber = null; // this should be stored/assigned at card generation

    foreach ($queuedWinners as $winner) {

        $cardStmt = $pdo->prepare("
            SELECT card_data, shared_number
            FROM user_cards 
            WHERE id = ?
        ");
        $cardStmt->execute([$winner['card_id']]);
        $rowData = $cardStmt->fetch(PDO::FETCH_ASSOC);
        $cardData = json_decode($rowData['card_data'], true);
        $sharedNumber = $rowData['shared_number']; // stored during card creation

        $neededNumbers = [];

        foreach ($pattern as $r => $cols) {
            foreach ($cols as $c => $val) {
                if ($val == 1) {
                    $num = $cardData[$letters[$c]][$r] ?? null;

                    if ($num !== null && $num !== "FREE" && !in_array($num, $drawnNumbers)) {
                        // ✅ skip shared number until last
                        if ($num != $sharedNumber) {
                            $neededNumbers[] = $num;
                        }
                    }
                }
            }
        }

        $allNeeded[] = $neededNumbers;
    }

    // 4️⃣ Determine numbers to draw
    // Merge all needed numbers into one array
    $drawPool = array_unique(array_merge(...$allNeeded));

    // Safety: available numbers not yet drawn
    $availableNumbers = array_values(array_diff($drawPool, $drawnNumbers));

    // If all other numbers drawn, now draw the shared number
    if (empty($availableNumbers) && $sharedNumber && !in_array($sharedNumber, $drawnNumbers)) {
        $number = $sharedNumber;
    } else {
        // Draw a number randomly from the pool
        if (!empty($availableNumbers)) {
            $number = $availableNumbers[array_rand($availableNumbers)];
        } else {
            // fallback to any remaining number in 1-75
            $remainingNumbers = array_values(array_diff(range(1,75), $drawnNumbers));
            if (empty($remainingNumbers)) exit; // all numbers drawn
            $number = $remainingNumbers[array_rand($remainingNumbers)];
        }
    }

    // 5️⃣ Add number to drawn numbers
    $drawnNumbers[] = $number;

    // 6️⃣ Save game state
    $pdo->prepare("
        UPDATE game
        SET drawn_numbers = ?, draw_count = draw_count + 1
        WHERE id = ?
    ")->execute([
        json_encode($drawnNumbers),
        $gameId
    ]);

    header("Location: screen.php?game_id=" . $gameId);
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Game Screen</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/design.css" rel="stylesheet">
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
                    Winners: <?= $claimedCount ?> / <?= $totalWinners ?>
                </h4>

                <?php if (!empty($winnerNames)): ?>
                    <div class="mt-3">
                        <?php foreach ($winnerNames as $index => $name): ?>
                            <div class="fs-4 text-warning">
                                #<?= $index + 1 ?> — <?= htmlspecialchars($name) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="mt-4">
                    <button type="submit" name="draw_number" class="btn btn-lg btn-success px-5">
                        Draw Number
                    </button>
                </form>
                <?php
                $drawnNumbers = json_decode($game['drawn_numbers'] ?? '[]', true);
                $lastNumber = end($drawnNumbers);

                if ($lastNumber):

                    if ($lastNumber >= 1 && $lastNumber <= 15) {
                        $letter = 'B';
                    } elseif ($lastNumber <= 30) {
                        $letter = 'I';
                    } elseif ($lastNumber <= 45) {
                        $letter = 'N';
                    } elseif ($lastNumber <= 60) {
                        $letter = 'G';
                    } else {
                        $letter = 'O';
                    }
                ?>
                    <div class="my-4 text-center">
                        <div class="bingo-ball <?= $letter ?>">
                            <div class="outer-letter">
                                <?= $letter ?>
                            </div>
                            <div class="inner-number">
                                <?= $lastNumber ?>
                            </div>
                        </div>
                        <p class="lead mt-3">Last number drawn</p>
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

<script src="sweetalert/dist/sweetalert2.all.min.js"></script>

<script>
let currentCount = <?= $playerCount ?>;
let gameStarted = <?= $started ? 1 : 0 ?>;
let currentClaimed = <?= $claimedCount ?>;
let totalWinners = <?= $totalWinners ?>;

if (currentClaimed >= totalWinners) {
    Swal.fire({
        icon: 'success',
        title: '🎉 Game Finished!',
        text: 'All winners have been claimed!',
        confirmButtonText: 'View Winners',
        confirmButtonColor: '#28a745',
        allowOutsideClick: false
    }).then(() => {
        window.location.href = 'winners.php?game_id=<?= $gameId ?>';
    });
}

function checkScreenChanges() {
    fetch('screen_status.php?game_id=<?= $gameId ?>')
        .then(res => res.json())
        .then(data => {

            let newCount = parseInt(data.count);
            let newStarted = parseInt(data.started);
            let newClaimed = parseInt(data.claimed);

            // Lobby player change
            if (!gameStarted && newCount !== currentCount) {
                location.reload();
            }

            // Game started change
            if (newStarted !== gameStarted) {
                location.reload();
            }

            // Winner claimed change
            if (newClaimed !== currentClaimed) {
                currentClaimed = newClaimed;

                if (newClaimed >= totalWinners) {
                    Swal.fire({
                        icon: 'success',
                        title: '🎉 Game Finished!',
                        text: 'All winners have been claimed!',
                        confirmButtonText: 'View Winners',
                        confirmButtonColor: '#28a745',
                        allowOutsideClick: false
                    }).then(() => {
                        window.location.href = 'winners.php?game_id=<?= $gameId ?>';
                    });
                } else {
                    location.reload();
                }
            }

        })
        .catch(err => console.error("Polling error:", err));
}

// Check every 3 seconds
setInterval(checkScreenChanges, 3000);
</script>

</body>
</html>