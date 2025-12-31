<?php

declare(strict_types=1);

namespace Apportionment;

class Apportionment
{
    public static function compute(string $method, Instance $instance, bool $buildExplanation = false): array
    {
        $method = strtolower($method);
        if (in_array($method, ["lrm", "hamilton", "largest_remainder"])) {
            return self::largestRemainder($instance, $buildExplanation);
        } elseif (in_array($method, ["dhondt", "jefferson", "saintelague", "webster", "modified_saintelague", "huntington", "hill", "adams", "dean", "smallestdivisor", "harmonicmean", "equalproportions", "majorfractions", "greatestdivisors"])) {
            return self::divisor($instance, $method, $buildExplanation);
        } elseif ($method === "quota") {
            return self::quota($instance, $buildExplanation);
        } else {
            throw new \InvalidArgumentException("Apportionment method $method not known");
        }
    }

    private static function largestRemainder(Instance $instance, bool $buildExplanation): array
    {
        $votes = $instance->votes;
        $seats = $instance->seats;
        $totalVotes = (float)array_sum($votes);
        
        if ($totalVotes == 0) {
            return [
                'representatives' => array_fill(0, count($votes), 0),
                'ties' => false,
                'detailed_info' => []
            ];
        }

        $q = $totalVotes / $seats;
        $quotas = array_map(fn($v) => $v * $seats / $totalVotes, $votes);
        $lowerQuotas = array_map(fn($v) => (int)floor($v / $q), $votes);
        $representatives = $lowerQuotas;
        $remainders = array_map(fn($i) => $quotas[$i] - $representatives[$i], array_keys($votes));

        $currentSeats = (int)array_sum($representatives);
        $ties = false;
        $atCutoff = [];
        $numAtCutoffReceivingSeat = 0;

        if ($currentSeats < $seats) {
            $sortedRemainders = $remainders;
            rsort($sortedRemainders);
            $cutoff = $sortedRemainders[$seats - $currentSeats - 1];

            foreach ($votes as $i => $v) {
                $rem = $remainders[$i];
                if (array_sum($representatives) == $seats && $rem >= $cutoff - 0.0000001 && $rem <= $cutoff + 0.0000001) {
                    $ties = true;
                    $atCutoff[] = $i;
                } elseif (array_sum($representatives) < $seats && $rem > $cutoff + 0.0000001) {
                    $representatives[$i] += 1;
                } elseif (array_sum($representatives) < $seats && $rem >= $cutoff - 0.0000001 && $rem <= $cutoff + 0.0000001) {
                    $representatives[$i] += 1;
                    $numAtCutoffReceivingSeat += 1;
                    $atCutoff[] = $i;
                }
            }
        }

        $info = [];
        if ($buildExplanation) {
            $info = [
                'method' => 'hamilton',
                'quotas' => $quotas,
                'lower_quotas' => $lowerQuotas,
                'remainders' => $remainders,
                'at_cutoff' => $atCutoff,
                'num_at_cutoff_receiving_seat' => $numAtCutoffReceivingSeat,
                'ties' => $ties,
                'representatives' => $representatives
            ];
        }

        return [
            'representatives' => $representatives,
            'ties' => $ties,
            'detailed_info' => $info
        ];
    }

