<?php
require_once '../config/db.php';

if (!isset($_GET['game_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'game_id required']);
    exit;
}

$gameId = (int) $_GET['game_id'];

$playersStmt = $pdo->prepare("SELECT * FROM users WHERE current_game = ?");
$playersStmt->execute([$gameId]);
$players = $playersStmt->fetchAll();

ob_start();

if (empty($players)) {
    echo '<p class="text-muted">No players joined yet.</p>';
} else {
    ?>
    <div class="table-responsive">
        <table class="table table-bordered align-middle">
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
                        <td style="width:50px; text-align:center;">
                            <?= $index + 1 ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($player['name']) ?>
                        </td>

                        <td style="width:120px; text-align:center;">
                            <span class="badge <?= $player['auto_mode'] ? 'bg-primary' : 'bg-secondary' ?>">
                                <?= $player['auto_mode'] ? 'Auto' : 'Manual' ?>
                            </span>
                        </td>

                        <td style="width:120px; text-align:center;">
                            <?= $player['card_count'] ?? 1 ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

$html = ob_get_clean();

header('Content-Type: application/json');
echo json_encode([
    'count' => count($players),
    'html'  => $html,
]);