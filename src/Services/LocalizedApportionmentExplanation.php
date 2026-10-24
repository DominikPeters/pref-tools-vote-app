<?php

namespace App\Services;

use App\i18n\Translator;

/** Render cached calculation facts in the viewer's locale without running a voting rule. */
final class LocalizedApportionmentExplanation
{
    public static function render(array $data): string
    {
        $info = $data['detailed_info'] ?? [];
        if (!$info) return '<p>' . self::text('explanation_unavailable') . '</p>';
        $votes = $data['votes'];
        $names = $data['party_names'];
        $seats = (int) $data['seats'];
        $method = $info['method'];
        $html = "<div class='apportionment-explanation'>";
        if ($method === 'hamilton') {
            $html .= '<h2>' . self::text('explanation_hamilton_title') . '</h2>';
            $html .= '<p>' . self::text('explanation_hamilton_description') . '</p>';
            $html .= self::tableHeader(['explanation_option', 'explanation_votes', 'explanation_quota', 'explanation_lower_quota', 'explanation_remainder', 'explanation_seats']);
            foreach ($votes as $i => $vote) {
                $extra = $info['representatives'][$i] > $info['lower_quotas'][$i];
                $style = $extra ? " style='font-weight:bold'" : (in_array($i, $info['at_cutoff'], true) ? " style='font-style:italic'" : '');
                $html .= '<tr><td>' . self::escape($names[$i]) . '</td><td>' . self::number($vote) . '</td><td>' . self::number($info['quotas'][$i], 2) . '</td><td>' . self::number($info['lower_quotas'][$i]) . '</td><td' . $style . '>' . self::number($info['remainders'][$i], 2) . '</td><td>' . self::number($info['representatives'][$i]) . '</td></tr>';
            }
            $html .= '</tbody></table>';
            if ($info['ties']) {
                $html .= '<p>' . self::text('explanation_hamilton_ties', [
                    'options' => self::names($names, $info['at_cutoff']),
                    'extra' => (int) $info['num_at_cutoff_receiving_seat'],
                    'remaining' => count($info['at_cutoff']) - (int) $info['num_at_cutoff_receiving_seat'],
                ]) . '</p>';
            }
        } elseif ($method === 'quota') {
            $html .= '<h2>' . self::text('explanation_quota_title') . '</h2><p>' . self::text('explanation_quota_properties') . '</p><p>' . self::text('explanation_quota_description') . '</p>';
            $divisors = range(1, max($info['representatives']) + 1);
            $html .= "<div style='overflow-x:auto;max-width:100%'>" . self::divisorHeader($divisors);
            foreach ($votes as $i => $vote) {
                $html .= '<tr><td>' . self::escape($names[$i]) . '</td>';
                foreach ($divisors as $divisor) {
                    $style = $info['representatives'][$i] >= $divisor ? " style='background-color:var(--color-success-bg)'" : '';
                    $html .= '<td' . $style . '>' . self::number($vote / $divisor, 2) . '</td>';
                }
                $html .= '</tr>';
            }
            $html .= '</tbody></table></div>';
        } else {
            $html .= self::divisor($votes, $names, $seats, $info);
        }
        return $html . '</div>';
    }

