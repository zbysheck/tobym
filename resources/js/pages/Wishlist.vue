<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AddGiftDialog from '@/components/AddGiftDialog.vue';
import GiftItem from '@/components/GiftItem.vue';
import type {
    GiftPreview,
    ThemeOption,
    WishlistData,
    WishlistItem,
    WishlistLayout,
} from '@/types';

type SortKey = 'added' | 'newest' | 'price-asc' | 'price-desc' | 'name';

const props = defineProps<{
    wishlist: WishlistData;
    themes: ThemeOption[];
    editable: boolean;
    shareUrl: string | null;
}>();

const sortOptions: { value: SortKey; label: string }[] = [
    { value: 'added', label: 'Kolejność dodania' },
    { value: 'newest', label: 'Najnowsze' },
    { value: 'price-asc', label: 'Cena: od najniższej' },
    { value: 'price-desc', label: 'Cena: od najwyższej' },
    { value: 'name', label: 'Nazwa: A–Z' },
];

const layoutOptions: { value: WishlistLayout; label: string }[] = [
    { value: 'tiles', label: 'Kafelki' },
    { value: 'rows', label: 'Rzędy' },
];

const page = usePage();
const user = computed(() => page.props.auth.user);
const status = computed(() => page.props.flash.status);
const errors = computed(() => page.props.errors);

const theme = ref(props.wishlist.theme);
const layout = ref<WishlistLayout>(props.wishlist.layout);
watch(
    () => [props.wishlist.theme, props.wishlist.layout] as const,
    ([nextTheme, nextLayout]) => {
        theme.value = nextTheme;
        layout.value = nextLayout;
    },
);

const url = ref('');
const urlError = ref('');
const loadingPreview = ref(false);
const preview = ref<GiftPreview | null>(null);
const savingGift = ref(false);

const email = ref('');
const sending = ref(false);

const search = ref('');
const sort = ref<SortKey>('added');
const shop = ref('');

const shops = computed(() =>
    [
        ...new Set(
            props.wishlist.items
                .map((item) => item.shop)
                .filter((name): name is string => name !== null),
        ),
    ].sort((a, b) => a.localeCompare(b, 'pl')),
);

function priceValue(item: WishlistItem): number | null {
    const match = /\d+(?:\.\d+)?/.exec(
        (item.price ?? '').replace(/[\s ]/g, '').replace(',', '.'),
    );

    return match ? Number(match[0]) : null;
}

function byPrice(direction: 1 | -1) {
    return (a: WishlistItem, b: WishlistItem): number => {
        const first = priceValue(a);
        const second = priceValue(b);

        if (first === null || second === null) {
            return first === second ? 0 : first === null ? 1 : -1;
        }

        return (first - second) * direction;
    };
}

const visibleItems = computed(() => {
    const phrase = search.value.trim().toLowerCase();
    const items = props.wishlist.items.filter(
        (item) =>
            (shop.value === '' || item.shop === shop.value) &&
            (phrase === '' || item.title.toLowerCase().includes(phrase)),
    );

    switch (sort.value) {
        case 'newest':
            return items.reverse();
        case 'price-asc':
            return items.sort(byPrice(1));
        case 'price-desc':
            return items.sort(byPrice(-1));
        case 'name':
            return items.sort((a, b) => a.title.localeCompare(b.title, 'pl'));
        default:
            return items;
    }
});

const countLabel = computed(() => {
    const count = props.wishlist.items.length;
    const lastDigit = count % 10;
    const lastTwo = count % 100;

    if (count === 0) {
        return 'Lista jest jeszcze pusta';
    }

    if (count === 1) {
        return '1 prezent na liście';
    }

    if (lastDigit >= 2 && lastDigit <= 4 && (lastTwo < 12 || lastTwo > 14)) {
        return `${count} prezenty na liście`;
    }

    return `${count} prezentów na liście`;
});

