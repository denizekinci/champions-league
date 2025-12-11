<?php

declare(strict_types=1);

namespace App\Services\League;

use App\Models\Game;
use App\Models\Team;
use Illuminate\Support\Collection;

/**
 * Domain service responsible for running Monte Carlo simulations to estimate
 * each team's probability of winning the league.
 *
 * High-level idea:
 * - Keep all played results as-is.
 * - For every unplayed game, simulate a realistic score using MatchSimulator.
 * - Compute final standings using the same tie-breakers as StandingsService.
 * - Repeat N times and count how often each team becomes champion.
 */
final class PredictionService
{
    /** Total number of weeks in the league schedule. */
    private const TOTAL_WEEKS = 6;

    /**
     * How many matchweeks before the end we start showing predictions.
     *
     * 3 => predictions visible from week 4 (last 3 weeks)
     * 2 => predictions visible from week 5 (last 2 weeks)
     */
    private const PREDICTION_WINDOW = 3;

    /** Default number of Monte Carlo runs. */
    private const DEFAULT_SIMULATION_COUNT = 300;

    public function __construct(
        protected MatchSimulator $matchSimulator
    ) {
    }

    /**
     * Returns championship probabilities for each team.
     *
     * Note: We only expose predictions in the last N matchweeks (PREDICTION_WINDOW)
     * to keep the output meaningful for the user (early weeks are too noisy).
     *
     * @return array<int, array{
     *     team_id:int,
     *     team_name:string,
     *     probability:float
     * }>
     */
    public function getChampionshipProbabilities(): array
    {
        if (! $this->isPredictionWindowOpen()) {
            return [];
        }

        $teams = $this->loadTeams();
        $games = $this->loadGamesWithTeams();

        $winsCount = $this->runSimulations(
            $teams,
            $games,
            self::DEFAULT_SIMULATION_COUNT
        );

        $result = $this->buildProbabilityTable(
            $teams,
            $winsCount,
            self::DEFAULT_SIMULATION_COUNT
        );

        // Sort by probability (descending).
        usort(
            $result,
            static fn (array $a, array $b): int => $b['probability'] <=> $a['probability']
        );

        return $result;
    }

    /**
     * Exposes the current prediction window size for callers (e.g. UI copy).
     */
    public function predictionWindow(): int
    {
        return self::PREDICTION_WINDOW;
    }

    /**
     * Determines whether prediction output should be shown based on current week.
     */
    private function isPredictionWindowOpen(): bool
    {
        $currentWeek = $this->getCurrentWeek();

        // Example with TOTAL_WEEKS = 6:
        // PREDICTION_WINDOW = 3 => visible from week 4 (last 3 weeks).
        // PREDICTION_WINDOW = 2 => visible from week 5 (last 2 weeks).
        return $currentWeek > (self::TOTAL_WEEKS - self::PREDICTION_WINDOW);
    }

    /**
     * Loads all teams ordered by primary key for deterministic iteration.
     *
     * @return Collection<int, Team>
     */
    private function loadTeams(): Collection
    {
        return Team::query()
            ->orderBy('id')
            ->get();
    }

    /**
     * Loads all games with their related teams eager-loaded.
     *
     * @return Collection<int, Game>
     */
    private function loadGamesWithTeams(): Collection
    {
        return Game::query()
            ->with(['homeTeam', 'awayTeam'])
            ->get();
    }

    /**
     * Runs Monte Carlo simulations and returns how many times each team wins.
     *
     * @param Collection<int, Team> $teams
     * @param Collection<int, Game> $games
     * @return array<int, int> teamId => winsAsChampion
     */
    private function runSimulations(Collection $teams, Collection $games, int $simulationCount): array
    {
        $teamIds   = $teams->pluck('id')->all();
        $winsCount = array_fill_keys($teamIds, 0);

        for ($i = 0; $i < $simulationCount; $i++) {
            $stats = $this->initializeStats($teams);
            $this->simulateSeasonIntoStats($games, $stats);
            $this->recalculateDerivedMetrics($stats);

            $championId = $this->resolveChampionTeamId($stats);

            if ($championId !== null) {
                $winsCount[$championId]++;
            }
        }

        return $winsCount;
    }

    /**
     * Returns an initialized stats array with zeroed metrics for each team.
     *
     * @param Collection<int, Team> $teams
     * @return array<int, array<string, int|string>>
     */
    private function initializeStats(Collection $teams): array
    {
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
     * Applies all games (played and simulated) into the stats array.
     *
     * @param Collection<int, Game>                  $games
     * @param array<int, array<string, int|string>> $stats
     */
    private function simulateSeasonIntoStats(Collection $games, array &$stats): void
    {
        foreach ($games as $game) {
            // Use actual recorded result if the game has been played.
            if ($game->is_played) {
                $homeGoals = (int) $game->home_goals;
                $awayGoals = (int) $game->away_goals;
            } else {
                // Otherwise, simulate a plausible scoreline based on team strengths.
                $result = $this->matchSimulator->simulateScore(
                    $game->homeTeam->power,
                    $game->awayTeam->power
                );

                $homeGoals = $result['home_goals'];
                $awayGoals = $result['away_goals'];
            }

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
     * Recomputes derived metrics like goal difference.
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
     * Applies the same tie-breaker rules as StandingsService and
     * returns the champion team id for a given stats snapshot.
     *
     * @param  array<int, array<string, int|string>> $stats
     * @return int|null
     */
    private function resolveChampionTeamId(array $stats): ?int
    {
        $rows = array_values($stats);

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

            // 4) Alphabetical team name as deterministic fallback
            return strcmp((string) $a['team_name'], (string) $b['team_name']);
        });

        return $rows[0]['team_id'] ?? null;
    }

    /**
     * Builds the response structure (probabilities) from raw win counts.
     *
     * @param Collection<int, Team> $teams
     * @param array<int, int>       $winsCount
     * @return array<int, array{
     *     team_id:int,
     *     team_name:string,
     *     probability:float
     * }>
     */
    private function buildProbabilityTable(
        Collection $teams,
        array $winsCount,
        int $simulationCount
    ): array {
        $result = [];

        foreach ($teams as $team) {
            $count = $winsCount[$team->id] ?? 0;

            // Cast to float explicitly; also guards against division by zero.
            $probability = 0.0;

            if ($simulationCount > 0) {
                $ratio       = (float) $count / (float) $simulationCount;
                $probability = (float) round($ratio * 100.0, 1);
            }

            $result[] = [
                'team_id'     => $team->id,
                'team_name'   => $team->name,
                'probability' => $probability,
            ];
        }

        return $result;
    }

    /**
     * Determines "current" week: first unplayed week, capped by TOTAL_WEEKS.
     */
    protected function getCurrentWeek(): int
    {
        $maxPlayedWeek = Game::query()
            ->where('is_played', true)
            ->max('week');

        if (! $maxPlayedWeek) {
            return 1;
        }

        $next = (int) $maxPlayedWeek + 1;

        return $next > self::TOTAL_WEEKS ? self::TOTAL_WEEKS : $next;
    }
}
