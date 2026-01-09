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
 * A mapping of utilities to candidates.
 */
class Utility extends Mapping
{
    /** @var array<int|string> The candidates */
    public array $candidates;

    /** @var array<int|string, string> Candidate display names */
    public array $cmap;

    /**
     * @param array<int|string, float|int> $utils Map from candidate to utility
     * @param array{domain?: array<int|string>, candidates?: array<int|string>, cmap?: array<int|string, string>} $kwargs
     */
    public function __construct(array $utils, array $kwargs = [])
    {
        if (isset($kwargs['domain']) && isset($kwargs['candidates'])) {
            throw new \InvalidArgumentException("You can only provide either 'domain' or 'candidates', not both.");
        }

        $domain = $kwargs['domain'] ?? $kwargs['candidates'] ?? array_keys($utils);
        sort($domain);
        
        $this->candidates = $domain;

        $this->cmap = $kwargs['cmap'] ?? array_combine($domain, array_map('strval', $domain));

        foreach (array_keys($utils) as $x) {
            if (!in_array($x, $domain, true)) {
                throw new \InvalidArgumentException("The domain must contain all elements in the utility map.");
            }
        }

        parent::__construct($utils, $domain, null, null, $this->cmap);
    }

    /**
     * Return the candidates in the profile.
     */
    public function getCandidates(): array
    {
        return $this->domain;
    }

    /**
     * Returns a list of the items that are assigned the utility u.
     */
    public function itemsWithUtil(float|int $u): array
    {
        return $this->inverseImage($u);
    }

    /**
     * Return True if x has a utility.
     */
    public function hasUtility(int|string $x): bool
    {
        return $this->hasValue($x);
    }

    /**
     * Returns a utility with the item x removed.
     */
    public function removeCand(int|string $x): Utility
    {
        $newUtils = [];
        foreach ($this->definedDomain() as $y) {
            if ($y !== $x) $newUtils[$y] = $this->val($y);
        }
        $newDomain = array_values(array_filter($this->domain, fn($y) => $y !== $x));
        $newCmap = [];
        foreach ($this->cmap as $y => $name) {
            if ($y !== $x) $newCmap[$y] = $name;
        }
        return new Utility($newUtils, ['domain' => $newDomain, 'cmap' => $newCmap]);
    }

    /**
     * Return an approval ballot representation of the mapping.
     */
    public function toApprovalBallot(float $probToContApproving = 1.0, float $decayRate = 0.0): Grade
    {
        $avgGrade = $this->average();
        if ($avgGrade === null) {
            return new Grade([], [0, 1], $this->domain, $this->cmap);
        }

        $mainApprovalSet = [];
        foreach ($this->definedDomain() as $x) {
            if ($this->val($x) > $avgGrade) {
                $mainApprovalSet[$x] = $this->val($x);
            }
        }

        if (empty($mainApprovalSet)) {
            return new Grade([], [0, 1], $this->domain, $this->cmap);
        }

        arsort($mainApprovalSet);
        $sortedApprovalSet = [];
        foreach ($mainApprovalSet as $x => $u) {
            $sortedApprovalSet[] = [$x, $u];
        }

        $approvalSet = [$sortedApprovalSet[0][0]];

        $t = 0;
        for ($i = 1; $i < count($sortedApprovalSet); $i++) {
            $x = $sortedApprovalSet[$i][0];
            $rand = mt_rand() / mt_getrandmax();
            if ($rand < $probToContApproving * exp(-$decayRate * $t)) {
                $approvalSet[] = $x;
                $t++;
            } else {
                break;
            }
        }

        $gradeMap = [];
        foreach ($this->definedDomain() as $x) {
            $gradeMap[$x] = in_array($x, $approvalSet, true) ? 1 : 0;
        }

        return new Grade($gradeMap, [0, 1], $this->domain, $this->cmap);
    }

    /**
     * Return an k-approval ballot representation of the mapping.
     */
    public function toKApprovalBallot(int $k, float $probToContApproving = 1.0, float $decayRate = 0.0): Grade
    {
        $avgGrade = $this->average();
        if ($avgGrade === null) {
            return new Grade([], [0, 1], $this->domain, $this->cmap);
        }

        $mainApprovalSet = [];
        foreach ($this->definedDomain() as $x) {
            if ($this->val($x) > $avgGrade) {
                $mainApprovalSet[$x] = $this->val($x);
            }
        }

        if (empty($mainApprovalSet)) {
            return new Grade([], [0, 1], $this->domain, $this->cmap);
        }

        arsort($mainApprovalSet);
        $sortedApprovalSet = [];
        foreach ($mainApprovalSet as $x => $u) {
            $sortedApprovalSet[] = [$x, $u];
        }

        $approvalSet = [$sortedApprovalSet[0][0]];

        $t = 0;
        for ($i = 1; $i < count($sortedApprovalSet); $i++) {
            if (count($approvalSet) === $k) break;
            $x = $sortedApprovalSet[$i][0];
            $rand = mt_rand() / mt_getrandmax();
            if ($rand < $probToContApproving * exp(-$decayRate * $t)) {
                $approvalSet[] = $x;
                $t++;
            } else {
                break;
            }
        }

        $gradeMap = [];
        foreach ($this->definedDomain() as $x) {
            $gradeMap[$x] = in_array($x, $approvalSet, true) ? 1 : 0;
        }

        return new Grade($gradeMap, [0, 1], $this->domain, $this->cmap);
    }

