<?php

require_once 'config/db.php';

include 'partials/header.php';
include 'partials/sidebar.php';

?>

<div class="col-md-10 p-4">

    <h3 class="mb-4">Player Settings</h3>

    <div id="settingsAlert"></div>

    <div class="card shadow-sm">

        <div class="card-header">
            <strong>Players</strong>
        </div>

        <div class="card-body table-responsive">

            <table
                id="usersTable"
                class="table table-striped table-bordered w-100"
            >

                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Mode</th>
                        <th>Cards</th>
                    </tr>
                </thead>

                <tbody>
                    <!-- Loaded through DataTables -->
                </tbody>

            </table>

        </div>

    </div>

</div>

<link rel="stylesheet" href="css/datatables.min.css">

<script src="js/jquery-4.0.0.min.js"></script>
<script src="js/datatables.min.js"></script>
<script src="js/settings/settings.js"></script>

</body>
</html>
