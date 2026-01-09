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
 * An anonymous profile of (truncated) utilities.
 */
class UtilityProfile
{
    /** @var array<int|string> The domain of the profile */
    public array $domain;

    /** @var array<int|string> Candidates in the profile */
    public array $candidates;

    /** @var array<int|string, string> Candidate display names */
    public array $cmap;

    /** @var Utility[] The list of individual utility functions */
    private array $_utilities;

    /** @var int[] Number of voters for each utility function */
    public array $ucounts;

    /** @var int Total number of voters */
    public int $numVoters;

    /**
     * @param array<array<int|string, float|int>|Utility> $utilities Utility data
     * @param int[]|null $ucounts Voter counts
     * @param array<int|string>|null $domain The domain
     * @param array<int|string, string>|null $cmap Candidate display names
     */
    public function __construct(
        array $utilities,
        ?array $ucounts = null,
        ?array $domain = null,
        ?array $cmap = null
    )
    {
        if ($ucounts !== null && count($utilities) !== count($ucounts)) {
            throw new \InvalidArgumentException("The number of utilities must be the same as the number of ucounts");
        }

        if ($domain === null) {
            $allCands = [];
            foreach ($utilities as $u) {
                if ($u instanceof Utility) {
                    $allCands = array_merge($allCands, $u->domain);
                } else {
                    $allCands = array_merge($allCands, array_keys($u));
                }
            }
            $this->domain = array_values(array_unique($allCands));
            sort($this->domain);
        } else {
            $this->domain = $domain;
            sort($this->domain);
        }

        $this->candidates = $this->domain;

        $this->cmap = $cmap ?? array_combine($this->domain, array_map('strval', $this->domain));

        $this->_utilities = [];
        foreach ($utilities as $u) {
            if ($u instanceof Utility) {
                $this->_utilities[] = new Utility($u->mapping, ['domain' => $this->domain, 'cmap' => $this->cmap]);
            } else {
                $this->_utilities[] = new Utility($u, ['domain' => $this->domain, 'cmap' => $this->cmap]);
            }
        }

        $this->ucounts = $ucounts ?? array_fill(0, count($utilities), 1);
        $this->numVoters = (int)array_sum($this->ucounts);
    }

    /** @return array<int|string> */
    public function getCandidates(): array
    {
        return $this->domain;
    }

    public function getNumCands(): int
    {
        return count($this->domain);
    }

    /**
     * Returns utilities and their counts.
     * @return array{0: Utility[], 1: int[]}
     */
    public function getUtilitiesCounts(): array
    {
        return [$this->_utilities, $this->ucounts];
    }

    /**
     * Returns all individual utility functions.
     * @return Utility[]
     */
    public function getUtilities(): array
    {
        $us = [];
        foreach ($this->_utilities as $i => $u) {
            for ($n = 0; $n < $this->ucounts[$i]; $n++) {
                $us[] = $u;
            }
        }
        return $us;
    }

    public function normalizeByRange(): UtilityProfile
    {
        $newUtils = [];
        foreach ($this->_utilities as $u) {
            $newUtils[] = $u->normalizeByRange();
        }
        return new UtilityProfile($newUtils, $this->ucounts, $this->domain, $this->cmap);
    }

    public function normalizeByStandardScore(): UtilityProfile
    {
        $newUtils = [];
        foreach ($this->_utilities as $u) {
            $newUtils[] = $u->normalizeByStandardScore();
        }
        return new UtilityProfile($newUtils, $this->ucounts, $this->domain, $this->cmap);
    }

    public function hasUtility(int|string $x): bool
    {
        foreach ($this->_utilities as $u) {
            if ($u->hasUtility($x)) return true;
        }
        return false;
    }

    public function utilSum(int|string $x): ?float
    {
        if (!$this->hasUtility($x)) return null;
        $sum = 0.0;
        foreach ($this->_utilities as $i => $u) {
            if ($u->hasUtility($x)) {
                $sum += $u($x) * $this->ucounts[$i];
            }
        }
        return $sum;
    }

