<?php

declare(strict_types=1);

namespace App\Services\League;

/**
 * Domain service responsible for simulating football match scores
 * based on relative team strengths and a simple home-advantage model.
 *
 * Notes:
 * - Uses Poisson sampling, which is a common approach for modelling goal counts.
 * - Keeps all parameters configurable via class constants for now; can be
 *   externalized to config/env if needed later.
 */
final class MatchSimulator
{
    /** Home team power multiplier to model home advantage. */
    private const HOME_ADVANTAGE_MULTIPLIER = 1.10;

    /** Baseline expected goals before applying power differences. */
    private const BASE_HOME_EXPECTED_GOALS = 1.4;
    private const BASE_AWAY_EXPECTED_GOALS = 1.1;

    /** Hard caps to avoid unrealistic expected goal values. */
    private const MIN_HOME_EXPECTED = 0.2;
    private const MAX_HOME_EXPECTED = 3.5;

    private const MIN_AWAY_EXPECTED = 0.2;
    private const MAX_AWAY_EXPECTED = 3.0;

    /**
     * Simulates a realistic score given home/away team strengths.
     *
     * @param  int $homePower Absolute strength of home team (domain-specific scale).
     * @param  int $awayPower Absolute strength of away team (same scale as homePower).
     * @return array{home_goals:int, away_goals:int}
     */
    public function simulateScore(int $homePower, int $awayPower): array
    {
        // Apply a simple home-advantage factor to the rating
        $homeRating = $homePower * self::HOME_ADVANTAGE_MULTIPLIER;
        $awayRating = $awayPower;

        // Translate rating difference into an expected-goals delta
        $diff = ($homeRating - $awayRating) / 100.0;

        // Baseline expected goals, shifted by rating difference
        $homeExpected = self::BASE_HOME_EXPECTED_GOALS + $diff;
        $awayExpected = self::BASE_AWAY_EXPECTED_GOALS - $diff / 2;

        // Clamp expected goals to avoid extreme edge cases
        $homeExpected = $this->clamp($homeExpected, self::MIN_HOME_EXPECTED, self::MAX_HOME_EXPECTED);
        $awayExpected = $this->clamp($awayExpected, self::MIN_AWAY_EXPECTED, self::MAX_AWAY_EXPECTED);

        return [
            'home_goals' => $this->samplePoisson($homeExpected),
            'away_goals' => $this->samplePoisson($awayExpected),
        ];
    }

    /**
     * Poisson sampling using Knuth's algorithm.
     *
     * This models the number of events (goals) that occur in a fixed interval
     * with a given expected rate (lambda).
     */
    private function samplePoisson(float $lambda): int
    {
        $L = exp(-$lambda);
        $k = 0;
        $p = 1.0;

        do {
            $k++;
            $p *= lcg_value(); // uniform random in (0, 1)
        } while ($p > $L);

        return $k - 1;
    }

    /**
     * Clamps a numeric value to the given [min, max] range.
     */
    private function clamp(float $value, float $min, float $max): float
    {
        if ($value < $min) {
            return $min;
        }

        if ($value > $max) {
            return $max;
        }

        return $value;
    }
}