function xsrfToken(): string {
    const match = /(?:^|; )XSRF-TOKEN=([^;]+)/.exec(document.cookie);

    return match ? decodeURIComponent(match[1]) : '';
}

type PreviewResult =
    | { ok: true; preview: GiftPreview }
    | { ok: false; error: string };

async function fetchPreview(
    link: string,
    deep: boolean,
): Promise<PreviewResult> {
    try {
        const response = await fetch('/items/preview', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            body: JSON.stringify({ url: link, deep }),
        });

        if (response.status === 422) {
            const body = (await response.json()) as {
                errors?: Record<string, string[]>;
            };

            return {
                ok: false,
                error: body.errors?.url?.[0] ?? 'Nie udało się odczytać linku.',
            };
        }

        if (!response.ok) {
            return {
                ok: false,
                error: 'Nie udało się pobrać danych. Odśwież stronę i spróbuj ponownie.',
            };
        }

        return { ok: true, preview: (await response.json()) as GiftPreview };
    } catch {
        return { ok: false, error: 'Brak połączenia. Spróbuj ponownie.' };
    }
}

const enriching = ref(false);

async function requestPreview(): Promise<void> {
    const link = url.value.trim();

    if (link === '' || loadingPreview.value) {
        return;
    }

    loadingPreview.value = true;
    urlError.value = '';

    const quick = await fetchPreview(link, false);
    loadingPreview.value = false;

    if (!quick.ok) {
        urlError.value = quick.error;

        return;
    }

    preview.value = quick.preview;

    if (!quick.preview.pending) {
        return;
    }

    // Okno już jest otwarte; AI dociąga brakujące dane w tle.
    enriching.value = true;
    const deep = await fetchPreview(link, true);
    enriching.value = false;

    if (!deep.ok || preview.value?.url !== link) {
        return;
    }

    const current = preview.value;
    preview.value = {
        ...current,
        title: deep.preview.title,
        image_url: current.image_url ?? deep.preview.image_url,
        price: current.price ?? deep.preview.price,
        shop: current.shop ?? deep.preview.shop,
        pending: false,
    };
}

function submitLink(): void {
    void requestPreview();
}

function confirmGift(details: { title: string; price: string }): void {
    const current = preview.value;

    if (current === null) {
        return;
    }

    savingGift.value = true;
    router.post(
        '/items',
        {
            url: current.url,
            title: details.title,
            price: details.price,
            shop: current.shop,
            image_url: current.image_url,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                url.value = '';
                preview.value = null;
            },
            onFinish: () => {
                savingGift.value = false;
            },
        },
    );
}

function cancelGift(): void {
    preview.value = null;
}

function saveTitle(event: Event): void {
    const title = (event.target as HTMLInputElement).value.trim();

    if (title !== '' && title !== props.wishlist.title) {
        router.patch('/list', { title }, { preserveScroll: true });
    }
}

function setTheme(value: string): void {
    if (value !== theme.value) {
        theme.value = value;
        router.patch('/list', { theme: value }, { preserveScroll: true });
    }
}

function setLayout(value: WishlistLayout): void {
    if (value !== layout.value) {
        layout.value = value;
        router.patch('/list', { layout: value }, { preserveScroll: true });
    }
}

function sendLink(): void {
    if (email.value.trim() === '' || sending.value) {
        return;
    }

    sending.value = true;
    router.post(
        '/login-link',
        { email: email.value.trim() },
        {
            preserveScroll: true,
            onFinish: () => {
                sending.value = false;
            },
        },
    );
}

const copied = ref(false);

