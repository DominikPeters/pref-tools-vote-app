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
 * A partial function on a set of items.
 */
class Mapping
{
    /** @var array<int|string, mixed> The mapping as an associative array */
    public array $mapping;

    /** @var array<int|string> The domain of the mapping */
    public array $domain;

    /** @var array<mixed>|null The codomain of the mapping */
    public ?array $codomain;

    /** @var array<int|string, string> Mapping from items to their names */
    public array $itemMap;

    /** @var array<mixed, string>|null Mapping from values to their names */
    public ?array $valMap;

    /** @var callable Function used to compare values */
    public $compareFunction;

    /**
     * @param array<int|string, mixed> $mapping The mapping
     * @param array<int|string>|null $domain The domain
     * @param array<mixed>|null $codomain The codomain
     * @param callable|null $compareFunction Function to compare values
     * @param array<int|string, string>|null $itemMap Item name map
     * @param array<mixed, string>|null $valMap Value name map
     */
    public function __construct(
        array $mapping,
        ?array $domain = null,
        ?array $codomain = null,
        ?callable $compareFunction = null,
        ?array $itemMap = null,
        ?array $valMap = null
    ) {
        $this->mapping = $mapping;
        
        if ($domain === null) {
            $this->domain = array_keys($mapping);
            sort($this->domain);
        } else {
            $this->domain = $domain;
        }

        $this->codomain = $codomain;
        $this->itemMap = $itemMap ?? array_combine($this->domain, array_map('strval', $this->domain));
        $this->valMap = $valMap;
        
        $this->compareFunction = $compareFunction ?? function ($v1, $v2) {
            if ($v1 > $v2) return 1;
            if ($v2 > $v1) return -1;
            return 0;
        };
    }

    /**
     * The value assigned to x by the mapping.
     */
    public function val(int|string $x): mixed
    {
        if (!in_array($x, $this->domain, true)) {
            throw new \InvalidArgumentException("$x not in the domain.");
        }
        return $this->mapping[$x] ?? null;
    }

    /**
     * Returns true if x is defined by the mapping.
     */
    public function hasValue(int|string $x): bool
    {
        return array_key_exists($x, $this->mapping);
    }

    /**
     * Returns the list of items for which the mapping is defined.
     */
    public function definedDomain(): array
    {
        $keys = array_keys($this->mapping);
        sort($keys);
        return $keys;
    }

    /**
     * Return all the elements in the domain that are mapped to v.
     */
    public function inverseImage(mixed $v): array
    {
        $result = [];
        foreach ($this->domain as $x) {
            if ($this->val($x) === $v) {
                $result[] = $x;
            }
        }
        return $result;
    }

    /**
     * The image of the mapping for the given items.
     */
    public function image(?array $items = null): array
    {
        $items = $items ?? $this->definedDomain();
        $result = [];
        foreach ($items as $x) {
            $val = $this->val($x);
            if ($val !== null) {
                $result[] = $val;
            }
        }
        return $result;
    }

    /**
     * The range of the mapping.
     */
    public function range(): array
    {
        $range = array_values(array_unique($this->mapping, SORT_REGULAR));
        sort($range);
        return $range;
    }

    /**
     * Returns the average of values in the image.
     */
    public function average(?array $items = null): ?float
    {
        $img = $this->image($items);
        if (empty($img)) return null;
        return array_sum($img) / count($img);
    }

    /**
     * Returns the median of values in the image.
     */
    public function median(?array $items = null): mixed
    {
        $img = $this->image($items);
        if (empty($img)) return null;
        sort($img);
        $count = count($img);
        $middle = intdiv($count, 2);
        if ($count % 2 === 0) {
            $v1 = $img[$middle - 1];
            $v2 = $img[$middle];
            if (is_numeric($v1) && is_numeric($v2)) {
                return ($v1 + $v2) / 2;
            }
            return [$v1, $v2];
        }
        return $img[$middle];
    }

    /**
     * Returns 1 if val(x) > val(y), 0 if equal, -1 if val(x) < val(y).
     */
    public function compare(int|string $x, int|string $y): ?int
    {
        if (!$this->hasValue($x) || !$this->hasValue($y)) {
            return null;
        }
        return ($this->compareFunction)($this->val($x), $this->val($y));
    }

    /**
     * Extended comparison: x is better than y if x has a value and y doesn't.
     */
    public function extendedCompare(int|string $x, int|string $y): int
    {
        $hasX = $this->hasValue($x);
        $hasY = $this->hasValue($y);

        if ($hasX && !$hasY) return 1;
        if (!$hasX && $hasY) return -1;
        if (!$hasX && !$hasY) return 0;

        return ($this->compareFunction)($this->val($x), $this->val($y));
    }

    public function strictPref(int|string $x, int|string $y): bool
    {
        return $this->compare($x, $y) === 1;
    }

    public function extendedStrictPref(int|string $x, int|string $y): bool
    {
        return $this->extendedCompare($x, $y) === 1;
    }

    public function indiff(int|string $x, int|string $y): bool
    {
        return $this->compare($x, $y) === 0;
    }

    public function extendedIndiff(int|string $x, int|string $y): bool
    {
        return $this->extendedCompare($x, $y) === 0;
    }

    public function weakPref(int|string $x, int|string $y): bool
    {
        return $this->strictPref($x, $y) || $this->indiff($x, y);
    }

    public function extendedWeakPref(int|string $x, int|string $y): bool
    {
        return $this->extendedStrictPref($x, $y) || $this->extendedIndiff($x, $y);
    }

    /**
     * Returns indifference classes of items.
     */
    protected function indifferenceClasses(array $items, bool $useExtended = false): array
    {
        $classes = [];
        $processed = [];
        foreach ($items as $x) {
            if (in_array($x, $processed, true)) continue;
            
            $indiff = [];
            foreach ($items as $y) {
                if ($useExtended) {
                    if ($this->extendedIndiff($x, $y)) $indiff[] = $y;
                } else {
                    if ($this->indiff($x, $y)) $indiff[] = $y;
                }
            }
            
            if (!empty($indiff)) {
                $classes[] = $indiff;
                foreach ($indiff as $y) {
                    $processed[] = $y;
                }
            }
        }
        return $classes;
    }

    /**
     * Returns indifference classes sorted by value.
     */
    public function sortedDomain(bool $extended = false): array
    {
        $items = $extended ? $this->domain : $this->definedDomain();
        $classes = $this->indifferenceClasses($items, $extended);

        usort($classes, function ($a, $b) use ($extended) {
            if ($extended) {
                return $this->extendedCompare($b[0], $a[0]);
            }
            return $this->compare($b[0], $a[0]);
        });

        return $classes;
    }

    public function asDict(): array
    {
        $result = [];
        foreach ($this->definedDomain() as $x) {
            $result[$x] = $this->val($x);
        }
        return $result;
    }

    public function __invoke(int|string $x): mixed
    {
        return $this->val($x);
    }

    public function __toString(): string
    {
        $parts = [];
        foreach ($this->domain as $x) {
            $val = $this->val($x);
            $valStr = $val === null ? "None" : (isset($this->valMap) ? ($this->valMap)[$val] ?? (string)$val : (string)$val);
            $parts[] = "{$this->itemMap[$x]}:$valStr";
        }
        return implode(", ", $parts);
    }
}
