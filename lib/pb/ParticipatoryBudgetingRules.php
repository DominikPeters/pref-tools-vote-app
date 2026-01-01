<?php

namespace PB;

/**
 * Implementation of the Method of Equal Shares for Participatory Budgeting.
 * Translated from lib/pb/js/methodOfEqualSharesWorker.js
 */
class ParticipatoryBudgetingRules
{
    public static function sum(array $arr): float
    {
        return array_sum($arr);
    }

    public static function breakTies(array $projectIds, array $costs, array $approvers, array $params, array $choices): array
    {
        $remaining = $choices;
        foreach ($params['tieBreaking'] ?? [] as $method) {
            if ($method === "maxVotes") {
                $maxVotes = -1;
                foreach ($remaining as $c) {
                    $count = count($approvers[$c]);
                    if ($count > $maxVotes) {
                        $maxVotes = $count;
                    }
                }
                $remaining = array_filter($remaining, fn($c) => count($approvers[$c]) === $maxVotes);
            } elseif ($method === "minCost") {
                $minCost = PHP_FLOAT_MAX;
                foreach ($remaining as $c) {
                    if ($costs[$c] < $minCost) {
                        $minCost = $costs[$c];
                    }
                }
                $remaining = array_filter($remaining, fn($c) => abs($costs[$c] - $minCost) < 1e-12);
            } elseif ($method === "maxCost") {
                $maxCost = -PHP_FLOAT_MAX;
                foreach ($remaining as $c) {
                    if ($costs[$c] > $maxCost) {
                        $maxCost = $costs[$c];
                    }
                }
                $remaining = array_filter($remaining, fn($c) => abs($costs[$c] - $maxCost) < 1e-12);
            } else {
                if (!is_array($method)) {
                    throw new \Exception("Unknown tie-breaking method: " . json_encode($method));
                }
                // take the first remaining candidate in list
                foreach ($method as $c) {
                    if (in_array($c, $remaining)) {
                        $remaining = [$c];
                        break;
                    }
                }
            }
            if (count($remaining) === 1) {
                break;
            }
        }
        
        if (empty($remaining)) {
            throw new \Exception("Tie-breaking failed in a way that should not happen: " . json_encode($choices));
        }
        
        return array_values($remaining);
    }

