<?php
require_once 'config/db.php';
include 'partials/header.php';
include 'partials/sidebar.php';

/* Get only admins */
$players = $pdo->query("
    SELECT *
    FROM users
    WHERE role IN ('admin')
    ORDER BY id ASC
")->fetchAll();

/* Update player settings */
if (isset($_POST['update_player'])) {

    $userId = (int)$_POST['user_id'];
    $cardCount = max(1, (int)$_POST['card_count']);
    $autoMode = isset($_POST['auto_mode']) ? 1 : 0;

    $stmt = $pdo->prepare("
        UPDATE users
        SET auto_mode = ?, card_count = ?
        WHERE id = ?
    ");
    $stmt->execute([$autoMode, $cardCount, $userId]);

    header("Location: settings.php");
    exit;
}
?>

<div class="col-md-10 p-4">
    <h3 class="mb-4">Player Settings</h3>

    <div class="card shadow-sm">
        <div class="card-body table-responsive">

            <table class="table table-striped align-middle">

                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Mode</th>
                        <th>Cards</th>
                        <th>Save</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($players as $index => $player): ?>
                    <tr>
                        <form method="POST">

                            <td style="width:60px;">
                                <?= $index + 1 ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($player['name']) ?>
                            </td>

                            <!-- AUTO / MANUAL -->
                            <td style="width:150px;">
                                <div class="form-check form-switch">
                                    <input class="form-check-input"
                                           type="checkbox"
                                           name="auto_mode"
                                           value="1"
                                           <?= $player['auto_mode'] ? 'checked' : '' ?>>
                                    <label class="form-check-label">
                                        Auto
                                    </label>
                                </div>
                            </td>

                            <!-- CARD COUNT -->
                            <td style="width:120px;">
                                <input type="number"
                                       name="card_count"
                                       class="form-control"
                                       min="1"
                                       value="<?= $player['card_count'] ?? 1 ?>">
                            </td>

                            <!-- SAVE -->
                            <td style="width:120px;">
                                <input type="hidden" name="user_id" value="<?= $player['id'] ?>">

                                <button type="submit"
                                        name="update_player"
                                        class="btn btn-sm btn-success w-100">
                                    Save
                                </button>
                            </td>

                        </form>
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