    private static function divisor(array $votes, array $names, int $seats, array $info): string
    {
        $method = $info['method'];
        $title = match ($method) {
            'dhondt', 'jefferson', 'greatestdivisors' => __('explanation_dhondt_title'),
            'saintelague', 'webster', 'majorfractions' => __('explanation_saintelague_title'),
            'huntington', 'hill', 'equalproportions' => __('explanation_huntington_title'),
            'dean', 'harmonicmean' => __('explanation_dean_title'),
            'adams', 'smallestdivisor' => __('explanation_adams_title'),
            'modified_saintelague' => __('explanation_modified_saintelague_title'),
            default => ucfirst($method),
        };
        $html = '<h2>' . self::escape($title) . '</h2>';
        if ($info['fewer_seats_than_parties']) {
            return $html . '<p>' . self::text('explanation_fewer_seats', ['seats' => $seats, 'options' => count($names)]) . '</p>';
        }
        $divisor = $info['min_weight'];
        $lowerBound = $info['lower_bound_divisor'];
        if ($method === 'saintelague') {
            $divisor *= 2;
            $lowerBound *= 2;
        }
        // This presentation adjustment selects an equivalent divisor, not a new allocation.
        for ($candidate = ceil($lowerBound); $candidate <= floor($divisor); $candidate++) {
            if ($candidate > $lowerBound) {
                $divisor = $candidate;
                break;
            }
        }
        // Some rules assign one mandatory seat per option without a quotient stage.
        if ($divisor <= 0) return $html . '<p>' . self::text('explanation_unavailable') . '</p>';
        $html .= '<p><strong>' . self::text('explanation_computation') . '</strong> ' . self::text('explanation_divide_votes', ['divisor' => self::formatNumber($divisor, 2)]) . '</p>';
        $rounding = match ($method) {
            'dhondt', 'jefferson', 'greatestdivisors' => 'explanation_round_down',
            'saintelague', 'webster', 'majorfractions' => 'explanation_round_nearest',
            'huntington', 'hill', 'equalproportions' => 'explanation_round_geometric',
            'dean', 'harmonicmean' => 'explanation_round_harmonic',
            'adams', 'smallestdivisor' => 'explanation_round_up',
            default => null,
        };
        if ($rounding !== null) $html .= '<p>' . self::text($rounding) . '</p>';
        $html .= self::tableHeader(['explanation_option', 'explanation_votes', 'explanation_quotient', 'explanation_rounded_to']);
        foreach ($votes as $i => $vote) {
            $quotient = $vote / ($method === 'saintelague' ? $divisor / 2 : $divisor);
            $html .= '<tr><td>' . self::escape($names[$i]) . '</td><td>' . self::number($vote) . '</td><td>' . self::number($quotient, 2) . '</td><td>' . self::number($info['representatives'][$i]) . '</td></tr>';
        }
        $html .= '<tr><td>' . self::text('explanation_total') . '</td><td>' . self::number(array_sum($votes)) . '</td><td></td><td><strong>' . self::number(array_sum($info['representatives'])) . '</strong></td></tr></tbody></table>';
        $html .= '<h3>' . self::text('explanation_table_title') . '</h3><p>' . self::text('explanation_table_description', ['seats' => $seats]) . '</p>';
        $divisors = array_slice($info['divisors'], 0, max($info['representatives']) + 1);
        $html .= "<div style='overflow-x:auto;max-width:100%'>" . self::divisorHeader($divisors);
        foreach ($votes as $i => $vote) {
            $html .= '<tr><td>' . self::escape($names[$i]) . '</td>';
            foreach ($divisors as $j => $columnDivisor) {
                $weight = $info['weights'][$i][$j];
                $style = $info['representatives'][$i] >= $j + 1 ? " style='background-color:var(--color-success-bg)'" : (abs($weight - $info['min_weight']) < 1e-12 ? " style='background-color:var(--color-warning-bg)'" : '');
                $html .= '<td' . $style . '>' . self::number($weight, 2) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table></div>';
        if ($info['ties']) {
            $html .= '<p>' . self::text('explanation_divisor_ties', [
                'options' => self::names($names, $info['at_cutoff']),
                'receiving' => self::names($names, $info['at_cutoff_receiving_seats']),
            ]) . '</p>';
        }
        return $html;
    }

    private static function tableHeader(array $keys): string
    {
        return "<table class='table'><thead><tr>" . implode('', array_map(static fn(string $key): string => '<th>' . self::text($key) . '</th>', $keys)) . '</tr></thead><tbody>';
    }

    private static function divisorHeader(array $divisors): string
    {
        return "<table class='table'><thead><tr><th>" . self::text('explanation_option') . '</th>' . implode('', array_map(static fn($value): string => '<th>' . self::number($value, is_int($value) ? 0 : 2) . '</th>', $divisors)) . '</tr></thead><tbody>';
    }

    private static function names(array $names, array $indices): string
    {
        return implode(', ', array_map(static fn($index): string => (string) $names[$index], $indices));
    }

    private static function text(string $key, array $params = []): string
    {
        return self::escape(Translator::translate($key, $params));
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function number(int|float $value, int $decimals = 0): string
    {
        return self::escape(self::formatNumber($value, $decimals));
    }

    private static function formatNumber(int|float $value, int $decimals): string
    {
        static $formatters = [];
        $key = Translator::getLocale() . ':' . $decimals;
        if (!isset($formatters[$key])) {
            $formatter = new \NumberFormatter(Translator::getLocale(), \NumberFormatter::DECIMAL);
            $formatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
            $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $decimals);
            $formatters[$key] = $formatter;
        }
        return $formatters[$key]->format($value);
    }
}
