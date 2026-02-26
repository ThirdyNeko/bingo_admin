<?php
require_once 'config/db.php';
include 'partials/header.php';
include 'partials/sidebar.php';

$games = $pdo->query("SELECT * FROM game ORDER BY id DESC")->fetchAll();
?>

<div class="col-md-10 p-4">
    <h3 class="mb-4">Manage Games</h3>

    <div class="card shadow-sm">
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
                    <?php foreach($games as $g): ?>
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

</div></div>
</body>
</html>