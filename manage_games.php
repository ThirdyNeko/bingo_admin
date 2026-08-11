<?php
require_once 'config/db.php';
include 'partials/header.php';
include 'partials/sidebar.php';
?>

<div class="col-md-10 p-4">
    <h3 class="mb-4">Manage Games</h3>

    <div class="card shadow-sm">
        <div class="card-body table-responsive">
            <table id="gamesTable" class="table table-striped" style="width:100%">
                <thead>
                    <tr>
                        <th>Game Code</th>
                        <th>Winners Needed</th>
                        <th>Winners Declared</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

</div>
</body>
<link rel="stylesheet" href="css/datatables.min.css">
<script src="js/jquery-4.0.0.min.js"></script>
<script src="js/datatables.min.js"></script>

<script src="js/game/manage_games.js"></script>
</html>