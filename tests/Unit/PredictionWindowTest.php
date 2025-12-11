<?php

use App\Services\League\FixtureService;
use App\Services\League\PredictionService;
use App\Services\League\SimulationService;
use Database\Seeders\LeagueSeeder;

beforeEach(function () {
    // Seed the canonical 4-team league used by the app.
    $this->seed(LeagueSeeder::class);

    // Resolve domain services from the container, exactly as production does.
    $this->fixtureService    = app(FixtureService::class);
    $this->simulationService = app(SimulationService::class);
    $this->predictionService = app(PredictionService::class);

    // Generate the double round-robin schedule (6 matchweeks, 12 games).
    $this->fixtureService->generateRandomFixtures();
});

it('returns no predictions before entering the prediction window', function () {
    // Week 1: no matches have been played yet.
    $predictions = $this->predictionService->getChampionshipProbabilities();
    expect($predictions)->toBeEmpty();

    // After week 2 (currentWeek = 3) we are still outside the prediction window.
    $this->simulationService->playWeek(1);
    $this->simulationService->playWeek(2);

    $predictions = $this->predictionService->getChampionshipProbabilities();
    expect($predictions)->toBeEmpty();
});

it('returns predictions after entering the prediction window', function () {
    // Play weeks 1–3 → currentWeek = 4.
    // With TOTAL_WEEKS = 6 and PREDICTION_WINDOW = 3,
    // this is the first week when predictions become visible.
    $this->simulationService->playWeek(1);
    $this->simulationService->playWeek(2);
    $this->simulationService->playWeek(3);

    $predictions = $this->predictionService->getChampionshipProbabilities();

    // Now the prediction window must be open.
    expect($predictions)->not->toBeEmpty()
        ->and($predictions)->toHaveCount(4);

    // The league has 4 teams → we expect exactly 4 rows.

    // Payload contract / type safety.
    foreach ($predictions as $row) {
        expect($row['team_id'])->toBeInt()
            ->and($row['team_name'])->toBeString()
            ->and($row['probability'])->toBeFloat();
    }

    // Monte Carlo + rounding can introduce minor drift; allow a small tolerance.
    $total = array_sum(array_column($predictions, 'probability'));

    expect($total)
        ->toBeGreaterThanOrEqual(99.0)
        ->toBeLessThanOrEqual(101.0);
});
