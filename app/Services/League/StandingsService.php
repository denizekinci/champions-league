<?php

declare(strict_types=1);

namespace App\Services\League;

use App\Models\Game;
use App\Models\Team;

/**
 * Domain service responsible for computing league standings
 * from the persisted Game results.
 *
 * Tie-breakers (in order):
 *  1. Points
 *  2. Goal difference
 *  3. Goals scored
 *  4. Team name (alphabetical, as a stable fallback)
 */
final class StandingsService
{
    /**
     * Computes the current standings table from all played games.
     *
     * @return array<int, array{
     *     team_id:int,
     *     team_name:string,
     *     played:int,
     *     wins:int,
     *     draws:int,
     *     losses:int,
     *     goals_for:int,
     *     goals_against:int,
     *     goal_diff:int,
     *     points:int,
     *     position:int
     * }>
     */
    public function getStandings(): array
    {
        $stats = $this->initializeTeamStats();
        $this->accumulateGameStats($stats);
        $this->recalculateDerivedMetrics($stats);

        $rows = array_values($stats);

        $this->sortStandings($rows);
        $this->assignPositions($rows);

        return $rows;
    }

    /**
     * Initializes a zeroed stats row for every team.
     *
     * @return array<int, array<string, int|string>>
     */
    private function initializeTeamStats(): array
    {
        $teams = Team::query()
            ->orderBy('name')
            ->get();

        $stats = [];

        foreach ($teams as $team) {
            $stats[$team->id] = [
                'team_id'       => $team->id,
                'team_name'     => $team->name,
                'played'        => 0,
                'wins'          => 0,
                'draws'         => 0,
                'losses'        => 0,
                'goals_for'     => 0,
                'goals_against' => 0,
                'goal_diff'     => 0,
                'points'        => 0,
            ];
        }

        return $stats;
    }

    /**
     * Folds all played games into the stats array (in-place).
     *
     * @param array<int, array<string, int|string>> $stats
     */
    private function accumulateGameStats(array &$stats): void
    {
        $games = Game::query()
            ->where('is_played', true)
            ->get();

        foreach ($games as $game) {
            if ($game->home_goals === null || $game->away_goals === null) {
                // Defensive: ignore partially recorded games.
                continue;
            }

            $homeGoals = (int) $game->home_goals;
            $awayGoals = (int) $game->away_goals;

            // Use references for concise in-place mutation of the stats rows.
            $home =& $stats[$game->home_team_id];
            $away =& $stats[$game->away_team_id];

            $home['played']++;
            $away['played']++;

            $home['goals_for']     += $homeGoals;
            $home['goals_against'] += $awayGoals;

            $away['goals_for']     += $awayGoals;
            $away['goals_against'] += $homeGoals;

            if ($homeGoals > $awayGoals) {
                $home['wins']++;
                $away['losses']++;
                $home['points'] += 3;
            } elseif ($homeGoals < $awayGoals) {
                $away['wins']++;
                $home['losses']++;
                $away['points'] += 3;
            } else {
                $home['draws']++;
                $away['draws']++;
                $home['points']++;
                $away['points']++;
            }

            unset($home, $away);
        }
    }

    /**
     * Recomputes derived fields such as goal difference.
     *
     * @param array<int, array<string, int|string>> $stats
     */
    private function recalculateDerivedMetrics(array &$stats): void
    {
        foreach ($stats as &$row) {
            $row['goal_diff'] = (int) $row['goals_for'] - (int) $row['goals_against'];
        }
        unset($row);
    }

    /**
     * Sorts standings in-place according to the tie-breaker rules.
     *
     * @param array<int, array<string, int|string>> $rows
     */
    private function sortStandings(array &$rows): void
    {
        usort($rows, static function (array $a, array $b): int {
            // 1) Points
            if ($a['points'] !== $b['points']) {
                return $b['points'] <=> $a['points'];
            }

            // 2) Goal difference
            if ($a['goal_diff'] !== $b['goal_diff']) {
                return $b['goal_diff'] <=> $a['goal_diff'];
            }

            // 3) Goals scored
            if ($a['goals_for'] !== $b['goals_for']) {
                return $b['goals_for'] <=> $a['goals_for'];
            }

            // 4) Alphabetical team name (stable fallback)
            return strcmp((string) $a['team_name'], (string) $b['team_name']);
        });
    }

    /**
     * Assigns 1-based league positions after sorting.
     *
     * @param array<int, array<string, int|string>> $rows
     */
    private function assignPositions(array &$rows): void
    {
        $position = 1;

        foreach ($rows as &$row) {
            $row['position'] = $position++;
        }

        unset($row);
    }
}
