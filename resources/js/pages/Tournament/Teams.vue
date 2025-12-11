<script setup lang="ts">
import { computed } from "vue";
import { useForm, router } from "@inertiajs/vue3";
import { Button } from '@/components/ui/button';

interface Team {
    id: number;
    name: string;
    power: number;
}

interface FixtureGame {
    id: number;
    week: number;
    home_team: string;
    away_team: string;
}

const props = defineProps<{
    teams: Team[];
    hasFixtures: boolean;
    weeks: number[];
    fixturesByWeek: Record<number, FixtureGame[]>;
}>();

// We only care about the submission state here, no payload required.
const form = useForm({});

/**
 * Generates (or regenerates) the fixture schedule on the backend.
 */
const generateFixtures = () => {
    form.post("/tournament/fixtures/generate", {
        preserveScroll: true,
    });
};

/**
 * Clears all existing fixtures while keeping the teams intact.
 */
const clearFixtures = () => {
    router.post(
        "/tournament/fixtures/clear",
        {},
        {
            preserveScroll: true,
        }
    );
};

/**
 * Navigates to the main simulation dashboard.
 */
const goToSimulation = () => {
    router.get("/tournament/simulation");
};

/**
 * Button label reflects whether a fixture set already exists.
 */
const generateButtonLabel = computed(() =>
    props.hasFixtures ? "Regenerate Fixtures" : "Generate Fixtures"
);
</script>

<template>
    <div class="space-y-8 mx-auto w-full max-w-5xl px-4 py-8">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Tournament Teams</h1>
                <p class="text-sm text-muted-foreground">
                    Manage your teams and generate the fixtures before starting the simulation.
                </p>
            </div>

            <div class="flex items-center gap-3">
                <Button
                    variant="secondary"
                    @click="clearFixtures"
                    :disabled="!hasFixtures"
                >
                    Clear Fixtures
                </Button>

                <Button
                    variant="outline"
                    @click="generateFixtures"
                    :disabled="form.processing"
                >
                    {{ generateButtonLabel }}
                </Button>

                <Button
                    variant="default"
                    @click="goToSimulation"
                    :disabled="!hasFixtures"
                >
                    Start Simulation
                </Button>
            </div>
        </div>

        <!-- Teams table -->
        <div class="rounded-xl border bg-card">
            <div class="px-4 py-3 border-b">
                <h2 class="font-semibold text-sm">Teams</h2>
            </div>
            <div class="p-4">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs text-muted-foreground border-b">
                    <tr>
                        <th class="py-2">Team Name</th>
                        <th class="py-2">Power</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr
                        v-for="team in teams"
                        :key="team.id"
                        class="border-b last:border-0"
                    >
                        <td class="py-2 font-medium">
                            {{ team.name }}
                        </td>
                        <td class="py-2">
                            {{ team.power }}
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Fixtures preview -->
        <div class="rounded-xl border bg-card">
            <div class="px-4 py-3 border-b flex items-center justify-between">
                <h2 class="font-semibold text-sm">Fixtures</h2>
                <span
                    v-if="!hasFixtures"
                    class="text-xs text-muted-foreground"
                >
                    No fixtures yet. Generate fixtures to see weekly matchups.
                </span>
            </div>

            <div
                v-if="hasFixtures"
                class="p-4 space-y-4"
            >
                <div
                    v-for="week in weeks"
                    :key="week"
                    class="border rounded-lg px-3 py-2"
                >
                    <h3 class="text-sm font-semibold mb-2">
                        Week {{ week }}
                    </h3>
                    <ul class="text-sm space-y-1">
                        <li
                            v-for="game in fixturesByWeek[week]"
                            :key="game.id"
                            class="flex items-center justify-between"
                        >
                            <span>
                                {{ game.home_team }} - {{ game.away_team }}
                            </span>
                        </li>
                    </ul>
                </div>
            </div>

            <div
                v-else
                class="p-4 text-sm text-muted-foreground"
            >
                Fixtures will appear here after you generate them.
            </div>
        </div>
    </div>
</template>
