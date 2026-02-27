<?php
require_once 'config/db.php';
include 'partials/header.php';
include 'partials/sidebar.php';

if (!isset($_GET['game_id'])) {
    die("Game not found.");
}

$gameId = (int) $_GET['game_id'];

// Get Game
$stmt = $pdo->prepare("SELECT * FROM game WHERE id = ?");
$stmt->execute([$gameId]);
$game = $stmt->fetch();

if (!$game) {
    die("Game does not exist.");
}

// Get Players
$playersStmt = $pdo->prepare("
    SELECT *
    FROM users
    WHERE current_game = ?
");
$playersStmt->execute([$gameId]);
$players = $playersStmt->fetchAll();
?>

<div class="col-md-10 p-4">

    <h3 class="mb-4">Manage Game</h3>

    <!-- Game Info Card -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-dark text-white">
            Game Information
        </div>
        <div class="card-body">
            <p><strong>Game Code:</strong> <?= htmlspecialchars($game['game_code']) ?></p>
            <p><strong>Winners Required:</strong> <?= $game['winners'] ?></p>
            <p><strong>Current Winners:</strong> <?= $game['game_winners'] ?></p>
        </div>
    </div>

    <!-- Players Card -->
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white">
            Joined Players (<?= count($players) ?>)
        </div>
        <div class="card-body">

            <?php if (empty($players)): ?>
                <p class="text-muted">No players joined yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Joined At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($players as $index => $player): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><?= htmlspecialchars($player['name']) ?></td>
                                    <td><?= htmlspecialchars($player['join_time']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        </div>
    </div>

</div>

</body>
</html>