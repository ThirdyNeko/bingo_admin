<?php
require_once 'config/db.php';

// One row per session_id, aggregated across all game rounds under that session
$stmt = $pdo->query("
    SELECT
        session_id,
        COUNT(*) AS rounds,
        SUM(winners) AS total_winners
    FROM game
    WHERE session_id IS NOT NULL AND session_id <> ''
      AND game_winners IS NOT NULL
      AND game_winners <> ''
      AND game_winners <> '0'
      AND game_winners <> '[]'
    GROUP BY session_id
    ORDER BY session_id DESC
");
$sessions = $stmt->fetchAll();

include 'partials/header.php';
include 'partials/sidebar.php';
?>

<div class="col-md-10 p-4">
    <h3 class="mb-4">Winners</h3>

    <table id="sessionsTable" class="table table-striped table-hover align-middle">
        <thead>
            <tr>
                <th>Session ID</th>
                <th>Rounds</th>
                <th>Total Winners</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($sessions as $s):
                $displayDate = $s['session_id'];
                $d = DateTime::createFromFormat('Ymd', $s['session_id']);
                if ($d !== false) {
                    $displayDate = $d->format('M j, Y');
                }
            ?>
                <tr class="session-row" data-session-id="<?= htmlspecialchars($s['session_id']) ?>" style="cursor:pointer;">
                    <td><?= htmlspecialchars($s['session_id']) ?></td>
                    <td><?= (int)$s['rounds'] ?></td>
                    <td><?= (int)$s['total_winners'] ?></td>
                    <td><?= htmlspecialchars($displayDate) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Winners Modal -->
<div class="modal fade" id="winnersModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title">Session Winners — <span id="modalSessionId"></span></h5>
            
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div id="winnersLoading" class="text-center py-4 d-none">
                <div class="spinner-border" role="status"></div>
            </div>
            <table id="winnersDetailTable" class="table table-bordered align-middle">
                <thead>
                    <tr>
                        <th>Game Code</th>
                        <th>Rank</th>
                        <th>Winner</th>
                        <th>Prize</th>
                        <th>Description</th>
                        <th>Picture</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
            <a id="screenViewLink" href="#" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success ms-auto me-3">
                🎬 Screen View
            </a>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
    </div>
  </div>
</div>

<script src="js/winners/winners_list.js"></script>

</div>
</body>
</html>