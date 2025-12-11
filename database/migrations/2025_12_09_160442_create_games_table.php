<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stores all scheduled and played matches.
     * Each row represents exactly one fixture in the tournament calendar.
     *
     * The "week" attribute acts as the logical matchday grouping.
     */
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();

            // Matchday index. Small integer is more appropriate since tournaments have fixed length.
            $table->unsignedTinyInteger('week');

            // Self-referencing team relations.
            $table->foreignId('home_team_id')
                ->constrained('teams')
                ->cascadeOnDelete();

            $table->foreignId('away_team_id')
                ->constrained('teams')
                ->cascadeOnDelete();

            // Nullable goals: only set after the match is simulated or manually entered.
            $table->unsignedTinyInteger('home_goals')->nullable();
            $table->unsignedTinyInteger('away_goals')->nullable();

            // Indicates whether the match result is final.
            $table->boolean('is_played')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