    public static function equalSharesFixedBudget(
        array $voterIds,
        array $projectIds,
        array $costs,
        array $approvers,
        float $totalBudget,
        array $params,
        bool $reportDetails = false
    ): array {
        $voterBudget = [];
        $endowment = $totalBudget / count($voterIds);
        foreach ($voterIds as $i) {
            $voterBudget[$i] = $endowment;
        }

        $report = [
            'moneyBehindCandidate' => [],
            'effectiveVoteCount' => [],
            'endowment' => $endowment,
        ];

        $remainingEffort = []; // candidate -> previous effective vote count
        foreach ($projectIds as $c) {
            if ($costs[$c] > 0 && !empty($approvers[$c])) {
                $remainingEffort[$c] = (float) count($approvers[$c]);
            }
            $report['moneyBehindCandidate'][$c] = [];
            $report['effectiveVoteCount'][$c] = [];
        }

        $winners = [];
        while (true) {
            $best = [];
            $bestEffVoteCount = 0.0;

            // Sort remaining by decreasing previous effective vote count
            arsort($remainingEffort);
            $remainingSorted = array_keys($remainingEffort);

            foreach ($remainingSorted as $c) {
                $previousEffVoteCount = $remainingEffort[$c];
                if ($previousEffVoteCount < $bestEffVoteCount && !$reportDetails) {
                    break;
                }

                $moneyBehindNow = 0.0;
                foreach ($approvers[$c] as $i) {
                    $moneyBehindNow += $voterBudget[$i];
                }
                
                if ($reportDetails) {
                    $report['moneyBehindCandidate'][$c][] = $moneyBehindNow;
                }

                if ($moneyBehindNow < $costs[$c] - 1e-12) {
                    unset($remainingEffort[$c]);
                    if ($reportDetails) {
                        $report['effectiveVoteCount'][$c][] = 0.0;
                    }
                    continue;
                }

                // calculate the effective vote count of c
                $approversBudget = [];
                foreach ($approvers[$c] as $i) {
                    $approversBudget[] = ['id' => $i, 'budget' => $voterBudget[$i]];
                }
                usort($approversBudget, fn($a, $b) => $a['budget'] <=> $b['budget']);

                $paidSoFar = 0.0;
                $denominator = count($approversBudget);
                for ($j = 0; $j < count($approversBudget); $j++) {
                    $i = $approversBudget[$j]['id'];
                    $maxPayment = ($costs[$c] - $paidSoFar) / $denominator;
                    
                    if ($maxPayment > $voterBudget[$i] + 1e-12) {
                        $paidSoFar += $voterBudget[$i];
                        $denominator -= 1;
                    } else {
                        $effVoteCount = $costs[$c] / $maxPayment;
                        $remainingEffort[$c] = $effVoteCount;
                        if ($reportDetails) {
                            $report['effectiveVoteCount'][$c][] = $effVoteCount;
                        }
                        
                        if ($effVoteCount > $bestEffVoteCount + 1e-12) {
                            $bestEffVoteCount = $effVoteCount;
                            $best = [$c];
                        } elseif (abs($effVoteCount - $bestEffVoteCount) < 1e-12) {
                            $best[] = $c;
                        }
                        break;
                    }
                }
            }

            if (empty($best)) {
                break;
            }

            $best = self::breakTies($projectIds, $costs, $approvers, $params, $best);
            if (count($best) > 1) {
                // This shouldn't happen if breakTies is exhaustive or uses defaults
                $best = [$best[0]];
            }
            
            $winner = $best[0];
            $winners[] = $winner;
            
            $bestMaxPayment = $costs[$winner] / $bestEffVoteCount;
            foreach ($approvers[$winner] as $i) {
                if ($voterBudget[$i] > $bestMaxPayment) {
                    $voterBudget[$i] -= $bestMaxPayment;
                } else {
                    $voterBudget[$i] = 0.0;
                }
            }
            unset($remainingEffort[$winner]);
        }

        return ['winners' => $winners, 'report' => $report];
    }

    public static function equalSharesAdd1(
        array $voterIds,
        array $projectIds,
        array $costs,
        array $approvers,
        float $totalBudget,
        array $params
    ): array {
        $startBudget = $totalBudget;
        $add1options = $params['add1options'] ?? [];
        if (in_array("integral", $add1options)) {
            $perVoter = floor($totalBudget / count($voterIds));
            $startBudget = $perVoter * count($voterIds);
        }

        $res = self::equalSharesFixedBudget($voterIds, $projectIds, $costs, $approvers, $startBudget, $params, false);
        $winners = $res['winners'];
        
        $currentCost = 0.0;
        foreach ($winners as $w) {
            $currentCost += $costs[$w];
        }
        
        $budget = $startBudget;
        $increment = $params['increment'] ?? 0.01;

        while (true) {
            if (in_array("exhaustive", $add1options)) {
                $isExhaustive = true;
                $winnersSet = array_flip($winners);
                foreach ($projectIds as $extra) {
                    if (!isset($winnersSet[$extra]) && $currentCost + $costs[$extra] <= $totalBudget + 1e-12) {
                        $isExhaustive = false;
                        break;
                    }
                }
                if ($isExhaustive) {
                    break;
                }
            }

            $nextBudget = $budget + (count($voterIds) * $increment);
            $nextRes = self::equalSharesFixedBudget($voterIds, $projectIds, $costs, $approvers, $nextBudget, $params, false);
            $nextWinners = $nextRes['winners'];
            
            $nextCost = 0.0;
            foreach ($nextWinners as $w) {
                $nextCost += $costs[$w];
            }

            if ($nextCost <= $totalBudget + 1e-12) {
                $budget = $nextBudget;
                $winners = $nextWinners;
                $currentCost = $nextCost;
            } else {
                break;
            }
        }

        // Final run with details
        return self::equalSharesFixedBudget($voterIds, $projectIds, $costs, $approvers, $budget, $params, true);
    }

