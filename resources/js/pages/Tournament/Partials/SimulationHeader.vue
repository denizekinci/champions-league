<script setup lang="ts">
import { computed } from "vue";
import { ArrowLeft } from "lucide-vue-next";
import { Link } from "@inertiajs/vue3";

const props = defineProps<{
    statusMessage: string;
    isSubmitting: boolean;
    allGamesPlayed: boolean;
}>();

const emit = defineEmits<{
    (e: "reset"): void;
    (e: "play-next"): void;
    (e: "play-all"): void;
}>();

const nextWeekLabel = computed(() => {
    if (props.isSubmitting) return "Running...";
    if (props.allGamesPlayed) return "No weeks left";
    return "Play Next Week";
});

const allWeeksLabel = computed(() => {
    if (props.isSubmitting) return "Simulating...";
    if (props.allGamesPlayed) return "Completed";
    return "Play All Weeks";
});
</script>

<template>
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">

        <!-- left header section with logo -->
        <div class="flex items-center gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Champions League Group Simulation
                </h1>
                <p class="text-sm text-muted-foreground">
                    Play matches week by week, see live standings and championship probabilities.
                </p>
            </div>
        </div>

        <!-- right section: buttons -->
        <div class="flex flex-col items-stretch gap-1 sm:items-end">
            <div class="flex items-center gap-3">
                <Link
                    href="/tournament/teams"
                    class="inline-flex items-center gap-2 px-3 py-2 rounded-md border text-sm font-medium hover:bg-accent transition hover:-translate-x-1"
                >
                    <ArrowLeft class="w-4 h-4" />
                    Teams
                </Link>
                <button
                    type="button"
                    class="inline-flex items-center justify-center px-4 py-2 rounded-md border text-sm font-medium hover:bg-accent disabled:opacity-60 disabled:cursor-not-allowed"
                    @click="emit('reset')"
                    :disabled="props.isSubmitting"
                >
                    Reset
                </button>

                <button
                    type="button"
                    class="inline-flex items-center justify-center px-4 py-2 rounded-md border text-sm font-medium hover:bg-accent disabled:opacity-60 disabled:cursor-not-allowed min-w-[140px]"
                    @click="emit('play-next')"
                    :disabled="props.isSubmitting || props.allGamesPlayed"
                >
                    {{ nextWeekLabel }}
                </button>

                <button
                    type="button"
                    class="inline-flex items-center justify-center px-4 py-2 rounded-md bg-primary text-primary-foreground text-sm font-medium hover:opacity-90 disabled:opacity-60 disabled:cursor-not-allowed min-w-[140px]"
                    @click="emit('play-all')"
                    :disabled="props.isSubmitting || props.allGamesPlayed"
                >
                    {{ allWeeksLabel }}
                </button>
            </div>

            <p class="text-xs text-muted-foreground text-right">
                {{ props.statusMessage }}
            </p>
        </div>
    </div>
</template>
