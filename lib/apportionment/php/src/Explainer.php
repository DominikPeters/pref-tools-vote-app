<?php

declare(strict_types=1);

namespace Apportionment;

class Explainer
{
    public static function explain(Instance $instance, array $result): string
    {
        if (!isset($result['detailed_info']) || empty($result['detailed_info'])) {
            return "<p>No detailed explanation available for this rule or configuration.</p>";
        }

        $info = $result['detailed_info'];
        $method = $info['method'];
        $html = "<div class='apportionment-explanation'>";

        if ($method === 'hamilton') {
            $html .= self::explainHamilton($instance, $info);
        } elseif ($method === 'quota') {
            $html .= self::explainQuota($instance, $info);
        } else {
            $html .= self::explainDivisor($instance, $info);
        }

        $html .= "</div>";
        return $html;
    }

    private static function explainHamilton(Instance $instance, array $info): string
    {
        $html = "<h2>Hamilton's Method / Largest Remainder Method</h2>";
        $html .= "<p>This method works by computing the exact <i>quota</i> of each option (the fractional number of seats it is entitled to).
            It then rounds this down to the <i>lower quota</i> and gives each option that many seats.
            Any remaining seats are given to the options with the largest remainders (i.e. fractional part, or quota minus lower quota).</p>";

        $html .= "<table class='table'>";
        $html .= "<thead><tr><th>Option</th><th>Votes</th><th>Quota</th><th>Lower quota</th><th>Remainder</th><th>Seats</th></tr></thead>";
        $html .= "<tbody>";
        foreach ($instance->votes as $i => $v) {
            $hasExtraSeat = $info['representatives'][$i] > $info['lower_quotas'][$i];
            $style = "";
            if ($hasExtraSeat) {
                $style = " style='font-weight:bold'";
            } elseif (in_array($i, $info['at_cutoff'])) {
                $style = " style='font-style:italic'";
            }

            $html .= "<tr>";
            $html .= "<td>" . htmlspecialchars($instance->partyNames[$i]) . "</td>";
            $html .= "<td>" . $v . "</td>";
            $html .= "<td>" . number_format($info['quotas'][$i], 2) . "</td>";
            $html .= "<td>" . $info['lower_quotas'][$i] . "</td>";
            $html .= "<td$style>" . number_format($info['remainders'][$i], 2) . "</td>";
            $html .= "<td>" . $info['representatives'][$i] . "</td>";
            $html .= "</tr>";
        }
        $html .= "</tbody></table>";

        if ($info['ties']) {
            $names = array_map(fn($i) => htmlspecialchars($instance->partyNames[$i]), $info['at_cutoff']);
            $html .= "<p>There were ties. The possible Hamilton apportionments can be obtained by giving out seats as in the table above, except for the following options: " . implode(", ", $names) . ". From these options, exactly " . $info['num_at_cutoff_receiving_seat'] . " options (arbitrarily selected) receive one seat more than their lower quota, and the remaining " . (count($info['at_cutoff']) - $info['num_at_cutoff_receiving_seat']) . " receive their lower quota.</p>";
        }

        return $html;
    }

    private static function explainQuota(Instance $instance, array $info): string
    {
        $html = "<h2>Balinski and Young's Quota Method</h2>";
        $html .= "<p>This method is a variation of the D'Hondt/Jefferson method that is house monotone and that satisfies lower and upper quota.</p>
            <p>The quota method works by repeatedly taking the largest number in a table of quotients (votes divided by 1, 2, 3...) and giving the option it corresponds to a seat.
            However, the method never gives an option more seats than its upper quota; if the option has already reached its upper quota,
            the method instead looks for the next-largest number in the table, until it finds one belonging to an option that has not yet
            reached its upper quota.</p>";

        $maxReps = max($info['representatives']);
        $divisors = range(1, $maxReps + 1);

        $html .= "<div style='overflow-x: auto; max-width: 100%;'>";
        $html .= "<table class='table'>";
        $html .= "<thead><tr><th>Option</th>";
        foreach ($divisors as $d) {
            $html .= "<th>$d</th>";
        }
        $html .= "</tr></thead><tbody>";

        foreach ($instance->votes as $j => $v) {
            $html .= "<tr>";
            $html .= "<td>" . htmlspecialchars($instance->partyNames[$j]) . "</td>";
            foreach ($divisors as $i) {
                $weight = $v / $i;
                $style = "";
                if ($info['representatives'][$j] >= $i) {
                    $style = " style='background-color: var(--color-success-bg);'";
                }
                $html .= "<td$style>" . number_format($weight, 2) . "</td>";
            }
            $html .= "</tr>";
        }
        $html .= "</tbody></table></div>";

        return $html;
    }

