<script setup lang="ts">
import type { RequestPayload } from '@inertiajs/core';
import { router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { confirmAction, toast } from '@/lib/ui';
import { listUrl } from '@/lib/wishlist';
import type {
    ListSummary,
    ThemeOption,
    WishlistData,
    WishlistLayout,
} from '@/types';

const props = defineProps<{
    wishlist: WishlistData;
    themes: ThemeOption[];
    slug: string | null;
    shareUrl: string | null;
    lists: ListSummary[];
    loadingPreview: boolean;
    urlError: string;
}>();

const theme = defineModel<string>('theme', { required: true });
const layout = defineModel<WishlistLayout>('layout', { required: true });
const accent = defineModel<string | null>('accent', { required: true });

const emit = defineEmits<{
    'add-link': [url: string];
    'add-manual': [];
    'open-qr': [];
}>();

const page = usePage();
const errors = computed(() => page.props.errors);

// Ustawienia są rozwinięte na dużym ekranie, a na telefonie schowane, żeby lista była od razu widoczna.
const settingsOpen = ref(false);
onMounted(() => {
    settingsOpen.value = window.matchMedia('(min-width: 1024px)').matches;
});

function onSettingsToggle(event: Event): void {
    settingsOpen.value = (event.target as HTMLDetailsElement).open;
}

const url = ref('');
const bulkOpen = ref(false);
const bulkText = ref('');
const bulkSending = ref(false);
const pin = ref('');
const copied = ref(false);

const layoutOptions: { value: WishlistLayout; label: string }[] = [
    { value: 'tiles', label: 'Kafelki' },
    { value: 'rows', label: 'Rzędy' },
];

const currentLists = computed(() => props.lists.filter((list) => !list.past));
const pastLists = computed(() => props.lists.filter((list) => list.past));

function save(data: RequestPayload): void {
    router.patch(listUrl(props.slug), data, {
        preserveScroll: true,
        onSuccess: () => toast('Zapisano'),
    });
}

function submitLink(): void {
    const link = url.value.trim();

    if (link !== '') {
        emit('add-link', link);
    }
}

function clearLink(): void {
    url.value = '';
}

defineExpose({ clearLink });

function switchList(event: Event): void {
    const slug = (event.target as HTMLSelectElement).value;

    if (slug === '__new') {
        router.post('/lists', {});

        return;
    }

    if (slug !== props.slug) {
        router.visit(`/l/${slug}`);
    }
}

function setLayout(value: WishlistLayout): void {
    if (value !== layout.value) {
        layout.value = value;
        save({ layout: value });
    }
}

function onThemeChange(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;
    theme.value = value;
    save({ theme: value });
}

function onAccentInput(event: Event): void {
    accent.value = (event.target as HTMLInputElement).value;
}

function saveAccent(): void {
    save({ accent: accent.value });
}

function resetAccent(): void {
    accent.value = null;
    save({ accent: null });
}

function onDateChange(event: Event): void {
    const value = (event.target as HTMLInputElement).value;
    save({ event_date: value === '' ? null : value });
}

function savePin(): void {
    if (pin.value.trim() !== '') {
        save({ pin: pin.value.trim() });
        pin.value = '';
    }
}

function removePin(): void {
    save({ pin: null });
}

function sendBulk(): void {
    if (
        props.slug === null ||
        bulkText.value.trim() === '' ||
        bulkSending.value
    ) {
        return;
    }

    bulkSending.value = true;
    router.post(
        `/l/${props.slug}/items/bulk`,
        { urls: bulkText.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                bulkText.value = '';
                bulkOpen.value = false;
            },
            onFinish: () => {
                bulkSending.value = false;
            },
        },
    );
}

