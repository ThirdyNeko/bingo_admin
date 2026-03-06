<div class="col-md-2 sidebar p-3">
    <h4 class="text-center">BINGO ADMIN</h4>
    <hr>
    <a href="index.php" class="<?= basename($_SERVER['PHP_SELF'])=='index.php'?'active-link':'' ?>">
        Dashboard
    </a>

    <a href="create_game.php" class="<?= basename($_SERVER['PHP_SELF'])=='create_game.php'?'active-link':'' ?>">
        Create Game
    </a>

    <a href="manage_games.php" 
        class="<?= in_array(basename($_SERVER['PHP_SELF']), ['manage_games.php','manage_game.php']) ? 'active-link' : '' ?>">
        Manage Games
    </a>

    <a href="settings.php" class="<?= basename($_SERVER['PHP_SELF'])=='settings.php'?'active-link':'' ?>">
        Settings
    </a>

    <a href="auth/logout.php" class="text-danger">
        Logout
    </a>
</div>