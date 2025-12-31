<?php

require_once __DIR__ . '/src/Instance.php';
require_once __DIR__ . '/src/Rule.php';
require_once __DIR__ . '/src/Apportionment.php';
require_once __DIR__ . '/src/Explainer.php';

use Apportionment\Instance;
use Apportionment\Apportionment;
use Apportionment\Explainer;

function test() {
    // test_balinski_young_example2
    $votes = [9061, 7179, 5259, 3319, 1182];
    $seats = 26;
    $instance = new Instance($seats, $votes);

    $expected = [
        "quota" => [10, 7, 5, 3, 1],
        "hamilton" => [9, 7, 5, 4, 1],
        "dhondt" => [10, 7, 5, 3, 1],
        "saintelague" => [9, 8, 5, 3, 1],
        "huntington" => [9, 7, 6, 3, 1],
        "adams" => [9, 7, 5, 3, 2],
        "dean" => [9, 7, 5, 4, 1]
    ];

    foreach ($expected as $method => $expReps) {
        $result = Apportionment::compute($method, $instance, true);
        $reps = $result['representatives'];
        if ($reps !== $expReps) {
            echo "FAILED: $method. Expected [" . implode(", ", $expReps) . "], got [" . implode(", ", $reps) . "]\n";
        } else {
            echo "PASSED: $method\n";
            // Check if explanation is generated
            $explanation = Explainer::explain($instance, $result);
            if (empty($explanation)) {
                echo "FAILED: $method explanation is empty\n";
            } else {
                echo "Explanation generated for $method (" . strlen($explanation) . " bytes)\n";
            }
        }
    }
}

test();
