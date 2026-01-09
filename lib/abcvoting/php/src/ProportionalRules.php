<?php

/**
 * This file is based on a translation of the abcvoting python package
 * (https://github.com/martinlackner/abcvoting)
 * Copyright (c) 2019 Martin Lackner, MIT licensed.
 *
 * This file Copyright (c) 2026 Dominik Peters, also MIT licensed.
 */

declare(strict_types=1);

namespace AbcVoting;

class ProportionalRules
{
    /**
     * Compute winning committees with the Method of Equal Shares (aka Rule X).
     */
    public static function computeEqualShares(
        Profile $profile,
        int $committeesize,
        bool $resolute = true,
        ?int $maxNumOfCommittees = null,
        ?string $completion = "seqphragmen",
        bool $returnDetailed = false
    ): array {
        if ($completion === "increment") {
            return self::equalSharesAlgorithmWithIncrementCompletion($profile, $committeesize, $returnDetailed);
        }

        return self::equalSharesAlgorithm($profile, $committeesize, $resolute, $maxNumOfCommittees, $completion, null, $returnDetailed);
    }

    private static function equalSharesAlgorithm(
        Profile $profile,
        int $committeesize,
        bool $resolute,
        ?int $maxNumOfCommittees = null,
        ?string $completion = "seqphragmen",
        ?float $perVoterBudget = null,
        bool $returnDetailed = false
    ): array {
        $voters = $profile->getVoters();
        $totalWeight = $profile->totalWeight();

        if ($perVoterBudget === null) {
            $perVoterBudget = (float)$committeesize / $totalWeight;
        }

        $budget = [];
        foreach ($voters as $vIdx => $voter) {
            $budget[$vIdx] = $perVoterBudget * $voter->weight;
        }

        $detailed_info = [
            'start_budget' => $budget,
            'next_cand' => [],
            'tied_cands' => [],
            'cost' => [],
            'budget' => [],
            'phragmen_start_load' => null,
            'phragmen_phase' => null,
            'av_phase' => null,
            'too_few_approved_candidates' => null,
            'increment_committeesize' => null
        ];

        $committee = [];
        $candidates = range(0, $profile->numCand - 1);

        while (count($committee) < $committeesize) {
            $bestCands = [];
            $minQ = INF;

            foreach ($candidates as $cand) {
                if (in_array($cand, $committee)) {
                    continue;
                }

                $q = self::equalSharesGetMinQ($profile, $budget, $cand);

                if ($q !== null) {
                    if ($q < $minQ - 1e-12) {
                        $minQ = $q;
                        $bestCands = [$cand];
                    } elseif (abs($q - $minQ) < 1e-12) {
                        $bestCands[] = $cand;
                    }
                }
            }

            if (empty($bestCands)) {
                break;
            }

            sort($bestCands);
            $bestCand = $bestCands[0];
            $committee[] = $bestCand;

            foreach ($voters as $vIdx => $voter) {
                if (in_array($bestCand, $voter->approved)) {
                    $budget[$vIdx] -= min($budget[$vIdx], $minQ * $voter->weight);
                }
            }

            $detailed_info['next_cand'][] = $bestCand;
            $detailed_info['tied_cands'][] = $bestCands;
            $detailed_info['cost'][] = $minQ;
            $detailed_info['budget'][] = $budget;
        }

        $winningCommittees = [];
        if (count($committee) === $committeesize || $completion === null) {
            sort($committee);
            $winningCommittees[] = [
                'committee' => $committee,
                'detailed_info' => $detailed_info
            ];
        } elseif ($completion === "seqphragmen") {
            $startLoad = [];
            foreach ($voters as $vIdx => $voter) {
                $startLoad[$vIdx] = -$budget[$vIdx] / $voter->weight;
            }
            $detailed_info['phragmen_start_load'] = $startLoad;
            $phragmenRes = PhragmenRules::computeSeqPhragmen($profile, $committeesize, $committee, $startLoad, $resolute, true);
            foreach ($phragmenRes as $pr) {
                $newDetailed = $detailed_info;
                $newDetailed['phragmen_phase'] = $pr['detailed_info'];
                $winningCommittees[] = [
                    'committee' => $pr['committee'],
                    'detailed_info' => $newDetailed
                ];
            }
        } elseif ($completion === "av") {
            $numMissing = $committeesize - count($committee);
            $score = array_fill(0, $profile->numCand, 0.0);
            foreach ($profile->getVoters() as $voter) {
                foreach ($voter->approved as $cand) {
                    if (!in_array($cand, $committee)) {
                        $score[$cand] += $voter->weight;
                    }
                }
            }
            $rem = range(0, $profile->numCand - 1);
            $rem = array_filter($rem, fn($c) => !in_array($c, $committee));
            usort($rem, function($a, $b) use ($score) {
                if (abs($score[$b] - $score[$a]) > 1e-12) return $score[$b] <=> $score[$a];
                return $a <=> $b;
            });
            $avComm = array_slice($rem, 0, $numMissing);
            $newComm = array_merge($committee, $avComm);
            sort($newComm);
            $detailed_info['av_phase'] = ['added' => $avComm];
            $winningCommittees[] = [
                'committee' => $newComm,
                'detailed_info' => $detailed_info
            ];
        }

        if ($returnDetailed) {
            return $winningCommittees;
        }
        $onlyComms = [];
        foreach ($winningCommittees as $wc) $onlyComms[] = $wc['committee'];
        return $onlyComms;
    }

