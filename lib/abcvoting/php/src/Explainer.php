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

class Explainer
{
    public static function explain(Profile $profile, array $result): string
    {
        if (!isset($result['detailed_info']) || empty($result['detailed_info'])) {
            return "<p>No detailed explanation available for this rule or configuration.</p>";
        }

        $info = $result['detailed_info'];
        $html = "<div class='abcvoting-explanation'>";

        if (isset($info['rule_id']) && str_starts_with($info['rule_id'], 'seq')) {
            $html .= self::explainSeqThiele($profile, $info);
        } elseif (isset($info['times'])) { // Phragmen
            $html .= self::explainSeqPhragmen($profile, $info);
        } elseif (isset($info['start_budget'])) { // Rule X
            $html .= self::explainEqualShares($profile, $info);
        }

        $html .= "</div>";
        return $html;
    }

    private static function explainSeqThiele(Profile $profile, array $info): string
    {
        $html = "<h3>Sequential Thiele Method</h3>";
        $html .= "<p>Starting with an empty committee (score: " . sprintf("%.2f", $info['base_score']) . ")</p>";
        $html .= "<ul>";
        foreach ($info['next_cand'] as $i => $cand) {
            $html .= "<li>Step " . ($i + 1) . ": Added <strong>" . htmlspecialchars($profile->candNames[$cand]) . "</strong>";
            $html .= " (score increases by " . sprintf("%.2f", $info['delta_score'][$i]) . ")";
            if (count($info['tied_cands'][$i]) > 1) {
                $html .= "<br><small>Tie broken in favor of " . htmlspecialchars($profile->candNames[$cand]) . ". Tied candidates: ";
                $names = array_map(fn($c) => htmlspecialchars($profile->candNames[$c]), $info['tied_cands'][$i]);
                $html .= implode(', ', $names) . ".</small>";
            }
            $html .= "</li>";
        }
        $html .= "</ul>";
        return $html;
    }

    private static function explainSeqPhragmen(Profile $profile, array $info, string $title = "Sequential Phragmén"): string
    {
        $html = "<h3>$title</h3>";
        $html .= "<ul>";
        foreach ($info['next_cand'] as $i => $cand) {
            $html .= "<li>Step " . ($i + 1) . ": Added <strong>" . htmlspecialchars($profile->candNames[$cand]) . "</strong>";
            $html .= " (time: " . sprintf("%.4f", $info['times'][$i]) . ")";
            if (count($info['tied_cands'][$i]) > 1) {
                $html .= "<br><small>Tie broken in favor of " . htmlspecialchars($profile->candNames[$cand]) . ". Tied candidates: ";
                $names = array_map(fn($c) => htmlspecialchars($profile->candNames[$c]), $info['tied_cands'][$i]);
                $html .= implode(', ', $names) . ".</small>";
            }
            $html .= "</li>";
        }
        $html .= "</ul>";
        return $html;
    }

    private static function explainEqualShares(Profile $profile, array $info): string
    {
        $html = "<h3>Method of Equal Shares (Rule X)</h3>";
        
        if ($info['too_few_approved_candidates']) {
            return $html . "<p>Fewer approved candidates than requested committee size. Falling back to AV.</p>";
        }

        if ($info['increment_committeesize']) {
            $html .= "<p>Incremented starting budget to fill the committee (virtual size: " . $info['increment_committeesize'] . ").</p>";
        }

        $html .= "<h4>Phase 1: Buying Candidates</h4>";
        $html .= "<ul>";
        foreach ($info['next_cand'] as $i => $cand) {
            $html .= "<li>Step " . ($i + 1) . ": Bought <strong>" . htmlspecialchars($profile->candNames[$cand]) . "</strong>";
            $html .= " at cost q = " . sprintf("%.4f", $info['cost'][$i]);
            if (count($info['tied_cands'][$i]) > 1) {
                $html .= " (tie broken)";
            }
            $html .= "</li>";
        }
        $html .= "</ul>";

        if ($info['phragmen_phase']) {
            $html .= "<h4>Phase 2: Completion (Sequential Phragmén)</h4>";
            $html .= self::explainSeqPhragmen($profile, $info['phragmen_phase'], "Phragmén Completion");
        } elseif ($info['av_phase']) {
            $html .= "<h4>Phase 2: Completion (Approval Voting)</h4>";
            $html .= "<p>Added remaining candidates based on approval counts: ";
            $names = array_map(fn($c) => htmlspecialchars($profile->candNames[$c]), $info['av_phase']['added']);
            $html .= "<strong>" . implode(', ', $names) . "</strong>.</p>";
        }

        return $html;
    }
}
