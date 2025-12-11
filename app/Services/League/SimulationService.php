<?php

declare(strict_types=1);

namespace App\Services\League;

use App\Models\Game;

/**
 * Application service responsible for driving the league simulation:
 * - advancing the season week-by-week,
 * - auto-playing fixtures using MatchSimulator,
 * - resetting all results.
 */
final class SimulationService
{
    /** Total number of weeks in the schedule. */
    private const TOTAL_WEEKS = 6;

    public function __construct(
        protected MatchSimulator $matchSimulator,
    ) {
    }

    /**
     * Returns the "current" week:
     * - first week after the last fully played week,
     * - capped by TOTAL_WEEKS.
     */
    public function getCurrentWeek(): int
    {
        $maxPlayedWeek = Game::query()
            ->where('is_played', true)
            ->max('week');

        if (!$maxPlayedWeek) {
            return 1;
        }

        $next = (int) $maxPlayedWeek + 1;

        return $next > self::TOTAL_WEEKS ? self::TOTAL_WEEKS : $next;
    }

    /**
     * Simulates and finalizes all unplayed games for the given week.
     */
    public function playWeek(int $week): void
    {
        $games = Game::query()
            ->with(['homeTeam', 'awayTeam'])
            ->where('week', $week)
            ->where('is_played', false)
            ->get();

        foreach ($games as $game) {
            $result = $this->matchSimulator->simulateScore(
                $game->homeTeam->power,
                $game->awayTeam->power,
            );

            $game->update([
                'home_goals' => $result['home_goals'],
                'away_goals' => $result['away_goals'],
                'is_played'  => true,
            ]);
        }
    }

    /**
     * Advances the league by one matchweek (if there are any games left).
     */
    public function playNextWeek(): void
    {
        if (Game::query()->where('is_played', false)->doesntExist()) {
            return;
        }

        $week = $this->getCurrentWeek();

        $this->playWeek($week);
    }

    /**
     * Plays all remaining fixtures in the season.
     *
     * Note: playWeek() itself only affects unplayed games, so looping from 1..N
     * is safe and idempotent with respect to already-played weeks.
     */
    public function playAllRemaining(): void
    {
        for ($week = 1; $week <= self::TOTAL_WEEKS; $week++) {
            $this->playWeek($week);
        }
    }

    /**
     * Resets all games to an "unplayed" state while keeping the schedule intact.
     */
    public function resetAll(): void
    {
        Game::query()->update([
            'home_goals' => null,
            'away_goals' => null,
            'is_played'  => false,
        ]);
    }
}
