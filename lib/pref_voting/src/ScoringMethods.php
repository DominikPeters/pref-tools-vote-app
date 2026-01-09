<?php

/**
 * This file is based on a translation of the pref_voting python package
 * (https://github.com/voting-tools/pref_voting/)
 * Copyright (c) 2024 Wes Holliday and Eric Pacuit, MIT licensed.
 *
 * This file Copyright (c) 2026 Dominik Peters, also MIT licensed.
 */

declare(strict_types=1);

namespace PrefVoting;

/**
 * Scoring-based voting methods.
 *
 * These methods assign scores to candidates based on their positions
 * in voters' rankings, then select the candidate(s) with the highest score.
 */
class ScoringMethods
{
    /**
     * Plurality voting method.
     *
     * The Plurality score of a candidate is the number of voters that rank
     * them in first place. Winners are candidates with the highest score.
     */
    public static function plurality(): VotingMethod
    {
        return new VotingMethod(
            function (Profile|ProfileWithTies $profile, ?array $currCands = null): array {
                $currCands = $currCands ?? $profile->candidates;

                $scores = $profile->pluralityScores($currCands);

                if (empty($scores)) {
                    return [];
                }

                $maxScore = max($scores);
                $winners = array_keys(array_filter($scores, fn($s) => $s === $maxScore));
                sort($winners);
                return $winners;
            },
            'Plurality'
        );
    }

    /**
     * Borda voting method.
     *
     * The Borda score of a candidate c is calculated as:
     * sum over all ranks r of (m - r) * count(c at rank r)
     * where m is the number of candidates.
     *
     * Winners are candidates with the highest Borda score.
     */
    public static function borda(): VotingMethod
    {
        return new VotingMethod(
            function (Profile|ProfileWithTies $profile, ?array $currCands = null): array {
                $currCands = $currCands ?? $profile->candidates;

                $scores = $profile->bordaScores($currCands);

                if (empty($scores)) {
                    return [];
                }

                $maxScore = max($scores);
                $winners = array_keys(array_filter($scores, fn($s) => $s === $maxScore));
                sort($winners);
                return $winners;
            },
            'Borda'
        );
    }

    /**
     * Anti-Plurality voting method.
     *
     * The Anti-Plurality score of a candidate is the number of voters that
     * rank them in last place. Winners are candidates with the LOWEST score.
     */
    public static function antiPlurality(): VotingMethod
    {
        return new VotingMethod(
            function (Profile $profile, ?array $currCands = null): array {
                $currCands = $currCands ?? $profile->candidates;
                $candsToIgnore = array_diff($profile->candidates, $currCands);

                [$rankings, $rcounts] = $profile->getRankingsCounts();

                $lastPlaceScores = [];
                foreach ($currCands as $c) {
                    $lastPlaceScores[$c] = 0;
                }

                foreach ($rankings as $i => $ranking) {
                    // Filter to only currCands
                    $filtered = array_values(array_filter(
                        $ranking,
                        fn($c) => in_array($c, $currCands, true)
                    ));
                    if (!empty($filtered)) {
                        $lastCand = end($filtered);
                        $lastPlaceScores[$lastCand] += $rcounts[$i];
                    }
                }

                if (empty($lastPlaceScores)) {
                    return [];
                }

                $minScore = min($lastPlaceScores);
                $winners = array_keys(array_filter($lastPlaceScores, fn($s) => $s === $minScore));
                sort($winners);
                return $winners;
            },
            'Anti-Plurality'
        );
    }