    /**
     * Return the ranking generated by this utility function.
     */
    public function ranking(): Ranking
    {
        $rmap = [];
        $sortedDomain = $this->sortedDomain();
        foreach ($sortedDomain as $idx => $indiffClass) {
            foreach ($indiffClass as $x) {
                $rmap[$x] = $idx + 1;
            }
        }
        return new Ranking($rmap, $this->cmap);
    }

    /**
     * Return the ranking generated by this utility function (extended).
     */
    public function extendedRanking(): Ranking
    {
        $rmap = [];
        $sortedDomain = $this->sortedDomain(true);
        foreach ($sortedDomain as $idx => $indiffClass) {
            foreach ($indiffClass as $x) {
                $rmap[$x] = $idx + 1;
            }
        }
        return new Ranking($rmap, $this->cmap);
    }

    /**
     * Return True when there are at least two candidates that are assigned the same utility.
     */
    public function hasTie(bool $useExtended = false): bool
    {
        foreach ($this->sortedDomain($useExtended) as $cs) {
            if (count($cs) !== 1) return true;
        }
        return false;
    }

    /**
     * Return True when the assignment of utilities is a linear order of numCands candidates.
     */
    public function isLinear(int $numCands): bool
    {
        return $this->ranking()->isLinear($numCands);
    }

    /**
     * Return True when the utility represents the ranking r.
     */
    public function representsRanking(Ranking $r, bool $useExtended = false): bool
    {
        if ($useExtended) {
            $cands = $r->getCands();
            foreach ($cands as $x) {
                foreach ($cands as $y) {
                    if ($r->extendedStrictPref($x, $y) && !$this->extendedStrictPref($x, $y)) return false;
                    if ($r->extendedIndiff($x, $y) && !$this->extendedIndiff($x, $y)) return false;
                }
            }
        } else {
            $cands = $r->getCands();
            foreach ($cands as $x) {
                if (!$this->hasUtility($x)) return false;
            }
            foreach ($cands as $x) {
                foreach ($cands as $y) {
                    if ($r->strictPref($x, $y) && !$this->strictPref($x, $y)) return false;
                    if ($r->indiff($x, $y) && !$this->indiff($x, $y)) return false;
                }
            }
        }
        return true;
    }

    /**
     * Return a new utility function that is the transformation of this utility function by func.
     */
    public function transformation(callable $func): Utility
    {
        $newUtils = [];
        foreach ($this->definedDomain() as $x) {
            $newUtils[$x] = $func($this->val($x));
        }
        return new Utility($newUtils, ['domain' => $this->domain, 'cmap' => $this->cmap]);
    }

    /**
     * Return a linear transformation of the utility function: a * u(x) + b.
     */
    public function linearTransformation(float $a = 1.0, float $b = 0.0): Utility
    {
        return $this->transformation(fn($val) => $a * $val + $b);
    }

    /**
     * Return a normalized utility function (Kaplan normalization).
     */
    public function normalizeByRange(): Utility
    {
        $range = $this->range();
        if (empty($range)) return $this;
        $maxUtil = max($range);
        $minUtil = min($range);

        if ($maxUtil === $minUtil) {
            $newUtils = [];
            foreach ($this->definedDomain() as $x) $newUtils[$x] = 0;
            return new Utility($newUtils, ['domain' => $this->domain, 'cmap' => $this->cmap]);
        }

        return $this->transformation(fn($val) => ($val - $minUtil) / ($maxUtil - $minUtil));
    }

    /**
     * Replace each utility value with its standard score.
     */
    public function normalizeByStandardScore(): Utility
    {
        $img = $this->image();
        if (empty($img)) return $this;

        $mean = array_sum($img) / count($img);
        $variance = 0;
        foreach ($img as $val) $variance += pow($val - $mean, 2);
        $stdDev = sqrt($variance / count($img));

        if ($stdDev == 0) return $this;

        return $this->transformation(fn($val) => ($val - $mean) / $stdDev);
    }

    /**
     * Return the expected utility given a probability distribution prob.
     */
    public function expectation(array $prob): float
    {
        $sum = 0.0;
        foreach ($prob as $x => $p) {
            if (in_array($x, $this->domain, true) && $this->hasUtility($x)) {
                $sum += $p * $this->val($x);
            }
        }
        return $sum;
    }

    /**
     * Return a utility function from a linear ranking.
     */
    public static function fromLinearRanking(array $ranking, ?int $seed = null): Utility
    {
        if ($seed !== null) mt_srand($seed);
        
        $numCands = count($ranking);
        $utilities = [];
        for ($i = 0; $i < $numCands; $i++) {
            $utilities[] = mt_rand() / mt_getrandmax();
        }
        rsort($utilities);

        $uDict = [];
        foreach ($ranking as $i => $c) {
            $uDict[$c] = $utilities[$i];
        }

        return new Utility($uDict);
    }

    public function __toString(): string
    {
        $parts = [];
        foreach ($this->domain as $x) {
            $val = $this->val($x);
            $valStr = $val === null ? "None" : (string)$val;
            $parts[] = "U({$this->cmap[$x]}) = $valStr";
        }
        return implode(", ", $parts);
    }
}
