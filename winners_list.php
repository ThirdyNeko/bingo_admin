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

$pageStyles = [
    'css/datatables.min.css',
    'css/winners_list.css',
];
$bodyClass = 'winners-list-page';

include 'partials/header.php';
include 'partials/sidebar.php';
?>

<div class="col-md-10 manage-games-content p-4">

    <!-- PAGE HEADER -->
    <div class="mg-page-header">
        <div>
            <span class="mg-eyebrow">RESULTS</span>
            <h2>Winners</h2>
            <p>Review winners from every session and open the winners screen.</p>
        </div>
    </div>

    <!-- SESSIONS CARD -->
    <div class="mg-card">
        <div class="mg-card-header">
            <div class="mg-card-title">
                <div class="mg-icon"><i class="bi bi-trophy"></i></div>
                <div>
                    <h5>Session Winners</h5>
                    <span>Click a session to view its winners</span>
                </div>
            </div>
        </div>

        <div class="mg-card-body">
            <div class="mg-table-wrap">
                <table id="sessionsTable" class="table align-middle">
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
                            <tr class="session-row" data-session-id="<?= htmlspecialchars($s['session_id']) ?>">
                                <td><?= htmlspecialchars($s['session_id']) ?></td>
                                <td><?= (int)$s['rounds'] ?></td>
                                <td><span class="mg-count-badge"><?= (int)$s['total_winners'] ?></span></td>
                                <td><?= htmlspecialchars($displayDate) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Winners Modal -->
<div class="modal fade" id="winnersModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
    <div class="modal-content">
        <div class="modal-header">
            <div class="mg-modal-heading">
                <div class="mg-icon"><i class="bi bi-trophy"></i></div>
                <div>
                    <h5 class="modal-title">Session Winners</h5>
                    <span>Session <b id="modalSessionId"></b></span>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <div id="winnersLoading" class="text-center py-4 d-none">
                <div class="spinner-border" role="status"></div>
            </div>
            <div class="table-responsive">
                <table id="winnersDetailTable" class="table align-middle mb-0">
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
            </div>
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

<script src="js/jquery.min.js"></script>
<script src="js/datatables.min.js"></script>
<script src="js/winners/winners_list.js"></script>

</div>
</body>
</html>