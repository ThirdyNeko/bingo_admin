<?php

require_once 'config/db.php';

$pageStyles = [
    'css/datatables.min.css',
    'css/player_settings.css',
];

include 'partials/header.php';
include 'partials/sidebar.php';

?>

<div class="col-md-10 manage-games-content p-4">

    <!-- PAGE HEADER -->
    <div class="mg-page-header">
        <div>
            <span class="mg-eyebrow">SETTINGS</span>
            <h2>Player Settings</h2>
            <p>Choose auto or manual mode and set the number of cards for each player.</p>
        </div>
    </div>

    <div id="settingsAlert"></div>

    <!-- PLAYERS CARD -->
    <div class="mg-card">

        <div class="mg-card-header">
            <div class="mg-card-title">
                <div class="mg-icon"><i class="bi bi-people"></i></div>
                <div>
                    <h5>Players</h5>
                    <span>Changes are saved as soon as you edit a row</span>
                </div>
            </div>
        </div>

        <div class="mg-card-body">
            <div class="mg-table-wrap">

                <table id="usersTable" class="table align-middle w-100">

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

</div>

<script src="js/jquery-4.0.0.min.js"></script>
<script src="js/datatables.min.js"></script>
<script src="js/settings/settings.js"></script>

</body>
</html>