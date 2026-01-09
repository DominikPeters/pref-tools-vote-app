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
 * An anonymous profile of (truncated) grades.
 */
class GradeProfile
{
    /** @var array<int|string> Candidates in the profile */
    public array $candidates;

    /** @var array<int|string, string> Candidate display names */
    public array $cmap;

    /** @var array<mixed> Allowed grades */
    public array $grades;

    /** @var bool Whether grades can be summed (are numeric) */
    public bool $canSumGrades;

    /** @var array<mixed> Sorted grades from largest to smallest */
    public array $gradeOrder;

    /** @var bool Whether an explicit grade order is used */
    public bool $useGradeOrder;

    /** @var callable Comparison function for grades */
    public $compareFunction;

    /** @var array<mixed, string> Grade display names */
    public array $gmap;

    /** @var Grade[] The list of individual grade functions */
    private array $_grades;

    /** @var int[] Number of voters for each grade function */
    public array $gcounts;

    /** @var int Total number of voters */
    public int $numVoters;

    /**
     * @param array<array<int|string, mixed>|Grade> $gradeMaps Grade data
     * @param array<mixed> $grades Allowed grades
     * @param int[]|null $gcounts Voter counts
     * @param array<int|string>|null $candidates Candidates
     * @param array<int|string, string>|null $cmap Candidate display names
     * @param array<mixed, string>|null $gmap Grade display names
     * @param array<mixed>|null $gradeOrder Order of grades (high to low)
     */
    public function __construct(
        array $gradeMaps,
        array $grades,
        ?array $gcounts = null,
        ?array $candidates = null,
        ?array $cmap = null,
        ?array $gmap = null,
        ?array $gradeOrder = null
    )
    {
        if ($gcounts !== null && count($gradeMaps) !== count($gcounts)) {
            throw new \InvalidArgumentException("The number of grades must be the same as the number of gcounts");
        }

        if ($candidates === null) {
            $allCands = [];
            foreach ($gradeMaps as $gm) {
                $cands = $gm instanceof Grade ? $gm->domain : array_keys($gm);
                $allCands = array_merge($allCands, $cands);
            }
            $this->candidates = array_values(array_unique($allCands));
            sort($this->candidates);
        } else {
            $this->candidates = $candidates;
            sort($this->candidates);
        }

        $this->cmap = $cmap ?? array_combine($this->candidates, array_map('strval', $this->candidates));
        $this->grades = $grades;
        
        $this->canSumGrades = true;
        foreach ($this->grades as $g) {
            if (!is_numeric($g)) {
                $this->canSumGrades = false;
                break;
            }
        }

        if ($gradeOrder !== null) {
            $this->gradeOrder = $gradeOrder;
            $this->useGradeOrder = true;
        } else {
            $this->gradeOrder = $grades;
            rsort($this->gradeOrder);
            $this->useGradeOrder = false;
        }

        if ($this->useGradeOrder) {
            $orderMap = array_flip($this->gradeOrder);
            $this->compareFunction = function ($v1, $v2) use ($orderMap) {
                if ($v1 === null || $v2 === null) return null;
                $idx1 = $orderMap[$v1];
                $idx2 = $orderMap[$v2];
                if ($idx1 < $idx2) return 1;
                if ($idx1 > $idx2) return -1;
                return 0;
            };
        } else {
            $this->compareFunction = function ($v1, $v2) {
                if ($v1 === null || $v2 === null) return null;
                if ($v1 > $v2) return 1;
                if ($v2 > $v1) return -1;
                return 0;
            };
        }

        $this->gmap = $gmap ?? array_combine($this->grades, array_map('strval', $this->grades));

        $this->_grades = [];
        foreach ($gradeMaps as $gm) {
            if ($gm instanceof Grade) {
                $this->_grades[] = new Grade($gm->mapping, $this->grades, $this->candidates, $this->cmap, $this->gmap, $this->compareFunction);
            } else {
                $this->_grades[] = new Grade($gm, $this->grades, $this->candidates, $this->cmap, $this->gmap, $this->compareFunction);
            }
        }

        $this->gcounts = $gcounts ?? array_fill(0, count($gradeMaps), 1);
        $this->numVoters = array_sum($this->gcounts);
    }

    /**
     * Returns grades and their counts.
     * @return array{0: Grade[], 1: int[]}
     */
    public function getGradesCounts(): array
    {
        return [$this->_grades, $this->gcounts];
    }

    /**
     * Returns all individual grade functions.
     * @return Grade[]
     */
    public function getGradeFunctions(): array
    {
        $gs = [];
        foreach ($this->_grades as $i => $g) {
            for ($n = 0; $n < $this->gcounts[$i]; $n++) {
                $gs[] = $g;
            }
        }
        return $gs;
    }

