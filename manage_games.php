
<?php
require_once 'config/db.php';
include 'partials/header.php';
include 'partials/sidebar.php';
?>

<div class="col-md-10 p-4 manage-games-content">

    <!-- Page Header -->
    <div class="mg-page-header">
        <div>
            <span class="mg-eyebrow">GAME ADMINISTRATION</span>
            <h2>Manage Games</h2>
            <p>Monitor game sessions, winner counts, and manage active game records.</p>
        </div>
    </div>

    <!-- Games Table Card -->
    <div class="mg-card">

        <div class="mg-card-header">
            <div class="mg-card-title">
                <div class="mg-icon">
                    <i class="bi bi-controller"></i>
                </div>

                <div>
                    <h5>Game Records</h5>
                    <span>View and manage your created games</span>
                </div>
            </div>

            <a href="create_game.php" class="mg-create-btn">
                <i class="bi bi-plus-lg"></i>
                Create Game
            </a>
        </div>

        <div class="mg-card-body">

            <div class="mg-table-wrap">
                <table id="gamesTable" class="table" style="width:100%">
                    <thead>
                        <tr>
                            <th>Game Code</th>
                            <th>Winners Needed</th>
                            <th>Winners Declared</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>

        </div>
    </div>

</div>

</div>
</body>

<link rel="stylesheet" href="css/datatables.min.css">
<link rel="stylesheet" href="css/manage_games.css">

<script src="js/jquery-4.0.0.min.js"></script>
<script src="js/datatables.min.js"></script>
<script src="js/game/manage_games.js"></script>

</html>
