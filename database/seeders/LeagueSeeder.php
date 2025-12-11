<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Team;
use Illuminate\Database\Seeder;

/**
 * Seeds the league with an initial set of teams.
 *
 * Domain note:
 * - "power" reflects the base strength metric used by MatchSimulator.
 * - Seeding is idempotent via firstOrCreate, so running the seeder multiple times
 *   will not duplicate teams.
 */
class LeagueSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->defaultTeams() as $teamData) {
            Team::firstOrCreate(
                ['name' => $teamData['name']], // Unique domain key
                $teamData                       // Attributes to apply on creation
            );
        }
    }

    /**
     * Default team configuration for initial league setup.
     *
     * @return array<int, array{name:string, power:int}>
     */
    private function defaultTeams(): array
    {
        return [
            ['name' => 'Chelsea',         'power' => 60],
            ['name' => 'Arsenal',         'power' => 75],
            ['name' => 'Manchester City', 'power' => 90],
            ['name' => 'Liverpool',       'power' => 50],
        ];
    }
}
