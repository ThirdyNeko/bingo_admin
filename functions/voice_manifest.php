<?php
// functions/voice_manifest.php
//
// Lists the voice clips that exist for each bingo number so screen.js can
// pick one at random. Numbers can have 1, 2 or more clips:
//
//   js/audio/numbers/b1.mp3      <- first clip for B1
//   js/audio/numbers/b1_2.mp3    <- optional extra clip for B1
//   js/audio/numbers/b1_3.mp3    <- optional extra clip for B1
//   js/audio/numbers/o75.mp3
//
// Output example: {"b1":["b1.mp3","b1_2.mp3"],"b2":["b2.mp3"], ...}
// Numbers with no clip are simply absent (the screen stays silent for them).

header('Content-Type: application/json');
header('Cache-Control: no-store');

$dir = __DIR__ . '/../js/audio/numbers';

$manifest = [];

if (is_dir($dir)) {
    $files = scandir($dir);
    sort($files);

    foreach ($files as $f) {
        // b1.mp3, b1_2.mp3, o75_3.mp3 ... (letter + 1-2 digits + optional _N)
        if (preg_match('/^([bingo])(\d{1,2})(?:_\d+)?\.mp3$/i', $f, $m)) {
            $letter = strtolower($m[1]);
            $number = (int) $m[2];

            // Only accept a letter/number pair that is valid for that column
            $ranges = [
                'b' => [1, 15],
                'i' => [16, 30],
                'n' => [31, 45],
                'g' => [46, 60],
                'o' => [61, 75],
            ];
            if ($number < $ranges[$letter][0] || $number > $ranges[$letter][1]) {
                continue;
            }

            $manifest[$letter . $number][] = $f;
        }
    }
}

// (object) cast keeps an empty manifest as {} while the clip lists stay arrays
echo json_encode((object) $manifest);