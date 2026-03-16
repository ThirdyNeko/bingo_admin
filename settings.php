<?php
require_once 'config/db.php';

/* Update ALL player settings BEFORE output */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['card_count'])) {

    foreach ($_POST['card_count'] as $userId => $cardCount) {

        $userId = (int)$userId;
        $cardCount = max(1, (int)$cardCount);
        $autoMode = isset($_POST['auto_mode'][$userId]) ? 1 : 0;

        $stmt = $pdo->prepare("
            UPDATE users
            SET auto_mode = ?, card_count = ?
            WHERE id = ?
        ");

        $stmt->execute([$autoMode, $cardCount, $userId]);
    }

    header("Location: settings.php");
    exit;
}

/* Get players */
$players = $pdo->query("
    SELECT *
    FROM users
    WHERE role IN ('admin','priority')
    ORDER BY id ASC
")->fetchAll();

/* Include layout AFTER logic */
include 'partials/header.php';
include 'partials/sidebar.php';
?>

<div class="col-md-10 p-4">

<h3 class="mb-4">Player Settings</h3>

<form method="POST">

<div class="card shadow-sm">

<div class="card-header d-flex justify-content-between align-items-center">
<strong>Players</strong>

<button class="btn btn-success btn-sm">
<i class="bi bi-save"></i> Update All
</button>

</div>

<div class="card-body table-responsive">

<table class="table table-striped align-middle">

<thead>
<tr>
<th>#</th>
<th>Name</th>
<th>Mode</th>
<th>Cards</th>
</tr>
</thead>

<tbody>

<?php foreach ($players as $index => $player): ?>

<tr>

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
       name="auto_mode[<?= $player['id'] ?>]"
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
       name="card_count[<?= $player['id'] ?>]"
       class="form-control"
       min="1"
       value="<?= $player['card_count'] ?? 1 ?>">

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>
</div>

</form>

</div>
</body>
</html>