    private static function equalSharesGetMinQ(Profile $profile, array $budget, int $cand): ?float
    {
        $voters = $profile->getVoters();
        $rich = [];
        foreach ($voters as $vIdx => $voter) {
            if (in_array($cand, $voter->approved)) {
                $rich[$vIdx] = $vIdx;
            }
        }
        $poor = [];

        while (!empty($rich)) {
            $poorBudgetSum = 0.0;
            foreach ($poor as $vIdx) $poorBudgetSum += $budget[$vIdx];
            $richWeightSum = 0.0;
            foreach ($rich as $vIdx) $richWeightSum += $voters[$vIdx]->weight;

            $q = (1.0 - $poorBudgetSum) / $richWeightSum;
            $newPoor = [];
            foreach ($rich as $vIdx) {
                if ($budget[$vIdx] < $q * $voters[$vIdx]->weight - 1e-12) $newPoor[] = $vIdx;
            }
            if (empty($newPoor)) return $q;
            foreach ($newPoor as $vIdx) {
                unset($rich[$vIdx]);
                $poor[$vIdx] = $vIdx;
            }
        }
        return null;
    }

    private static function equalSharesAlgorithmWithIncrementCompletion(Profile $profile, int $committeesize, bool $returnDetailed = false): array
    {
        $approvedCands = [];
        foreach ($profile->getVoters() as $voter) {
            foreach ($voter->approved as $cand) $approvedCands[$cand] = true;
        }
        if (count($approvedCands) < $committeesize) {
            $av = SimpleRules::computeAv($profile, $committeesize, true);
            $res = [['committee' => $av[0], 'detailed_info' => ['too_few_approved_candidates' => true]]];
            if ($returnDetailed) return $res;
            return [$res[0]['committee']];
        }

        $totalWeight = $profile->totalWeight();
        $maxLimit = (int)ceil($committeesize * $totalWeight + 1);

        for ($incSize = $committeesize; $incSize < $maxLimit; $incSize++) {
            $res = self::equalSharesAlgorithm($profile, $committeesize, true, null, null, (float)$incSize / $totalWeight, true);
            if (!empty($res) && count($res[0]['committee']) === $committeesize) {
                $res[0]['detailed_info']['increment_committeesize'] = $incSize;
                if ($returnDetailed) return $res;
                return [$res[0]['committee']];
            }
        }
        throw new \RuntimeException("Increment completion failed.");
    }
}