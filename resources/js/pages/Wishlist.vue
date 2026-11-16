<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import type { ThemeOption, WishlistData, WishlistItem } from '@/types';

const props = defineProps<{
    wishlist: WishlistData;
    themes: ThemeOption[];
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);
const status = computed(() => page.props.flash.status);
const errors = computed(() => page.props.errors);

const theme = ref(props.wishlist.theme);
watch(
    () => props.wishlist.theme,
    (value) => {
        theme.value = value;
    },
);

const url = ref('');
const adding = ref(false);
const email = ref('');
const sending = ref(false);

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

function inputValue(event: Event): string {
    return (event.target as HTMLInputElement).value.trim();
}

function addItem(): void {
    if (url.value.trim() === '' || adding.value) {
        return;
    }

    adding.value = true;
    router.post(
        '/items',
        { url: url.value.trim() },
        {
            preserveScroll: true,
            onSuccess: () => {
                url.value = '';
            },
            onFinish: () => {
                adding.value = false;
            },
        },
    );
}

function saveTitle(event: Event): void {
    const title = inputValue(event);

    if (title !== '' && title !== props.wishlist.title) {
        router.patch('/list', { title }, { preserveScroll: true });
    }
}

function setTheme(value: string): void {
    if (value === theme.value) {
        return;
    }

    theme.value = value;
    router.patch('/list', { theme: value }, { preserveScroll: true });
}

function saveItemTitle(item: WishlistItem, event: Event): void {
    const title = inputValue(event);

    if (title !== '' && title !== item.title) {
        router.patch(`/items/${item.id}`, { title }, { preserveScroll: true });
    }
}

function saveItemPrice(item: WishlistItem, event: Event): void {
    const price = inputValue(event);

    if (price !== (item.price ?? '')) {
        router.patch(
            `/items/${item.id}`,
            { price: price === '' ? null : price },
            { preserveScroll: true },
        );
    }
}

function removeItem(item: WishlistItem): void {
    router.delete(`/items/${item.id}`, { preserveScroll: true });
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

function logout(): void {
    router.post('/logout');
}

function blurOnEnter(event: KeyboardEvent): void {
    (event.target as HTMLInputElement).blur();
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
            v-if="!user"
            class="skin-outline border-x-0 border-t-0 bg-band text-on-band"
        >
            <form
                class="mx-auto flex max-w-5xl flex-wrap items-center gap-x-5 gap-y-3 px-5 py-3.5 sm:px-8"
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

        <div class="mx-auto max-w-5xl px-5 pb-24 sm:px-8">
            <header
                class="flex flex-wrap items-center justify-between gap-4 py-6"
            >
                <div class="skin-display text-[28px]">tobym</div>
                <div class="flex flex-wrap items-center gap-2">
                    <div
                        role="group"
                        aria-label="Skórka listy"
                        class="flex flex-wrap gap-2"
                    >
                        <button
                            v-for="option in themes"
                            :key="option.value"
                            type="button"
                            :aria-pressed="option.value === theme"
                            class="skin-outline h-11 rounded-control px-4 text-sm font-semibold"
                            :class="
                                option.value === theme
                                    ? 'bg-ink text-ground'
                                    : 'bg-surface text-ink'
                            "
                            @click="setTheme(option.value)"
                        >
                            {{ option.label }}
                        </button>
                    </div>
                    <button
                        v-if="user"
                        type="button"
                        class="h-11 px-3 text-sm text-muted underline underline-offset-4"
                        @click="logout"
                    >
                        Wyloguj ({{ user.email }})
                    </button>
                </div>
            </header>

            <p
                v-if="user && status"
                role="status"
                class="skin-outline rounded-control bg-surface px-4 py-3 text-[15px]"
            >
                {{ status }}
            </p>

            <section class="skin-hero flex flex-col gap-3 pt-8 pb-9">
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
                <p class="text-[17px] text-muted">{{ countLabel }}</p>
            </section>

            <form
                class="skin-outline flex flex-wrap gap-2.5 rounded-card bg-surface p-2.5 shadow-card"
                @submit.prevent="addItem"
            >
                <label for="item-url" class="sr-only">Link do prezentu</label>
                <input
                    id="item-url"
                    v-model="url"
                    v-focus
                    type="url"
                    inputmode="url"
                    placeholder="Wklej link do prezentu"
                    class="h-12 min-w-0 flex-[1_1_16rem] rounded-control bg-transparent px-3.5 text-[17px] text-ink placeholder:text-muted"
                />
                <button
                    type="submit"
                    :disabled="adding"
                    class="skin-outline h-12 rounded-control bg-accent px-6 text-base font-semibold text-on-accent disabled:opacity-60"
                >
                    {{ adding ? 'Pobieram…' : 'Dodaj' }}
                </button>
            </form>
            <p
                v-if="errors.url"
                role="alert"
                class="mt-2 text-sm font-semibold text-accent"
            >
                {{ errors.url }}
            </p>

            <TransitionGroup
                tag="ul"
                name="item"
                class="relative mt-10 grid grid-cols-[repeat(auto-fill,minmax(16.5rem,1fr))] gap-7"
            >
                <li
                    v-for="item in wishlist.items"
                    :key="item.id"
                    class="skin-outline flex flex-col overflow-hidden rounded-card bg-surface shadow-card"
                >
                    <div
                        class="skin-outline flex aspect-[4/3] items-center justify-center border-x-0 border-t-0 bg-tile"
                    >
                        <img
                            v-if="item.image_url"
                            :src="item.image_url"
                            alt=""
                            loading="lazy"
                            referrerpolicy="no-referrer"
                            class="h-full w-full object-cover"
                        />
                        <span v-else class="text-sm text-ink">bez zdjęcia</span>
                    </div>
                    <div class="flex flex-1 flex-col gap-3 p-4">
                        <div class="flex flex-col gap-1">
                            <label :for="`title-${item.id}`" class="sr-only"
                                >Nazwa prezentu</label
                            >
                            <input
                                :id="`title-${item.id}`"
                                :key="item.title"
                                type="text"
                                :value="item.title"
                                maxlength="255"
                                class="skin-display w-full bg-transparent text-[22px] leading-tight text-ink"
                                @blur="saveItemTitle(item, $event)"
                                @keydown.enter="blurOnEnter"
                            />
                            <a
                                v-if="item.url"
                                :href="item.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="truncate text-sm text-muted underline underline-offset-4"
                                >{{ item.shop ?? 'otwórz stronę' }}</a
                            >
                        </div>
                        <div
                            class="mt-auto flex items-center justify-between gap-3"
                        >
                            <label :for="`price-${item.id}`" class="sr-only"
                                >Cena</label
                            >
                            <input
                                :id="`price-${item.id}`"
                                :key="item.price ?? ''"
                                type="text"
                                :value="item.price ?? ''"
                                maxlength="64"
                                placeholder="cena"
                                class="h-11 min-w-0 flex-1 bg-transparent text-base font-semibold text-ink placeholder:font-normal placeholder:text-muted"
                                @blur="saveItemPrice(item, $event)"
                                @keydown.enter="blurOnEnter"
                            />
                            <button
                                type="button"
                                :aria-label="`Usuń: ${item.title}`"
                                class="skin-outline flex h-11 w-11 flex-none items-center justify-center rounded-control bg-surface text-ink"
                                @click="removeItem(item)"
                            >
                                <svg
                                    aria-hidden="true"
                                    width="18"
                                    height="18"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <path d="M3 6h18" />
                                    <path d="M8 6V4h8v2" />
                                    <path d="M6 6l1 14h10l1-14" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </li>
            </TransitionGroup>
        </div>
    </div>
</template>
