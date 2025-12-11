<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';

/**
 * Pure presentational table for league standings.
 * Rendering logic only; sorting & computation handled upstream.
 */
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

const props = defineProps<{
    rows: StandingRow[];
    isLoading: boolean; // Enables skeleton UI during server-side updates
}>();
</script>

<template>
    <div class="rounded-xl border bg-card">
        <div class="px-4 py-3 border-b flex items-center justify-between">
            <h2 class="font-semibold text-sm">League Table</h2>
        </div>

        <div class="p-4">
            <table class="w-full text-sm">
                <thead class="text-xs text-muted-foreground border-b">
                <tr>
                    <th class="py-2 text-left">Pos</th>
                    <th class="py-2 text-left">Team</th>
                    <th class="py-2 text-center">P</th>
                    <th class="py-2 text-center">W</th>
                    <th class="py-2 text-center">D</th>
                    <th class="py-2 text-center">L</th>
                    <th class="py-2 text-center">GF</th>
                    <th class="py-2 text-center">GA</th>
                    <th class="py-2 text-center">GD</th>
                    <th class="py-2 text-center">Pts</th>
                </tr>
                </thead>

                <tbody>
                <tr
                    v-for="row in props.rows"
                    :key="row.team_id"
                    class="border-b last:border-0 transition-colors duration-300"
                    :class="row.position === 1 ? 'bg-emerald-50/60 dark:bg-emerald-900/30' : ''"
                >
                    <!-- Pos -->
                    <td class="py-2 text-left w-10">
                        <Skeleton v-if="props.isLoading" class="h-3 w-4 skeleton-shimmer" />
                        <span v-else>{{ row.position }}</span>
                    </td>

                    <!-- Team -->
                    <td class="py-2 font-medium">
                        <Skeleton v-if="props.isLoading" class="h-3 w-24 skeleton-shimmer" />
                        <span v-else>{{ row.team_name }}</span>
                    </td>

                    <!-- Played -->
                    <td class="py-2 text-center">
                        <Skeleton v-if="props.isLoading" class="h-3 w-6 mx-auto skeleton-shimmer" />
                        <span v-else>{{ row.played }}</span>
                    </td>

                    <!-- W -->
                    <td class="py-2 text-center">
                        <Skeleton v-if="props.isLoading" class="h-3 w-6 mx-auto skeleton-shimmer" />
                        <span v-else>{{ row.wins }}</span>
                    </td>

                    <!-- D -->
                    <td class="py-2 text-center">
                        <Skeleton v-if="props.isLoading" class="h-3 w-6 mx-auto skeleton-shimmer" />
                        <span v-else>{{ row.draws }}</span>
                    </td>

                    <!-- L -->
                    <td class="py-2 text-center">
                        <Skeleton v-if="props.isLoading" class="h-3 w-6 mx-auto skeleton-shimmer" />
                        <span v-else>{{ row.losses }}</span>
                    </td>

                    <!-- GF -->
                    <td class="py-2 text-center">
                        <Skeleton v-if="props.isLoading" class="h-3 w-6 mx-auto skeleton-shimmer" />
                        <span v-else>{{ row.goals_for }}</span>
                    </td>

                    <!-- GA -->
                    <td class="py-2 text-center">
                        <Skeleton v-if="props.isLoading" class="h-3 w-6 mx-auto skeleton-shimmer" />
                        <span v-else>{{ row.goals_against }}</span>
                    </td>

                    <!-- GD -->
                    <td class="py-2 text-center">
                        <Skeleton v-if="props.isLoading" class="h-3 w-8 mx-auto skeleton-shimmer" />
                        <span v-else>{{ row.goal_diff }}</span>
                    </td>

                    <!-- Pts -->
                    <td class="py-2 text-center font-semibold">
                        <Skeleton v-if="props.isLoading" class="h-3 w-8 mx-auto skeleton-shimmer" />
                        <span v-else>{{ row.points }}</span>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
