<?php
/* ==============================
   BINGO GAME HELPER FUNCTIONS
============================== */

function calculatePriorityWeight($wins, $department, $role) {
    $weight = 100;
    $weight -= ($wins * 10); // more wins = lower priority
    if (in_array(strtolower($department), ['softdev','soft dev','software development','soft developer','institutional'])) {
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