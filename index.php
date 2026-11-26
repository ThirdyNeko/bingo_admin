<?php
require_once 'config/db.php';
include 'partials/header.php';
include 'partials/sidebar.php';

// Dashboard statistics
$totalGames = (int) $pdo->query("SELECT COUNT(*) FROM game")->fetchColumn();

$totalWinnersRequired = (int) ($pdo->query("SELECT SUM(winners) FROM game")->fetchColumn() ?: 0);

$stmt = $pdo->query("SELECT game_winners FROM game");

$totalWinnersDeclared = 0;

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $winners = json_decode($row['game_winners'] ?? '', true);

    if (is_array($winners)) {
        $totalWinnersDeclared += count($winners);
    }
}
?>

<link rel="stylesheet" href="css/dashboard.css">

<div class="col-md-10 dashboard-content">

    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <div>
            <span class="dashboard-eyebrow">OVERVIEW</span>
            <h2>Dashboard</h2>
            <p>Here's an overview of your Bingo games and winners.</p>
        </div>

        <a href="create_game.php" class="dashboard-create-btn">
            <i class="bi bi-plus-lg"></i>
            Create Game
        </a>
    </div>

    <!-- Statistics -->
    <div class="row dashboard-stat-row">

        <!-- Total Games -->
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="dashboard-stat-card">

                <div class="stat-card-top">
                    <div class="stat-icon stat-icon-blue">
                        <i class="bi bi-controller"></i>
                    </div>

                    <span class="stat-label">TOTAL GAMES</span>
                </div>

                <div class="stat-value">
                    <?= number_format($totalGames) ?>
                </div>

                <div class="stat-description">
                    <i class="bi bi-grid"></i>
                    <span>Games created in the system</span>
                </div>

            </div>
        </div>

        <!-- Total Winners Required -->
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="dashboard-stat-card">

                <div class="stat-card-top">
                    <div class="stat-icon stat-icon-purple">
                        <i class="bi bi-people"></i>
                    </div>

                    <span class="stat-label">WINNERS REQUIRED</span>
                </div>

                <div class="stat-value">
                    <?= number_format($totalWinnersRequired) ?>
                </div>

                <div class="stat-description">
                    <i class="bi bi-bullseye"></i>
                    <span>Total winner slots across games</span>
                </div>

            </div>
        </div>

        <!-- Winners Declared -->
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="dashboard-stat-card">

                <div class="stat-card-top">
                    <div class="stat-icon stat-icon-green">
                        <i class="bi bi-trophy"></i>
                    </div>

                    <span class="stat-label">WINNERS DECLARED</span>
                </div>

                <div class="stat-value">
                    <?= number_format($totalWinnersDeclared) ?>
                </div>

                <div class="stat-description">
                    <i class="bi bi-check-circle"></i>
                    <span>Total winners recorded</span>
                </div>

            </div>
        </div>

    </div>

    <!-- Quick Actions -->
    <div class="dashboard-quick-actions">

        <div class="quick-action-header">
            <div>
                <h5>Quick Actions</h5>
                <p>Shortcuts to frequently used features</p>
            </div>
        </div>

        <div class="row">

            <div class="col-lg-4 mb-3">
                <a href="create_game.php" class="quick-action-item">
                    <div class="quick-action-icon">
                        <i class="bi bi-plus-circle"></i>
                    </div>

                    <div class="quick-action-text">
                        <strong>Create New Game</strong>
                        <span>Set up a new Bingo game</span>
                    </div>

                    <i class="bi bi-arrow-right quick-action-arrow"></i>
                </a>
            </div>

            <div class="col-lg-4 mb-3">
                <a href="manage_games.php" class="quick-action-item">
                    <div class="quick-action-icon quick-icon-purple">
                        <i class="bi bi-controller"></i>
                    </div>

                    <div class="quick-action-text">
                        <strong>Manage Games</strong>
                        <span>View and manage existing games</span>
                    </div>

                    <i class="bi bi-arrow-right quick-action-arrow"></i>
                </a>
            </div>

            <div class="col-lg-4 mb-3">
                <a href="winners_list.php" class="quick-action-item">
                    <div class="quick-action-icon quick-icon-green">
                        <i class="bi bi-trophy"></i>
                    </div>

                    <div class="quick-action-text">
                        <strong>View Winners</strong>
                        <span>See the list of declared winners</span>
                    </div>

                    <i class="bi bi-arrow-right quick-action-arrow"></i>
                </a>
            </div>

        </div>

    </div>

</div>

</body>
</html>