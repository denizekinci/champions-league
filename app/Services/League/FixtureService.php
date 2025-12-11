<?php

namespace App\Services\League;

use App\Models\Game;
use App\Models\Team;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Application service responsible for generating and querying league fixtures.
 *
 * The core idea:
 * - FIXTURE_TEMPLATE encodes the round-robin structure using slot indices.
 * - We only randomize "which team goes into which slot", not the schedule itself.
 *   This keeps the calendar deterministic while still producing random matchups.
 */
final class FixtureService
{
    private const TEAM_COUNT = 4;
    private const WEEKS = 6;

    /**
     * Slot-based fixture template (0-based slot indices).
     *
     * Shape:
     *  week => [
     *      [homeSlotIndex, awaySlotIndex],
     *      ...
     *  ]
     *
     * This template represents a fixed double round-robin schedule for 4 teams.
     */
    private const FIXTURE_TEMPLATE = [
        1 => [[0, 3], [1, 2]],
        2 => [[2, 0], [3, 1]],
        3 => [[0, 1], [2, 3]],
        4 => [[2, 1], [3, 0]],
        5 => [[0, 2], [1, 3]],
        6 => [[1, 0], [3, 2]],
    ];

    public function hasFixtures(): bool
    {
        return Game::query()->exists();
    }

    /**
     * Returns all fixtures grouped by week, eager-loading team relations.
     *
     * @return Collection<int, Collection<int, Game>>
     */
    public function getFixturesByWeek(): Collection
    {
        return Game::query()
            ->with(['homeTeam', 'awayTeam'])
            ->orderBy('week')
            ->get()
            ->groupBy('week');
    }

    /**
     * Public API: regenerates the entire fixture using a random slot assignment.
     *
     * Important: this is a destructive operation – it wipes all existing Game rows
     * before seeding the new schedule.
     */
    public function generateRandomFixtures(): void
    {
        DB::transaction(function (): void {
            Game::query()->truncate();

            $teams       = $this->loadTeams();
            $slotTeamIds = $this->assignTeamsToSlots($teams);

            $this->generateGamesFromTemplate($slotTeamIds);
        });
    }

    /**
     * Loads teams ordered by descending power and enforces the expected team count.
     *
     * @return Collection<int, Team>
     */
    private function loadTeams(): Collection
    {
        $teams = Team::query()
            ->orderByDesc('power')
            ->get(['id', 'power']);

        $count = $teams->count();

        if ($count !== self::TEAM_COUNT) {
            throw new RuntimeException(
                sprintf(
                    'FixtureService requires exactly %d teams, got %d.',
                    self::TEAM_COUNT,
                    $count
                )
            );
        }

        // Re-index from 0..N-1 for predictable iteration
        return $teams->values();
    }

    /**
     * Randomly assigns each team to a slot used by the template.
     *
     * @param  Collection<int, Team> $seededTeams
     * @return array<int, int>       slotIndex => teamId
     */
    private function assignTeamsToSlots(Collection $seededTeams): array
    {
        $availableSlots = range(0, self::TEAM_COUNT - 1);
        shuffle($availableSlots);

        $slotTeamIds = [];

        foreach ($seededTeams as $index => $team) {
            $slot = $availableSlots[$index];
            $slotTeamIds[$slot] = $team->id;
        }

        ksort($slotTeamIds); // make the mapping deterministic for debugging

        return $slotTeamIds;
    }

    /**
     * Materializes Game rows based on the static slot template and slot->team mapping.
     *
     * @param array<int, int> $slotTeamIds
     */
    private function generateGamesFromTemplate(array $slotTeamIds): void
    {
        foreach (self::FIXTURE_TEMPLATE as $week => $matches) {
            foreach ($matches as [$homeSlot, $awaySlot]) {
                $homeTeamId = $slotTeamIds[$homeSlot] ?? null;
                $awayTeamId = $slotTeamIds[$awaySlot] ?? null;

                if ($homeTeamId === null || $awayTeamId === null) {
                    throw new RuntimeException(
                        sprintf(
                            'Invalid fixture template: no team mapped for slot(s) %d or %d in week %d.',
                            $homeSlot,
                            $awaySlot,
                            $week
                        )
                    );
                }

                Game::query()->create([
                    'week'         => $week,
                    'home_team_id' => $homeTeamId,
                    'away_team_id' => $awayTeamId,
                    'home_goals'   => null,
                    'away_goals'   => null,
                    'is_played'    => false,
                ]);
            }
        }
    }
}
