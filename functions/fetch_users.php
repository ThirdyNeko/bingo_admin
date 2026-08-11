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
     * DataTables columns:
     *
     * 0 = Name
     * 1 = Department
     * 2 = Role
     */
    $columns = [
        0 => 'name',
        1 => 'department',
        2 => 'role'
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
     * Pagination
     */
    $startRow = $start + 1;
    $endRow = $start + $length;


    /*
     * Base WHERE
     */
    $where = "WHERE role != :admin";

    $params = [
        ':admin' => 'admin'
    ];


    /*
     * Search
     */
    if ($search !== '') {

        $where .= "
            AND (
                name LIKE :search
                OR department LIKE :search
                OR role LIKE :search
            )
        ";

        $params[':search'] = '%' . $search . '%';
    }


    /*
     * Total records
     */
    $countStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM users
        WHERE role != :admin
    ");

    $countStmt->execute([
        ':admin' => 'admin'
    ]);

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
     * SQL Server 2012-compatible pagination
     */
    $sql = "
        SELECT
            id_number,
            name,
            department,
            role
        FROM
        (
            SELECT
                id_number,
                name,
                department,
                role,

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


    /*
     * DataTables data
     */
    $data = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

        $idNumber = htmlspecialchars(
            $row['id_number'] ?? '',
            ENT_QUOTES,
            'UTF-8'
        );

        $name = htmlspecialchars(
            $row['name'] ?? '',
            ENT_QUOTES,
            'UTF-8'
        );

        $department = htmlspecialchars(
            $row['department'] ?? '',
            ENT_QUOTES,
            'UTF-8'
        );


        /*
         * Role dropdown
         *
         * ID is kept internally in data-user-id
         * but is NOT displayed.
         */
        $roleSelect = '
            <select
                class="form-select form-select-sm user-role"
                data-name="' . $name . '"
                data-department="' . $department . '"
                data-original-role="' . htmlspecialchars(
                    $row['role'] ?? '',
                    ENT_QUOTES,
                    'UTF-8'
                ) . '"
            >

                <option value="player" ' .
                    ($row['role'] === 'player'
                        ? 'selected'
                        : '') . '
                >
                    Player
                </option>

                <option value="priority" ' .
                    ($row['role'] === 'priority'
                        ? 'selected'
                        : '') . '
                >
                    Priority
                </option>

                <option value="gamemaster" ' .
                    ($row['role'] === 'gamemaster'
                        ? 'selected'
                        : '') . '
                >
                    Game Master
                </option>

            </select>
        ';


        /*
         * Only display:
         *
         * Name
         * Department
         * Role
         */
        $data[] = [
            $name,
            $department,
            $roleSelect
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
        'draw' => isset($draw) ? $draw : 0,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => $e->getMessage()
    ]);
}
