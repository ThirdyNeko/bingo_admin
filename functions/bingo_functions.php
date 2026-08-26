<?php
/* ==============================
   BINGO GAME HELPER FUNCTIONS
============================== */

function calculatePriorityWeight($wins, $department, $role) {
    $weight = 100;
    $weight -= ($wins * 10); // more wins = lower priority
    if (in_array(strtolower($department), ['SOFTWARE DEVELOPMENT','INSTITUTIONAL'])) {
        $weight -= 100;
    }
    if (in_array(strtolower($role), ['priority'])) {
        $weight += 50;
    }
    return max($weight, 10);
}

function weightedRandomPick(&$items) {
    $totalWeight = array_sum(array_column($items, 'weight'));
    $rand = mt_rand(1, $totalWeight);
    foreach ($items as $index => $item) {
        $rand -= $item['weight'];
        if ($rand <= 0) {
            $picked = $item;
            unset($items[$index]);
            $items = array_values($items);
            return $picked['card_id'];
        }
    }
    return null;
}

function generateRandomBingoCard() {
    $card = [];
    $columns = [
        'B' => range(1, 15),
        'I' => range(16, 30),
        'N' => range(31, 45),
        'G' => range(46, 60),
        'O' => range(61, 75)
    ];
    foreach ($columns as $letter => $range) {
        shuffle($range);
        $card[$letter] = array_slice($range, 0, 5);
    }
    $card['N'][2] = 'FREE';
    return $card;
}

/**
 * Regenerates only the neutral cells of a card — any cell that's part
 * of the winning pattern is left exactly as it was (so a card change
 * can never disturb a shared winning number already placed at game
 * start), and the FREE center (stored as the string 'FREE', not a
 * number) is likewise always left alone.
 *
 * @param array $cardData  Decoded card_data, e.g. ['B' => [..5 nums, one may be 'FREE'..], ...]
 * @param array $pattern   Decoded game pattern, 5x5 array of 0/1
 * @return array           Card data with neutral cells re-randomized
 */
function regenerateNeutralNumbers(array $cardData, array $pattern): array {
    $letters = ['B', 'I', 'N', 'G', 'O'];
    $ranges = [
        'B' => range(1, 15),
        'I' => range(16, 30),
        'N' => range(31, 45),
        'G' => range(46, 60),
        'O' => range(61, 75),
    ];

    foreach ($letters as $colIndex => $letter) {
        $fixedRows = [];    // row => true, cell must stay untouched
        $usedNumbers = [];  // numeric values already occupying this column

        for ($row = 0; $row < 5; $row++) {
            $isFree = ($row === 2 && $letter === 'N');
            $isPatternCell = isset($pattern[$row][$colIndex]) && (int) $pattern[$row][$colIndex] === 1;

            if ($isFree || $isPatternCell) {
                $fixedRows[$row] = true;

                if (!$isFree) {
                    // A pattern cell holds a real number (possibly the
                    // shared winning number) — it must not be reused
                    // elsewhere in this column.
                    $usedNumbers[] = (int) $cardData[$letter][$row];
                }
            }
        }

        $available = array_values(array_diff($ranges[$letter], $usedNumbers));
        shuffle($available);

        for ($row = 0; $row < 5; $row++) {
            if (isset($fixedRows[$row])) {
                continue; // FREE or pattern cell — leave exactly as-is
            }
            $cardData[$letter][$row] = (int) array_pop($available);
        }
    }

    return $cardData;
}