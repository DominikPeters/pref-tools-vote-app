<?php

require_once __DIR__ . '/../../../src/bootstrap.php';
require_once __DIR__ . '/../../../src/Models/Question.php';
require_once __DIR__ . '/../../../src/Models/Option.php';
require_once __DIR__ . '/../../../src/Models/Response.php';
require_once __DIR__ . '/../../../src/Models/Answer.php';
require_once __DIR__ . '/../../../src/Services/ReportRegistry.php';
require_once __DIR__ . '/../../../src/Services/ApportionmentRulesRegistry.php';

use App\Models\Question;
use App\Models\Option;
use App\Models\Response;
use App\Models\Answer;
use App\Services\ReportRegistry;

function testApportionmentReports() {
    echo "Testing Apportionment Reports...\n";

    // 1. Check Registry
    $winnerHandler = ReportRegistry::get('apportionment_winner');
    $multiHandler = ReportRegistry::get('apportionment_multi_rule_comparison');

    if (!$winnerHandler) {
        echo "FAILED: apportionment_winner not registered\n";
        return;
    }
    if (!$multiHandler) {
        echo "FAILED: apportionment_multi_rule_comparison not registered\n";
        return;
    }
    echo "PASSED: Reports registered\n";

    // 2. Mock Data
    $question = new Question();
    $question->id = 1;
    $question->type = 'single_choice';
    
    $opt1 = new Option(); $opt1->id = 101; $opt1->label = "Party A";
    $opt2 = new Option(); $opt2->id = 102; $opt2->label = "Party B";
    $opt3 = new Option(); $opt3->id = 103; $opt3->label = "Party C";
    
    // Explicitly set properties to avoid dynamic property warnings in PHP 8.2+
    @$opt1->color = "#ff0000";
    @$opt2->color = "#00ff00";
    @$opt3->color = "#0000ff";
    
    // Manually set options and prevent loadOptions from clearing them
    $question->options = [$opt1, $opt2, $opt3];
    
    // We need to override loadOptions or ensure it doesn't clear our mock
    // In our case, the Model has a public array $options.
    // Let's check Question model to see if loadOptions clears it.

    // Simulate responses: 50 for A, 30 for B, 20 for C
    $responses = [];
    for ($i = 0; $i < 50; $i++) $responses[] = createMockResponse(1, 101, $i);
    for ($i = 0; $i < 30; $i++) $responses[] = createMockResponse(1, 102, 50 + $i);
    for ($i = 0; $i < 20; $i++) $responses[] = createMockResponse(1, 103, 80 + $i);

    // 3. Test Computation
    $config = ['seats' => 10, 'rule' => 'hamilton'];
    $result = $winnerHandler->compute($question, $responses, $config);

    if (isset($result['error'])) {
        echo "FAILED: Compute error: " . $result['error'] . "\n";
        return;
    }

    echo "PASSED: Compute success\n";
    echo "Rule: " . $result['rule_name'] . "\n";
    foreach ($result['allocation'] as $row) {
        echo "- " . $row['option'] . ": " . $row['votes'] . " votes -> " . $row['seats'] . " seats\n";
    }

    if ($result['allocation'][0]['seats'] == 5 && $result['allocation'][1]['seats'] == 3 && $result['allocation'][2]['seats'] == 2) {
        echo "PASSED: Correct allocation for 50/30/20 with 10 seats\n";
    } else {
        echo "FAILED: Incorrect allocation\n";
    }
}

function createMockResponse($questionId, $optionId, $id) {
    $response = new Response();
    $response->id = $id;
    $answer = new Answer();
    $answer->questionId = $questionId;
    $answer->valueChoice = $optionId;
    $response->answers = [$answer];
    return $response;
}

testApportionmentReports();
