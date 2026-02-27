<?php
require_once 'config/db.php';

if (!isset($_GET['game_id'])) {
    exit;
}

$gameId = (int) $_GET['game_id'];

/* Get Game */
$stmt = $pdo->prepare("SELECT * FROM game WHERE id = ?");
$stmt->execute([$gameId]);
$game = $stmt->fetch();

/* Get Players */
$playersStmt = $pdo->prepare("
    SELECT *
    FROM users
    WHERE current_game = ?
");
$playersStmt->execute([$gameId]);
$players = $playersStmt->fetchAll();
?>

<?php if (empty($players)): ?>
    <p class="text-muted">No players joined yet.</p>
<?php else: ?>

<div class="table-responsive">
<table class="table table-bordered align-middle">
    <thead>
        <tr>
            <th>#</th>
            <th>Name</th>
            <th>Mode</th>
            <th>Cards</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($players as $index => $player): ?>
            <tr>
                <form method="POST" action="manage_game.php?game_id=<?= $gameId ?>">
                    <td style="width:50px; text-align:center;">
                        <?= $index + 1 ?>
                    </td>

                    <td><?= htmlspecialchars($player['name']) ?></td>

                    <!-- AUTO / MANUAL SWITCH -->
                    <td style="width:120px; text-align:center;">
                        <div class="form-check form-switch">
                            <input class="form-check-input"
                                type="checkbox"
                                name="auto_mode"
                                <?= $player['auto_mode'] == 1 ? 'checked' : '' ?>
                                <?= $game['started'] ? 'disabled' : '' ?>>
                            <label class="form-check-label">
                                <?= $player['auto_mode'] == 1 ? 'Auto' : 'Manual' ?>
                            </label>
                        </div>
                    </td>

                    <!-- CARD COUNT -->
                    <td style="width:120px;">
                        <input type="number"
                            name="card_count"
                            class="form-control"
                            min="1"
                            value="<?= $player['card_count'] ?? 1 ?>"
                            <?= $game['started'] ? 'disabled' : '' ?>>
                    </td>

                    <!-- UPDATE BUTTON -->
                    <td style="width:120px;">
                        <?php if (!$game['started']): ?>
                            <input type="hidden" name="user_id" value="<?= $player['id'] ?>">
                            <button type="submit"
                                    name="update_player"
                                    class="btn btn-sm btn-success w-100">
                                Update
                            </button>
                        <?php else: ?>
                            <span class="text-muted">Locked</span>
                        <?php endif; ?>
                    </td>
                </form>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php endif; ?>