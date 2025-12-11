<?php

declare(strict_types=1);

use App\Http\Controllers\SimulationController;
use App\Http\Controllers\TournamentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
| No authentication in this case study.
| App opens directly into the tournament flow.
*/

Route::get('/', function () {
    return redirect()->route('tournament.teams');
})->name('home');

Route::prefix('tournament')
    ->name('tournament.')
    ->group(function () {
        // Team & fixture management
        Route::get('/teams', [TournamentController::class, 'teams'])
            ->name('teams');

        Route::post('/fixtures/generate', [TournamentController::class, 'generateFixtures'])
            ->name('fixtures.generate');

        Route::post('/fixtures/clear', [TournamentController::class, 'clearFixtures'])
            ->name('fixtures.clear');

        // Simulation dashboard & actions
        Route::get('/simulation', [SimulationController::class, 'index'])
            ->name('simulation');

        Route::post('/simulation/play-next-week', [SimulationController::class, 'playNextWeek'])
            ->name('play-next-week');

        Route::post('/simulation/play-all', [SimulationController::class, 'playAll'])
            ->name('play-all');

        Route::post('/simulation/reset', [SimulationController::class, 'reset'])
            ->name('reset');

        Route::patch('/simulation/games/{game}', [SimulationController::class, 'updateGameScore'])
            ->name('games.update');
    });

require __DIR__ . '/settings.php';