async function copyLink(): Promise<void> {
    if (props.shareUrl === null) {
        return;
    }

    try {
        await navigator.clipboard.writeText(props.shareUrl);
        toast('Skopiowano link do listy');
        copied.value = true;
        window.setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch {
        window.prompt('Skopiuj link do listy:', props.shareUrl);
    }
}

async function deleteList(): Promise<void> {
    if (props.slug === null) {
        return;
    }

    const confirmed = await confirmAction({
        title: 'Usunąć całą listę?',
        text: `Lista „${props.wishlist.title}” zniknie razem ze wszystkimi prezentami. Tego nie da się cofnąć.`,
        confirmLabel: 'Usuń listę',
    });

    if (confirmed) {
        router.delete(`/l/${props.slug}`);
    }
}
</script>

<template>
    <aside
        class="flex flex-col gap-6 lg:sticky lg:top-6 lg:order-2"
        aria-label="Opcje listy"
    >
        <div v-if="lists.length > 0" class="flex flex-col gap-2.5">
            <label for="list-switch" class="text-sm font-semibold"
                >Twoje listy</label
            >
            <select
                id="list-switch"
                :value="slug ?? ''"
                class="skin-outline h-11 w-full rounded-control bg-surface px-3 text-[15px] text-ink"
                @change="switchList"
            >
                <optgroup v-if="currentLists.length > 0" label="Aktualne">
                    <option
                        v-for="list in currentLists"
                        :key="list.slug"
                        :value="list.slug"
                    >
                        {{ list.title }}
                    </option>
                </optgroup>
                <optgroup v-if="pastLists.length > 0" label="Minione">
                    <option
                        v-for="list in pastLists"
                        :key="list.slug"
                        :value="list.slug"
                    >
                        {{ list.title }}
                    </option>
                </optgroup>
                <option value="__new">+ Nowa lista</option>
            </select>
        </div>

        <form
            class="skin-outline flex flex-col gap-3 rounded-card bg-surface p-4 shadow-card"
            @submit.prevent="submitLink"
        >
            <label for="item-url" class="skin-display text-[22px]"
                >Dodaj prezent</label
            >
            <input
                id="item-url"
                v-model="url"
                v-focus
                type="url"
                inputmode="url"
                placeholder="Wklej link do prezentu"
                class="skin-outline h-12 w-full rounded-control bg-surface px-3.5 text-base text-ink placeholder:text-muted"
            />
            <button
                type="submit"
                :disabled="loadingPreview"
                class="skin-outline h-12 rounded-control bg-accent px-6 text-base font-semibold text-on-accent disabled:opacity-60"
            >
                {{ loadingPreview ? 'Pobieram dane…' : 'Dodaj' }}
            </button>
            <p
                v-if="urlError || errors.url"
                role="alert"
                class="text-sm font-semibold text-accent"
            >
                {{ urlError || errors.url }}
            </p>
            <p class="hidden text-xs text-muted lg:block">
                Możesz też wkleić link (Ctrl+V) w dowolnym miejscu strony albo
                przeciągnąć go tutaj.
            </p>
            <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                <button
                    type="button"
                    class="h-9 text-muted underline underline-offset-4"
                    @click="emit('add-manual')"
                >
                    Prezent bez linku
                </button>
                <button
                    v-if="slug"
                    type="button"
                    :aria-expanded="bulkOpen"
                    class="h-9 text-muted underline underline-offset-4"
                    @click="bulkOpen = !bulkOpen"
                >
                    Kilka linków naraz
                </button>
            </div>
            <template v-if="bulkOpen && slug">
                <label for="bulk-urls" class="text-sm font-semibold"
                    >Linki, każdy w nowej linii (do 20)</label
                >
                <textarea
                    id="bulk-urls"
                    v-model="bulkText"
                    rows="5"
                    class="skin-outline w-full rounded-control bg-surface px-3 py-2 text-sm text-ink"
                ></textarea>
                <button
                    type="button"
                    :disabled="bulkSending"
                    class="skin-outline h-11 rounded-control bg-surface px-4 text-sm font-semibold text-ink disabled:opacity-60"
                    @click="sendBulk"
                >
                    {{
                        bulkSending
                            ? 'Dodaję, to chwilę potrwa…'
                            : 'Dodaj wszystkie'
                    }}
                </button>
                <p
                    v-if="errors.urls"
                    role="alert"
                    class="text-sm font-semibold text-accent"
                >
                    {{ errors.urls }}
                </p>
            </template>
        </form>

        <div v-if="shareUrl" class="flex flex-col gap-2.5">
            <h2 class="text-sm font-semibold">Udostępnij</h2>
            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    class="skin-outline h-11 flex-1 rounded-control bg-surface px-4 text-sm font-semibold text-ink"
                    @click="copyLink"
                >
                    {{ copied ? 'Skopiowano' : 'Kopiuj link' }}
                </button>
                <button
                    type="button"
                    class="skin-outline h-11 rounded-control bg-surface px-4 text-sm font-semibold text-ink"
                    @click="emit('open-qr')"
                >
                    Kod QR
                </button>
            </div>
            <p class="text-xs break-all text-muted">{{ shareUrl }}</p>
        </div>

        <details
            class="skin-outline group rounded-card bg-surface p-4"
            :open="settingsOpen"
            @toggle="onSettingsToggle"
        >
            <summary
                class="flex cursor-pointer list-none items-center justify-between text-[15px] font-semibold"
            >
                Ustawienia listy
                <span
                    aria-hidden="true"
                    class="text-xl leading-none transition-transform group-open:rotate-45"
                    >+</span
                >
            </summary>
            <div class="mt-5 flex flex-col gap-6">
                <div
                    role="group"
                    aria-labelledby="layout-heading"
                    class="flex flex-col gap-2.5"
                >
                    <h2 id="layout-heading" class="text-sm font-semibold">
                        Widok
                    </h2>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="option in layoutOptions"
                            :key="option.value"
                            type="button"
                            :aria-pressed="option.value === layout"
                            class="skin-outline h-11 flex-1 rounded-control px-4 text-sm font-semibold"
                            :class="
                                option.value === layout
                                    ? 'bg-ink text-ground'
                                    : 'bg-surface text-ink'
                            "
                            @click="setLayout(option.value)"
                        >
                            {{ option.label }}
                        </button>
                    </div>
                </div>

                <div class="flex flex-col gap-2.5">
                    <label for="list-theme" class="text-sm font-semibold"
                        >Skórka</label
                    >
                    <select
                        id="list-theme"
                        :value="theme"
                        class="skin-outline h-11 w-full rounded-control bg-surface px-3 text-[15px] text-ink"
                        @change="onThemeChange"
                    >
                        <option
                            v-for="option in themes"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <div class="flex items-center gap-3">
                        <label for="list-accent" class="text-sm"
                            >Kolor akcentu</label
                        >
                        <input
                            id="list-accent"
                            type="color"
                            :value="accent ?? '#b5302a'"
                            class="h-11 w-14 cursor-pointer rounded-control bg-transparent"
                            @input="onAccentInput"
                            @change="saveAccent"
                        />
                        <button
                            v-if="accent"
                            type="button"
                            class="h-11 text-sm text-muted underline underline-offset-4"
                            @click="resetAccent"
                        >
                            Domyślny
                        </button>
                    </div>
                </div>

                <div v-if="slug" class="flex flex-col gap-2.5">
                    <label for="list-date" class="text-sm font-semibold"
                        >Data wydarzenia</label
                    >
                    <input
                        id="list-date"
                        type="date"
                        :value="wishlist.event_date ?? ''"
                        class="skin-outline h-11 w-full rounded-control bg-surface px-3 text-[15px] text-ink"
                        @change="onDateChange"
                    />
                    <p class="text-xs text-muted">
                        Po tej dacie lista trafi do „Minione”.
                    </p>
                </div>

                <div v-if="slug" class="flex flex-col gap-2">
                    <form class="flex flex-col gap-2" @submit.prevent="savePin">
                        <label for="list-pin" class="text-sm">
                            {{
                                wishlist.has_pin
                                    ? 'Lista chroniona PIN-em'
                                    : 'Ochrona PIN-em (opcjonalnie)'
                            }}
                        </label>
                        <div class="flex gap-2">
                            <input
                                id="list-pin"
                                v-model="pin"
                                type="text"
                                inputmode="numeric"
                                maxlength="8"
                                autocomplete="off"
                                :placeholder="
                                    wishlist.has_pin ? 'nowy PIN' : '4–8 cyfr'
                                "
                                class="skin-outline h-11 min-w-0 flex-1 rounded-control bg-surface px-3 text-[15px] text-ink placeholder:text-muted"
                            />
                            <button
                                type="submit"
                                class="skin-outline h-11 rounded-control bg-surface px-4 text-sm font-semibold text-ink"
                            >
                                Ustaw
                            </button>
                        </div>
                        <button
                            v-if="wishlist.has_pin"
                            type="button"
                            class="h-9 self-start text-sm text-muted underline underline-offset-4"
                            @click="removePin"
                        >
                            Wyłącz PIN
                        </button>
                        <p
                            v-if="errors.pin"
                            role="alert"
                            class="text-sm font-semibold text-accent"
                        >
                            {{ errors.pin }}
                        </p>
                    </form>
                </div>

                <button
                    v-if="slug"
                    type="button"
                    class="h-9 self-start text-sm text-muted underline underline-offset-4"
                    @click="deleteList"
                >
                    Usuń tę listę
                </button>
            </div>
        </details>
    </aside>
</template>
