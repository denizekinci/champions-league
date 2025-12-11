<script setup lang="ts">
import { reactive, watch } from "vue";

/**
 * Score editing modal kept intentionally dumb:
 * - owns its own local form state
 * - delegates validation and persistence back to the parent.
 */
interface FixtureGame {
    id: number;
    week: number;
    home_team: string;
    away_team: string;
}

const props = defineProps<{
    game: FixtureGame;
    home: string | number;
    away: string | number;
    error: string | null;
}>();

/**
 * Emits are coarse-grained: parent remains the single source of truth.
 */
const emit = defineEmits<{
    (e: "cancel"): void;
    (e: "save", payload: { home: string; away: string }): void;
}>();

/**
 * Local buffer for score inputs.
 * Incoming props may be number or string; here we normalize to string
 * so that inputs are always controlled text fields.
 */
const localForm = reactive({
    home: props.home != null ? String(props.home) : "",
    away: props.away != null ? String(props.away) : "",
});

/**
 * Keep the local buffer in sync if parent updates the values
 * (e.g. after a successful save or external refresh).
 */
watch(
    () => [props.home, props.away],
    ([h, a]) => {
        localForm.home = h != null ? String(h) : "";
        localForm.away = a != null ? String(a) : "";
    }
);

const save = () => {
    emit("save", {
        home: localForm.home,
        away: localForm.away,
    });
};
</script>

<template>
    <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/40">
        <div class="w-full max-w-sm rounded-xl border bg-card p-4 shadow-lg">
            <h3 class="text-sm font-semibold mb-2">
                Edit Score – Week {{ props.game.week }}
            </h3>
            <p class="text-xs text-muted-foreground mb-4">
                {{ props.game.home_team }} vs {{ props.game.away_team }}
            </p>

            <form @submit.prevent="save" class="space-y-3">
                <div class="flex items-center gap-3">
                    <div class="flex-1">
                        <label class="block text-xs text-muted-foreground mb-1">
                            Home
                        </label>
                        <input
                            v-model="localForm.home"
                            type="number"
                            min="0"
                            class="w-full rounded-md border px-2 py-1 text-sm"
                            :class="props.error ? 'border-red-500' : ''"
                        />
                    </div>

                    <div class="flex-1">
                        <label class="block text-xs text-muted-foreground mb-1">
                            Away
                        </label>
                        <input
                            v-model="localForm.away"
                            type="number"
                            min="0"
                            class="w-full rounded-md border px-2 py-1 text-sm"
                            :class="props.error ? 'border-red-500' : ''"
                        />
                    </div>
                </div>

                <p v-if="props.error" class="text-xs text-red-500">
                    {{ props.error }}
                </p>

                <div class="flex justify-end gap-2 pt-2">
                    <button
                        type="button"
                        class="px-3 py-1 rounded-md border text-xs hover:bg-accent"
                        @click="emit('cancel')"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        class="px-3 py-1 rounded-md bg-primary text-primary-foreground text-xs hover:opacity-90"
                    >
                        Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