    /**
     * General scoring rule.
     *
     * @param callable $scoreFn Function(int $numCands, int $rank): float
     * @param string $name Method name
     */
    public static function scoringRule(callable $scoreFn, string $name = 'Scoring Rule'): VotingMethod
    {
        return new VotingMethod(
            function (Profile $profile, ?array $currCands = null) use ($scoreFn): array {
                $currCands = $currCands ?? $profile->candidates;
                $candsToIgnore = array_diff($profile->candidates, $currCands);

                [$rankings, $rcounts] = $profile->getRankingsCounts();

                $scores = [];
                foreach ($currCands as $c) {
                    $scores[$c] = 0.0;
                }

                $numCurrCands = count($currCands);

                foreach ($rankings as $i => $ranking) {
                    // Filter to only currCands
                    $filtered = array_values(array_filter(
                        $ranking,
                        fn($c) => in_array($c, $currCands, true)
                    ));

                    foreach ($filtered as $pos => $c) {
                        $rank = $pos + 1; // 1-indexed
                        $scores[$c] += $scoreFn($numCurrCands, $rank) * $rcounts[$i];
                    }
                }

                if (empty($scores)) {
                    return [];
                }

                $maxScore = max($scores);
                $winners = array_keys(array_filter($scores, fn($s) => $s === $maxScore));
                sort($winners);
                return $winners;
            },
            $name
        );
    }

    /**
     * Dowdall method (Nauru).
     *
     * First-ranked candidate gets 1 point, second gets 1/2 point,
     * third gets 1/3 point, etc.
     */
    public static function dowdall(): VotingMethod
    {
        return self::scoringRule(
            fn(int $numCands, int $rank): float => 1.0 / $rank,
            'Dowdall'
        );
    }

    /**
     * Positive-Negative Voting.
     *
     * +1 for first place, -1 for last place, 0 otherwise.
     */
    public static function positiveNegativeVoting(): VotingMethod
    {
        return self::scoringRule(
            fn(int $numCands, int $rank): float =>
                $rank === 1 ? 1.0 : ($rank === $numCands ? -1.0 : 0.0),
            'Positive-Negative Voting'
        );
    }

    /**
     * The symmetric Borda score of a candidate c for a ranking r is the number
     * of candidates ranked strictly below c minus the number ranked strictly above c.
     */
    public static function symmetricBordaScores(Profile|ProfileWithTies $profile): array
    {
        $scores = [];
        [$rankings, $rcounts] = $profile->getRankingsCounts();

        foreach ($profile->candidates as $cand) {
            $score = 0;
            foreach ($rankings as $i => $r) {
                $below = 0;
                $above = 0;
                foreach ($profile->candidates as $otherCand) {
                    if ($cand === $otherCand) continue;
                    if ($r instanceof Ranking) {
                        if ($r->extendedStrictPref($cand, $otherCand)) $below++;
                        if ($r->extendedStrictPref($otherCand, $cand)) $above++;
                    } else {
                        // Linear Profile case
                        $pos1 = array_search($cand, $r, true);
                        $pos2 = array_search($otherCand, $r, true);
                        if ($pos1 !== false && $pos2 !== false) {
                            if ($pos1 < $pos2) $below++;
                            else $above++;
                        } elseif ($pos1 !== false) {
                            $below++;
                        } elseif ($pos2 !== false) {
                            $above++;
                        }
                    }
                }
                $score += ($below - $above) * $rcounts[$i];
            }
            $scores[$cand] = $score;
        }
        return $scores;
    }

    /**
     * The domination Borda score of a candidate c is the number of candidates
     * ranked strictly below c.
     */
    public static function dominationBordaScores(Profile|ProfileWithTies $profile): array
    {
        $scores = [];
        [$rankings, $rcounts] = $profile->getRankingsCounts();

        foreach ($profile->candidates as $cand) {
            $score = 0;
            foreach ($rankings as $i => $r) {
                $below = 0;
                foreach ($profile->candidates as $otherCand) {
                    if ($cand === $otherCand) continue;
                    if ($r instanceof Ranking) {
                        if ($r->extendedStrictPref($cand, $otherCand)) $below++;
                    } else {
                        $pos1 = array_search($cand, $r, true);
                        $pos2 = array_search($otherCand, $r, true);
                        if ($pos1 !== false && ($pos2 === false || $pos1 < $pos2)) $below++;
                    }
                }
                $score += $below * $rcounts[$i];
            }
            $scores[$cand] = $score;
        }
        return $scores;
    }

