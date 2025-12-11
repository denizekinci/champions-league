<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\Team;
use App\Services\League\FixtureService;

it('generates a balanced double round-robin schedule', function (): void {
    // Seed a simple 4-team league with deterministic names.
    Team::insert([
        ['name' => 'Arsenal',         'power' => 80, 'created_at' => now(), 'updated_at' => now()],
        ['name' => 'Chelsea',         'power' => 75, 'created_at' => now(), 'updated_at' => now()],
        ['name' => 'Liverpool',       'power' => 70, 'created_at' => now(), 'updated_at' => now()],
        ['name' => 'Manchester City', 'power' => 90, 'created_at' => now(), 'updated_at' => now()],
    ]);

    /** @var FixtureService $fixtureService */
    $fixtureService = app(FixtureService::class);

    // Act: build the full season schedule.
    $fixtureService->generateRandomFixtures();

    $games = Game::with(['homeTeam', 'awayTeam'])->get();

    // 4 teams in a double round-robin → 12 unique fixtures (each pair home & away).
    expect($games)->toHaveCount(12);

    $teams = Team::all();

    foreach ($teams as $team) {
        // Each team must play every other team twice (home & away) → 6 games.
        $played = $games->filter(
            fn (Game $game): bool =>
                $game->home_team_id === $team->id || $game->away_team_id === $team->id
        );

        expect($played)->toHaveCount(6);
    }

    // Sanity-check the temporal layout: weeks must be within the expected range [1..6].
    $weeks = $games->pluck('week')->unique()->all();

    expect(min($weeks))->toBeGreaterThanOrEqual(1)
        ->and(max($weeks))->toBeLessThanOrEqual(6);
});
