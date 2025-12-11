<script setup lang="ts">
/**
 * Lightweight read-only probability panel.
 * Parent computes probabilities; this component focuses purely on display.
 */
interface PredictionRow {
    team_id: number;
    team_name: string;
    probability: number;
}

const props = defineProps<{
    rows: PredictionRow[];
}>();
</script>

<template>
    <div class="rounded-xl border bg-card">
        <div class="px-4 py-3 border-b">
            <h2 class="font-semibold text-sm">Championship Predictions</h2>
            <p class="mt-1 text-xs text-muted-foreground">
                Probabilities will appear in the last 3 weeks of the group.
            </p>
        </div>

        <div class="p-4">
            <div
                v-if="props.rows.length === 0"
                class="text-sm text-muted-foreground"
            >
                Not enough data yet to calculate probabilities.
            </div>

            <div v-else class="space-y-2 text-sm">
                <div
                    v-for="row in props.rows"
                    :key="row.team_id"
                    class="flex items-center justify-between"
                >
                    <div class="flex flex-col">
                        <span class="font-medium">{{ row.team_name }}</span>
                    </div>

                    <div class="flex items-center gap-2">
                        <span class="text-xs text-muted-foreground w-10 text-right">
                            {{ row.probability }}%
                        </span>

                        <div class="w-20 h-1.5 rounded-full bg-muted overflow-hidden">
                            <div
                                class="h-full bg-primary"
                                :style="{ width: Math.min(row.probability, 100) + '%' }"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