    /**
     * Returns True if c is assigned a grade by at least one voter.
     */
    public function hasGrade(int|string $c): bool
    {
        foreach ($this->_grades as $g) {
            if ($g->hasGrade($c)) return true;
        }
        return false;
    }

    /**
     * Returns the margin of c1 over c2.
     */
    public function margin(int|string $c1, int|string $c2, bool $useExtended = false): int
    {
        $margin = 0;
        foreach ($this->_grades as $i => $g) {
            $pref12 = $useExtended ? $g->extendedStrictPref($c1, $c2) : $g->strictPref($c1, $c2);
            $pref21 = $useExtended ? $g->extendedStrictPref($c2, $c1) : $g->strictPref($c2, $c1);
            if ($pref12) $margin += $this->gcounts[$i];
            if ($pref21) $margin -= $this->gcounts[$i];
        }
        return $margin;
    }

    /**
     * Returns the proportion of voters that assign cand the grade grade.
     */
    public function proportion(int|string $cand, mixed $grade): float
    {
        $count = 0;
        foreach ($this->_grades as $i => $g) {
            if ($g($cand) === $grade) {
                $count += $this->gcounts[$i];
            }
        }
        return $count / $this->numVoters;
    }

    /**
     * Returns the sum of the grades of c.
     */
    public function sum(int|string $c): ?float
    {
        if (!$this->canSumGrades) {
            throw new \Exception("The grades in the profile cannot be summed.");
        }
        if (!$this->hasGrade($c)) return null;
        
        $sum = 0.0;
        foreach ($this->_grades as $i => $g) {
            if ($g->hasGrade($c)) {
                $sum += $g($c) * $this->gcounts[$i];
            }
        }
        return $sum;
    }

    /**
     * Returns the average of the grades of c.
     */
    public function avg(int|string $c): ?float
    {
        if (!$this->canSumGrades) {
            throw new \Exception("The grades in the profile cannot be summed.");
        }
        if (!$this->hasGrade($c)) return null;

        $sum = 0.0;
        $count = 0;
        foreach ($this->_grades as $i => $g) {
            if ($g->hasGrade($c)) {
                $sum += $g($c) * $this->gcounts[$i];
                $count += $this->gcounts[$i];
            }
        }
        return $count > 0 ? $sum / $count : null;
    }

    /**
     * Returns the maximum of the grades of c.
     */
    public function max(int|string $c): mixed
    {
        if (!$this->hasGrade($c)) return null;

        if ($this->useGradeOrder) {
            $orderMap = array_flip($this->gradeOrder);
            $minIdx = PHP_INT_MAX;
            foreach ($this->_grades as $g) {
                if ($g->hasGrade($c)) {
                    $idx = $orderMap[$g($c)];
                    if ($idx < $minIdx) $minIdx = $idx;
                }
            }
            return $this->gradeOrder[$minIdx];
        } else {
            $max = null;
            foreach ($this->_grades as $g) {
                if ($g->hasGrade($c)) {
                    $val = $g($c);
                    if ($max === null || $val > $max) $max = $val;
                }
            }
            return $max;
        }
    }

    /**
     * Returns the minimum of the grades of c.
     */
    public function min(int|string $c): mixed
    {
        if (!$this->hasGrade($c)) return null;

        if ($this->useGradeOrder) {
            $orderMap = array_flip($this->gradeOrder);
            $maxIdx = -1;
            foreach ($this->_grades as $g) {
                if ($g->hasGrade($c)) {
                    $idx = $orderMap[$g($c)];
                    if ($idx > $maxIdx) $maxIdx = $idx;
                }
            }
            return $this->gradeOrder[$maxIdx];
        } else {
            $min = null;
            foreach ($this->_grades as $g) {
                if ($g->hasGrade($c)) {
                    $val = $g($c);
                    if ($min === null || $val < $min) $min = $val;
                }
            }
            return $min;
        }
    }

