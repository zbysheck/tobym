<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import AddGiftDialog from '@/components/AddGiftDialog.vue';
import AppHeader from '@/components/AppHeader.vue';
import GiftItem from '@/components/GiftItem.vue';
import ImageLightbox from '@/components/ImageLightbox.vue';
import ListSidebar from '@/components/ListSidebar.vue';
import QrDialog from '@/components/QrDialog.vue';
import ReserveDialog from '@/components/ReserveDialog.vue';
import SiteFooter from '@/components/SiteFooter.vue';
import UiLayer from '@/components/UiLayer.vue';
import {
    countdown,
    fetchPreview,
    formatDate,
    itemsUrl,
    listUrl,
    priceValue,
    textOn,
} from '@/lib/wishlist';
import { themeFontsUrl } from '@/lib/themes';
import { celebrate } from '@/lib/celebrate';
import { toast } from '@/lib/ui';
import type {
    GiftDetails,
    GiftPreview,
    ListSummary,
    ThemeOption,
    WishlistData,
    WishlistItem,
    WishlistLayout,
} from '@/types';

type SortKey =
    | 'added'
    | 'priority'
    | 'newest'
    | 'price-asc'
    | 'price-desc'
    | 'name';

const props = defineProps<{
    wishlist: WishlistData;
    themes: ThemeOption[];
    editable: boolean;
    slug: string | null;
    shareUrl: string | null;
    lists: ListSummary[];
}>();

const sortOptions: { value: SortKey; label: string }[] = [
    { value: 'added', label: 'Moja kolejność' },
    { value: 'priority', label: 'Najważniejsze najpierw' },
    { value: 'newest', label: 'Najnowsze' },
    { value: 'price-asc', label: 'Cena: od najniższej' },
    { value: 'price-desc', label: 'Cena: od najwyższej' },
    { value: 'name', label: 'Nazwa: A–Z' },
];

const page = usePage();
const user = computed(() => page.props.auth.user);
const status = computed(() => page.props.flash.status);
const errors = computed(() => page.props.errors);

const theme = ref(props.wishlist.theme);
const layout = ref<WishlistLayout>(props.wishlist.layout);
const accent = ref<string | null>(props.wishlist.accent);
watch(
    () => props.wishlist,
    (next) => {
        theme.value = next.theme;
        layout.value = next.layout;
        accent.value = next.accent;
    },
);

const accentStyle = computed(() =>
    accent.value
        ? {
              '--skin-accent': accent.value,
              '--skin-on-accent': textOn(accent.value),
          }
        : {},
);

const sidebar = ref<InstanceType<typeof ListSidebar> | null>(null);
const urlError = ref('');
const loadingPreview = ref(false);
const enriching = ref(false);
const preview = ref<GiftPreview | null>(null);
const savingGift = ref(false);
const giftErrors = ref<Record<string, string>>({});
const qrOpen = ref(false);

const email = ref('');
const sending = ref(false);

const search = ref('');
const onlyFree = ref(false);
const sort = ref<SortKey>('added');
const shop = ref('');
const filtersOpen = ref(false);

