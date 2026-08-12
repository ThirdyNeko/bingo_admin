<?php
require_once '../config/db.php';

header('Content-Type: application/json');

// ===== DataTables request params (read early so the error response can include them) =====
$draw = isset($_POST['draw']) ? (int) $_POST['draw'] : 1;

if (!isset($_POST['game_id'])) {
    echo json_encode([
        'draw'            => $draw,
        'recordsTotal'    => 0,
        'recordsFiltered' => 0,
        'data'            => [],
        'error'           => 'Missing game_id',
    ]);
    exit;
}

$gameId = (int) $_POST['game_id'];

$start  = isset($_POST['start']) ? (int) $_POST['start'] : 0;
$length = isset($_POST['length']) ? (int) $_POST['length'] : 25;
$searchValue = trim($_POST['search']['value'] ?? '');

if ($length <= 0) {
    $length = 25;
}

// ===== WHERE clause (always scoped to game_id, optional name search) =====
$where = "WHERE u.current_game = ?";
$params = [$gameId];

if ($searchValue !== '') {
    $where .= " AND u.name LIKE ?";
    $params[] = '%' . $searchValue . '%';
}

// ===== Total count for this game (unfiltered) =====
$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE current_game = ?");
$totalStmt->execute([$gameId]);
$totalCount = (int) $totalStmt->fetchColumn();

// ===== Filtered count =====
$countSql = "SELECT COUNT(*) FROM users u $where";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$filteredCount = (int) $countStmt->fetchColumn();

// ===== Paged data (ROW_NUMBER for SQL Server 2012 compatibility) =====
// actual_cards = how many cards actually exist in user_cards for this game right now
// (only meaningful once the game has started; will be 0 for everyone before that).
$sql = "
    SELECT id, name, auto_mode, card_count, actual_cards
    FROM (
        SELECT
            u.id, u.name, u.auto_mode, u.card_count,
            (SELECT COUNT(*) FROM user_cards uc WHERE uc.user_id = u.id AND uc.game_id = ?) AS actual_cards,
            ROW_NUMBER() OVER (ORDER BY u.id ASC) AS rn
        FROM users u
        $where
    ) AS paged
    WHERE rn BETWEEN ? AND ?
";

$rowStart = $start + 1;
$rowEnd   = $start + $length;

$dataParams = array_merge([$gameId], $params, [$rowStart, $rowEnd]);

$stmt = $pdo->prepare($sql);
$stmt->execute($dataParams);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ===== Build response rows =====
$data = [];
$rowNum = $start + 1;

foreach ($rows as $p) {
    $modeBadge = $p['auto_mode']
        ? '<span class="badge bg-primary">Auto</span>'
        : '<span class="badge bg-secondary">Manual</span>';

    $data[] = [
        'row_num'      => $rowNum++,
        'name'         => htmlspecialchars($p['name']),
        'mode'         => $modeBadge,
        'card_count'   => (int) ($p['card_count'] ?? 1),
        'actual_cards' => (int) ($p['actual_cards'] ?? 0),
    ];
}

echo json_encode([
    'draw'            => $draw,
    'recordsTotal'    => $totalCount,
    'recordsFiltered' => $filteredCount,
    'data'            => $data,
]);