    /**
     * The weak domination Borda score of a candidate c is the number of
     * candidates ranked weakly below c.
     */
    public static function weakDominationBordaScores(Profile|ProfileWithTies $profile): array
    {
        $scores = [];
        [$rankings, $rcounts] = $profile->getRankingsCounts();

        foreach ($profile->candidates as $cand) {
            $score = 0;
            foreach ($rankings as $i => $r) {
                $below = 0;
                foreach ($profile->candidates as $otherCand) {
                    if ($cand === $otherCand) continue;
                    if ($r instanceof Ranking) {
                        if ($r->extendedWeakPref($cand, $otherCand)) $below++;
                    } else {
                        $pos1 = array_search($cand, $r, true);
                        $pos2 = array_search($otherCand, $r, true);
                        if ($pos1 !== false && ($pos2 === false || $pos1 <= $pos2)) $below++;
                    }
                }
                $score += $below * $rcounts[$i];
            }
            $scores[$cand] = $score;
        }
        return $scores;
    }

    /**
     * The non-domination Borda score of a candidate c is -1 times the number
     * of candidates ranked strictly above c.
     */
    public static function nonDominationBordaScores(Profile|ProfileWithTies $profile): array
    {
        $scores = [];
        [$rankings, $rcounts] = $profile->getRankingsCounts();

        foreach ($profile->candidates as $cand) {
            $score = 0;
            foreach ($rankings as $i => $r) {
                $above = 0;
                foreach ($profile->candidates as $otherCand) {
                    if ($cand === $otherCand) continue;
                    if ($r instanceof Ranking) {
                        if ($r->extendedStrictPref($otherCand, $cand)) $above++;
                    } else {
                        $pos1 = array_search($cand, $r, true);
                        $pos2 = array_search($otherCand, $r, true);
                        if ($pos2 !== false && ($pos1 === false || $pos2 < $pos1)) $above++;
                    }
                }
                $score += -$above * $rcounts[$i];
            }
            $scores[$cand] = $score;
        }
        return $scores;
    }

    /**
     * Borda voting method for truncated profiles (ProfileWithTies).
     */
    public static function bordaForProfileWithTies(?callable $scoreFn = null): VotingMethod
    {
        $scoreFn = $scoreFn ?? [self::class, 'symmetricBordaScores'];

        return new VotingMethod(
            function (Profile|ProfileWithTies $profile, ?array $currCands = null) use ($scoreFn): array {
                if ($profile instanceof Profile && $currCands === null) {
                    return self::borda()($profile);
                }

                $currCands = $currCands ?? $profile->candidates;
                $restrictedProf = $profile->removeCandidates(array_diff($profile->candidates, $currCands));
                if (is_array($restrictedProf)) {
                    $restrictedProf = $restrictedProf[0];
                }

                $scores = $scoreFn($restrictedProf);
                $maxScore = max($scores);
                $winners = array_keys(array_filter($scores, fn($s) => $s == $maxScore));
                sort($winners);
                return $winners;
            },
            'Borda'
        );
    }

    /**
     * SWF that ranks candidates according to their plurality scores.
     */
    public static function pluralityRanking(bool $local = true, ?string $tieBreaking = null): SocialWelfareFunction
    {
        return new SocialWelfareFunction(
            function (Profile|ProfileWithTies $profile, ?array $currCands = null) use ($local, $tieBreaking): array {
                $cands = $currCands ?? $profile->candidates;
                if ($local) {
                    $scores = $profile->pluralityScores($cands);
                } else {
                    $allScores = $profile->pluralityScores();
                    $scores = array_filter($allScores, fn($c) => in_array($c, $cands, true), ARRAY_FILTER_USE_KEY);
                }

                // Invert scores for Ranking class (which expects smaller values for better ranks)
                $rmap = [];
                foreach ($scores as $c => $s) {
                    $rmap[$c] = -$s;
                }

                $ranking = new Ranking($rmap, $profile->cmap);
                $ranking->normalizeRanks();

                if ($tieBreaking === 'alphabetic') {
                    $ranking = Ranking::breakTiesAlphabetically($ranking);
                }

                return [$ranking];
            },
            'Plurality ranking'
        );
    }

