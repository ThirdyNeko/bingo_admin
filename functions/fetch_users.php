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
     * ==========================================================
     * TOTAL RECORDS
     * ==========================================================
     *
     * Count all non-admin users.
     */
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) AS total
        FROM users
        WHERE role <> ?
    ");

    $countStmt->execute(['admin']);

    $recordsTotal = (int) $countStmt->fetchColumn();


    /*
     * ==========================================================
     * FILTERED RECORDS
     * ==========================================================
     */
    $filteredSql = "
        SELECT COUNT(*) AS total
        FROM users
        WHERE role <> ?
    ";

    $filteredParams = ['admin'];


    if ($search !== '') {

        $filteredSql .= "
            AND (
                name LIKE ?
                OR department LIKE ?
                OR role LIKE ?
            )
        ";

        $searchValue = '%' . $search . '%';

        $filteredParams[] = $searchValue;
        $filteredParams[] = $searchValue;
        $filteredParams[] = $searchValue;
    }


    $filteredStmt = $pdo->prepare($filteredSql);

    $filteredStmt->execute($filteredParams);

    $recordsFiltered = (int) $filteredStmt->fetchColumn();


    /*
     * ==========================================================
     * DATA QUERY
     * ==========================================================
     *
     * SQL Server 2012 compatible.
     *
     * Uses ROW_NUMBER() instead of OFFSET/FETCH.
     */
    $sql = "
        SELECT
            name,
            department,
            role
        FROM
        (
            SELECT
                name,
                department,
                role,

                ROW_NUMBER() OVER (
                    ORDER BY $orderColumn $orderDirection
                ) AS row_num

            FROM users

            WHERE role <> ?
    ";

    $dataParams = ['admin'];


    /*
     * Search condition
     */
    if ($search !== '') {

        $sql .= "
            AND (
                name LIKE ?
                OR department LIKE ?
                OR role LIKE ?
            )
        ";

        $searchValue = '%' . $search . '%';

        $dataParams[] = $searchValue;
        $dataParams[] = $searchValue;
        $dataParams[] = $searchValue;
    }


    $sql .= "
        ) AS numbered_users

        WHERE row_num BETWEEN ? AND ?

        ORDER BY row_num
    ";


    $dataParams[] = $startRow;
    $dataParams[] = $endRow;


    $stmt = $pdo->prepare($sql);

    $stmt->execute($dataParams);


    /*
     * ==========================================================
     * DATA TABLE
     * ==========================================================
     */
    $data = [];


    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

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

        $role = $row['role'] ?? '';


        $roleEscaped = htmlspecialchars(
            $role,
            ENT_QUOTES,
            'UTF-8'
        );


        /*
         * Role dropdown
         */
        $roleSelect = '
            <select
                class="form-select form-select-sm user-role"
                data-name="' . $name . '"
                data-department="' . $department . '"
                data-original-role="' . $roleEscaped . '"
            >

                <option value="player" ' .
                    ($role === 'player'
                        ? 'selected'
                        : '') . '
                >
                    Player
                </option>

                <option value="priority" ' .
                    ($role === 'priority'
                        ? 'selected'
                        : '') . '
                >
                    Priority
                </option>

                <option value="gamemaster" ' .
                    ($role === 'gamemaster'
                        ? 'selected'
                        : '') . '
                >
                    Game Master
                </option>

            </select>
        ';


        /*
         * DataTables columns:
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


    /*
     * ==========================================================
     * RESPONSE
     * ==========================================================
     */
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