    /**
     * Returns the median of the grades of c.
     */
    public function median(int|string $c, bool $useLower = true, bool $useAverage = false): mixed
    {
        if (!$this->hasGrade($c)) return null;

        $gradesForC = [];
        foreach ($this->getGradeFunctions() as $g) {
            if ($g->hasGrade($c)) {
                $gradesForC[] = $g($c);
            }
        }

        if ($this->useGradeOrder) {
            $orderMap = array_flip($this->gradeOrder);
            usort($gradesForC, fn($a, $b) => $orderMap[$a] <=> $orderMap[$b]);
        } else {
            sort($gradesForC);
        }

        $numGrades = count($gradesForC);
        $medianIdx = intdiv($numGrades, 2);
        
        if ($numGrades % 2 === 0) {
            $medianGrades = [$gradesForC[$medianIdx - 1], $gradesForC[$medianIdx]];
        } else {
            $medianGrades = [$gradesForC[$medianIdx]];
        }

        if ($useLower) {
            return $medianGrades[0];
        } elseif ($useAverage) {
            if ($this->canSumGrades) {
                return array_sum($medianGrades) / count($medianGrades);
            }
            // If not numeric, average doesn't make sense, return first or the pair
            return $medianGrades[0];
        } else {
            return count($medianGrades) === 1 ? $medianGrades[0] : $medianGrades;
        }
    }

    public function sumGradeFunction(): Mapping
    {
        if (!$this->canSumGrades) {
            throw new \Exception("The grades in the profile cannot be summed.");
        }
        $mapping = [];
        foreach ($this->candidates as $c) {
            if ($this->hasGrade($c)) {
                $mapping[$c] = $this->sum($c);
            }
        }
        return new Mapping($mapping, $this->candidates, null, $this->compareFunction, $this->cmap);
    }

    public function avgGradeFunction(): Mapping
    {
        if (!$this->canSumGrades) {
            throw new \Exception("The grades in the profile cannot be summed.");
        }
        $mapping = [];
        foreach ($this->candidates as $c) {
            if ($this->hasGrade($c)) {
                $mapping[$c] = $this->avg($c);
            }
        }
        return new Mapping($mapping, $this->candidates, null, $this->compareFunction, $this->cmap);
    }

    public function proportionWithGrade(int|string $cand, mixed $grade): float
    {
        $count = 0;
        foreach ($this->_grades as $i => $g) {
            if (($this->compareFunction)($g($cand), $grade) === 0) {
                $count += $this->gcounts[$i];
            }
        }
        return $count / $this->numVoters;
    }

    public function proportionWithHigherGrade(int|string $cand, mixed $grade): float
    {
        $count = 0;
        foreach ($this->_grades as $i => $g) {
            if (($this->compareFunction)($g($cand), $grade) === 1) {
                $count += $this->gcounts[$i];
            }
        }
        return $count / $this->numVoters;
    }

    public function proportionWithLowerGrade(int|string $cand, mixed $grade): float
    {
        $count = 0;
        foreach ($this->_grades as $i => $g) {
            if (($this->compareFunction)($g($cand), $grade) === -1) {
                $count += $this->gcounts[$i];
            }
        }
        return $count / $this->numVoters;
    }

    public function approvalScores(): array
    {
        if (!$this->canSumGrades) {
            throw new \Exception("The grades in the profile cannot be summed.");
        }
        $sortedGrades = $this->grades;
        sort($sortedGrades);
        if ($sortedGrades !== [0, 1] && $sortedGrades !== [0.0, 1.0]) {
            // Check if they are effectively 0 and 1
            if (count($sortedGrades) !== 2 || floatval($sortedGrades[0]) !== 0.0 || floatval($sortedGrades[1]) !== 1.0) {
                throw new \Exception("The grades in the profile must be 0 and 1.");
            }
        }

        $scores = [];
        foreach ($this->candidates as $c) {
            $scores[$c] = $this->sum($c);
        }
        return $scores;
    }

    public function toRankingProfile(): ProfileWithTies
    {
        $rankings = [];
        foreach ($this->_grades as $g) {
            $rankings[] = $g->ranking();
        }
        return new ProfileWithTies($rankings, $this->gcounts, $this->candidates, $this->cmap);
    }

    public function display(bool $showTotals = false): void
    {
        if ($showTotals) {
            $sumGradeFnc = $this->sumGradeFunction();
            // Basic table display
            echo "\t" . implode("\t", $this->gcounts) . "\tSum\tMedian\n";
            foreach ($this->candidates as $c) {
                echo $this->cmap[$c] . "\t";
                foreach ($this->_grades as $g) {
                    echo ($g->hasGrade($c) ? $this->gmap[$g($c)] : "") . "\t";
                }
                echo $sumGradeFnc($c) . "\t" . (string)$this->median($c) . "\n";
            }
        } else {
            echo "\t" . implode("\t", $this->gcounts) . "\n";
            foreach ($this->candidates as $c) {
                echo $this->cmap[$c] . "\t";
                foreach ($this->_grades as $g) {
                    echo ($g->hasGrade($c) ? $this->gmap[$g($c)] : "") . "\t";
                }
                echo "\n";
            }
        }
    }
}
