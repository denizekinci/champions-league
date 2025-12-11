<script setup lang="ts">
/**
 * Weekly fixtures list with a lightweight tab API.
 * Parent owns the full schedule; this component focuses purely on the active week.
 */
interface FixtureGame {
    id: number;
    week: number;
    home_team: string;
    away_team: string;
    home_goals: number | null;
    away_goals: number | null;
    is_played: boolean;
}

const props = defineProps<{
    weeks: number[];
    selectedWeek: number;
    fixtures: FixtureGame[];
}>();

/**
 * Exposes a v-model-compatible contract for selectedWeek
 * and a simple edit event for score editing modals.
 */
const emit = defineEmits<{
    (e: 'update:selectedWeek', value: number): void;
    (e: 'edit', game: FixtureGame): void;
}>();
</script>

<template>
    <div class="rounded-xl border bg-card">
        <div class="px-4 py-3 border-b flex items-center justify-between">
            <h2 class="font-semibold text-sm">Weekly Matches</h2>
            <div class="flex gap-1">
                <button
                    v-for="week in props.weeks"
                    :key="week"
                    type="button"
                    class="relative px-3 py-1 rounded-md text-xs border transition-all duration-200"
                    :class="
                        week === props.selectedWeek
                            ? 'bg-primary text-primary-foreground shadow-sm'
                            : 'bg-background text-foreground hover:bg-accent'
                    "
                    @click="emit('update:selectedWeek', week)"
                >
                    <span>Week {{ week }}</span>
                    <span
                        v-if="week === props.selectedWeek"
                        class="pointer-events-none absolute inset-x-1 bottom-0 h-0.5 rounded-full bg-primary-foreground/80 transition-all duration-200"
                    />
                </button>
            </div>
        </div>

        <div class="p-4 space-y-2">
            <div
                v-if="props.fixtures.length === 0"
                class="text-sm text-muted-foreground"
            >
                No fixtures defined for this week.
            </div>

            <div
                v-for="game in props.fixtures"
                :key="game.id"
                class="flex items-center justify-between rounded-lg border px-3 py-2"
            >
                <div class="flex flex-col">
                    <span class="text-sm">
                        {{ game.home_team }} vs {{ game.away_team }}
                    </span>
                    <span class="text-xs text-muted-foreground">
                        Week {{ game.week }} •
                        <span v-if="game.is_played">Played</span>
                        <span v-else>Not played</span>
                    </span>
                </div>

                <div class="flex items-center gap-3">
                    <span class="text-sm font-semibold w-14 text-center">
                        <template v-if="game.is_played">
                            {{ game.home_goals }} - {{ game.away_goals }}
                        </template>
                        <template v-else> - : - </template>
                    </span>
                    <button
                        type="button"
                        class="text-xs px-2 py-1 rounded-md border hover:bg-accent"
                        @click="emit('edit', game)"
                    >
                        Edit
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