    private static function divisor(Instance $instance, string $method, bool $buildExplanation): array
    {
        $votes = $instance->votes;
        $seats = $instance->seats;
        $numParties = count($votes);
        $representatives = array_fill(0, $numParties, 0);
        $fewerSeatsThanParties = false;
        $divisors = [];

        $seatsRange = range(0, $seats - 1);

        if (in_array($method, ["dhondt", "jefferson", "greatestdivisors"])) {
            $divisors = array_map(fn($i) => $i + 1, $seatsRange);
        } elseif (in_array($method, ["saintelague", "webster", "majorfractions"])) {
            $divisors = array_map(fn($i) => 2 * $i + 1, $seatsRange);
        } elseif ($method === "modified_saintelague") {
            $divisors = array_map(fn($i) => $i == 0 ? 1.4 : 2 * $i + 1, $seatsRange);
        } elseif (in_array($method, ["huntington", "hill", "equalproportions", "adams", "smallestdivisor", "dean", "harmonicmean"])) {
            if ($seats < $numParties) {
                $res = self::divZeroFewerSeatsThanParties($votes, $seats);
                $representatives = $res['representatives'];
                $fewerSeatsThanParties = true;
            } else {
                foreach ($votes as $i => $v) {
                    if ($v > 0) $representatives[$i] = 1;
                }
                if (in_array($method, ["huntington", "hill", "equalproportions"])) {
                    $divisors = array_map(fn($i) => sqrt(($i + 1) * ($i + 2)), $seatsRange);
                } elseif (in_array($method, ["adams", "smallestdivisor"])) {
                    $divisors = array_map(fn($i) => $i + 1, $seatsRange);
                } elseif (in_array($method, ["dean", "harmonicmean"])) {
                    $divisors = array_map(fn($i) => 2 * ($i + 1) * ($i + 2) / (2 * ($i + 1) + 1), $seatsRange);
                }
            }
        }

        $weights = [];
        $flatWeights = [];
        if (!$fewerSeatsThanParties && $seats > array_sum($representatives)) {
            foreach ($votes as $i => $v) {
                $w_row = array_map(fn($d) => $v / $d, $divisors);
                $weights[$i] = $w_row;
                foreach ($w_row as $w) {
                    $flatWeights[] = $w;
                }
            }
            rsort($flatWeights);
            $needed = $seats - (int)array_sum($representatives);
            $minWeight = $flatWeights[$needed - 1];
            $lowerBoundDivisor = $minWeight;
            if ($needed < count($flatWeights)) {
                $lowerBoundDivisor = $flatWeights[$needed];
            }

            foreach ($votes as $i => $v) {
                foreach ($weights[$i] as $w) {
                    if ($w > $minWeight + 1e-12) {
                        $representatives[$i] += 1;
                    }
                }
            }

            $ties = false;
            $atCutoff = [];
            $atCutoffReceivingSeats = [];
            $numAtCutoffReceivingSeat = 0;
            $currentSum = (int)array_sum($representatives);

            foreach ($votes as $i => $v) {
                $hasMinWeight = false;
                foreach ($weights[$i] as $w) {
                    if (abs($w - $minWeight) < 1e-12) {
                        $hasMinWeight = true;
                        break;
                    }
                }
                
                if ($currentSum == $seats && $hasMinWeight) {
                    $ties = true;
                    $atCutoff[] = $i;
                }
                if ($currentSum < $seats && $hasMinWeight) {
                    $representatives[$i] += 1;
                    $numAtCutoffReceivingSeat += 1;
                    $atCutoffReceivingSeats[] = $i;
                    $atCutoff[] = $i;
                    $currentSum++; // Need to keep track since we update representatives in the loop
                }
            }
        } else {
            $ties = false;
            $minWeight = 0;
            $lowerBoundDivisor = 0;
            $atCutoff = [];
            $atCutoffReceivingSeats = [];
            $numAtCutoffReceivingSeat = 0;
        }

        $info = [];
        if ($buildExplanation) {
            $info = [
                'method' => $method,
                'fewer_seats_than_parties' => $fewerSeatsThanParties,
                'min_weight' => $minWeight,
                'lower_bound_divisor' => $lowerBoundDivisor,
                'representatives' => $representatives,
                'divisors' => $divisors,
                'weights' => $weights,
                'ties' => $ties,
                'at_cutoff' => $atCutoff,
                'at_cutoff_receiving_seats' => $atCutoffReceivingSeats,
                'num_at_cutoff_receiving_seat' => $numAtCutoffReceivingSeat
            ];
        }

        return [
            'representatives' => $representatives,
            'ties' => $ties,
            'detailed_info' => $info
        ];
    }

    private static function divZeroFewerSeatsThanParties(array $votes, int $seats): array
    {
        $numParties = count($votes);
        $representatives = array_fill(0, $numParties, 0);
        $ties = false;
        
        $sortedVotes = $votes;
        rsort($sortedVotes);
        $minCount = $sortedVotes[$seats - 1];
        
        $currentSum = 0;
        foreach ($votes as $i => $v) {
            if ($currentSum < $seats && $v >= $minCount) {
                $representatives[$i] = 1;
                $currentSum++;
            } else if ($currentSum == $seats && $v >= $minCount) {
                $ties = true;
            }
        }
        
        return [
            'representatives' => $representatives,
            'ties' => $ties
        ];
    }

    private static function quota(Instance $instance, bool $buildExplanation): array
    {
        $votes = $instance->votes;
        $seats = $instance->seats;
        $numParties = count($votes);
        $representatives = array_fill(0, $numParties, 0);
        $totalVotes = array_sum($votes);

        if ($totalVotes == 0) {
            return [
                'representatives' => array_fill(0, $numParties, 0),
                'ties' => false,
                'detailed_info' => []
            ];
        }

        while (array_sum($representatives) < $seats) {
            $quotas = [];
            foreach ($votes as $i => $v) {
                $quotas[$i] = $v / ($representatives[$i] + 1);
                
                // check if upper quota is violated
                $upperQuota = ceil($v * (array_sum($representatives) + 1) / $totalVotes);
                if ($representatives[$i] >= $upperQuota) {
                    $quotas[$i] = -1; // Use -1 as "effectively zero" to avoid max issues
                }
            }
            
            $maxQuota = max($quotas);
            if ($maxQuota == -1) {
                // This shouldn't happen with the Quota method's properties, 
                // but as a fallback, take the highest quota ignoring the constraint
                foreach ($votes as $i => $v) {
                    $quotas[$i] = $v / ($representatives[$i] + 1);
                }
                $maxQuota = max($quotas);
            }

            foreach ($votes as $i => $v) {
                if ($quotas[$i] == $maxQuota) {
                    $representatives[$i] += 1;
                    break;
                }
            }
        }

        $info = [];
        if ($buildExplanation) {
            $info = [
                'method' => 'quota',
                'representatives' => $representatives,
                'total_votes' => $totalVotes
            ];
        }

        return [
            'representatives' => $representatives,
            'ties' => false,
            'detailed_info' => $info
        ];
    }
}
