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

class OtherRules
{
    /**
     * Minimax AV (MAV) - brute force.
     */
    public static function computeMinimaxAv(Profile $profile, int $committeesize): array
    {
        $optCommittees = [];
        $minMaxDist = INF;

        $candidates = range(0, $profile->numCand - 1);
        foreach (Utils::combinations($candidates, $committeesize) as $committee) {
            $maxDist = 0;
            foreach ($profile->getVoters() as $voter) {
                $dist = Utils::hamming($voter->approved, $committee);
                if ($dist > $maxDist) {
                    $maxDist = $dist;
                }
            }

            if ($maxDist < $minMaxDist) {
                $minMaxDist = $maxDist;
                $optCommittees = [$committee];
            } elseif ($maxDist == $minMaxDist) {
                $optCommittees[] = $committee;
            }
        }

        foreach ($optCommittees as &$c) sort($c);
        return $optCommittees;
    }

    /**
     * Greedy Monroe.
     */
    public static function computeGreedyMonroe(Profile $profile, int $committeesize): array
    {
        $numVoters = $profile->count();
        $voters = $profile->getVoters();
        $committee = [];
        $remainingVoters = range(0, $numVoters - 1);
        $remainingCands = range(0, $profile->numCand - 1);

        for ($t = 0; $t < $committeesize; $t++) {
            $maxApprovals = -1;
            $selected = -1;
            foreach ($remainingCands as $cand) {
                $approvals = 0;
                foreach ($remainingVoters as $vIdx) {
                    if (in_array($cand, $voters[$vIdx]->approved)) {
                        $approvals++;
                    }
                }
                if ($approvals > $maxApprovals) {
                    $maxApprovals = $approvals;
                    $selected = $cand;
                }
            }

            if ($t < $numVoters - $committeesize * intdiv($numVoters, $committeesize)) {
                $numRemove = intdiv($numVoters, $committeesize) + 1;
            } else {
                $numRemove = intdiv($numVoters, $committeesize);
            }

            $toRemove = [];
            foreach ($remainingVoters as $vIdx) {
                if (in_array($selected, $voters[$vIdx]->approved)) {
                    $toRemove[] = $vIdx;
                }
            }

            if (count($toRemove) > $numRemove) {
                $toRemove = array_slice($toRemove, 0, $numRemove);
            }

            $committee[] = $selected;
            $remainingVoters = array_values(array_diff($remainingVoters, $toRemove));
            $remainingCands = array_values(array_diff($remainingCands, [$selected]));
        }

        sort($committee);
        return [$committee];
    }

    /**
     * Phragmen-Enestroem.
     */
    public static function computePhragmenEnestroem(Profile $profile, int $committeesize): array
    {
        $initialVoterBudget = [];
        foreach ($profile->getVoters() as $voter) {
            $initialVoterBudget[] = $voter->weight;
        }
        $price = array_sum($initialVoterBudget) / $committeesize;
        
        $committeeBudgetPairs = [[[], $initialVoterBudget]];
        $committees = [];

        while (!empty($committeeBudgetPairs)) {
            [$committee, $budget] = array_pop($committeeBudgetPairs);
            
            $availableCandidates = array_diff(range(0, $profile->numCand - 1), $committee);
            $support = [];
            foreach ($availableCandidates as $cand) {
                $support[$cand] = 0.0;
                foreach ($profile->getVoters() as $i => $voter) {
                    if (in_array($cand, $voter->approved)) {
                        $support[$cand] += $budget[$i];
                    }
                }
            }

            if (empty($support)) break;
            $maxSupport = max($support);
            $tiedCands = [];
            foreach ($support as $cand => $supp) {
                if (abs($supp - $maxSupport) < 1e-12) {
                    $tiedCands[] = $cand;
                }
            }
            sort($tiedCands);

            $newPairs = [];
            foreach ($tiedCands as $cand) {
                $newBudget = $budget;
                $multiplier = $maxSupport > $price ? ($maxSupport - $price) / $maxSupport : 0.0;
                foreach ($profile->getVoters() as $i => $voter) {
                    if (in_array($cand, $voter->approved)) {
                        $newBudget[$i] *= $multiplier;
                    }
                }
                $newCommittee = $committee;
                $newCommittee[] = $cand;

                if (count($newCommittee) == $committeesize) {
                    sort($newCommittee);
                    $committees[implode(',', $newCommittee)] = $newCommittee;
                } else {
                    $newPairs[] = [$newCommittee, $newBudget];
                }
            }
            foreach (array_reverse($newPairs) as $pair) {
                $committeeBudgetPairs[] = $pair;
            }
            
            if (count($committees) > 100) break; 
        }

        return array_values($committees);
    }
}