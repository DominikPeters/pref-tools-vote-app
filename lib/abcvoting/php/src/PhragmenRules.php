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

class PhragmenRules
{
    /**
     * Sequential Phragmen's rule.
     */
    public static function computeSeqPhragmen(
        Profile $profile, 
        int $committeesize, 
        array $partialCommittee = [], 
        ?array $startLoad = null,
        bool $resolute = false,
        bool $returnDetailed = false
    ): array {
        $voters = $profile->getVoters();
        $initialLoads = $startLoad ?? array_fill(0, count($voters), 0.0);
        
        $states = [[$partialCommittee, $initialLoads, [
            'next_cand' => [],
            'tied_cands' => [],
            'times' => []
        ]]];
        $finishedCommittees = [];

        while (!empty($states)) {
            [$committee, $loads, $detailed_info] = array_pop($states);

            if (count($committee) === $committeesize) {
                sort($committee);
                $key = implode(',', $committee);
                if (!isset($finishedCommittees[$key])) {
                    $finishedCommittees[$key] = [
                        'committee' => $committee,
                        'detailed_info' => $detailed_info
                    ];
                }
                if ($resolute) {
                    $res = array_values($finishedCommittees);
                    return $returnDetailed ? $res : [$res[0]['committee']];
                }
                continue;
            }

            $bestCands = [];
            $minTime = INF;

            for ($cand = 0; $cand < $profile->numCand; $cand++) {
                if (in_array($cand, $committee)) {
                    continue;
                }

                $approvers = [];
                foreach ($voters as $vIdx => $voter) {
                    if (in_array($cand, $voter->approved)) {
                        $approvers[] = $vIdx;
                    }
                }

                if (empty($approvers)) {
                    continue;
                }

                $approversWeight = 0.0;
                $approversLoad = 0.0;
                foreach ($approvers as $vIdx) {
                    $approversWeight += $voters[$vIdx]->weight;
                    $approversLoad += $voters[$vIdx]->weight * $loads[$vIdx];
                }

                $time = (1.0 + $approversLoad) / $approversWeight;

                if ($time < $minTime - 1e-12) {
                    $minTime = $time;
                    $bestCands = [$cand];
                } elseif (abs($time - $minTime) < 1e-12) {
                    $bestCands[] = $cand;
                }
            }

            if (empty($bestCands)) {
                sort($committee);
                $key = implode(',', $committee);
                if (!isset($finishedCommittees[$key])) {
                    $finishedCommittees[$key] = [
                        'committee' => $committee,
                        'detailed_info' => $detailed_info
                    ];
                }
                if ($resolute) {
                    $res = array_values($finishedCommittees);
                    return $returnDetailed ? $res : [$res[0]['committee']];
                }
                continue;
            }

            if ($resolute) {
                sort($bestCands);
                $bestCands = [$bestCands[0]];
            }

            foreach (array_reverse($bestCands) as $cand) {
                $newCommittee = $committee;
                $newCommittee[] = $cand;
                $newLoads = $loads;
                foreach ($voters as $vIdx => $voter) {
                    if (in_array($cand, $voter->approved)) {
                        $newLoads[$vIdx] = $minTime;
                    }
                }
                $newDetailed = $detailed_info;
                $newDetailed['next_cand'][] = $cand;
                $newDetailed['tied_cands'][] = $bestCands;
                $newDetailed['times'][] = $minTime;
                $states[] = [$newCommittee, $newLoads, $newDetailed];
            }
        }

        $res = array_values($finishedCommittees);
        if ($returnDetailed) {
            return $res;
        }
        $onlyComms = [];
        foreach ($res as $r) $onlyComms[] = $r['committee'];
        return $onlyComms;
    }
}