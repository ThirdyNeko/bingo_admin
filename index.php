<?php
session_name('Bingo');
session_start();
date_default_timezone_set('Asia/Manila');

require_once 'config/db.php';

// 🔐 Admin restriction
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: auth/admin_login.php");
    exit;
}

$success = '';
$error = '';

// Generate random game code
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

        // Insert into DB; id and game_number are auto-incremented
        $insert = $pdo->prepare("
            INSERT INTO game (pattern, winners, game_winners, game_code)
            VALUES (?, ?, 0, ?)
        ");
        $insert->execute([$pattern_json, $winners, $gameCode]);

        $success = "Game Created Successfully! Game Code: $gameCode";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard - Bingo</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <script src="js/bootstrap.bundle.min.js"></script>

    <style>
        body {
            background: #f4f6f9;
        }
        .sidebar {
            min-height: 100vh;
            background: #1a1a1a;
            color: white;
        }
        .sidebar a {
            color: #bbb;
            text-decoration: none;
            display: block;
            padding: 12px 15px;
        }
        .sidebar a:hover {
            background: #333;
            color: #fff;
        }
        .card-stat {
            border-left: 5px solid #0d6efd;
        }

        /* Bingo Pattern */
        .bingo-table td, .bingo-table th {
            width: 50px;
            height: 50px;
            vertical-align: middle;
            cursor: pointer;
        }
        .pattern-cell.active {
            background-color: #198754 !important;
            color: white !important;
        }
        .free {
            background-color: #ffc107 !important;
            font-weight: bold;
        }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">

        <!-- Sidebar -->
        <div class="col-md-2 sidebar p-3">
            <h4 class="text-center">🎯 BINGO ADMIN</h4>
            <hr>
            <a href="#">Dashboard</a>
            <a href="#">Create Game</a>
            <a href="#">Manage Games</a>
            <a href="auth/logout.php" class="text-danger">Logout</a>
        </div>

        <!-- Main Content -->
        <div class="col-md-10 p-4">

            <h3 class="mb-4">Admin Dashboard</h3>

            <!-- Stats Row -->
            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="card shadow-sm card-stat">
                        <div class="card-body">
                            <h6>Total Games</h6>
                            <h3>
                                <?php
                                $totalGames = $pdo->query("SELECT COUNT(*) FROM game")->fetchColumn();
                                echo $totalGames;
                                ?>
                            </h3>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <div class="card shadow-sm card-stat">
                        <div class="card-body">
                            <h6>Total Winners Required</h6>
                            <h3>
                                <?php
                                $totalWinners = $pdo->query("SELECT SUM(winners) FROM game")->fetchColumn();
                                echo $totalWinners ?: 0;
                                ?>
                            </h3>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <div class="card shadow-sm card-stat">
                        <div class="card-body">
                            <h6>Winners Already Declared</h6>
                            <h3>
                                <?php
                                $declared = $pdo->query("SELECT SUM(game_winners) FROM game")->fetchColumn();
                                echo $declared ?: 0;
                                ?>
                            </h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Create Game Section -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-dark text-white">
                    Create New Bingo Game
                </div>
                <div class="card-body">

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= $success ?></div>
                    <?php endif; ?>

                    <form method="POST">

                        <div class="mb-3">
                            <label class="form-label">Number of Winners</label>
                            <input type="number" name="winners" class="form-control" min="1" value="1" required>
                        </div>

                        <div class="mb-3 text-center">
                            <table class="table table-bordered table-dark bingo-table m-auto">
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
                                                    <td class="pattern-cell" data-row="<?= $r ?>" data-col="<?= $c ?>"></td>
                                                <?php endif; ?>
                                            <?php endfor; ?>
                                        </tr>
                                    <?php endfor; ?>
                                </tbody>
                            </table>
                            <input type="hidden" name="pattern_json" id="pattern_json" required>
                        </div>

                        <button class="btn btn-primary w-100">Create Game</button>
                    </form>
                </div>
            </div>

            <!-- Recent Games Table -->
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white">
                    Recent Games
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Game Code</th>
                                <th>Winners Needed</th>
                                <th>Winners Declared</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $games = $pdo->query("SELECT * FROM game ORDER BY id DESC LIMIT 10")->fetchAll();
                            foreach($games as $g):
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($g['game_code']) ?></td>
                                <td><?= $g['winners'] ?></td>
                                <td><?= $g['game_winners'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
    const size = 5;
    const pattern = Array.from({length:size}, () => Array(size).fill(0));
    const hiddenInput = document.getElementById('pattern_json');

    document.querySelectorAll('.pattern-cell').forEach(cell => {
        cell.addEventListener('click', function() {
            const row = parseInt(this.dataset.row);
            const col = parseInt(this.dataset.col);
            pattern[row][col] = pattern[row][col] ? 0 : 1;
            this.classList.toggle('active');
            hiddenInput.value = JSON.stringify(pattern);
        });
    });
</script>

</body>
</html>