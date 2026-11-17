<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '@/components/AdminLayout.vue';

type Result = {
    steps: string[];
    fields: Record<string, string | null> | null;
    ai: unknown;
    recipe: Record<string, unknown> | null;
    seconds: number;
};

const props = defineProps<{
    result: Result | null;
    input: { url: string; mode: string };
}>();

const form = useForm({
    url: props.input.url,
    html: '',
    mode: props.input.mode === '' ? 'ai' : props.input.mode,
});

const modes = [
    { value: 'quick', label: 'Szybki (bez AI)' },
    { value: 'ai', label: 'Normalny (AI, gdy czegoś brakuje)' },
    { value: 'fresh', label: 'Od nowa (usuń przepis i ucz AI)' },
];

const labels: Record<string, string> = {
    title: 'Nazwa',
    shop: 'Sklep',
    image: 'Zdjęcie',
    price: 'Cena',
};

function run(): void {
    form.post('/admin/symulator', { preserveScroll: true });
}
</script>

<template>
    <AdminLayout title="Symulator">
        <p class="text-sm text-muted">
            Sprawdź, co tobym odczyta z linku. Bez HTML-a serwer pobiera stronę
            sam, tak jak po wklejeniu linku. Z wklejonym HTML-em działa jak
            wtyczka. HTML skopiujesz w sklepie przez „Pokaż źródło strony”.
        </p>
        <form
            class="flex flex-col gap-4 rounded-card border border-line bg-surface p-5"
            @submit.prevent="run"
        >
            <label class="flex flex-col gap-1.5">
                <span class="text-sm font-semibold">Link do produktu</span>
                <input
                    v-model="form.url"
                    type="url"
                    required
                    placeholder="https://…"
                    class="h-11 rounded-control border border-line bg-surface px-3"
                />
            </label>
            <label class="flex flex-col gap-1.5">
                <span class="text-sm font-semibold"
                    >HTML strony
                    <span class="font-normal text-muted"
                        >(opcjonalnie, jak z wtyczki)</span
                    ></span
                >
                <textarea
                    v-model="form.html"
                    rows="4"
                    class="rounded-control border border-line bg-surface px-3 py-2 font-mono text-xs"
                ></textarea>
            </label>
            <fieldset class="flex flex-wrap gap-x-5 gap-y-2">
                <legend class="mb-1.5 text-sm font-semibold">Tryb</legend>
                <label
                    v-for="mode in modes"
                    :key="mode.value"
                    class="flex items-center gap-2 text-sm"
                >
                    <input
                        v-model="form.mode"
                        type="radio"
                        name="mode"
                        :value="mode.value"
                    />
                    {{ mode.label }}
                </label>
            </fieldset>
            <p
                v-for="(message, field) in form.errors"
                :key="field"
                role="alert"
                class="text-sm font-semibold text-accent"
            >
                {{ message }}
            </p>
            <button
                type="submit"
                :disabled="form.processing"
                class="h-11 self-start rounded-control bg-ink px-6 font-semibold text-ground disabled:opacity-60"
            >
                {{
                    form.processing ? 'Sprawdzam… (z AI do 2 minut)' : 'Sprawdź'
                }}
            </button>
        </form>

        <section v-if="result" class="grid gap-4 lg:grid-cols-2">
            <div
                class="flex flex-col gap-3 rounded-card border border-line bg-surface p-5"
            >
                <h2 class="text-xl font-semibold">
                    Wynik
                    <span class="text-sm font-normal text-muted"
                        >({{ result.seconds }} s)</span
                    >
                </h2>
                <template v-if="result.fields">
                    <img
                        v-if="result.fields.image"
                        :src="result.fields.image"
                        alt=""
                        referrerpolicy="no-referrer"
                        class="h-40 w-40 rounded-control border border-line bg-white object-contain"
                    />
                    <dl
                        class="grid grid-cols-[6rem_1fr] gap-x-3 gap-y-2 text-sm"
                    >
                        <template
                            v-for="(value, key) in result.fields"
                            :key="key"
                        >
                            <dt class="font-semibold">
                                {{ labels[key] ?? key }}
                            </dt>
                            <dd class="break-all">
                                <span v-if="value">{{ value }}</span>
                                <span v-else class="text-accent"
                                    >nie znaleziono</span
                                >
                            </dd>
                        </template>
                    </dl>
                </template>
                <p v-else class="text-accent">Nic nie odczytano.</p>
            </div>
            <div
                class="flex flex-col gap-3 rounded-card border border-line bg-surface p-5"
            >
                <h2 class="text-xl font-semibold">Kroki</h2>
                <ol class="flex list-decimal flex-col gap-1 pl-5 text-sm">
                    <li v-for="(step, index) in result.steps" :key="index">
                        {{ step }}
                    </li>
                </ol>
                <details v-if="result.recipe">
                    <summary class="cursor-pointer text-sm font-semibold">
                        Zapisany przepis sklepu
                    </summary>
                    <pre
                        class="mt-2 overflow-x-auto rounded-control bg-tile p-2 text-xs whitespace-pre-wrap"
                        >{{ JSON.stringify(result.recipe, null, 2) }}</pre>
                </details>
                <details v-if="result.ai">
                    <summary class="cursor-pointer text-sm font-semibold">
                        Odpowiedź AI
                    </summary>
                    <pre
                        class="mt-2 overflow-x-auto rounded-control bg-tile p-2 text-xs whitespace-pre-wrap"
                        >{{ JSON.stringify(result.ai, null, 2) }}</pre>
                </details>
            </div>
        </section>
    </AdminLayout>
</template>
