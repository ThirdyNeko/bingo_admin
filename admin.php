<?php
require_once 'config/db.php';

$pageStyles = [
    'css/datatables.min.css',
    'css/user_roles.css',
];

require_once 'partials/header.php';
require_once 'partials/sidebar.php';
?>

<div class="col-md-10 manage-games-content p-4">

    <!-- PAGE HEADER -->
    <div class="mg-page-header">
        <div>
            <span class="mg-eyebrow">ADMINISTRATION</span>
            <h2>User Role Management</h2>
            <p>Assign a role to each user. Changes are saved as soon as you pick a role.</p>
        </div>
    </div>

    <div id="roleAlert"></div>

    <!-- USERS CARD -->
    <div class="mg-card">

        <div class="mg-card-header">
            <div class="mg-card-title">
                <div class="mg-icon"><i class="bi bi-person-gear"></i></div>
                <div>
                    <h5>Users</h5>
                    <span>Search, sort and update roles</span>
                </div>
            </div>
        </div>

        <div class="mg-card-body">
            <div class="mg-table-wrap">

                <table id="usersTable" class="table align-middle w-100">

                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Department</th>
                            <th>Role</th>
                        </tr>
                    </thead>

                    <tbody>
                        <!-- Loaded by DataTables -->
                    </tbody>

                </table>

            </div>
        </div>
    </div>

</div>

<script src="js/jquery-4.0.0.min.js"></script>
<script src="js/datatables.min.js"></script>
<script src="js/admin/admin.js"></script>

</body>
</html>