    public static function utilitarianCompletion(
        array $projectIds,
        array $costs,
        array $approvers,
        float $totalBudget,
        array $alreadyWinners
    ): array {
        $winners = $alreadyWinners;
        $costSoFar = 0.0;
        foreach ($winners as $w) {
            $costSoFar += $costs[$w];
        }

        $sortedProjects = $projectIds;
        usort($sortedProjects, function($a, $b) use ($approvers) {
            $countA = count($approvers[$a]);
            $countB = count($approvers[$b]);
            if ($countA !== $countB) {
                return $countB <=> $countA;
            }
            return $a <=> $b;
        });

        $addedByUtilitarianCompletion = [];
        $winnersSet = array_flip($winners);
        foreach ($sortedProjects as $c) {
            if (isset($winnersSet[$c]) || $costSoFar + $costs[$c] > $totalBudget + 1e-12) {
                continue;
            }
            $winners[] = $c;
            $addedByUtilitarianCompletion[] = $c;
            $costSoFar += $costs[$c];
            $winnersSet[$c] = true;
        }

        return ['winners' => $winners, 'addedByUtilitarianCompletion' => $addedByUtilitarianCompletion];
    }

    public static function comparisonStep(
        array $voterIds,
        array $projectIds,
        array $costs,
        array $approvers,
        float $totalBudget,
        array $greedy,
        array $winners,
        array $params
    ): array {
        $prefersMES = 0;
        $prefersGreedy = 0;

        if (($params['comparison'] ?? 'none') === "satisfaction") {
            $mesSatisfaction = array_fill_keys($voterIds, 0);
            $greedySatisfaction = array_fill_keys($voterIds, 0);

            foreach ($winners as $c) {
                foreach ($approvers[$c] as $i) {
                    $mesSatisfaction[$i]++;
                }
            }
            foreach ($greedy as $c) {
                foreach ($approvers[$c] as $i) {
                    $greedySatisfaction[$i]++;
                }
            }

            foreach ($voterIds as $i) {
                if ($mesSatisfaction[$i] > $greedySatisfaction[$i]) {
                    $prefersMES++;
                } elseif ($greedySatisfaction[$i] > $mesSatisfaction[$i]) {
                    $prefersGreedy++;
                }
            }
        } elseif (($params['comparison'] ?? 'none') === "exclusionRatio") {
            $mesApprovals = [];
            foreach ($winners as $c) {
                foreach ($approvers[$c] as $i) {
                    $mesApprovals[$i] = true;
                }
            }
            $greedyApprovals = [];
            foreach ($greedy as $c) {
                foreach ($approvers[$c] as $i) {
                    $greedyApprovals[$i] = true;
                }
            }

            foreach ($voterIds as $i) {
                $inMES = isset($mesApprovals[$i]);
                $inGreedy = isset($greedyApprovals[$i]);
                if ($inMES && !$inGreedy) {
                    $prefersMES++;
                } elseif ($inGreedy && !$inMES) {
                    $prefersGreedy++;
                }
            }
        }

        $stickToMES = true;
        if ($prefersGreedy > $prefersMES) {
            $stickToMES = false;
        }

        return [
            'stickToMES' => $stickToMES,
            'prefersMES' => $prefersMES,
            'prefersGreedy' => $prefersGreedy
        ];
    }

