<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { router, useForm } from "@inertiajs/vue3";

import SimulationHeader from './Partials/SimulationHeader.vue';
import StandingsTable from './Partials/StandingsTable.vue';
import WeeklyFixtures from './Partials/WeeklyFixtures.vue';
import PredictionsPanel from './Partials/PredictionsPanel.vue';
import EditScoreModal from './Partials/EditScoreModal.vue';

interface StandingRow {
    team_id: number;
    team_name: string;
    played: number;
    wins: number;
    draws: number;
    losses: number;
    goals_for: number;
    goals_against: number;
    goal_diff: number;
    points: number;
    position: number;
}

interface FixtureGame {
    id: number;
    week: number;
    home_team: string;
    away_team: string;
    home_goals: number | null;
    away_goals: number | null;
    is_played: boolean;
}

interface PredictionRow {
    team_id: number;
    team_name: string;
    probability: number;
}

const props = defineProps<{
    standings: StandingRow[];
    weeks: number[];
    fixturesByWeek: Record<number, FixtureGame[]>;
    currentWeek: number;
    totalWeeks: number;
    predictions: PredictionRow[];
}>();

const isSubmitting = ref(false);

/**
 * At least one game in any week has been marked as played.
 * Used as a coarse-grained “simulation started or not” flag.
 */
const anyGamePlayed = computed<boolean>(() => {
    for (const key in props.fixturesByWeek) {
        const games = props.fixturesByWeek[key] ?? [];
        if (games.some((g) => g.is_played)) {
            return true;
        }
    }
    return false;
});

/**
 * There is at least one fixture and all fixtures are played.
 * “No fixtures at all” is intentionally treated as not-finished.
 */
const allGamesPlayed = computed<boolean>(() => {
    let hasGames = false;

    for (const key in props.fixturesByWeek) {
        const games = props.fixturesByWeek[key] ?? [];
        if (games.length > 0) {
            hasGames = true;
        }
        for (const game of games) {
            if (!game.is_played) {
                return false;
            }
        }
    }

    return hasGames;
});

/**
 * Last week index that contains at least one played game.
 * Drives both the status message and the initial selected tab.
 */
const lastPlayedWeek = computed<number | null>(() => {
    let max: number | null = null;

    for (const key in props.fixturesByWeek) {
        const week = Number(key);
        const games = props.fixturesByWeek[week] ?? [];

        if (games.some((g) => g.is_played)) {
            if (max === null || week > max) {
                max = week;
            }
        }
    }

    return max;
});

/**
 * High–level narrative for the header, derived from the global simulation state.
 */
const statusMessage = computed<string>(() => {
    if (!anyGamePlayed.value) {
        return "No matches have been played yet. Click “Play Next Week” to start the simulation.";
    }

    if (!allGamesPlayed.value) {
        const week = lastPlayedWeek.value ?? 1;
        const remaining = props.totalWeeks - week;
        return `Simulation in progress – Week ${week} of ${props.totalWeeks}. ${remaining} week(s) remaining.`;
    }

    return "All matches have been played. You can edit scores or reset the simulation.";
});

/**
 * Current week tab selection for the fixtures panel.
 * Defaults to the last played week, or the first configured week as a fallback.
 */
const selectedWeek = ref(
    lastPlayedWeek.value !== null
        ? lastPlayedWeek.value
        : props.weeks[0] ?? 1
);

/**
 * Keep the currently selected week aligned with server state.
 * If a new week gets played, UI jumps forward to that week.
 */
watch(
    () => props.fixturesByWeek,
    () => {
        if (lastPlayedWeek.value !== null) {
            selectedWeek.value = lastPlayedWeek.value;
        } else if (props.weeks.length > 0) {
            selectedWeek.value = props.weeks[0];
        }
    },
    { deep: true }
);

const playNextWeek = () => {
    if (isSubmitting.value || allGamesPlayed.value) return;

    isSubmitting.value = true;

    router.post(
        "/tournament/simulation/play-next-week",
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                isSubmitting.value = false;
            },
        }
    );
};

const playAllWeeks = () => {
    if (isSubmitting.value || allGamesPlayed.value) return;

    isSubmitting.value = true;

    router.post(
        "/tournament/simulation/play-all",
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                isSubmitting.value = false;
            },
        }
    );
};

const resetData = () => {
    if (isSubmitting.value) return;

    isSubmitting.value = true;

    router.post(
        "/tournament/simulation/reset",
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                isSubmitting.value = false;
            },
        }
    );
};

const editingGame = ref<FixtureGame | null>(null);
const scoreForm = useForm({ home_goals: "", away_goals: "" });
const scoreError = ref<string | null>(null);

const openEdit = (game: FixtureGame) => {
    editingGame.value = game;
    scoreError.value = null;
    scoreForm.home_goals = game.home_goals !== null ? String(game.home_goals) : "";
    scoreForm.away_goals = game.away_goals !== null ? String(game.away_goals) : "";
};

const cancelEdit = () => {
    editingGame.value = null;
    scoreError.value = null;
    scoreForm.reset();
};

/**
 * Validates the raw string payload from the modal, then delegates to Inertia form.
 * Validation is intentionally kept light here; the backend remains the source of truth.
 */
const applyScoreAndSave = (payload: { home: string; away: string }) => {
    const home = Number(payload.home);
    const away = Number(payload.away);

    if (
        payload.home === "" ||
        payload.away === "" ||
        Number.isNaN(home) ||
        Number.isNaN(away) ||
        home < 0 ||
        away < 0
    ) {
        scoreError.value = "Score fields must be filled and non-negative numbers.";
        return;
    }

    if (!editingGame.value) return;

    scoreError.value = null;

    scoreForm.home_goals = payload.home;
    scoreForm.away_goals = payload.away;

    scoreForm.patch(`/tournament/simulation/games/${editingGame.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editingGame.value = null;
            scoreForm.reset();
        },
    });
};

const selectedWeekFixtures = computed<FixtureGame[]>(() => {
    return props.fixturesByWeek[selectedWeek.value] ?? [];
});
</script>

<template>
    <div class="space-y-8 mx-auto w-full max-w-5xl px-4 py-8">
        <SimulationHeader
            :status-message="statusMessage"
            :is-submitting="isSubmitting"
            :all-games-played="allGamesPlayed"
            @reset="resetData"
            @play-next="playNextWeek"
            @play-all="playAllWeeks"
        />

        <StandingsTable
            :rows="standings"
            :is-loading="isSubmitting"
        />

        <div class="grid gap-6 lg:grid-cols-3">
            <WeeklyFixtures
                class="lg:col-span-2"
                :weeks="weeks"
                v-model:selected-week="selectedWeek"
                :fixtures="selectedWeekFixtures"
                @edit="openEdit"
            />

            <PredictionsPanel :rows="predictions" />
        </div>

        <EditScoreModal
            v-if="editingGame"
            :game="editingGame"
            :home="scoreForm.home_goals"
            :away="scoreForm.away_goals"
            :error="scoreError"
            @cancel="cancelEdit"
            @save="applyScoreAndSave"
        />
    </div>
</template>
