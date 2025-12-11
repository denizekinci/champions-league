<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a team participating in the league.
 *
 * Domain notes:
 * - "power" is a simplified strength metric used by simulation services
 *   (e.g. MatchSimulator) to derive expected goals.
 */
class Team extends Model
{
    protected $fillable = [
        'name',
        'power',
    ];

    protected $casts = [
        'power' => 'int',
    ];

    /**
     * Games where this team plays at home.
     */
    public function homeGames(): HasMany
    {
        return $this->hasMany(Game::class, 'home_team_id');
    }

    /**
     * Games where this team plays away.
     */
    public function awayGames(): HasMany
    {
        return $this->hasMany(Game::class, 'away_team_id');
    }

    /**
     * Convenience helper returning all games (home + away) for this team.
     *
     * This is intentionally a method (not a relationship) because it merges
     * two distinct relations into a single in-memory collection.
     *
     * @return EloquentCollection<int, Game>
     */
    public function games(): EloquentCollection
    {
        // If relations are already eager-loaded, this will not trigger extra queries.
        return $this->homeGames()->get()->merge(
            $this->awayGames()->get()
        );
    }
}