    public static function gatherOutcomeStatistics(
        array $voterIds,
        array $projectIds,
        array $costs,
        array $approvers,
        float $totalBudget,
        array $winners
    ): array {
        $stats = [];
        $totalCost = 0.0;
        foreach ($winners as $w) {
            $totalCost += $costs[$w];
        }
        $stats['totalCost'] = $totalCost;

        $sumApproved = 0;
        foreach ($winners as $w) {
            $sumApproved += count($approvers[$w]);
        }
        $stats['avgApprovedProjects'] = count($voterIds) > 0 ? $sumApproved / count($voterIds) : 0;

        $sumCostApproved = 0.0;
        foreach ($winners as $w) {
            $sumCostApproved += count($approvers[$w]) * $costs[$w];
        }
        $stats['avgCostOfWinningApprovedProjects'] = count($voterIds) > 0 ? $sumCostApproved / count($voterIds) : 0;

        $voterUtility = array_fill_keys($voterIds, 0);
        foreach ($winners as $w) {
            foreach ($approvers[$w] as $i) {
                $voterUtility[$i]++;
            }
        }

        $stats['utilityDistribution'] = array_fill(0, count($winners) + 1, 0);
        foreach ($voterIds as $i) {
            $u = $voterUtility[$i];
            if ($u <= count($winners)) {
                $stats['utilityDistribution'][$u]++;
            }
        }

        return $stats;
    }

    public static function compute(array $instance, array $params): array
    {
        $voterIds = $instance['voterIds'];
        $projectIds = $instance['projectIds'];
        $costs = $instance['costs'];
        $approvers = $instance['approvers'];
        $totalBudget = (float) $instance['budget'];

        $allCostsSum = array_sum($costs);
        $everythingAffordable = $allCostsSum <= $totalBudget + 1e-12;

        $rule = $params['rule'] ?? 'mes';
        $completion = $params['completion'] ?? 'none';

        if ($rule === 'greedy') {
            $result = self::utilitarianCompletion($projectIds, $costs, $approvers, $totalBudget, []);
            $winners = $result['winners'];
            $notes = [
                'addedByUtilitarianCompletion' => $result['addedByUtilitarianCompletion']
            ];
            $notes['stats'] = self::gatherOutcomeStatistics($voterIds, $projectIds, $costs, $approvers, $totalBudget, $winners);
            return ['winners' => $winners, 'notes' => $notes];
        }

        if (in_array($completion, ["none", "utilitarian"]) || $everythingAffordable) {
            $result = self::equalSharesFixedBudget($voterIds, $projectIds, $costs, $approvers, $totalBudget, $params, true);
        } elseif (in_array($completion, ["add1", "add1e", "add1u", "add1eu"])) {
            $result = self::equalSharesAdd1($voterIds, $projectIds, $costs, $approvers, $totalBudget, $params);
        } else {
            throw new \Exception("Unknown completion rule: " . $completion);
        }

        $winners = $result['winners'];
        $notes = [
            'endowment' => $result['report']['endowment'],
            'moneyBehindCandidate' => $result['report']['moneyBehindCandidate'],
            'effectiveVoteCount' => $result['report']['effectiveVoteCount'],
        ];

        if (in_array($completion, ["utilitarian", "add1u", "add1eu"])) {
            $completionResult = self::utilitarianCompletion($projectIds, $costs, $approvers, $totalBudget, $winners);
            $winners = $completionResult['winners'];
            $notes['addedByUtilitarianCompletion'] = $completionResult['addedByUtilitarianCompletion'];
        }

        $greedyOutput = self::utilitarianCompletion($projectIds, $costs, $approvers, $totalBudget, []);
        $greedy = $greedyOutput['winners'];

        if (($params['comparison'] ?? 'none') !== "none") {
            $comp = self::comparisonStep($voterIds, $projectIds, $costs, $approvers, $totalBudget, $greedy, $winners, $params);
            if (!$comp['stickToMES']) {
                $winners = $greedy;
                $notes['comparison'] = "The committee chosen by the greedy algorithm is preferred by {$comp['prefersGreedy']} voters, while the committee chosen by the method of equal shares is preferred by {$comp['prefersMES']} voters.";
            }
        }

        $notes['stats'] = self::gatherOutcomeStatistics($voterIds, $projectIds, $costs, $approvers, $totalBudget, $winners);
        $notes['greedyStats'] = self::gatherOutcomeStatistics($voterIds, $projectIds, $costs, $approvers, $totalBudget, $greedy);

        return ['winners' => $winners, 'notes' => $notes];
    }
}
