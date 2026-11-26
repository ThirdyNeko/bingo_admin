<?php
/*
 * Cached QR code images (qr/qr_<GAMECODE>_<hash>.jpg).
 *
 * screen.php creates one the first time a game's screen is opened. These
 * helpers remove them again so the qr/ folder doesn't fill up:
 *   - qrCacheDeleteForGame(): when a game is finished
 *   - qrCacheSweep():         anything older than a day (abandoned/cancelled games,
 *                             or files left over from the old naming scheme)
 * A deleted file is simply re-created the next time screen.php needs it.
 */

/** Absolute path of the qr/ folder (sits in the project root, next to screen.php). */
function qrCacheDir(): string
{
    return dirname(__DIR__) . '/qr';
}

/** Game codes are letters/digits; strip anything else so the value is safe in a file name. */
function qrCacheSafeCode(string $gameCode): string
{
    return preg_replace('/[^A-Za-z0-9]/', '', $gameCode);
}

/**
 * File name for a game's QR. The game code is part of the name so the file can
 * be found again later; the short hash of the join URL means a different
 * host/IP gets its own image.
 */
function qrCacheFileName(string $gameCode, string $registerUrl): string
{
    return 'qr_' . qrCacheSafeCode($gameCode) . '_' . substr(md5($registerUrl), 0, 8) . '.jpg';
}

/** Delete every cached QR image that belongs to this game code. */
function qrCacheDeleteForGame(string $gameCode): void
{
    $code = qrCacheSafeCode($gameCode);
    if ($code === '') {
        return;
    }

    $files = glob(qrCacheDir() . '/qr_' . $code . '_*.jpg');
    foreach ($files ?: [] as $file) {
        @unlink($file);
    }
}

/** Delete cached QR images older than $maxAgeSeconds, plus stray temp files. */
function qrCacheSweep(int $maxAgeSeconds = 86400): void
{
    $dir = qrCacheDir();
    if (!is_dir($dir)) {
        return;
    }

    $now = time();

    foreach (glob($dir . '/qr_*.jpg') ?: [] as $file) {
        $mtime = @filemtime($file);
        if ($mtime !== false && ($now - $mtime) > $maxAgeSeconds) {
            @unlink($file);
        }
    }

    // Half-written files from an interrupted save.
    foreach (glob($dir . '/qr_*.tmp') ?: [] as $file) {
        $mtime = @filemtime($file);
        if ($mtime !== false && ($now - $mtime) > 3600) {
            @unlink($file);
        }
    }
}