const shops = computed(() =>
    [
        ...new Set(
            props.wishlist.items
                .map((item) => item.shop)
                .filter((name): name is string => name !== null),
        ),
    ].sort((a, b) => a.localeCompare(b, 'pl')),
);

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
            (!onlyFree.value || item.reservation === null) &&
            (phrase === '' ||
                item.title.toLowerCase().includes(phrase) ||
                (item.note ?? '').toLowerCase().includes(phrase)),
    );

    switch (sort.value) {
        case 'priority':
            return items.sort(
                (a, b) => Number(b.priority) - Number(a.priority),
            );
        case 'newest':
            return items.sort((a, b) => b.id - a.id);
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

// Przeciąganie działa tylko w "mojej kolejności", bez filtrów, żeby kolejność była jednoznaczna.
const canReorder = computed(
    () =>
        props.editable &&
        props.slug !== null &&
        sort.value === 'added' &&
        search.value.trim() === '' &&
        shop.value === '',
);
const orderedItems = ref<WishlistItem[]>([...props.wishlist.items]);
const highlightId = ref<number | null>(null);
watch(
    () => props.wishlist.items,
    (items, previous) => {
        orderedItems.value = [...items];

        // Nowo dodany prezent na chwilę się podświetla.
        const known = new Set((previous ?? []).map((item) => item.id));
        const added = items.find((item) => !known.has(item.id));

        if (previous !== undefined && added !== undefined) {
            highlightId.value = added.id;
            window.setTimeout(() => {
                highlightId.value = null;
            }, 1800);
        }
    },
);

const zoomed = ref<WishlistItem | null>(null);
const quickAddOpen = ref(false);
const quickAdd = ref<HTMLDialogElement | null>(null);
const quickUrl = ref('');

watch(
    quickAddOpen,
    (open) => {
        if (open) {
            quickUrl.value = '';
            quickAdd.value?.showModal();
        } else {
            quickAdd.value?.close();
        }
    },
    { flush: 'post' },
);

function submitQuickAdd(): void {
    const link = quickUrl.value.trim();

    if (link !== '') {
        quickAddOpen.value = false;
        void addLink(link);
    }
}

function quickManual(): void {
    quickAddOpen.value = false;
    addManual();
}

function saveOrder(): void {
    router.patch(
        `/l/${props.slug ?? ''}/order`,
        { ids: orderedItems.value.map((item) => item.id) },
        { preserveScroll: true },
    );
}

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

const eventLabel = computed(() =>
    props.wishlist.event_date
        ? `${formatDate(props.wishlist.event_date)} · ${countdown(props.wishlist.event_date)}`
        : null,
);

async function addLink(link: string): Promise<void> {
    if (loadingPreview.value) {
        return;
    }

    loadingPreview.value = true;
    urlError.value = '';
    giftErrors.value = {};

    const quick = await fetchPreview(link, false);
    loadingPreview.value = false;

    if (!quick.ok) {
        urlError.value = quick.error;
        toast(quick.error);

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

function addManual(): void {
    giftErrors.value = {};
    preview.value = {
        url: null,
        title: '',
        shop: null,
        image_url: null,
        price: null,
        pending: false,
    };
}

function confirmGift(details: GiftDetails): void {
    const current = preview.value;

    if (current === null) {
        return;
    }

    savingGift.value = true;
    router.post(
        itemsUrl(props.slug),
        {
            url: current.url,
            title: details.title,
            price: details.price,
            note: details.note,
            priority: details.priority ? 1 : 0,
            shop: current.shop,
            image_url: current.image_url,
            photo: details.photo,
        },
        {
            preserveScroll: true,
            forceFormData: details.photo !== null,
            onSuccess: () => {
                sidebar.value?.clearLink();
                preview.value = null;
                toast('Dodano do listy');
                celebrate({ x: 0.5, y: 0.35 });
            },
            onError: (bag) => {
                giftErrors.value = bag;
            },
            onFinish: () => {
                savingGift.value = false;
            },
        },
    );
}

function saveTitle(event: Event): void {
    const title = (event.target as HTMLInputElement).value.trim();

    if (title !== '' && title !== props.wishlist.title) {
        router.patch(
            listUrl(props.slug),
            { title },
            { preserveScroll: true, onSuccess: () => toast('Zapisano') },
        );
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

const NAME_KEY = 'tobym:guest-name';
const reserving = ref<WishlistItem | null>(null);
const guestName = ref(readGuestName());

function readGuestName(): string {
    try {
        return window.localStorage.getItem(NAME_KEY) ?? '';
    } catch {
        return '';
    }
}

function reservationUrl(item: WishlistItem): string {
    return `/l/${props.slug ?? ''}/items/${item.id}/reservation`;
}

function confirmReservation(name: string): void {
    const item = reserving.value;

    if (item === null) {
        return;
    }

    guestName.value = name;

    try {
        window.localStorage.setItem(NAME_KEY, name);
    } catch {
        // Bez pamięci przeglądarki imię trzeba będzie podać ponownie.
    }

    router.post(
        reservationUrl(item),
        { name },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast('Zarezerwowano. Dziękujemy!');
                celebrate();
            },
            onFinish: () => {
                reserving.value = null;
            },
        },
    );
}

function cancelReservation(item: WishlistItem): void {
    router.delete(reservationUrl(item), {
        preserveScroll: true,
        onSuccess: () => toast('Rezerwacja cofnięta'),
    });
}

function blurOnEnter(event: KeyboardEvent): void {
    (event.target as HTMLElement).blur();
}

const listClass = computed(() =>
    layout.value === 'rows'
        ? 'flex flex-col gap-4'
        : 'grid grid-cols-2 gap-3 sm:grid-cols-[repeat(auto-fill,minmax(15.5rem,1fr))] sm:gap-6',
);
</script>

<template>
    <Head :title="wishlist.title">
        <link rel="preconnect" href="https://fonts.bunny.net" />
        <link rel="stylesheet" :href="themeFontsUrl(theme)" />
    </Head>

    <div
        :data-theme="theme"
        :style="accentStyle"
        class="skin-backdrop min-h-screen bg-ground font-body text-ink antialiased"
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

        <div class="skin-band-stripe" aria-hidden="true"></div>

        <div class="mx-auto max-w-6xl px-5 pb-24 sm:px-8">
            <AppHeader />

            <p
                v-if="status && (user || !editable)"
                role="status"
                class="skin-outline rounded-control bg-surface px-4 py-3 text-[15px]"
            >
                {{ status }}
            </p>

            <section class="skin-hero flex flex-col gap-3 pt-6 pb-9">
                <div class="skin-ornament" aria-hidden="true"></div>
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
                <p v-if="eventLabel" class="text-[17px] font-semibold">
                    {{ eventLabel }}
                </p>
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
                <ListSidebar
                    v-if="editable"
                    ref="sidebar"
                    v-model:theme="theme"
                    v-model:layout="layout"
                    v-model:accent="accent"
                    :wishlist="wishlist"
                    :themes="themes"
                    :slug="slug"
                    :share-url="shareUrl"
                    :lists="lists"
                    :loading-preview="loadingPreview"
                    :url-error="urlError"
                    @add-link="addLink"
                    @add-manual="addManual"
                    @open-qr="qrOpen = true"
                />

                <main class="flex min-w-0 flex-col gap-6 lg:order-1">
                    <p
                        v-if="!editable && wishlist.items.length > 0"
                        class="skin-outline rounded-control bg-surface px-4 py-3 text-[15px]"
                    >
                        Kliknij <strong>„Rezerwuję”</strong> przy prezencie,
                        który kupujesz. Inni goście zobaczą, że jest zajęty, a
                        właściciel listy się nie dowie.
                    </p>
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
                                placeholder="Nazwa albo notatka"
                                class="skin-outline h-11 w-full rounded-control bg-surface px-3.5 text-[15px] text-ink placeholder:text-muted"
                            />
                        </div>
                        <button
                            type="button"
                            :aria-expanded="filtersOpen"
                            class="skin-outline h-11 rounded-control bg-surface px-4 text-[15px] font-semibold text-ink sm:hidden"
                            @click="filtersOpen = !filtersOpen"
                        >
                            Filtry
                        </button>
                        <div
                            v-if="shops.length > 1"
                            :class="filtersOpen ? '' : 'max-sm:hidden'"
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
                            :class="filtersOpen ? '' : 'max-sm:hidden'"
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
                        <label
                            v-if="!editable"
                            class="flex h-11 items-center gap-2 text-[15px]"
                            :class="filtersOpen ? '' : 'max-sm:hidden'"
                        >
                            <input
                                v-model="onlyFree"
                                type="checkbox"
                                class="h-5 w-5 accent-accent"
                            />
                            Tylko wolne
                        </label>
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

                    <VueDraggable
                        v-if="canReorder"
                        v-model="orderedItems"
                        tag="ul"
                        handle=".drag-handle"
                        :animation="150"
                        :class="listClass"
                        @end="saveOrder"
                    >
                        <GiftItem
                            v-for="item in orderedItems"
                            :key="item.id"
                            :item="item"
                            :layout="layout"
                            :editable="editable"
                            :draggable="true"
                            :highlight="highlightId === item.id"
                            @zoom="zoomed = $event"
                        />
                    </VueDraggable>
                    <TransitionGroup
                        v-else
                        tag="ul"
                        name="item"
                        class="relative"
                        :class="listClass"
                    >
                        <GiftItem
                            v-for="item in visibleItems"
                            :key="item.id"
                            :item="item"
                            :layout="layout"
                            :editable="editable"
                            :draggable="false"
                            :highlight="highlightId === item.id"
                            @zoom="zoomed = $event"
                            @reserve="reserving = $event"
                            @unreserve="cancelReservation"
                        />
                    </TransitionGroup>
                </main>
            </div>
        </div>

        <SiteFooter />
        <UiLayer />

        <ReserveDialog
            :gift-title="reserving?.title ?? null"
            :initial-name="guestName"
            @confirm="confirmReservation"
            @cancel="reserving = null"
        />

        <AddGiftDialog
            :preview="preview"
            :enriching="enriching"
            :saving="savingGift"
            :errors="giftErrors"
            @confirm="confirmGift"
            @cancel="preview = null"
        />

        <ImageLightbox
            :src="zoomed ? (zoomed.image_large ?? zoomed.image_url) : null"
            :alt="zoomed?.title ?? ''"
            @close="zoomed = null"
        />

        <button
            v-if="editable"
            type="button"
            class="press fixed right-4 bottom-5 z-30 flex h-14 items-center gap-2 rounded-full bg-accent px-5 text-base font-semibold text-on-accent shadow-[0_12px_28px_-10px_rgb(0_0_0/0.5)] lg:hidden"
            @click="quickAddOpen = true"
        >
            <span aria-hidden="true" class="text-2xl leading-none">+</span>
            Dodaj prezent
        </button>

        <dialog
            ref="quickAdd"
            aria-labelledby="quick-add-heading"
            class="sheet skin-outline m-auto w-[min(30rem,calc(100vw-2rem))] rounded-card bg-surface p-0 font-body text-ink shadow-card backdrop:bg-black/50"
            @close="quickAddOpen = false"
        >
            <form
                class="flex flex-col gap-4 p-6"
                @submit.prevent="submitQuickAdd"
            >
                <h2 id="quick-add-heading" class="skin-display text-[26px]">
                    Dodaj prezent
                </h2>
                <label for="quick-url" class="sr-only">Link do prezentu</label>
                <input
                    id="quick-url"
                    v-model="quickUrl"
                    type="url"
                    inputmode="url"
                    placeholder="Wklej link do prezentu"
                    class="skin-outline h-12 w-full rounded-control bg-surface px-3.5 text-base text-ink placeholder:text-muted"
                />
                <button
                    type="submit"
                    class="skin-outline press h-12 rounded-control bg-accent px-6 text-base font-semibold text-on-accent"
                >
                    Dodaj z linku
                </button>
                <button
                    type="button"
                    class="skin-outline press h-12 rounded-control bg-surface px-6 text-base font-semibold text-ink"
                    @click="quickManual"
                >
                    Prezent bez linku
                </button>
            </form>
        </dialog>

        <QrDialog
            :url="qrOpen ? shareUrl : null"
            :title="wishlist.title"
            @close="qrOpen = false"
        />
    </div>
</template>
