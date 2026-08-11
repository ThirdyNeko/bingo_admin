<?php
require_once '../config/db.php';

header('Content-Type: application/json');

// ===== DataTables request params =====
$draw   = isset($_POST['draw']) ? (int) $_POST['draw'] : 1;
$start  = isset($_POST['start']) ? (int) $_POST['start'] : 0;
$length = isset($_POST['length']) ? (int) $_POST['length'] : 25;
$searchValue = trim($_POST['search']['value'] ?? '');

if ($length <= 0) {
    $length = 25;
}

$page = (int) floor($start / $length) + 1;

// ===== WHERE clause (search on game_code only) =====
$where = '';
$params = [];

if ($searchValue !== '') {
    $where = "WHERE game_code LIKE ?";
    $params[] = '%' . $searchValue . '%';
}

// ===== Total count (unfiltered) =====
$totalCount = (int) $pdo->query("SELECT COUNT(*) FROM game")->fetchColumn();

// ===== Filtered count =====
$countSql = "SELECT COUNT(*) FROM game $where";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$filteredCount = (int) $countStmt->fetchColumn();

// ===== Paged data (ROW_NUMBER for SQL Server 2012 compatibility) =====
$sql = "
    SELECT id, game_code, winners, game_winners
    FROM (
        SELECT
            id, game_code, winners, game_winners,
            ROW_NUMBER() OVER (ORDER BY id DESC) AS rn
        FROM game
        $where
    ) AS paged
    WHERE rn BETWEEN ? AND ?
";

$rowStart = $start + 1;
$rowEnd   = $start + $length;

$dataParams = array_merge($params, [$rowStart, $rowEnd]);

$stmt = $pdo->prepare($sql);
$stmt->execute($dataParams);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ===== Build response rows =====
$data = [];
foreach ($rows as $g) {
    $winners = json_decode($g['game_winners'], true);
    $winnersDisplay = !empty($winners)
        ? htmlspecialchars(implode(', ', $winners))
        : '—';

    $data[] = [
        'game_code'       => htmlspecialchars($g['game_code']),
        'winners'         => (int) $g['winners'],
        'winners_declared'=> $winnersDisplay,
        'action'          => '<a href="manage_game.php?game_id=' . (int) $g['id'] . '" class="btn btn-sm btn-primary">Manage</a>',
    ];
}

echo json_encode([
    'draw'            => $draw,
    'recordsTotal'    => $totalCount,
    'recordsFiltered' => $filteredCount,
    'data'            => $data,
]);