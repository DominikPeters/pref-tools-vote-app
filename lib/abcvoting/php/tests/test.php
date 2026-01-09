<?php

/**
 * This file is based on a translation of the abcvoting python package
 * (https://github.com/martinlackner/abcvoting)
 * Copyright (c) 2019 Martin Lackner, MIT licensed.
 *
 * This file Copyright (c) 2026 Dominik Peters, also MIT licensed.
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/Utils.php';
require_once __DIR__ . '/../src/Voter.php';
require_once __DIR__ . '/../src/Profile.php';
require_once __DIR__ . '/../src/Scores.php';
require_once __DIR__ . '/../src/SimpleRules.php';
require_once __DIR__ . '/../src/ThieleRules.php';
require_once __DIR__ . '/../src/SequentialRules.php';
require_once __DIR__ . '/../src/PhragmenRules.php';
require_once __DIR__ . '/../src/ProportionalRules.php';
require_once __DIR__ . '/../src/OtherRules.php';
require_once __DIR__ . '/../src/Explainer.php';

use AbcVoting\Profile;
use AbcVoting\SimpleRules;
use AbcVoting\ThieleRules;
use AbcVoting\SequentialRules;
use AbcVoting\PhragmenRules;
use AbcVoting\ProportionalRules;
use AbcVoting\OtherRules;
use AbcVoting\Utils;
use AbcVoting\Explainer;

// Simple test case
$profile = new Profile(4, ['A', 'B', 'C', 'D']);
$profile->addVoters([
    [0, 1],
    [0, 1],
    [0, 2],
    [2, 3]
]);

echo "Profile:\n" . $profile . "\n\n";

$k = 2;

echo "--- Explanatory Output (HTML) ---

";

echo "Seq-PAV:\n";
$res = SequentialRules::computeSeqPav($profile, $k, true, true);
echo Explainer::explain($profile, $res) . "\n\n";

echo "Seq-Phragmen:\n";
$res = PhragmenRules::computeSeqPhragmen($profile, $k, [], null, true, true);
echo Explainer::explain($profile, $res[0]) . "\n\n";

echo "Rule X (SeqPhragmen completion):\n";
$res = ProportionalRules::computeEqualShares($profile, $k, true, null, "seqphragmen", true);
echo Explainer::explain($profile, $res[0]) . "\n\n";

echo "Rule X (AV completion):\n";
$res = ProportionalRules::computeEqualShares($profile, $k, true, null, "av", true);
echo Explainer::explain($profile, $res[0]) . "\n\n";

echo "Rule X (Increment completion):\n";
$res = ProportionalRules::computeEqualShares($profile, $k, true, null, "increment", true);
echo Explainer::explain($profile, $res[0]) . "\n\n";