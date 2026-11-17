<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AddGiftDialog from '@/components/AddGiftDialog.vue';
import AppHeader from '@/components/AppHeader.vue';
import DropOverlay from '@/components/DropOverlay.vue';
import SiteFooter from '@/components/SiteFooter.vue';
import UiLayer from '@/components/UiLayer.vue';
import { allThemeFontsUrl } from '@/lib/themes';
import { confirmAction } from '@/lib/ui';
import { useGiftAdder } from '@/lib/useGiftAdder';
import { useLinkCapture } from '@/lib/useLinkCapture';
import { countdown, formatDate } from '@/lib/wishlist';
import type { ThemeOption } from '@/types';

type ListCard = {
    slug: string;
    title: string;
    theme: string;
    accent: string | null;
    event_date: string | null;
    past: boolean;
    items_count: number;
    images: (string | null)[];
    has_pin: boolean;
};

const props = defineProps<{
    lists: ListCard[];
    themes: ThemeOption[];
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);
const status = computed(() => page.props.flash.status);
const errors = computed(() => page.props.errors);

const current = computed(() => props.lists.filter((list) => !list.past));

const adder = useGiftAdder();
const { preview, enriching, saving: savingGift, errors: giftErrors } = adder;
const { dragging } = useLinkCapture(
    (url) => void adder.addLink(url),
    () => true,
);
const targetLists = computed(() =>
    props.lists.map((list) => ({ slug: list.slug, title: list.title })),
);
const past = computed(() => props.lists.filter((list) => list.past));

const dialog = ref<HTMLDialogElement | null>(null);
const creating = ref(false);
const newTitle = ref('');
const newTheme = ref('choinkowa');
const newDate = ref('');
const saving = ref(false);
const email = ref('');
const sending = ref(false);

watch(
    creating,
    (open) => {
        if (open) {
            newTitle.value = '';
            newDate.value = '';
            dialog.value?.showModal();
        } else {
            dialog.value?.close();
        }
    },
    { flush: 'post' },
);

function itemsLabel(count: number): string {
    if (count === 1) {
        return '1 prezent';
    }

    const lastDigit = count % 10;
    const lastTwo = count % 100;

    return lastDigit >= 2 && lastDigit <= 4 && (lastTwo < 12 || lastTwo > 14)
        ? `${count} prezenty`
        : `${count} prezentów`;
}

