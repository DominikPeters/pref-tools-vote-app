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
 * Helper classes and functions for voting methods.
 */
class Helper
{
    /**
     * Creates a SPO (Strict Partial Order) instance.
     */
    public static function spo(int $n): SPO
    {
        return new SPO($n);
    }
}