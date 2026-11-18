<script setup lang="ts">
import type { RequestPayload } from '@inertiajs/core';
import { router } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';
import { confirmAction, toast } from '@/lib/ui';
import type { WishlistItem, WishlistLayout } from '@/types';

const props = defineProps<{
    item: WishlistItem;
    layout: WishlistLayout;
    editable: boolean;
    draggable: boolean;
    highlight?: boolean;
}>();

const emit = defineEmits<{
    reserve: [item: WishlistItem];
    unreserve: [item: WishlistItem];
    zoom: [item: WishlistItem];
}>();

// Gwiazdka zmienia się od razu, zanim serwer potwierdzi zapis.
const priority = ref(props.item.priority);
const starPop = ref(false);
watch(
    () => props.item.priority,
    (value) => {
        priority.value = value;
    },
);

const rows = computed(() => props.layout === 'rows');
// Nazwa sklepu jako domena ("allegro.pl"): podpis pod "Kup teraz" i kafelek bez zdjęcia.
const shopLabel = computed(() => {
    if (props.item.url) {
        try {
            return new URL(props.item.url).hostname.replace(/^www\./, '');
        } catch {
            // Nieprawidłowy adres: zostaje nazwa sklepu.
        }
    }

    return (props.item.shop ?? '')
        .replace(/^https?:\/\//, '')
        .replace(/^www\./, '');
});
const editingTitle = ref(false);
const titleField = ref<HTMLTextAreaElement | null>(null);
const editingNote = ref(false);
const noteField = ref<HTMLTextAreaElement | null>(null);
const takenByOther = computed(
    () =>
        !props.editable &&
        props.item.reservation !== null &&
        !props.item.reservation.mine,
);

function patch(data: RequestPayload): void {
    router.patch(`/items/${props.item.id}`, data, {
        preserveScroll: true,
        onSuccess: () => toast('Zapisano'),
    });
}

function fieldValue(event: Event): string {
    return (
        event.target as HTMLInputElement | HTMLTextAreaElement
    ).value.trim();
}

async function startEditingTitle(): Promise<void> {
    editingTitle.value = true;
    await nextTick();
    titleField.value?.focus();
}

async function startEditingNote(): Promise<void> {
    editingNote.value = true;
    await nextTick();
    noteField.value?.focus();
}

function saveTitle(event: Event): void {
    editingTitle.value = false;
    const title = fieldValue(event);

    if (title !== '' && title !== props.item.title) {
        patch({ title });
    }
}

function saveNote(event: Event): void {
    editingNote.value = false;
    const note = fieldValue(event);

    if (note !== (props.item.note ?? '')) {
        patch({ note: note === '' ? null : note });
    }
}

function savePrice(event: Event): void {
    const price = fieldValue(event);

    if (price !== (props.item.price ?? '')) {
        patch({ price: price === '' ? null : price });
    }
}

function togglePriority(): void {
    priority.value = !priority.value;
    starPop.value = true;
    window.setTimeout(() => {
        starPop.value = false;
    }, 400);
    router.patch(
        `/items/${props.item.id}`,
        { priority: priority.value },
        {
            preserveScroll: true,
            onSuccess: () =>
                toast(
                    priority.value
                        ? 'Oznaczono: bardzo chcę'
                        : 'Zdjęto oznaczenie',
                ),
            onError: () => {
                priority.value = !priority.value;
            },
        },
    );
}

function changePhoto(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0];

    if (file) {
        router.post(
            `/items/${props.item.id}/photo`,
            { photo: file },
            {
                preserveScroll: true,
                forceFormData: true,
                onSuccess: () => toast('Zdjęcie zmienione'),
            },
        );
    }
}

async function remove(): Promise<void> {
    const confirmed = await confirmAction({
        title: 'Usunąć prezent?',
        text: `„${props.item.title}” zniknie z listy.`,
    });

    if (confirmed) {
        router.delete(`/items/${props.item.id}`, {
            preserveScroll: true,
            onSuccess: () => toast('Usunięto z listy'),
        });
    }
}

