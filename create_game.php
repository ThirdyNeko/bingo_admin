<?php
require_once 'config/db.php';
include 'partials/header.php';
include 'partials/sidebar.php';

$success = '';
$error = '';

function generateGameCode($length = 5) {
    return 'BINGO-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, $length));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pattern_json = $_POST['pattern_json'] ?? '';
    $winners = (int) ($_POST['winners'] ?? 1);

    if (empty($pattern_json) || $winners <= 0) {
        $error = "All fields are required.";
    } else {
        $gameCode = generateGameCode();
        $insert = $pdo->prepare("
            INSERT INTO game (pattern, winners, game_winners, game_code)
            VALUES (?, ?, 0, ?)
        ");
        $insert->execute([$pattern_json, $winners, $gameCode]);
        $success = "Game Created Successfully! Code: $gameCode";
    }
}
?>

<div class="col-md-10 p-4 d-flex justify-content-center">
    <div style="width:100%; max-width:600px;">
    <h3 class="mb-4">Create Game</h3>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white">
            Define Winning Pattern
        </div>
        <div class="card-body">

            <form method="POST">

                <!-- Number of Winners -->
                <div class="mb-4">
                    <label class="form-label fw-bold">Number of Winners</label>
                    <input type="number" name="winners"
                           class="form-control"
                           min="1"
                           value="1"
                           required>
                </div>

                <!-- Bingo Pattern Grid -->
                <div class="text-center mb-4">
                    <table class="table table-bordered table-dark m-auto bingo-table">
                        <thead>
                            <tr>
                                <th>B</th>
                                <th>I</th>
                                <th>N</th>
                                <th>G</th>
                                <th>O</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php for($r=0;$r<5;$r++): ?>
                                <tr>
                                    <?php for($c=0;$c<5;$c++): ?>
                                        <?php if($r==2 && $c==2): ?>
                                            <td class="free">FREE</td>
                                        <?php else: ?>
                                            <td class="pattern-cell"
                                                data-row="<?= $r ?>"
                                                data-col="<?= $c ?>">
                                            </td>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>

                <input type="hidden" name="pattern_json" id="pattern_json" required>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100">
                        🎯 Create Game
                    </button>
                    <button type="button" id="resetPattern"
                            class="btn btn-outline-secondary w-100">
                        Reset Pattern
                    </button>
                </div>

            </form>

        </div>
    </div>
    </div>
</div>

<style>
.bingo-table td,
.bingo-table th {
    width: 60px;
    height: 60px;
    text-align: center;
    vertical-align: middle;
    cursor: pointer;
    font-weight: bold;
    transition: 0.2s ease-in-out;
}

.pattern-cell:hover {
    transform: scale(1.05);
}

.pattern-cell.active {
    background-color: #198754 !important;
    color: white;
}

.free {
    background-color: #ffc107 !important;
    color: black;
}
</style>

<script>
const size = 5;
const pattern = Array.from({length:size}, () => Array(size).fill(0));
const hiddenInput = document.getElementById('pattern_json');

document.querySelectorAll('.pattern-cell').forEach(cell => {
    cell.addEventListener('click', function() {
        const row = parseInt(this.dataset.row);
        const col = parseInt(this.dataset.col);

        pattern[row][col] = pattern[row][col] ? 0 : 1;
        this.classList.toggle('active');

        hiddenInput.value = JSON.stringify(pattern);
    });
});

// Reset Button
document.getElementById('resetPattern').addEventListener('click', function() {
    document.querySelectorAll('.pattern-cell').forEach(cell => {
        cell.classList.remove('active');
    });

    for (let r = 0; r < size; r++) {
        for (let c = 0; c < size; c++) {
            pattern[r][c] = 0;
        }
    }

    hiddenInput.value = '';
});
</script>

</div></div>
</body>
</html>
</div></div>
</body>
</html>