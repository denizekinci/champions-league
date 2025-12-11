<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Game;
use App\Services\League\FixtureService;
use App\Services\League\PredictionService;
use App\Services\League\SimulationService;
use App\Services\League\StandingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Thin HTTP controller that orchestrates the simulation-related use cases
 * and prepares data for the Inertia front-end.
 *
 * All domain logic lives in the underlying services.
 */
final class SimulationController extends Controller
{
    private const TOTAL_WEEKS = 6;

    public function __construct(
        protected FixtureService $fixtureService,
        protected StandingsService $standingsService,
        protected SimulationService $simulationService,
        protected PredictionService $predictionService,
    ) {
    }

    /**
     * Main simulation dashboard:
     * - current standings
     * - fixtures by week
     * - current week & total weeks
     * - championship probabilities.
     */
    public function index(): Response
    {
        $standings   = $this->standingsService->getStandings();
        $fixtures    = $this->fixtureService->getFixturesByWeek();
        $currentWeek = $this->simulationService->getCurrentWeek();

        $weeks = $fixtures->keys()->values()->all();

        $fixturesForFrontend = $fixtures->map(
            static function ($games) {
                return $games->map(static function (Game $game): array {
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
            }
        );

        $predictions = $this->predictionService->getChampionshipProbabilities();

        return Inertia::render('Tournament/Simulation', [
            'standings'      => $standings,
            'weeks'          => $weeks,
            'fixturesByWeek' => $fixturesForFrontend,
            'currentWeek'    => $currentWeek,
            'totalWeeks'     => self::TOTAL_WEEKS,
            'predictions'    => $predictions,
        ]);
    }

    public function playNextWeek(): RedirectResponse
    {
        $this->simulationService->playNextWeek();

        return redirect()->route('tournament.simulation');
    }

    public function playAll(): RedirectResponse
    {
        $this->simulationService->playAllRemaining();

        return redirect()->route('tournament.simulation');
    }

    public function reset(): RedirectResponse
    {
        $this->simulationService->resetAll();

        return redirect()->route('tournament.simulation');
    }

    /**
     * Allows manually overriding a single game's score from the UI.
     */
    public function updateGameScore(Request $request, Game $game): RedirectResponse
    {
        $data = $request->validate([
            'home_goals' => ['required', 'integer', 'min:0'],
            'away_goals' => ['required', 'integer', 'min:0'],
        ]);

        $game->update([
            'home_goals' => $data['home_goals'],
            'away_goals' => $data['away_goals'],
            'is_played'  => true,
        ]);

        return redirect()->route('tournament.simulation');
    }
}
