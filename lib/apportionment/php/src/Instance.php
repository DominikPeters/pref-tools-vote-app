<?php

declare(strict_types=1);

namespace Apportionment;

class Instance
{
    /** @var int[] Party votes */
    public array $votes;
    
    /** @var string[] Party names */
    public array $partyNames;
    
    /** @var string[] Party colors (optional, for explanation) */
    public array $partyColors;
    
    /** @var int Number of seats to allocate */
    public int $seats;

    /**
     * @param int $seats
     * @param int[] $votes
     * @param string[]|null $partyNames
     * @param string[]|null $partyColors
     */
    public function __construct(int $seats, array $votes, ?array $partyNames = null, ?array $partyColors = null)
    {
        $this->seats = $seats;
        $this->votes = array_values($votes);
        $numParties = count($this->votes);
        
        if ($partyNames !== null) {
            $this->partyNames = array_values($partyNames);
        } else {
            $this->partyNames = array_map(fn($i) => "Option " . ($i + 1), range(0, $numParties - 1));
        }
        
        if ($partyColors !== null) {
            $this->partyColors = array_values($partyColors);
        } else {
            $this->partyColors = array_fill(0, $numParties, "var(--color-text-dim)");
        }
    }

    public function getNumParties(): int
    {
        return count($this->votes);
    }
}