async function copyLink(): Promise<void> {
    if (props.shareUrl === null) {
        return;
    }

    try {
        await navigator.clipboard.writeText(props.shareUrl);
        copied.value = true;
        window.setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch {
        window.prompt('Skopiuj link do listy:', props.shareUrl);
    }
}

function onThemeChange(event: Event): void {
    setTheme((event.target as HTMLSelectElement).value);
}

function logout(): void {
    router.post('/logout');
}

function blurOnEnter(event: KeyboardEvent): void {
    (event.target as HTMLElement).blur();
}
</script>

<template>
    <Head :title="wishlist.title">
        <link rel="preconnect" href="https://fonts.bunny.net" />
        <link
            rel="stylesheet"
            href="https://fonts.bunny.net/css?family=dm-serif-display:400|dm-sans:400,600|cormorant-garamond:500,500i|jost:400,500|bricolage-grotesque:800|inter:400,600,700&display=swap"
        />
    </Head>

    <div
        :data-theme="theme"
        class="min-h-screen bg-ground font-body text-ink antialiased"
    >
        <div
            v-if="!user && editable"
            class="skin-outline border-x-0 border-t-0 bg-band text-on-band"
        >
            <form
                class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-5 gap-y-3 px-5 py-3.5 sm:px-8"
                @submit.prevent="sendLink"
            >
                <label
                    for="save-email"
                    class="min-w-0 flex-[1_1_18rem] text-[15px] leading-snug"
                >
                    <template v-if="status">{{ status }}</template>
                    <template v-else>
                        <strong class="font-semibold"
                            >Lista jest na razie tylko w tej
                            przeglądarce.</strong
                        >
                        Podaj maila, aby ją zapisać.
                    </template>
                </label>
                <div class="flex flex-[1_1_20rem] flex-wrap gap-2">
                    <input
                        id="save-email"
                        v-model="email"
                        type="email"
                        autocomplete="email"
                        placeholder="twoj@email.pl"
                        class="skin-outline h-11 min-w-0 flex-[1_1_12rem] rounded-control bg-surface px-3.5 text-[15px] text-ink placeholder:text-muted"
                    />
                    <button
                        type="submit"
                        :disabled="sending"
                        class="skin-outline h-11 rounded-control bg-accent px-5 text-[15px] font-semibold text-on-accent disabled:opacity-60"
                    >
                        {{ sending ? 'Wysyłam…' : 'Zapisz listę' }}
                    </button>
                </div>
                <p
                    v-if="errors.email"
                    role="alert"
                    class="basis-full text-sm font-semibold"
                >
                    {{ errors.email }}
                </p>
            </form>
        </div>

        <div class="mx-auto max-w-6xl px-5 pb-24 sm:px-8">
            <header
                class="flex flex-wrap items-center justify-between gap-4 py-5"
            >
                <div class="skin-display text-[26px]">tobym</div>
                <button
                    v-if="user"
                    type="button"
                    class="h-11 px-1 text-sm text-muted underline underline-offset-4"
                    @click="logout"
                >
                    Wyloguj ({{ user.email }})
                </button>
            </header>

            <p
                v-if="user && status"
                role="status"
                class="skin-outline rounded-control bg-surface px-4 py-3 text-[15px]"
            >
                {{ status }}
            </p>

            <section class="skin-hero flex flex-col gap-3 pt-6 pb-9">
                <template v-if="editable">
                    <label for="list-title" class="sr-only">Nazwa listy</label>
                    <input
                        id="list-title"
                        :key="wishlist.title"
                        type="text"
                        :value="wishlist.title"
                        maxlength="120"
                        class="skin-display skin-title w-full bg-transparent text-ink outline-offset-8"
                        @blur="saveTitle"
                        @keydown.enter="blurOnEnter"
                    />
                </template>
                <h1 v-else class="skin-display skin-title text-ink">
                    {{ wishlist.title }}
                </h1>
                <p class="text-[17px] text-muted">{{ countLabel }}</p>
            </section>

            <div
                class="grid gap-8"
                :class="
                    editable
                        ? 'lg:grid-cols-[minmax(0,1fr)_19rem] lg:items-start'
                        : ''
                "
            >
                <aside
                    v-if="editable"
                    class="flex flex-col gap-6 lg:sticky lg:top-6 lg:order-2"
                    aria-label="Opcje listy"
                >
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
                    </form>

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
                    </div>

                    <div v-if="shareUrl" class="flex flex-col gap-2.5">
                        <h2 class="text-sm font-semibold">Udostępnij</h2>
                        <button
                            type="button"
                            class="skin-outline h-11 rounded-control bg-surface px-4 text-sm font-semibold text-ink"
                            @click="copyLink"
                        >
                            {{
                                copied
                                    ? 'Skopiowano link'
                                    : 'Kopiuj link do listy'
                            }}
                        </button>
                        <p class="text-xs break-all text-muted">
                            {{ shareUrl }}
                        </p>
                    </div>
                </aside>

                <main class="flex min-w-0 flex-col gap-6 lg:order-1">
                    <div
                        v-if="wishlist.items.length > 0"
                        class="flex flex-wrap items-end gap-3"
                    >
                        <div
                            class="flex min-w-0 flex-[1_1_14rem] flex-col gap-1.5"
                        >
                            <label
                                for="list-search"
                                class="text-sm font-semibold"
                                >Szukaj</label
                            >
                            <input
                                id="list-search"
                                v-model="search"
                                type="search"
                                placeholder="Nazwa prezentu"
                                class="skin-outline h-11 w-full rounded-control bg-surface px-3.5 text-[15px] text-ink placeholder:text-muted"
                            />
                        </div>
                        <div
                            v-if="shops.length > 1"
                            class="flex min-w-0 flex-[1_1_10rem] flex-col gap-1.5"
                        >
                            <label for="list-shop" class="text-sm font-semibold"
                                >Sklep</label
                            >
                            <select
                                id="list-shop"
                                v-model="shop"
                                class="skin-outline h-11 w-full rounded-control bg-surface px-3 text-[15px] text-ink"
                            >
                                <option value="">Wszystkie sklepy</option>
                                <option
                                    v-for="name in shops"
                                    :key="name"
                                    :value="name"
                                >
                                    {{ name }}
                                </option>
                            </select>
                        </div>
                        <div
                            class="flex min-w-0 flex-[1_1_12rem] flex-col gap-1.5"
                        >
                            <label for="list-sort" class="text-sm font-semibold"
                                >Sortowanie</label
                            >
                            <select
                                id="list-sort"
                                v-model="sort"
                                class="skin-outline h-11 w-full rounded-control bg-surface px-3 text-[15px] text-ink"
                            >
                                <option
                                    v-for="option in sortOptions"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <p
                        v-if="wishlist.items.length === 0"
                        class="skin-outline rounded-card bg-surface p-8 text-center text-[17px] text-muted"
                    >
                        <template v-if="editable"
                            >Wklej link do pierwszego prezentu w polu „Dodaj
                            prezent”.</template
                        >
                        <template v-else>Ta lista jest jeszcze pusta.</template>
                    </p>
                    <p
                        v-else-if="visibleItems.length === 0"
                        class="text-[17px] text-muted"
                    >
                        Żaden prezent nie pasuje do tych filtrów.
                    </p>

                    <TransitionGroup
                        tag="ul"
                        name="item"
                        class="relative"
                        :class="
                            layout === 'rows'
                                ? 'flex flex-col gap-4'
                                : 'grid grid-cols-[repeat(auto-fill,minmax(15.5rem,1fr))] gap-6'
                        "
                    >
                        <GiftItem
                            v-for="item in visibleItems"
                            :key="item.id"
                            :item="item"
                            :layout="layout"
                            :editable="editable"
                        />
                    </TransitionGroup>
                </main>
            </div>
        </div>

        <AddGiftDialog
            :preview="preview"
            :enriching="enriching"
            :saving="savingGift"
            @confirm="confirmGift"
            @cancel="cancelGift"
        />
    </div>
</template>
