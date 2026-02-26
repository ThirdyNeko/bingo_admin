<?php
require_once 'config/db.php';
include 'partials/header.php';
include 'partials/sidebar.php';
?>

<div class="col-md-10 p-4">
    <h3 class="mb-4">Dashboard</h3>

    <div class="row">
        <div class="col-md-4 mb-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6>Total Games</h6>
                    <h3><?= $pdo->query("SELECT COUNT(*) FROM game")->fetchColumn(); ?></h3>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6>Total Winners Required</h6>
                    <h3><?= $pdo->query("SELECT SUM(winners) FROM game")->fetchColumn() ?: 0; ?></h3>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6>Winners Declared</h6>
                    <h3><?= $pdo->query("SELECT SUM(game_winners) FROM game")->fetchColumn() ?: 0; ?></h3>
                </div>
            </div>
        </div>
    </div>
</div>

</div></div>
</body>
</html>