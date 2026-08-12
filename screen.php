<?php
require_once 'config/db.php';

if (!isset($_GET['game_id'])) {
    die("Game not found.");
}

$gameId = (int) $_GET['game_id'];

$stmt = $pdo->prepare("SELECT * FROM game WHERE id = ?");
$stmt->execute([$gameId]);
$game = $stmt->fetch();

if (!$game) {
    die("Game does not exist.");
}

$claimedStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM game_winner_queue 
    WHERE game_id = ? AND claimed = 1
");
$claimedStmt->execute([$gameId]);
$claimedCount = (int) $claimedStmt->fetchColumn();

$totalWinners = (int) $game['winners'];

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

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE current_game = ?");
$countStmt->execute([$gameId]);
$playerCount = $countStmt->fetchColumn();

$registerUrl = "http://localhost/bingo/index.php?game_code=" . urlencode($game['game_code']);
$qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($registerUrl);

$started = (int)$game['started'] === 1;
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

        #prev-numbers-section {
            border-top: 1px solid #2a2a2a;
            padding-top: 1rem;
            margin-top: 1.5rem;
        }

        .prev-letter-row {
            background: rgba(255,255,255,0.03);
            border-radius: 10px;
            padding: 0.5rem 0.75rem;
        }

        .prev-letter-label {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            font-weight: bold;
            font-size: 1rem;
            flex-shrink: 0;
            color: #fff;
        }

        .prev-letter-label.B { background: #0277bd; }
        .prev-letter-label.I { background: #2e7d32; }
        .prev-letter-label.N { background: #f57f17; }
        .prev-letter-label.G { background: #c62828; }
        .prev-letter-label.O { background: #6a1b9a; }

        .prev-ball {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            font-weight: bold;
            line-height: 1;
            border: 2px solid rgba(255,255,255,0.15);
            flex-shrink: 0;
        }

        .prev-ball .prev-num {
            font-size: 1rem;
        }

        .prev-ball.B { background: radial-gradient(circle at 35% 35%, #4fc3f7, #0277bd); color: #fff; }
        .prev-ball.I { background: radial-gradient(circle at 35% 35%, #a5d6a7, #2e7d32); color: #fff; }
        .prev-ball.N { background: radial-gradient(circle at 35% 35%, #ffe082, #f57f17); color: #fff; }
        .prev-ball.G { background: radial-gradient(circle at 35% 35%, #ef9a9a, #c62828); color: #333; }
        .prev-ball.O { background: radial-gradient(circle at 35% 35%, #ce93d8, #6a1b9a); color: #fff; }

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

        .prev-ball:not(.newest-ball) {
            opacity: 0.65;
            transition: opacity 0.3s;
        }
        .prev-ball:not(.newest-ball):hover {
            opacity: 1;
        }

        /* ==============================
        BALL TRANSIT ANIMATION
        (current ball flies into its slot in the
        Previously Drawn row, fading as it travels)
        ================================ */
        .ball-transit {
            position: fixed;
            z-index: 2000;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            line-height: 1;
            pointer-events: none;
            box-shadow: 0 0 14px rgba(255,255,255,0.35);
            transition:
                transform 0.55s cubic-bezier(0.65, 0, 0.35, 1),
                opacity 0.55s ease-in,
                font-size 0.55s cubic-bezier(0.65, 0, 0.35, 1);
            will-change: transform, opacity, font-size;
        }

        #current-ball-wrap {
            position: relative;
            min-height: 180px;
        }

        /* ==============================
        NUMBER CYCLE REVEAL
        (rapid flicker through random numbers
        before settling on the actual drawn number)
        ================================ */
        .bingo-ball.cycling {
            animation: cyclePulse 0.18s ease-in-out infinite alternate;
        }

        @keyframes cyclePulse {
            from { transform: scale(1); }
            to   { transform: scale(1.06); }
        }
    </style>
</head>
<body>

<?php if (!$started): ?>

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

    <div class="container-fluid py-5">
        <div class="row">

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

                        <div class="d-flex mb-2">
                            <?php foreach ($letters as $letter): ?>
                                <div class="text-center fw-bold text-warning" style="width:60px;">
                                    <?= $letter ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

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

            <div class="col-lg-9 text-center">

                <h1 class="display-2 text-success mb-4">
                    🎮 Game Started!
                </h1>

                <p class="lead">
                    Live game display will go here.
                </p>

                <h4 class="mt-4" id="winners-header">
                    Winners: <?= $claimedCount ?> / <?= $totalWinners ?>
                </h4>

                <div id="winners-list" class="mt-3">
                    <?php foreach ($winnerNames as $index => $name): ?>
                        <div class="fs-4 text-warning">
                            #<?= $index + 1 ?> — <?= htmlspecialchars($name) ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div id="drawBtnWrap" class="mt-4">
                    <button type="button" id="drawBtn" class="btn btn-lg btn-success px-5">
                        Draw Number
                    </button>
                </div>

                <?php
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
                    <div class="my-4 text-center" id="current-ball-wrap">
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
                <?php else: ?>
                    <div class="my-4 text-center" id="current-ball-wrap"></div>
                <?php endif; ?>

                <?php
                $previousNumbers = array_slice($drawnNumbers, 0, -1);
                $mostRecentPrev  = !empty($previousNumbers) ? end($previousNumbers) : null;

                $groupedPrev = ['B' => [], 'I' => [], 'N' => [], 'G' => [], 'O' => []];
                foreach ($previousNumbers as $pn) {
                    if ($pn >= 1 && $pn <= 15)      $groupedPrev['B'][] = $pn;
                    elseif ($pn <= 30)              $groupedPrev['I'][] = $pn;
                    elseif ($pn <= 45)              $groupedPrev['N'][] = $pn;
                    elseif ($pn <= 60)              $groupedPrev['G'][] = $pn;
                    else                             $groupedPrev['O'][] = $pn;
                }
                foreach ($groupedPrev as &$nums) {
                    sort($nums);
                }
                unset($nums);
                ?>
                <div id="prev-numbers-section" class="mt-2 px-3">
                    <?php if (!empty($previousNumbers)): ?>
                    <p class="text-muted small mb-2 text-center">Previously Drawn</p>
                    <div id="prev-numbers-track" class="d-flex flex-column gap-2">
                        <?php foreach ($groupedPrev as $letter => $nums): ?>
                            <?php if (!empty($nums)): ?>
                                <div class="prev-letter-row d-flex align-items-center gap-2 flex-wrap">
                                    <div class="prev-letter-label <?= $letter ?>"><?= $letter ?></div>
                                    <?php foreach ($nums as $pn): ?>
                                        <div class="prev-ball <?= $letter ?><?= $pn === $mostRecentPrev ? ' newest-ball' : '' ?>">
                                            <span class="prev-num"><?= $pn ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

            </div>

        </div>
    </div>

<?php endif; ?>

<div id="screen-root"
     data-game-id="<?= $gameId ?>"
     data-player-count="<?= (int) $playerCount ?>"
     data-started="<?= $started ? 1 : 0 ?>"
     data-claimed-count="<?= $claimedCount ?>"
     data-total-winners="<?= $totalWinners ?>"
     data-reveal-duration="<?= (int) ($game['reveal_duration_ms'] ?? 1200) ?>"
     style="display:none;"></div>

<script src="sweetalert/dist/sweetalert2.all.min.js"></script>
<script src="js/game/screen.js"></script>

</body>
</html>