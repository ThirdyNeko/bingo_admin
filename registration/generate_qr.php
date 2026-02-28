<?php

$pageUrl = "https://192.168.40.14/registration/register.php";

$qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($pageUrl);

?>

<!DOCTYPE html>
<html>
<head>
    <title>Venue Registration QR</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-5 text-center">
    <div class="card shadow p-4 rounded-4 mx-auto" style="max-width:400px;">
        <h4 class="mb-3">Scan to Register</h4>

        <img src="<?= $qrUrl ?>" class="img-fluid mb-3" alt="QR Code">

        <p class="small text-muted">
            Scan this QR code to register for the Bingo venue.
        </p>
    </div>
</div>

</body>
</html>