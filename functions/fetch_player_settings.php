<?php

require_once '../config/db.php';

header('Content-Type: application/json');

try {

    $draw = isset($_POST['draw'])
        ? (int) $_POST['draw']
        : 0;

    $start = isset($_POST['start'])
        ? (int) $_POST['start']
        : 0;

    $length = isset($_POST['length'])
        ? (int) $_POST['length']
        : 25;

    $search = isset($_POST['search']['value'])
        ? trim($_POST['search']['value'])
        : '';


    if ($start < 0) {
        $start = 0;
    }

    if ($length < 1) {
        $length = 25;
    }


    /*
     * DataTables columns
     *
     * 0 = Name
     * 1 = Mode
     * 2 = Cards
     */
    $columns = [
        0 => 'name',
        1 => 'auto_mode',
        2 => 'card_count'
    ];


    $orderColumnIndex = isset($_POST['order'][0]['column'])
        ? (int) $_POST['order'][0]['column']
        : 0;

    $orderDirection = isset($_POST['order'][0]['dir'])
        ? strtolower($_POST['order'][0]['dir'])
        : 'asc';

    $orderColumn = $columns[$orderColumnIndex] ?? 'name';

    if (!in_array($orderDirection, ['asc', 'desc'], true)) {
        $orderDirection = 'asc';
    }


    /*
     * ROW_NUMBER pagination
     */
    $startRow = $start + 1;
    $endRow = $start + $length;


    /*
     * Only Admin and Priority
     */
    $where = "
        WHERE role IN ('admin', 'priority')
    ";

    $params = [];


    /*
     * Search
     */
    if ($search !== '') {

        $where .= "
            AND name LIKE :search
        ";

        $params[':search'] = '%' . $search . '%';
    }


    /*
     * Total records
     */
    $countStmt = $pdo->query("
        SELECT COUNT(*)
        FROM users
        WHERE role IN ('admin', 'priority')
    ");

    $recordsTotal = (int) $countStmt->fetchColumn();


    /*
     * Filtered records
     */
    $filteredStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM users
        $where
    ");

    $filteredStmt->execute($params);

    $recordsFiltered = (int) $filteredStmt->fetchColumn();


    /*
     * SQL Server 2012 pagination
     */
    $sql = "
        SELECT
            name,
            auto_mode,
            card_count

        FROM
        (
            SELECT
                name,
                auto_mode,
                card_count,

                ROW_NUMBER() OVER (
                    ORDER BY $orderColumn $orderDirection
                ) AS row_num

            FROM users

            $where

        ) AS numbered_users

        WHERE row_num BETWEEN :startRow AND :endRow

        ORDER BY row_num
    ";


    $stmt = $pdo->prepare($sql);


    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }


    $stmt->bindValue(
        ':startRow',
        $startRow,
        PDO::PARAM_INT
    );

    $stmt->bindValue(
        ':endRow',
        $endRow,
        PDO::PARAM_INT
    );


    $stmt->execute();


    $data = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

        $name = htmlspecialchars(
            $row['name'] ?? '',
            ENT_QUOTES,
            'UTF-8'
        );


        /*
         * AUTO / MANUAL
         */
        $autoMode = '
            <div class="form-check form-switch">

                <input
                    class="form-check-input player-auto-mode"
                    type="checkbox"
                    data-user-name="' . $name . '"
                    ' . (
                        !empty($row['auto_mode'])
                            ? 'checked'
                            : ''
                    ) . '
                >

                <label class="form-check-label">
                    Auto
                </label>

            </div>
        ';


        /*
         * CARD COUNT
         */
        $cardCount = (int) ($row['card_count'] ?? 1);

        if ($cardCount < 1) {
            $cardCount = 1;
        }


        $cardInput = '
            <input
                type="number"
                class="form-control player-card-count"
                data-user-name="' . $name . '"
                min="1"
                value="' . $cardCount . '"
            >
        ';


        $data[] = [
            $name,
            $autoMode,
            $cardInput
        ];
    }


    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
        'data' => $data
    ]);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'draw' => $draw ?? 0,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => $e->getMessage()
    ]);
}