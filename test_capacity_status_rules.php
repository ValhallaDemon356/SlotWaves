<?php

function getCapacityStatus(int $arrivals, int $departures, int $nac, bool $isOpsHour = true): array {
    $totalMovements = $arrivals + $departures;
    
    if ($totalMovements < $nac) {
        $status = 'AVAILABLE';
        $remaining = $nac - $totalMovements;
        $exceeded = 0;
        $desc = 'Kapasitas masih tersedia';
    } elseif ($totalMovements === $nac) {
        $status = 'FULL';
        $remaining = 0;
        $exceeded = 0;
        $desc = 'Kapasitas tepat mencapai NAC';
    } else {
        $status = 'OVER CAPACITY';
        $remaining = 0;
        $exceeded = $totalMovements - $nac;
        $desc = 'Melebihi NAC sebesar ' . $exceeded . ' movement';
    }

    return [
        'arrivals' => $arrivals,
        'departures' => $departures,
        'total' => $totalMovements,
        'nac' => $nac,
        'status' => $status,
        'remaining' => $remaining,
        'exceeded' => $exceeded,
        'description' => $desc,
        'is_ops' => $isOpsHour,
        'bg' => $isOpsHour ? 'green/ops' : 'gray/non-ops'
    ];
}

$testCases = [
    [
        'name' => 'CASE A — Available',
        'arr' => 2, 'dep' => 2, 'nac' => 6, 'is_ops' => true,
        'expected_status' => 'AVAILABLE', 'expected_remaining' => 2, 'expected_exceeded' => 0
    ],
    [
        'name' => 'CASE B — Full',
        'arr' => 3, 'dep' => 3, 'nac' => 6, 'is_ops' => true,
        'expected_status' => 'FULL', 'expected_remaining' => 0, 'expected_exceeded' => 0
    ],
    [
        'name' => 'CASE C — Over Capacity',
        'arr' => 5, 'dep' => 4, 'nac' => 6, 'is_ops' => true,
        'expected_status' => 'OVER CAPACITY', 'expected_remaining' => 0, 'expected_exceeded' => 3
    ],
    [
        'name' => 'CASE D — Zero Movement',
        'arr' => 0, 'dep' => 0, 'nac' => 6, 'is_ops' => true,
        'expected_status' => 'AVAILABLE', 'expected_remaining' => 6, 'expected_exceeded' => 0
    ],
    [
        'name' => 'CASE E — Non-Operational Hour',
        'arr' => 0, 'dep' => 0, 'nac' => 6, 'is_ops' => false,
        'expected_status' => 'AVAILABLE', 'expected_remaining' => 6, 'expected_exceeded' => 0
    ]
];

echo "======================================================================\n";
echo "SLOTWAVES — CAPACITY STATUS RULES VERIFICATION (SECTION 26)\n";
echo "======================================================================\n";

$passed = 0;
foreach ($testCases as $idx => $tc) {
    $res = getCapacityStatus($tc['arr'], $tc['dep'], $tc['nac'], $tc['is_ops']);
    $isOk = ($res['status'] === $tc['expected_status'] &&
             $res['remaining'] === $tc['expected_remaining'] &&
             $res['exceeded'] === $tc['expected_exceeded']);

    if ($isOk) {
        $passed++;
        echo sprintf("[PASS] %s\n       Arr: %d | Dep: %d | Total: %d | NAC: %d | Status: %s | Remaining: %d | Exceeded: %d\n",
            $tc['name'], $res['arrivals'], $res['departures'], $res['total'], $res['nac'], $res['status'], $res['remaining'], $res['exceeded']);
    } else {
        echo sprintf("[FAIL] %s\n       Expected Status: %s (got %s)\n", $tc['name'], $tc['expected_status'], $res['status']);
    }
}

echo "======================================================================\n";
echo sprintf("RESULT: %d/%d TESTS PASSED\n", $passed, count($testCases));
echo "======================================================================\n";
exit($passed === count($testCases) ? 0 : 1);