    /**
     * SWF that ranks candidates according to their Borda scores.
     */
    public static function bordaRanking(bool $local = true, ?string $tieBreaking = null): SocialWelfareFunction
    {
        return new SocialWelfareFunction(
            function (Profile|ProfileWithTies $profile, ?array $currCands = null) use ($local, $tieBreaking): array {
                $cands = $currCands ?? $profile->candidates;
                if ($local) {
                    $scores = $profile->bordaScores($cands);
                } else {
                    $allScores = $profile->bordaScores();
                    $scores = array_filter($allScores, fn($c) => in_array($c, $cands, true), ARRAY_FILTER_USE_KEY);
                }

                $rmap = [];
                foreach ($scores as $c => $s) {
                    $rmap[$c] = -$s;
                }

                $ranking = new Ranking($rmap, $profile->cmap);
                $ranking->normalizeRanks();

                if ($tieBreaking === 'alphabetic') {
                    $ranking = Ranking::breakTiesAlphabetically($ranking);
                }

                return [$ranking];
            },
            'Borda ranking'
        );
    }

    /**
     * SWF that ranks candidates according to their Anti-Plurality scores.
     */
    public static function antiPluralityRanking(bool $local = true, ?string $tieBreaking = null): SocialWelfareFunction
    {
        return new SocialWelfareFunction(
            function (Profile $profile, ?array $currCands = null) use ($local, $tieBreaking): array {
                $cands = $currCands ?? $profile->candidates;
                [$rankings, $rcounts] = $profile->getRankingsCounts();

                $candsToIgnore = $local ? array_diff($profile->candidates, $cands) : [];

                $lastPlaceScores = [];
                foreach ($cands as $c) {
                    $lastPlaceScores[$c] = 0;
                }

                foreach ($rankings as $i => $ranking) {
                    $filtered = array_values(array_filter($ranking, fn($c) => !in_array($c, $candsToIgnore, true)));
                    if (!empty($filtered)) {
                        $lastCand = end($filtered);
                        if (isset($lastPlaceScores[$lastCand])) {
                            $lastPlaceScores[$lastCand] += $rcounts[$i];
                        }
                    }
                }

                $ranking = new Ranking($lastPlaceScores, $profile->cmap);
                $ranking->normalizeRanks();

                if ($tieBreaking === 'alphabetic') {
                    $ranking = Ranking::breakTiesAlphabetically($ranking);
                }

                return [$ranking];
            },
            'Anti-Plurality ranking'
        );
    }

    /**
     * Generic SWF that ranks candidates according to a score function.
     */
    public static function scoreRanking(callable $scoreFn, string $name = 'Score ranking'): SocialWelfareFunction
    {
        return new SocialWelfareFunction(
            function (Profile $profile, ?array $currCands = null) use ($scoreFn): array {
                $cands = $currCands ?? $profile->candidates;
                $candsToIgnore = array_diff($profile->candidates, $cands);

                [$rankings, $rcounts] = $profile->getRankingsCounts();
                $numCurrCands = count($cands);

                $scores = array_fill_keys($cands, 0.0);

                foreach ($rankings as $i => $ranking) {
                    $filtered = array_values(array_filter($ranking, fn($c) => in_array($c, $cands, true)));
                    foreach ($filtered as $pos => $c) {
                        $rank = $pos + 1;
                        $scores[$c] += $scoreFn($numCurrCands, $rank) * $rcounts[$i];
                    }
                }

                $rmap = [];
                foreach ($scores as $c => $s) {
                    $rmap[$c] = -$s;
                }

                $ranking = new Ranking($rmap, $profile->cmap);
                $ranking->normalizeRanks();

                return [$ranking];
            },
            $name
        );
    }

    /**
     * Create a scoring method using a given score function and name.
     */
    public static function createScoringMethod(callable $scoreFn, string $name): VotingMethod
    {
        return self::scoringRule($scoreFn, $name);
    }
}

// Pre-instantiated voting methods for convenience
$plurality = ScoringMethods::plurality();
$borda = ScoringMethods::borda();
$antiPlurality = ScoringMethods::antiPlurality();
$dowdall = ScoringMethods::dowdall();

// Pre-instantiated SWFs
$pluralityRanking = ScoringMethods::pluralityRanking();
$bordaRanking = ScoringMethods::bordaRanking();
$antiPluralityRanking = ScoringMethods::antiPluralityRanking();
