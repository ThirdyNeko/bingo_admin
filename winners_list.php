<?php
require_once 'config/db.php';

// One row per session_id, aggregated across all game rounds under that session
$stmt = $pdo->query("
    SELECT
        session_id,
        COUNT(*)     AS rounds,
        SUM(winners)  AS total_winners
    FROM game
    WHERE session_id IS NOT NULL AND session_id <> ''
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    const winnersModalEl = document.getElementById('winnersModal');
    const winnersModal = new bootstrap.Modal(winnersModalEl);
    const detailBody = document.querySelector('#winnersDetailTable tbody');
    const loading = document.getElementById('winnersLoading');

    function renderTable(rounds) {
        detailBody.innerHTML = '';

        if (!rounds.length) {
            detailBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No winners found for this session.</td></tr>';
            return;
        }

        rounds.forEach(function (round) {
            round.winners.forEach(function (w, index) {
                const tr = document.createElement('tr');

                // Only render game-code and screen-link cells on the round's first row,
                // spanning all of that round's winner rows
                if (index === 0) {
                    const tdCode = document.createElement('td');
                    tdCode.textContent = round.game_code || '';
                    tdCode.rowSpan = round.winners.length;
                    tr.appendChild(tdCode);
                }

                const tdRank = document.createElement('td');
                tdRank.textContent = '#' + (index + 1);
                tr.appendChild(tdRank);

                const tdWinner = document.createElement('td');
                tdWinner.textContent = w.name || '';
                tr.appendChild(tdWinner);

                const tdPrize = document.createElement('td');
                tdPrize.textContent = round.prize ? (round.prize.name || '') : '';
                tr.appendChild(tdPrize);

                const tdDesc = document.createElement('td');
                tdDesc.textContent = round.prize ? (round.prize.description || '') : '';
                tr.appendChild(tdDesc);

                const tdPic = document.createElement('td');
                if (round.prize && round.prize.picture) {
                    const img = document.createElement('img');
                    img.src = 'data:image/jpeg;base64,' + round.prize.picture;
                    img.style.maxWidth = '60px';
                    img.style.maxHeight = '60px';
                    img.style.objectFit = 'cover';
                    img.style.borderRadius = '4px';
                    tdPic.appendChild(img);
                } else {
                    tdPic.textContent = '—';
                }
                tr.appendChild(tdPic);

                detailBody.appendChild(tr);
            });
        });
    }

    document.querySelectorAll('#sessionsTable tbody tr.session-row').forEach(function (row) {
        row.addEventListener('click', function () {
            const sessionId = row.dataset.sessionId;
            document.getElementById('modalSessionId').textContent = sessionId;
            document.getElementById('screenViewLink').href = 'session_winner.php?session_id=' + encodeURIComponent(sessionId);
            detailBody.innerHTML = '';
            loading.classList.remove('d-none');
            winnersModal.show();

            fetch('functions/get_session_winners.php?session_id=' + encodeURIComponent(sessionId))
                .then(function (res) {
                    if (!res.ok) throw new Error('Request failed');
                    return res.json();
                })
                .then(function (rounds) {
                    loading.classList.add('d-none');
                    renderTable(rounds);
                })
                .catch(function () {
                    loading.classList.add('d-none');
                    detailBody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Failed to load winners.</td></tr>';
                });
        });
    });
});
</script>

</div>
</body>
</html>