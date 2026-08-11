<?php
require_once 'config/db.php';
require_once 'partials/header.php';
require_once 'partials/sidebar.php';
?>

<div class="col-md-10 p-4">

    <h2 class="mb-4">User Role Management</h2>

    <div id="roleAlert"></div>

    <div class="card shadow-sm">

        <div class="card-header">
            <strong>Users</strong>
        </div>

        <div class="card-body">

            <table id="usersTable" class="table table-striped table-bordered w-100">

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

<link rel="stylesheet" href="css/datatables.min.css">

<script src="js/jquery-4.0.0.min.js"></script>
<script src="js/datatables.min.js"></script>
<script src="js/admin/admin.js"></script>