function blurOnEnter(event: KeyboardEvent): void {
    (event.target as HTMLElement).blur();
}
</script>

<template>
    <li
        :data-id="item.id"
        class="skin-outline skin-card lift group relative overflow-hidden rounded-card bg-surface shadow-card"
        :class="[
            rows ? 'flex items-stretch sm:h-36' : 'flex h-[27rem] flex-col',
            takenByOther ? 'opacity-60' : '',
            highlight ? 'flash' : '',
        ]"
    >
        <button
            v-if="draggable"
            type="button"
            aria-label="Przeciągnij, aby zmienić kolejność"
            title="Przeciągnij, aby zmienić kolejność"
            class="drag-handle absolute inset-y-0 left-0 z-10 flex w-1.5 cursor-grab items-center justify-center overflow-hidden bg-ink/85 text-ground transition-[width] duration-200 group-hover:w-8 focus-visible:w-8 active:cursor-grabbing [@media(hover:none)]:w-7"
        >
            <svg
                aria-hidden="true"
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="currentColor"
                class="flex-none opacity-0 transition-opacity group-hover:opacity-100 [@media(hover:none)]:opacity-100"
            >
                <circle cx="9" cy="6" r="1.6" />
                <circle cx="15" cy="6" r="1.6" />
                <circle cx="9" cy="12" r="1.6" />
                <circle cx="15" cy="12" r="1.6" />
                <circle cx="9" cy="18" r="1.6" />
                <circle cx="15" cy="18" r="1.6" />
            </svg>
        </button>
        <div
            class="skin-outline relative flex flex-none items-center justify-center bg-tile"
            :class="
                rows
                    ? 'w-24 border-y-0 border-l-0 sm:w-36'
                    : 'h-52 border-x-0 border-t-0'
            "
        >
            <button
                v-if="item.image_url"
                type="button"
                :aria-label="`Powiększ zdjęcie: ${item.title}`"
                class="h-full w-full cursor-zoom-in"
                @click="emit('zoom', item)"
            >
                <img
                    :src="item.image_url"
                    alt=""
                    loading="lazy"
                    referrerpolicy="no-referrer"
                    class="h-full w-full transition-transform duration-300 group-hover:scale-[1.03]"
                    :class="
                        item.image_url.startsWith('/storage/gifts/')
                            ? 'object-cover'
                            : 'bg-white object-contain p-2'
                    "
                />
            </button>
            <div
                v-else
                class="flex max-w-full flex-col items-center gap-1.5 px-3 text-center"
            >
                <svg
                    aria-hidden="true"
                    viewBox="0 0 24 24"
                    class="text-ink opacity-40"
                    :class="shopLabel ? 'h-9 w-9' : 'h-12 w-12'"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.4"
                    stroke-linejoin="round"
                >
                    <rect x="3.5" y="9" width="17" height="11.5" rx="2" />
                    <rect x="2.5" y="6" width="19" height="4" rx="1.5" />
                    <path
                        d="M12 6v14.5M12 6C10 2.5 6.5 2.5 7 5s5 1 5 1Zm0 0c2-3.5 5.5-3.5 5-1s-5 1-5 1Z"
                    />
                </svg>
                <span
                    v-if="shopLabel"
                    class="skin-display max-w-full truncate font-semibold text-ink opacity-60"
                    :class="rows ? 'text-sm' : 'text-lg'"
                    >{{ shopLabel }}</span
                >
            </div>

            <!-- Ulubiony: gwiazdka na zdjęciu (właściciel przełącza, gość widzi tylko zaznaczoną). -->
            <button
                v-if="editable"
                type="button"
                :aria-pressed="priority"
                :aria-label="
                    priority
                        ? 'Bardzo chcę (kliknij, aby zdjąć)'
                        : 'Oznacz: bardzo chcę'
                "
                :title="priority ? 'Bardzo chcę' : 'Oznacz: bardzo chcę'"
                class="press absolute top-2 right-2 flex h-9 w-9 items-center justify-center rounded-full bg-surface/90 shadow-card"
                :class="[
                    priority ? 'text-accent' : 'text-muted',
                    starPop ? 'pop' : '',
                ]"
                @click="togglePriority"
            >
                <svg
                    aria-hidden="true"
                    width="20"
                    height="20"
                    viewBox="0 0 24 24"
                    :fill="priority ? 'currentColor' : 'none'"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linejoin="round"
                >
                    <path
                        d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1 6.2L12 17.3 6.5 20.2l1-6.2L3 9.6l6.2-.9Z"
                    />
                </svg>
            </button>
            <span
                v-else-if="item.priority"
                title="Bardzo chcę"
                class="absolute top-2 right-2 flex h-8 items-center gap-1 rounded-full bg-accent px-2.5 text-xs font-semibold text-on-accent shadow-card"
            >
                <svg
                    aria-hidden="true"
                    width="14"
                    height="14"
                    viewBox="0 0 24 24"
                    fill="currentColor"
                >
                    <path
                        d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1 6.2L12 17.3 6.5 20.2l1-6.2L3 9.6l6.2-.9Z"
                    />
                </svg>
                <span :class="rows ? 'sr-only' : ''">Bardzo chcę</span>
            </span>

            <!-- Zmiana zdjęcia: mała ikonka w rogu. -->
            <label
                v-if="editable"
                :aria-label="`Zmień zdjęcie: ${item.title}`"
                title="Zmień zdjęcie"
                class="press absolute right-2 bottom-2 flex h-8 w-8 cursor-pointer items-center justify-center rounded-full bg-surface/90 text-ink shadow-card focus-within:outline-2"
            >
                <svg
                    aria-hidden="true"
                    width="15"
                    height="15"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linejoin="round"
                >
                    <path d="M4 8h3l2-3h6l2 3h3v11H4Z" />
                    <circle cx="12" cy="13" r="3.5" />
                </svg>
                <input
                    type="file"
                    accept="image/jpeg,image/png,image/webp,image/gif"
                    class="sr-only"
                    @change="changePhoto"
                />
            </label>
        </div>

        <div
            class="flex min-h-0 min-w-0 flex-1 gap-3 p-3 sm:p-4"
            :class="rows ? 'flex-col sm:flex-row sm:items-center' : 'flex-col'"
        >
            <!-- Tytuł i notatka pod nim. -->
            <div class="flex min-h-0 min-w-0 flex-1 flex-col gap-1">
                <template v-if="editable && editingTitle">
                    <label :for="`title-${item.id}`" class="sr-only"
                        >Nazwa prezentu</label
                    >
                    <textarea
                        :id="`title-${item.id}`"
                        ref="titleField"
                        :value="item.title"
                        rows="2"
                        maxlength="500"
                        class="skin-display w-full resize-none bg-transparent text-[17px] leading-tight text-ink sm:text-[19px]"
                        @blur="saveTitle"
                        @keydown.enter.prevent="blurOnEnter"
                        @keydown.esc="editingTitle = false"
                    ></textarea>
                </template>
                <button
                    v-else-if="editable"
                    type="button"
                    :title="item.title"
                    :aria-label="`Zmień nazwę: ${item.title}`"
                    class="skin-display line-clamp-2 cursor-text text-left text-[17px] leading-tight text-ink sm:text-[19px]"
                    @click="startEditingTitle"
                >
                    {{ item.title }}
                </button>
                <p
                    v-else
                    :title="item.title"
                    class="skin-display line-clamp-2 text-[17px] leading-tight text-ink sm:text-[19px]"
                >
                    {{ item.title }}
                </p>

                <template v-if="editable && editingNote">
                    <label :for="`note-${item.id}`" class="sr-only"
                        >Notatka</label
                    >
                    <textarea
                        :id="`note-${item.id}`"
                        ref="noteField"
                        :value="item.note ?? ''"
                        rows="2"
                        maxlength="2000"
                        placeholder="Rozmiar, kolor, wariant…"
                        class="skin-outline w-full resize-none rounded-control bg-surface px-2.5 py-1.5 text-sm text-ink placeholder:text-muted"
                        @blur="saveNote"
                        @keydown.esc="editingNote = false"
                    ></textarea>
                </template>
                <button
                    v-else-if="editable"
                    type="button"
                    :title="item.note ?? undefined"
                    class="line-clamp-2 text-left text-sm"
                    :class="
                        item.note
                            ? 'text-muted'
                            : 'text-muted underline underline-offset-4'
                    "
                    @click="startEditingNote"
                >
                    {{ item.note ?? 'Dodaj notatkę' }}
                </button>
                <p
                    v-else-if="item.note"
                    :title="item.note"
                    class="line-clamp-2 text-sm whitespace-pre-line text-muted"
                >
                    {{ item.note }}
                </p>
            </div>

            <!-- Właściciel: cena, sklep, usuwanie. -->
            <div
                v-if="editable"
                class="flex flex-none items-center gap-2"
                :class="rows ? 'sm:w-64' : ''"
            >
                <div class="flex min-w-0 flex-1 flex-col">
                    <label :for="`price-${item.id}`" class="sr-only"
                        >Cena</label
                    >
                    <input
                        :id="`price-${item.id}`"
                        :key="item.price ?? ''"
                        type="text"
                        :value="item.price ?? ''"
                        maxlength="64"
                        placeholder="dodaj cenę"
                        class="h-8 min-w-0 bg-transparent text-base font-semibold text-ink placeholder:font-normal placeholder:text-muted"
                        @blur="savePrice"
                        @keydown.enter="blurOnEnter"
                    />
                    <a
                        v-if="item.url"
                        :href="item.url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="truncate text-xs text-muted underline underline-offset-4"
                        >na {{ shopLabel || 'stronie sklepu' }}</a
                    >
                </div>
                <button
                    type="button"
                    :aria-label="`Usuń: ${item.title}`"
                    title="Usuń"
                    class="skin-outline flex h-10 w-10 flex-none items-center justify-center rounded-control bg-surface text-ink"
                    @click="remove"
                >
                    <svg
                        aria-hidden="true"
                        width="17"
                        height="17"
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

            <!-- Gość: Rezerwuj + Kup teraz [cena], pod spodem "na sklep.pl". -->
            <div
                v-else
                class="flex flex-none flex-col gap-1"
                :class="rows ? 'sm:w-72' : ''"
            >
                <div class="flex gap-2">
                    <button
                        v-if="item.reservation === null"
                        type="button"
                        class="skin-outline h-11 flex-1 rounded-control bg-accent px-3 text-[15px] font-semibold text-on-accent"
                        @click="emit('reserve', item)"
                    >
                        Rezerwuj
                    </button>
                    <div
                        v-else-if="item.reservation.mine"
                        class="flex h-11 flex-1 items-center justify-between gap-1 rounded-control bg-tile px-3 text-sm font-semibold"
                    >
                        <span class="truncate">Twoja rezerwacja</span>
                        <button
                            type="button"
                            class="text-xs font-normal text-muted underline underline-offset-4"
                            @click="emit('unreserve', item)"
                        >
                            Cofnij
                        </button>
                    </div>
                    <p
                        v-else
                        class="flex h-11 flex-1 items-center truncate rounded-control bg-tile px-3 text-sm font-semibold"
                    >
                        Kupuje: {{ item.reservation.name }}
                    </p>
                    <a
                        v-if="item.url && !takenByOther"
                        :href="item.url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="skin-outline flex h-11 flex-1 items-center justify-center gap-1.5 rounded-control bg-surface px-3 text-[15px] font-semibold whitespace-nowrap text-ink"
                    >
                        Kup teraz
                        <span v-if="item.price" class="opacity-70">{{
                            item.price
                        }}</span>
                    </a>
                    <p
                        v-else-if="item.price && !item.url"
                        class="flex h-11 items-center px-1 text-[15px] font-semibold"
                    >
                        {{ item.price }}
                    </p>
                </div>
                <a
                    v-if="item.url && !takenByOther"
                    :href="item.url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="self-end truncate text-xs text-muted underline underline-offset-4"
                    >na {{ shopLabel || 'stronie sklepu' }}</a
                >
            </div>
        </div>
    </li>
</template>
