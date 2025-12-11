<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\Team;
use App\Services\League\StandingsService;

it('computes standings using recorded game results', function (): void {
    // Arrange: create two teams and a single recorded result (Arsenal 2–1 Chelsea).
    $teamA = Team::create(['name' => 'Arsenal', 'power' => 80]);
    $teamB = Team::create(['name' => 'Chelsea', 'power' => 75]);

    Game::create([
        'week'         => 1,
        'home_team_id' => $teamA->id,
        'away_team_id' => $teamB->id,
        'home_goals'   => 2,
        'away_goals'   => 1,
        'is_played'    => true,
    ]);

    // Act: let the domain service build the league table.
    /** @var StandingsService $service */
    $service   = app(StandingsService::class);
    $standings = $service->getStandings();

    // Assert: there should be exactly two rows in the table.
    expect($standings)->toHaveCount(2);

    $arsenalRow = collect($standings)->firstWhere('team_id', $teamA->id);
    $chelseaRow = collect($standings)->firstWhere('team_id', $teamB->id);

    // Winner row: Arsenal gets 3 points and correct aggregates.
    expect($arsenalRow)
        ->played->toBe(1)
        ->wins->toBe(1)
        ->draws->toBe(0)
        ->losses->toBe(0)
        ->goals_for->toBe(2)
        ->goals_against->toBe(1)
        ->goal_diff->toBe(1)
        ->points->toBe(3)

        // Loser row: Chelsea gets 0 points and mirrored aggregates.
        ->and($chelseaRow)
        ->played->toBe(1)
        ->wins->toBe(0)
        ->draws->toBe(0)
        ->losses->toBe(1)
        ->goals_for->toBe(1)
        ->goals_against->toBe(2)
        ->goal_diff->toBe(-1)
        ->points->toBe(0)

        // Ordering: winner must be in the first row.
        ->and($standings[0]['team_id'])->toBe($teamA->id)
        ->and($standings[1]['team_id'])->toBe($teamB->id);
});
