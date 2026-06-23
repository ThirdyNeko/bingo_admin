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
$registerUrl = "http://localhost/bingo/index.php?game_code=" . urlencode($game['game_code']);
$qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($registerUrl);

$started = (int)$game['started'] === 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['draw_number'])) {

    // 1️⃣ Get pattern and drawn numbers first
    $pattern = json_decode($game['pattern'], true);
    $drawnNumbers = array_map('intval', json_decode($game['drawn_numbers'] ?? '[]', true));
    $letters = ['B','I','N','G','O'];
    

    // 2️⃣ Get queued winners
    $limit = (int)$totalWinners;

    $queueStmt = $pdo->prepare("
        SELECT TOP $limit *
        FROM game_winner_queue
        WHERE game_id = ? AND claimed = 0
        ORDER BY level ASC
    ");
    $queueStmt->execute([$gameId]);
    $queuedWinners = $queueStmt->fetchAll();

    if (!$queuedWinners) {
        die("No queued winner found.");
    }

    // 3️⃣ Collect needed numbers for each winner
    $allNeeded = [];
    $sharedNumber = null;

    foreach ($queuedWinners as $winner) {

        $cardStmt = $pdo->prepare("
            SELECT card_data, shared_number
            FROM user_cards 
            WHERE id = ?
        ");
        $cardStmt->execute([$winner['card_id']]);
        $rowData = $cardStmt->fetch(PDO::FETCH_ASSOC);
        $cardData = json_decode($rowData['card_data'], true);
        $sharedNumber = (int) $rowData['shared_number'];

        $neededNumbers = [];

        foreach ($pattern as $r => $cols) {
            foreach ($cols as $c => $val) {
                if ($val == 1) {
                    $num = $cardData[$letters[$c]][$r] ?? null;

                    if ($num !== null && $num !== "FREE" && !in_array($num, $drawnNumbers)) {
                        if ($num != $sharedNumber) {
                            $neededNumbers[] = $num;
                        }
                    }
                }
            }
        }

        $allNeeded[] = $neededNumbers;
    }

    // 4️⃣ Build draw pools
    $drawPool = !empty($allNeeded)
        ? array_unique(array_merge(...$allNeeded))
        : [];

    $allAvailableNumbers = array_values(array_diff(range(1,75), $drawnNumbers));

    /* ==============================
    SMART DRAW ENGINE
    ============================== */

    $winnerNumbers = array_values(array_diff($drawPool, $drawnNumbers));

    $neutralPool = array_values(array_diff($allAvailableNumbers, $drawPool));

    $dangerPool = [];
    $blockedNumbers = [];


    // Get ALL cards in game
    $cardsStmt = $pdo->prepare("
        SELECT id, card_data
        FROM user_cards
        WHERE game_id = ?
    ");
    $cardsStmt->execute([$gameId]);
    $cards = $cardsStmt->fetchAll(PDO::FETCH_ASSOC);


    foreach ($cards as $card) {

        $cardData = json_decode($card['card_data'], true);

        $missing = [];

        foreach ($pattern as $r => $cols) {
            foreach ($cols as $c => $val) {

                if ($val == 1) {

                    $num = $cardData[$letters[$c]][$r] ?? null;

                    if ($num !== null && $num !== "FREE" && !in_array($num, $drawnNumbers)) {
                        $missing[] = $num;
                    }
                }
            }
        }

        if (count($missing) == 1) {

            $lastNumber = $missing[0];

            $isQueued = false;

            foreach ($queuedWinners as $qw) {
                if ($qw['card_id'] == $card['id']) {
                    $isQueued = true;
                    break;
                }
            }

            if (!$isQueued) {
                $blockedNumbers[] = $lastNumber;
            }
        }
    }

    $blockedNumbers = array_unique($blockedNumbers);


    /* Remove blocked numbers from pools */

    $winnerNumbers = array_values(array_diff($winnerNumbers, $blockedNumbers));
    $neutralPool = array_values(array_diff($neutralPool, $blockedNumbers));
    $allAvailableNumbers = array_values(array_diff($allAvailableNumbers, $blockedNumbers));


    /* ==============================
    DRAW PROBABILITY SYSTEM
    ============================== */

    $drawCount = (int)($game['draw_count'] ?? 0);

    $winnerChance = min(20 + ($drawCount * 3), 75);
    $dangerChance = 15;

    $roll = rand(1,100);


    if (!empty($winnerNumbers) && $roll <= $winnerChance) {

        $number = $winnerNumbers[array_rand($winnerNumbers)];

    } elseif ($roll <= ($winnerChance + $dangerChance)) {

        if (!empty($allAvailableNumbers)) {
            $number = $allAvailableNumbers[array_rand($allAvailableNumbers)];
        } else {
            $number = $neutralPool[array_rand($neutralPool)];
        }

    }else {

        if (!empty($neutralPool)) {
            $number = $neutralPool[array_rand($neutralPool)];
        } else {
            $number = $allAvailableNumbers[array_rand($allAvailableNumbers)];
        }

    }

    // 5️⃣ Shared number trigger (final number for winners)
    foreach ($queuedWinners as $winner) {

        $cardStmt = $pdo->prepare("
            SELECT shared_number
            FROM user_cards
            WHERE id = ?
        ");
        $cardStmt->execute([$winner['card_id']]);
        $sharedNumber = (int) $cardStmt->fetchColumn();

        if ($sharedNumber && !in_array($sharedNumber, $drawnNumbers, true)) {

            $remaining = array_diff($drawPool, $drawnNumbers, [$sharedNumber]);

            if (empty($remaining)) {
                $number = $sharedNumber;
                break;
            }
        }
    }

    if (!in_array($number, $drawnNumbers)) {
        $drawnNumbers[] = $number;
    }

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

        /* ==============================
           PREVIOUS NUMBERS SECTION
        ================================ */
        #prev-numbers-section {
            border-top: 1px solid #2a2a2a;
            padding-top: 1rem;
            margin-top: 1.5rem;
        }

        .prev-ball {
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 52px;
            height: 52px;
            border-radius: 50%;
            font-weight: bold;
            line-height: 1;
            border: 2px solid rgba(255,255,255,0.15);
            flex-shrink: 0;
        }

        .prev-ball .prev-letter {
            font-size: 0.6rem;
            opacity: 0.85;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .prev-ball .prev-num {
            font-size: 1rem;
        }

        /* Letter-based colours (same as your bingo-ball palette) */
        .prev-ball.B { background: radial-gradient(circle at 35% 35%, #4fc3f7, #0277bd); color: #fff; }
        .prev-ball.I { background: radial-gradient(circle at 35% 35%, #a5d6a7, #2e7d32); color: #fff; }
        .prev-ball.N { background: radial-gradient(circle at 35% 35%, #ffe082, #f57f17); color: #fff; }
        .prev-ball.G { background: radial-gradient(circle at 35% 35%, #ef9a9a, #c62828); color: #333; }
        .prev-ball.O { background: radial-gradient(circle at 35% 35%, #ce93d8, #6a1b9a); color: #fff; }

        /* Drop-in animation for the newest ball */
        @keyframes dropIn {
            0%   { opacity: 0; transform: translateY(-60px) scale(1.4); }
            60%  { transform: translateY(8px) scale(0.95); }
            80%  { transform: translateY(-4px) scale(1.02); }
            100% { opacity: 1; transform: translateY(0) scale(1); }
        }

        .newest-ball {
            animation: dropIn 0.55s cubic-bezier(0.22, 0.61, 0.36, 1) both;
            box-shadow: 0 0 14px rgba(255,255,255,0.35);
        }

        /* Fade older balls slightly */
        .prev-ball:not(.newest-ball) {
            opacity: 0.65;
            transition: opacity 0.3s;
        }
        .prev-ball:not(.newest-ball):hover {
            opacity: 1;
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
                // ✅ Make sure all drawn numbers are integers
                $drawnNumbers = array_map('intval', json_decode($game['drawn_numbers'] ?? '[]', true));
                $lastNumber = (int)end($drawnNumbers);

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

                <!-- ==============================
                     PREVIOUS NUMBERS TICKER
                ================================ -->
                <?php
                // All drawn except the current last one, newest-first
                $previousNumbers = array_slice($drawnNumbers, 0, -1);
                $previousNumbers = array_reverse($previousNumbers);
                ?>
                <?php if (!empty($previousNumbers)): ?>
                <div id="prev-numbers-section" class="mt-2 px-3">
                    <p class="text-muted small mb-2 text-center">Previously Drawn</p>
                    <div id="prev-numbers-track" class="d-flex flex-wrap justify-content-center gap-2">
                        <?php foreach ($previousNumbers as $i => $pn):
                            if ($pn >= 1 && $pn <= 15)  $pl = 'B';
                            elseif ($pn <= 30)           $pl = 'I';
                            elseif ($pn <= 45)           $pl = 'N';
                            elseif ($pn <= 60)           $pl = 'G';
                            else                         $pl = 'O';
                        ?>
                            <div class="prev-ball <?= $pl ?><?= $i === 0 ? ' newest-ball' : '' ?>">
                                <span class="prev-letter"><?= $pl ?></span>
                                <span class="prev-num"><?= $pn ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
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