    private static function explainDivisor(Instance $instance, array $info): string
    {
        $method = $info['method'];
        $html = "<h2>" . self::getMethodName($method) . "</h2>";

        if ($info['fewer_seats_than_parties']) {
            $html .= "<p>In the special case where the number of available seats (" . $instance->seats . ") is smaller than the number of options (" . $instance->getNumParties() . "), then this divisor method gives 1 seat to the " . $instance->seats . " options with the highest votes, and 0 seats to the other options.</p>";
            return $html;
        }

        $divisor = $info['min_weight'];
        // Try to find an integer divisor for presentation
        if ($method === "saintelague") {
            $divisor *= 2;
            $lowerBound = $info['lower_bound_divisor'] * 2;
        } else {
            $lowerBound = $info['lower_bound_divisor'];
        }
        
        $divisorForPresentation = $divisor;
        for ($d = ceil($lowerBound); $d <= floor($divisor); $d++) {
            if ($d > $lowerBound && $d <= $divisor) {
                $divisorForPresentation = $d;
                break;
            }
        }

        $html .= "<p><b>Computation.</b> For each option, we take its votes and divide it by " . number_format($divisorForPresentation, 2) . ". The resulting number is the <i>quotient</i>. ";
        
        if (in_array($method, ["dhondt", "jefferson", "greatestdivisors"])) {
            $html .= "The quotient is then rounded down (i.e., we take the floor), to obtain the number of seats for the option.";
        } elseif (in_array($method, ["saintelague", "webster", "majorfractions"])) {
            $html .= "The quotient is then rounded to the nearest integer, to obtain the number of seats for the option.";
        } elseif (in_array($method, ["huntington", "hill", "equalproportions"])) {
            $html .= "The quotient is then rounded using 'geometric rounding': suppose the quotient lies between the integers <i>n</i> and <i>n</i>+1. Then we take their geometric mean <i>x</i> = sqrt(<i>n</i>(<i>n</i>+1)), and round the quotient up if it is greater than <i>x</i>, and down if it is less than <i>x</i>.";
        } elseif (in_array($method, ["dean", "harmonicmean"])) {
            $html .= "The quotient is then rounded using 'harmonic rounding': suppose the quotient lies between the integers <i>n</i> and <i>n</i>+1. Then we take their harmonic mean <i>x</i> = <i>n</i>(<i>n</i>+1)/(<i>n</i> + 0.5), and round the quotient up if it is greater than <i>x</i>, and down if it is less than <i>x</i>.";
        } elseif (in_array($method, ["adams", "smallestdivisor"])) {
            $html .= "The quotient is then rounded up (i.e., we take the ceiling), to obtain the number of seats for the option.";
        }
        $html .= "</p>";

        $html .= "<table class='table'><thead><tr><th>Option</th><th>Votes</th><th>Quotient</th><th>Rounded to</th></tr></thead><tbody>";
        foreach ($instance->votes as $i => $v) {
            $html .= "<tr>";
            $html .= "<td>" . htmlspecialchars($instance->partyNames[$i]) . "</td>";
            $html .= "<td>" . $v . "</td>";
            $html .= "<td>" . number_format($v / ($method === 'saintelague' ? $divisorForPresentation / 2 : $divisorForPresentation), 2) . "</td>";
            $html .= "<td>" . $info['representatives'][$i] . "</td>";
            $html .= "</tr>";
        }
        $html .= "<tr><td>Total</td><td>" . array_sum($instance->votes) . "</td><td></td><td><b>" . array_sum($info['representatives']) . "</b></td></tr>";
        $html .= "</tbody></table>";

        // Table method
        $html .= "<h3>Alternative computation (Table Method)</h3>";
        $html .= "<p>We make a big table, where each option occupies a row. In its row, we write the number of the option's votes divided by its divisors. We then highlight in green the " . $instance->seats . " largest numbers appearing in the table. Each option receives 1 seat for each green cell in its row.</p>";

        $maxReps = max($info['representatives']);
        $divisorsForTable = array_slice($info['divisors'], 0, $maxReps + 1);
        
        $html .= "<div style='overflow-x: auto; max-width: 100%;'>";
        $html .= "<table class='table'><thead><tr><th>Option</th>";
        foreach ($divisorsForTable as $d) {
            $html .= "<th>" . (is_int($d) ? $d : number_format($d, 2)) . "</th>";
        }
        $html .= "</tr></thead><tbody>";
        foreach ($instance->votes as $j => $v) {
            $html .= "<tr>";
            $html .= "<td>" . htmlspecialchars($instance->partyNames[$j]) . "</td>";
            foreach ($divisorsForTable as $i => $d) {
                $weight = $info['weights'][$j][$i];
                $style = "";
                if ($info['representatives'][$j] >= $i + 1) {
                    $style = " style='background-color: var(--color-success-bg);'";
                } elseif (abs($weight - $info['min_weight']) < 1e-12) {
                    $style = " style='background-color: var(--color-warning-bg);'";
                }
                $html .= "<td$style>" . number_format($weight, 2) . "</td>";
            }
            $html .= "</tr>";
        }
        $html .= "</tbody></table></div>";

        if ($info['ties']) {
            $atCutoffNames = array_map(fn($i) => htmlspecialchars($instance->partyNames[$i]), $info['at_cutoff']);
            $receivingNames = array_map(fn($i) => htmlspecialchars($instance->partyNames[$i]), $info['at_cutoff_receiving_seats']);
            
            $html .= "<p>There were ties. The possible apportionments can be obtained by giving out seats as in the table above, except changing the seat allocation among the following tied options: " . implode(", ", $atCutoffNames) . ". Currently, " . implode(", ", $receivingNames) . " received an extra seat.</p>";
        }

        return $html;
    }

    private static function getMethodName(string $method): string
    {
        $methods = [
            'dhondt' => "D'Hondt Method / Jefferson's Method",
            'jefferson' => "D'Hondt Method / Jefferson's Method",
            'greatestdivisors' => "D'Hondt Method / Jefferson's Method",
            'saintelague' => "Sainte-Laguë's Method / Webster's Method",
            'webster' => "Sainte-Laguë's Method / Webster's Method",
            'majorfractions' => "Sainte-Laguë's Method / Webster's Method",
            'huntington' => "Huntington-Hill Method",
            'hill' => "Huntington-Hill Method",
            'equalproportions' => "Huntington-Hill Method",
            'dean' => "Dean's Method",
            'harmonicmean' => "Dean's Method",
            'adams' => "Adams' Method",
            'smallestdivisor' => "Adams' Method",
            'modified_saintelague' => "Modified Sainte-Laguë Method"
        ];
        return $methods[$method] ?? ucfirst($method);
    }
}