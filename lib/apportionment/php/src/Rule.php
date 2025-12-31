<?php

declare(strict_types=1);

namespace Apportionment;

class Rule
{
    public string $ruleId;
    public string $name;
    public string $description;

    public function __construct(string $ruleId, string $name, string $description = "")
    {
        $this->ruleId = $ruleId;
        $this->name = $name;
        $this->description = $description;
    }

    public static function getMethods(): array
    {
        return [
            new Rule('hamilton', 'Hamilton / Largest Remainder'),
            new Rule('dhondt', "D'Hondt / Jefferson"),
            new Rule('saintelague', 'Sainte-Laguë / Webster'),
            new Rule('huntington', 'Huntington-Hill'),
            new Rule('dean', 'Dean'),
            new Rule('adams', 'Adams'),
            new Rule('quota', 'Quota'),
        ];
    }
}
