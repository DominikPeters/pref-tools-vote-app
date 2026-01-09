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

class SequentialRules
{
    /**
     * Sequential Thiele methods.
     */
    public static function computeSeqThiele(
        string $scorefctId,
        Profile $profile,
        int $committeesize,
        bool $resolute = true,
        bool $returnDetailed = false
    ): array {
        $marginalScoreFct = Scores::getMarginalScoreFct($scorefctId);
        
        if ($resolute) {
            $res = self::seqThieleResolute($marginalScoreFct, $profile, $committeesize);
            if ($returnDetailed) {
                return [
                    'committees' => [$res['committee']],
                    'detailed_info' => $res['detailed_info']
                ];
            }
            return [$res['committee']];
        } else {
            $committees = self::seqThieleIrresolute($marginalScoreFct, $profile, $committeesize);
            if ($returnDetailed) {
                return [
                    'committees' => $committees,
                    'detailed_info' => []
                ];
            }
            return $committees;
        }
    }

    private static function seqThieleResolute(callable $marginalScoreFct, Profile $profile, int $committeesize): array
    {
        $committee = [];
        $detailed_info = [
            'rule_id' => null, // set by wrapper
            'next_cand' => [],
            'tied_cands' => [],
            'delta_score' => [],
            'base_score' => Scores::thieleScore('pav', $profile, []) // 0.0 usually
        ];

        for ($i = 0; $i < $committeesize; $i++) {
            $marginalScores = Scores::marginalThieleScoresAdd($marginalScoreFct, $profile, $committee);
            $maxScore = max($marginalScores);
            $bestCandidates = [];
            foreach ($marginalScores as $cand => $score) {
                if (abs($score - $maxScore) < 1e-12) {
                    $bestCandidates[] = $cand;
                }
            }
            sort($bestCandidates);
            $nextCand = $bestCandidates[0];
            $committee[] = $nextCand;

            $detailed_info['next_cand'][] = $nextCand;
            $detailed_info['tied_cands'][] = $bestCandidates;
            $detailed_info['delta_score'][] = $maxScore;
        }
        sort($committee);
        return ['committee' => $committee, 'detailed_info' => $detailed_info];
    }

    private static function seqThieleIrresolute(callable $marginalScoreFct, Profile $profile, int $committeesize): array
    {
        $committees = [[]];
        for ($i = 0; $i < $committeesize; $i++) {
            $newCommittees = [];
            foreach ($committees as $committee) {
                $marginalScores = Scores::marginalThieleScoresAdd($marginalScoreFct, $profile, $committee);
                $maxScore = max($marginalScores);
                foreach ($marginalScores as $cand => $score) {
                    if (abs($score - $maxScore) < 1e-12) {
                        $newCom = $committee;
                        $newCom[] = $cand;
                        sort($newCom);
                        $newCommittees[] = $newCom;
                    }
                }
            }
            // Remove duplicates
            $uniqueCommittees = [];
            foreach ($newCommittees as $com) {
                $uniqueCommittees[implode(',', $com)] = $com;
            }
            $committees = array_values($uniqueCommittees);
        }
        return $committees;
    }

    public static function computeSeqPav(Profile $profile, int $committeesize, bool $resolute = true, bool $returnDetailed = false): array
    {
        $res = self::computeSeqThiele('pav', $profile, $committeesize, $resolute, $returnDetailed);
        if ($returnDetailed && $resolute) {
            $res['detailed_info']['rule_id'] = 'seqpav';
        }
        return $res;
    }

    public static function computeSeqCc(Profile $profile, int $committeesize, bool $resolute = true, bool $returnDetailed = false): array
    {
        $res = self::computeSeqThiele('cc', $profile, $committeesize, $resolute, $returnDetailed);
        if ($returnDetailed && $resolute) {
            $res['detailed_info']['rule_id'] = 'seqcc';
        }
        return $res;
    }

    public static function computeSeqSlav(Profile $profile, int $committeesize, bool $resolute = true, bool $returnDetailed = false): array
    {
        $res = self::computeSeqThiele('slav', $profile, $committeesize, $resolute, $returnDetailed);
        if ($returnDetailed && $resolute) {
            $res['detailed_info']['rule_id'] = 'seqslav';
        }
        return $res;
    }
}
