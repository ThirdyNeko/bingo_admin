<?php
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_POST['game_id'])) {
    echo json_encode(['error' => 'Missing game_id']);
    exit;
}

$gameId = (int) $_POST['game_id'];

// ===== DataTables request params =====
$draw   = isset($_POST['draw']) ? (int) $_POST['draw'] : 1;
$start  = isset($_POST['start']) ? (int) $_POST['start'] : 0;
$length = isset($_POST['length']) ? (int) $_POST['length'] : 25;
$searchValue = trim($_POST['search']['value'] ?? '');

if ($length <= 0) {
    $length = 25;
}

// ===== WHERE clause (always scoped to game_id, optional name search) =====
$where = "WHERE current_game = ?";
$params = [$gameId];

if ($searchValue !== '') {
    $where .= " AND name LIKE ?";
    $params[] = '%' . $searchValue . '%';
}

// ===== Total count for this game (unfiltered) =====
$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE current_game = ?");
$totalStmt->execute([$gameId]);
$totalCount = (int) $totalStmt->fetchColumn();

// ===== Filtered count =====
$countSql = "SELECT COUNT(*) FROM users $where";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$filteredCount = (int) $countStmt->fetchColumn();

// ===== Paged data (ROW_NUMBER for SQL Server 2012 compatibility) =====
$sql = "
    SELECT id, name, auto_mode, card_count
    FROM (
        SELECT
            id, name, auto_mode, card_count,
            ROW_NUMBER() OVER (ORDER BY id ASC) AS rn
        FROM users
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
$rowNum = $start + 1;

foreach ($rows as $p) {
    $modeBadge = $p['auto_mode']
        ? '<span class="badge bg-primary">Auto</span>'
        : '<span class="badge bg-secondary">Manual</span>';

    $data[] = [
        'row_num'    => $rowNum++,
        'name'       => htmlspecialchars($p['name']),
        'mode'       => $modeBadge,
        'card_count' => (int) ($p['card_count'] ?? 1),
    ];
}

echo json_encode([
    'draw'            => $draw,
    'recordsTotal'    => $totalCount,
    'recordsFiltered' => $filteredCount,
    'data'            => $data,
]);