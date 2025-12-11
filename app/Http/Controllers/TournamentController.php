<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Team;
use App\Services\League\FixtureService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * HTTP controller responsible for tournament setup:
 * - listing teams
 * - exposing a read-only fixture preview
 * - generating / clearing fixtures.
 *
 * All league rules and fixture generation logic live in FixtureService.
 */
final class TournamentController extends Controller
{
    public function __construct(
        protected FixtureService $fixtureService,
    ) {
    }

    /**
     * Teams & fixtures management screen.
     */
    public function teams(): Response
    {
        $teams = Team::query()
            ->orderBy('name')
            ->get();

        $games = Game::query()
            ->with(['homeTeam', 'awayTeam'])
            ->orderBy('week')
            ->get();

        $hasFixtures = $games->isNotEmpty();

        // Group fixtures by week and shape a front-end friendly payload.
        $fixturesByWeek = $games
            ->groupBy('week')
            ->map(static function ($gamesForWeek) {
                return $gamesForWeek->map(static function (Game $game): array {
                    return [
                        'id'         => $game->id,
                        'week'       => $game->week,
                        'home_team'  => $game->homeTeam->name,
                        'away_team'  => $game->awayTeam->name,
                        'home_goals' => $game->home_goals,
                        'away_goals' => $game->away_goals,
                        'is_played'  => (bool) $game->is_played,
                    ];
                })->values();
            })
            ->toArray();

        $weeks = array_keys($fixturesByWeek);

        return Inertia::render('Tournament/Teams', [
            'teams'          => $teams,
            'fixturesByWeek' => $fixturesByWeek,
            'weeks'          => $weeks,
            'hasFixtures'    => $hasFixtures,
        ]);
    }

    /**
     * Generates a new random fixture schedule.
     *
     * Existing fixtures are cleared and rebuilt at the service layer.
     */
    public function generateFixtures(): RedirectResponse
    {
        $this->fixtureService->generateRandomFixtures();

        return redirect()
            ->route('tournament.teams')
            ->with('success', 'Fixtures generated successfully.');
    }

    /**
     * Removes all fixtures while keeping the team roster intact.
     */
    public function clearFixtures(): RedirectResponse
    {
        Game::query()->delete();

        return back()->with('success', 'Fixtures cleared successfully.');
    }
}