    public function utilAvg(int|string $x): ?float
    {
        if (!$this->hasUtility($x)) return null;
        $sum = 0.0;
        $count = 0;
        foreach ($this->_utilities as $i => $u) {
            if ($u->hasUtility($x)) {
                $sum += $u($x) * $this->ucounts[$i];
                $count += $this->ucounts[$i];
            }
        }
        return $count > 0 ? $sum / $count : null;
    }

    public function utilMax(int|string $x): ?float
    {
        if (!$this->hasUtility($x)) return null;
        $max = null;
        foreach ($this->_utilities as $u) {
            if ($u->hasUtility($x)) {
                $val = $u($x);
                if ($max === null || $val > $max) $max = $val;
            }
        }
        return (float)$max;
    }

    public function utilMin(int|string $x): ?float
    {
        if (!$this->hasUtility($x)) return null;
        $min = null;
        foreach ($this->_utilities as $u) {
            if ($u->hasUtility($x)) {
                $val = $u($x);
                if ($min === null || $val < $min) $min = $val;
            }
        }
        return (float)$min;
    }

    public function sumUtilityFunction(): Utility
    {
        $mapping = [];
        foreach ($this->domain as $x) {
            $mapping[$x] = $this->utilSum($x);
        }
        return new Utility($mapping, ['domain' => $this->domain]);
    }

    public function avgUtilityFunction(): Utility
    {
        $mapping = [];
        foreach ($this->domain as $x) {
            $mapping[$x] = $this->utilAvg($x);
        }
        return new Utility($mapping, ['domain' => $this->domain]);
    }

    public function toRankingProfile(): ProfileWithTies
    {
        $rankings = [];
        foreach ($this->_utilities as $u) {
            $rankings[] = $u->ranking();
        }
        return new ProfileWithTies($rankings, $this->ucounts, $this->domain, $this->cmap);
    }

    public function toApprovalProfile(float $probToContApproving = 1.0, float $decayRate = 0.0): GradeProfile
    {
        $gradeMaps = [];
        foreach ($this->_utilities as $u) {
            $gradeMaps[] = $u->toApprovalBallot($probToContApproving, $decayRate);
        }
        return new GradeProfile($gradeMaps, [0, 1], $this->ucounts, $this->domain, $this->cmap);
    }

    public function toKApprovalProfile(int $k, float $probToContApproving = 1.0, float $decayRate = 0.0): GradeProfile
    {
        $gradeMaps = [];
        foreach ($this->_utilities as $u) {
            $gradeMaps[] = $u->toKApprovalBallot($k, $probToContApproving, $decayRate);
        }
        return new GradeProfile($gradeMaps, [0, 1], $this->ucounts, $this->domain, $this->cmap);
    }

    public function display(bool $showTotals = false): void
    {
        $utils = $this->getUtilities();
        $voters = range(0, count($utils) - 1);

        if ($showTotals) {
            echo "Voter\t" . implode("\t", array_map(fn($x) => $this->cmap[$x], $this->domain)) . "\n";
            foreach ($voters as $v) {
                echo ($v + 1) . "\t";
                foreach ($this->domain as $x) {
                    echo $utils[$v]($x) . "\t";
                }
                echo "\n";
            }
            echo "---\n";
            echo "Sum\t" . implode("\t", array_map(fn($x) => $this->utilSum($x), $this->domain)) . "\n";
            echo "Min\t" . implode("\t", array_map(fn($x) => $this->utilMin($x), $this->domain)) . "\n";
            echo "Max\t" . implode("\t", array_map(fn($x) => $this->utilMax($x), $this->domain)) . "\n";
        } else {
            echo "Voter\t" . implode("\t", array_map(fn($x) => (string)$x, $this->domain)) . "\n";
            foreach ($voters as $v) {
                echo ($v + 1) . "\t";
                foreach ($this->domain as $x) {
                    echo $utils[$v]($x) . "\t";
                }
                echo "\n";
            }
        }
    }
}
