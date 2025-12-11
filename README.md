# Champions League Group Simulation

A small application built with **Laravel 12**, **PostgreSQL**, and **Vue 3 + Inertia**.

> 🎮 **Live Demo**  
> You can try the running version of the application here:  
>  **https://cl.denizekinci.dev/tournament/teams**

The app simulates a 4-team Champions League–style group:
- Double round-robin fixture generation
- Week-by-week match simulation
- Live standings table
- Monte-Carlo based championship probability predictions in the last weeks of the group

---

## Table of Contents

- [Domain Overview](#domain-overview)
- [Architecture](#architecture)
- [Tech Stack](#tech-stack)
- [Local Setup (Laravel Sail)](#local-setup-laravel-sail)
- [Running the App](#running-the-app)
- [Core Use Cases](#core-use-cases)
- [Domain Services](#domain-services)
- [Automated Tests](#automated-tests)
- [Notes & Trade-offs](#notes--trade-offs)

---

## Domain Overview

The application models a simple group stage with:

- **4 teams**
- **Double round-robin schedule** (each team plays every other team twice, home & away)
- **6 matchweeks** total
- Standard football rules:
    - Win = 3 points
    - Draw = 1 point
    - Loss = 0 points
    - Standings tiebreakers: points → goal difference → goals scored → team name

The UI exposes two main flows:

1. **Tournament Teams**
    - View the four teams
    - Adjust their relative strength via a `power` attribute (0–100)
    - Generate or clear fixtures

2. **Simulation Dashboard**
    - Play the next week or all remaining weeks
    - Inspect weekly fixtures and edit individual scores
    - See live standings
    - See championship probabilities in the last weeks of the group

---

## Architecture

The application follows a **thin controller, rich domain service** approach:

- Controllers handle:
    - HTTP routing
    - Input validation / authorization (where applicable)
    - Passing DTO-like data structures to the front-end

- Domain services encapsulate:
    - Fixture generation
    - Match simulation
    - Standings calculation
    - Probability estimation

This keeps the HTTP layer clean and makes the core logic easily testable in isolation.

---

## Tech Stack

**Backend**

- PHP 8.5 (via Laravel Sail runtime)
- Laravel 12
- PostgreSQL 18
- Pest for tests

**Frontend**

- Vue 3 (`<script setup>` + TypeScript)
- Inertia.js (SPA feel with server-side controllers)
- Tailwind CSS based styling
- `lucide-vue-next` for icons
- Small, focused Vue components:
    - `SimulationHeader`
    - `StandingsTable`
    - `WeeklyFixtures`
    - `PredictionsPanel`
    - `EditScoreModal`

**Environment**

- Dockerized via **Laravel Sail**
- Separate database for tests (`testing`)

---

## Local Setup (Laravel Sail)

> Requirements: Docker + Docker Compose

1. **Clone the repository**

```bash
git clone https://github.com/denizekinci/champions-league.git
cd champions-league
```

2. **Install dependencies**

```bash
composer install
npm install
```

3. **Environment files**

Create `.env` from the example and keep the default Sail/PostgreSQL config:

```bash
cp .env.example .env
```

Minimal DB section (already set correctly for Sail):

```env
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password
```

For tests there is a dedicated `.env.testing`:

```env
APP_ENV=testing
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=testing
DB_USERNAME=sail
DB_PASSWORD=password
```

4. **Start the containers**

```bash
./vendor/bin/sail up -d
```

5. **Run migrations & seeders**

```bash
./vendor/bin/sail artisan migrate --seed
```

The seeder (`LeagueSeeder`) populates the four base teams and any other required initial data.

6. **Run the front-end dev server**

```bash
./vendor/bin/sail npm run dev
```

Now you can open the app at:

```text
http://localhost
```

---

## Running the App

### Main routes

- `GET /`  
  Redirects to the main tournament UI (no authentication layer is required for this case).

- `GET /tournament/teams`  
  Tournament teams & fixture management screen.

- `GET /tournament/simulation`  
  Simulation dashboard: standings, fixtures, predictions.

### Important actions

- `POST /tournament/fixtures/generate`  
  Generates a 6-week double round-robin fixture list.

- `POST /tournament/fixtures/clear`  
  Clears all fixtures & results.

- `POST /tournament/simulation/play-next-week`  
  Plays the next unplayed matchweek.

- `POST /tournament/simulation/play-all`  
  Plays all remaining matchweeks.

- `POST /tournament/simulation/reset`  
  Resets all results and returns to week 1.

- `PATCH /tournament/simulation/games/{game}`  
  Manually updates the score of a single match.

---

## Core Use Cases

### 1. Manage teams & fixtures

- Open **Tournament Teams** (`/tournament/teams`)
- Adjust each team’s `power` value to tweak their relative strength
- Click **Generate Fixtures** to create a balanced double round-robin schedule
- If needed, **Clear Fixtures** to start over

### 2. Run the simulation

- Open **Simulation** (`/tournament/simulation`)
- Use:
    - **Play Next Week** to advance week by week
    - **Play All Weeks** to quickly complete the group
    - **Reset** to clear all results and start from week 1

### 3. Edit a single game

- In **Weekly Matches**, select a week tab
- Click **Edit** on a match
- A modal opens with the current scores
- Enter new values and **Save**
- The standings and downstream probabilities are recalculated accordingly

### 4. Championship predictions

In the last weeks of the group, the **Championship Predictions** panel shows Monte-Carlo estimated championship probabilities for each team.

By default:

- Predictions are hidden in the early weeks (too noisy to be useful)
- The prediction “window” logic is centralized in `PredictionService`

---

## Domain Services

### `FixtureService`

Responsible for generating a **balanced double round-robin** schedule:

- Uses a template to ensure:
    - Each team plays every other team twice (home & away)
    - Each team has exactly one match per week
    - The full fixture fits exactly into 6 weeks

The associated test (`FixtureServiceTest`) asserts:

- 12 total matches for 4 teams
- Each team plays 6 matches
- All matches are distributed between weeks 1–6

---

### `SimulationService`

Applies match simulations week by week:

- **`playWeek($week)`**
    - Simulates all unplayed games in the given week
    - Uses `MatchSimulator` to generate realistic scores based on team power
- **`playAll()`**
    - Plays all remaining weeks
- **`reset()`**
    - Resets `home_goals`, `away_goals` and `is_played` flags for all matches

---

### `StandingsService`

Computes the league table:

- Aggregates stats from the `games` table:
    - `played`, `wins`, `draws`, `losses`
    - `goals_for`, `goals_against`, `goal_diff`, `points`
- Applies deterministic sorting:
    1. Points
    2. Goal difference
    3. Goals scored
    4. Team name (alphabetical) as final tie-breaker

The unit test (`StandingsServiceTest`) verifies:

- Correct aggregation for a simple fixture (e.g. 2–1 result)
- Correct ordering between winner and loser

---

### `MatchSimulator`

Encapsulates the **match outcome model**:

- Accepts `homePower` and `awayPower`
- Uses a simple probability model to:
    - Draw a match result (home win/draw/away win)
    - Generate a reasonable scoreline consistent with that result

This is intentionally pluggable: the core services don’t know how scores are generated, only that they receive realistic scores.

---

### `PredictionService`

Estimates **championship probabilities** via Monte-Carlo simulation:

- Keeps recorded results as-is
- For every unplayed game:
    - Uses `MatchSimulator` to simulate a scoreline
- Recomputes standings using the same tie-break rules as `StandingsService`
- Repeats this process `N` times (default: 300 runs)
- Counts how many times each team finishes 1st
- Exposes a probability table:

```php
[
    [
        'team_id'     => 1,
        'team_name'   => 'Arsenal',
        'probability' => 42.3, // %
    ],
    // ...
]
```

Prediction visibility is controlled by a small **window**:

```php
private const TOTAL_WEEKS = 6;
private const PREDICTION_WINDOW = 2; // last 2 weeks

private function isPredictionWindowOpen(): bool
{
    $currentWeek = $this->getCurrentWeek();

    return $currentWeek > (self::TOTAL_WEEKS - self::PREDICTION_WINDOW);
}
```

This logic makes it trivial to change when predictions start showing up.

---

## Automated Tests

The project uses **Pest** for a concise test syntax.

### Test suites

Configured in `phpunit.xml`:

- **Unit** (enabled)
    - `tests/Unit/FixtureServiceTest.php`
    - `tests/Unit/StandingsServiceTest.php`
    - `tests/Unit/PredictionWindowTest.php`
    - `tests/Unit/DbSanityTest.php`
    - And the default `ExampleTest`

Feature tests scaffolded by Laravel Breeze / Fortify are currently out of scope for this case and can be excluded if needed.

### Running tests

```bash
./vendor/bin/sail artisan test
```

The test environment uses:

- `APP_ENV=testing`
- Separate PostgreSQL database: `testing`
- `RefreshDatabase` to wrap each test in a clean transaction / migration cycle

Example expectation from `PredictionWindowTest`:

- Before the prediction window:
    - `getChampionshipProbabilities()` returns an empty array
- After playing enough weeks to enter the window:
    - Returns one row per team
    - Probabilities sum approximately to 100% (allowing minor rounding error)

---

## Notes & Trade-offs

- **Authentication removed on purpose**

  The original case description doesn’t require multi-user support or user accounts.  
  To keep the focus on domain logic, the authentication layer (Breeze / Fortify) is not used and the main tournament routes are publicly accessible.

- **State stored in the database**

  For simplicity and reproducibility, all simulation state (fixtures, scores, etc.) is persisted in the relational database rather than local storage.

- **Configurable prediction window**

  The prediction window size is centralized in `PredictionService::PREDICTION_WINDOW`, allowing easy adjustment between “last 3 weeks”, “last 2 weeks”, etc. with a single constant.

- **UI components kept small and focused**

  The main `Simulation.vue` page is split into dedicated components (`SimulationHeader`, `StandingsTable`, `WeeklyFixtures`, `PredictionsPanel`, `EditScoreModal`) to keep templates readable and logic scoped.

- **GitHub Actions deliberately disabled**

  The repository initially included CI (lint + tests) from the boilerplate.
  Since this case does not focus on CI/CD, and because full CI setup for a Laravel app would require database orchestration on GitHub runners, Actions were intentionally disabled to avoid unnecessary red pipelines.
  Local tests (Pest) are fully working and documented in the README.

## Deployment

- Provider: Hetzner Cloud (VPS)
- Stack: Laravel 12 + Sail (local) / Nginx + PHP-FPM (prod) + PostgreSQL
- Frontend: Vite + Inertia.js + Vue 3
- Production URL: https://cl.denizekinci.dev/tournament/teams
