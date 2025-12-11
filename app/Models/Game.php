<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents a single league fixture between two teams.
 *
 * Domain notes:
 * - Only one "official" result is stored per game (no replays/legs here).
 * - Derived helpers (winner/loser/draw) are kept on the model to keep
 *   higher-level services/controllers simpler and more expressive.
 */
class Game extends Model
{
    protected $fillable = [
        'week',
        'home_team_id',
        'away_team_id',
        'home_goals',
        'away_goals',
        'is_played',
    ];

    protected $casts = [
        'week'         => 'int',
        'home_team_id' => 'int',
        'away_team_id' => 'int',
        'home_goals'   => 'int',
        'away_goals'   => 'int',
        'is_played'    => 'bool',
    ];

    /**
     * Home side of the fixture.
     */
    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    /**
     * Away side of the fixture.
     */
    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    /**
     * Indicates whether the game has a final, recorded result.
     */
    public function isPlayed(): bool
    {
        return (bool) $this->is_played
            && $this->home_goals !== null
            && $this->away_goals !== null;
    }

    /**
     * True if the game was played and ended in a draw.
     */
    public function isDraw(): bool
    {
        if (!$this->isPlayed()) {
            return false;
        }

        return $this->home_goals === $this->away_goals;
    }

    /**
     * Returns the winning team, or null if draw / not played.
     */
    public function winner(): ?Team
    {
        if (!$this->isPlayed() || $this->isDraw()) {
            return null;
        }

        return $this->home_goals > $this->away_goals
            ? $this->homeTeam
            : $this->awayTeam;
    }

    /**
     * Returns the losing team, or null if draw / not played.
     */
    public function loser(): ?Team
    {
        if (!$this->isPlayed() || $this->isDraw()) {
            return null;
        }

        return $this->home_goals > $this->away_goals
            ? $this->awayTeam
            : $this->homeTeam;
    }
}