function createList(): void {
    saving.value = true;
    router.post(
        '/lists',
        {
            title: newTitle.value.trim() || 'Nowa lista',
            theme: newTheme.value,
            event_date: newDate.value || null,
        },
        {
            onFinish: () => {
                saving.value = false;
            },
        },
    );
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

async function deleteAccount(): Promise<void> {
    const confirmed = await confirmAction({
        title: 'Usunąć konto?',
        text: 'Usuniemy Twoje konto, wszystkie listy, prezenty i wgrane zdjęcia. Tego nie da się cofnąć.',
        confirmLabel: 'Usuń konto',
    });

    if (confirmed) {
        router.delete('/account');
    }
}
</script>

<template>
    <Head title="Moje listy">
        <link rel="preconnect" href="https://fonts.bunny.net" />
        <link rel="stylesheet" :href="allThemeFontsUrl()" />
    </Head>

    <div
        data-theme="swiateczna"
        class="skin-backdrop min-h-screen bg-ground font-body text-ink antialiased"
    >
        <div class="mx-auto max-w-6xl px-5 pb-20 sm:px-8">
            <AppHeader />

            <div
                class="flex flex-wrap items-end justify-between gap-4 pt-6 pb-8"
            >
                <div>
                    <h1
                        class="font-brand text-[clamp(2.4rem,6vw,3.6rem)] leading-tight"
                    >
                        Moje listy
                    </h1>
                    <p class="text-[17px] text-muted">
                        {{
                            lists.length === 0
                                ? 'Nie masz jeszcze żadnej listy.'
                                : `Wszystkie listy w jednym miejscu.`
                        }}
                    </p>
                    <p class="mt-1 hidden text-sm text-muted sm:block">
                        Wklej link (Ctrl+V) albo przeciągnij go na stronę, a
                        zapytamy, na którą listę go dodać.
                    </p>
                </div>
                <button
                    type="button"
                    class="skin-outline press inline-flex h-12 items-center gap-2 rounded-control bg-accent px-5 text-base font-semibold text-on-accent shadow-card"
                    @click="creating = true"
                >
                    <span aria-hidden="true" class="text-xl leading-none"
                        >+</span
                    >
                    Nowa lista
                </button>
            </div>

            <p
                v-if="status"
                role="status"
                class="skin-outline mb-6 rounded-control bg-surface px-4 py-3 text-[15px]"
            >
                {{ status }}
            </p>

            <section v-if="current.length > 0" aria-label="Aktualne listy">
                <ul class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <li v-for="list in current" :key="list.slug">
                        <a
                            :href="`/l/${list.slug}`"
                            :data-theme="list.theme"
                            :style="
                                list.accent
                                    ? { '--skin-accent': list.accent }
                                    : {}
                            "
                            class="skin-outline skin-card skin-backdrop lift group flex h-full flex-col gap-4 overflow-hidden rounded-card bg-ground p-5 shadow-card"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div
                                    class="skin-display text-[28px] leading-tight text-ink"
                                >
                                    {{ list.title }}
                                </div>
                                <span
                                    v-if="list.has_pin"
                                    class="rounded-control bg-surface px-2 py-1 text-xs font-semibold text-ink"
                                    >PIN</span
                                >
                            </div>
                            <div class="flex -space-x-3">
                                <div
                                    v-for="(image, index) in list.images"
                                    :key="index"
                                    class="h-16 w-16 overflow-hidden rounded-control border-2 border-surface bg-white"
                                >
                                    <img
                                        v-if="image"
                                        :src="image"
                                        alt=""
                                        loading="lazy"
                                        referrerpolicy="no-referrer"
                                        class="h-full w-full object-contain p-1"
                                    />
                                </div>
                                <div
                                    v-if="list.images.length === 0"
                                    class="flex h-16 items-center rounded-control bg-surface/80 px-3 text-sm text-muted"
                                >
                                    Jeszcze bez prezentów
                                </div>
                            </div>
                            <div
                                class="mt-auto flex flex-wrap items-center justify-between gap-2 text-sm text-ink"
                            >
                                <span class="font-semibold">{{
                                    itemsLabel(list.items_count)
                                }}</span>
                                <span v-if="list.event_date" class="text-muted">
                                    {{ formatDate(list.event_date) }} ·
                                    {{ countdown(list.event_date) }}
                                </span>
                            </div>
                        </a>
                    </li>
                </ul>
            </section>

            <button
                v-if="lists.length === 0"
                type="button"
                class="skin-outline press w-full rounded-card border-dashed bg-surface/70 p-10 text-center text-lg text-muted"
                @click="creating = true"
            >
                Stwórz pierwszą listę
            </button>

            <section
                v-if="past.length > 0"
                class="mt-14"
                aria-labelledby="past-heading"
            >
                <h2 id="past-heading" class="mb-4 text-xl font-semibold">
                    Minione
                </h2>
                <ul class="flex flex-col gap-2">
                    <li v-for="list in past" :key="list.slug">
                        <a
                            :href="`/l/${list.slug}`"
                            class="skin-outline press flex items-center justify-between gap-3 rounded-control bg-surface px-4 py-3"
                        >
                            <span class="font-semibold">{{ list.title }}</span>
                            <span class="text-sm text-muted"
                                >{{
                                    list.event_date
                                        ? formatDate(list.event_date)
                                        : ''
                                }}
                                · {{ itemsLabel(list.items_count) }}</span
                            >
                        </a>
                    </li>
                </ul>
            </section>

            <section
                class="skin-outline mt-14 flex flex-col gap-4 rounded-card bg-surface p-6"
                aria-labelledby="account-heading"
            >
                <h2 id="account-heading" class="text-xl font-semibold">
                    Konto
                </h2>
                <template v-if="user">
                    <p class="text-[15px]">
                        Zalogowano jako
                        <strong class="break-all">{{ user.email }}</strong
                        >. Logujesz się linkiem z maila, bez hasła.
                    </p>
                    <div class="flex flex-wrap gap-3">
                        <button
                            type="button"
                            class="skin-outline press h-11 rounded-control bg-surface px-4 text-sm font-semibold"
                            @click="router.post('/logout')"
                        >
                            Wyloguj
                        </button>
                        <button
                            type="button"
                            class="h-11 px-2 text-sm text-muted underline underline-offset-4"
                            @click="deleteAccount"
                        >
                            Usuń konto i wszystkie listy
                        </button>
                    </div>
                </template>
                <template v-else>
                    <p class="text-[15px]">
                        Twoje listy są zapisane tylko w tej przeglądarce. Podaj
                        maila, żeby otwierać je z każdego urządzenia.
                    </p>
                    <form
                        class="flex flex-wrap gap-2"
                        @submit.prevent="sendLink"
                    >
                        <label for="account-email" class="sr-only"
                            >E-mail</label
                        >
                        <input
                            id="account-email"
                            v-model="email"
                            type="email"
                            autocomplete="email"
                            placeholder="twoj@email.pl"
                            class="skin-outline h-11 min-w-0 flex-[1_1_14rem] rounded-control bg-surface px-3.5 text-[15px] placeholder:text-muted"
                        />
                        <button
                            type="submit"
                            :disabled="sending"
                            class="skin-outline press h-11 rounded-control bg-accent px-5 text-[15px] font-semibold text-on-accent disabled:opacity-60"
                        >
                            {{ sending ? 'Wysyłam…' : 'Zapisz listy' }}
                        </button>
                    </form>
                    <p
                        v-if="errors.email"
                        role="alert"
                        class="text-sm font-semibold text-accent"
                    >
                        {{ errors.email }}
                    </p>
                </template>
            </section>
        </div>

        <SiteFooter />
        <UiLayer />
        <DropOverlay :visible="dragging" />
        <AddGiftDialog
            :preview="preview"
            :enriching="enriching"
            :saving="savingGift"
            :errors="giftErrors"
            :lists="targetLists"
            :default-list="current[0]?.slug ?? lists[0]?.slug ?? null"
            @confirm="adder.confirm"
            @cancel="adder.cancel"
        />

        <dialog
            ref="dialog"
            aria-labelledby="new-list-heading"
            class="sheet skin-outline m-auto w-[min(34rem,calc(100vw-2rem))] rounded-card bg-surface p-0 font-body text-ink shadow-card backdrop:bg-black/50"
            @close="creating = false"
        >
            <form class="flex flex-col gap-5 p-6" @submit.prevent="createList">
                <h2 id="new-list-heading" class="font-brand text-[28px]">
                    Nowa lista
                </h2>
                <div class="flex flex-col gap-1.5">
                    <label for="new-title" class="text-sm font-semibold"
                        >Nazwa</label
                    >
                    <input
                        id="new-title"
                        v-model="newTitle"
                        type="text"
                        maxlength="120"
                        placeholder="np. Urodziny Ani"
                        class="skin-outline h-11 w-full rounded-control bg-surface px-3 text-base placeholder:text-muted"
                    />
                </div>
                <fieldset class="flex flex-col gap-2">
                    <legend class="mb-1.5 text-sm font-semibold">Skórka</legend>
                    <div class="grid grid-cols-3 gap-2">
                        <label
                            v-for="option in themes"
                            :key="option.value"
                            :data-theme="option.value"
                            class="skin-outline skin-backdrop press relative flex h-20 cursor-pointer flex-col justify-end rounded-control bg-ground p-2 has-[:checked]:ring-3 has-[:checked]:ring-ink"
                        >
                            <input
                                v-model="newTheme"
                                type="radio"
                                name="new-theme"
                                :value="option.value"
                                class="sr-only"
                            />
                            <span
                                class="skin-display text-[15px] leading-tight text-ink"
                                >{{
                                    option.label.replace('Święta: ', '')
                                }}</span
                            >
                            <span
                                class="mt-1 h-2 rounded-full bg-accent"
                                aria-hidden="true"
                            ></span>
                        </label>
                    </div>
                </fieldset>
                <div class="flex flex-col gap-1.5">
                    <label for="new-date" class="text-sm font-semibold"
                        >Data wydarzenia
                        <span class="font-normal text-muted"
                            >(opcjonalnie)</span
                        ></label
                    >
                    <input
                        id="new-date"
                        v-model="newDate"
                        type="date"
                        class="skin-outline h-11 w-full rounded-control bg-surface px-3 text-base"
                    />
                </div>
                <div class="flex flex-wrap justify-end gap-2.5">
                    <button
                        type="button"
                        class="skin-outline press h-11 rounded-control bg-surface px-5 text-[15px] font-semibold"
                        @click="creating = false"
                    >
                        Anuluj
                    </button>
                    <button
                        type="submit"
                        :disabled="saving"
                        class="skin-outline press h-11 rounded-control bg-accent px-6 text-[15px] font-semibold text-on-accent disabled:opacity-60"
                    >
                        {{ saving ? 'Tworzę…' : 'Utwórz listę' }}
                    </button>
                </div>
            </form>
        </dialog>
    </div>
</template>
