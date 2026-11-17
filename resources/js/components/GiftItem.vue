<script setup lang="ts">
import type { RequestPayload } from '@inertiajs/core';
import { router } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';
import { confirmAction, toast } from '@/lib/ui';
import type { WishlistItem, WishlistLayout } from '@/types';

const props = defineProps<{
    item: WishlistItem;
    layout: WishlistLayout;
    editable: boolean;
    draggable: boolean;
}>();

const emit = defineEmits<{
    reserve: [item: WishlistItem];
    unreserve: [item: WishlistItem];
}>();

const rows = computed(() => props.layout === 'rows');
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
    patch({ priority: !props.item.priority });
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
        class="skin-outline skin-card relative overflow-hidden rounded-card bg-surface shadow-card"
        :class="[
            rows ? 'flex items-stretch' : 'flex flex-col',
            takenByOther ? 'opacity-60' : '',
        ]"
    >
        <div
            class="skin-outline relative flex flex-none items-center justify-center bg-tile"
            :class="
                rows
                    ? 'min-h-24 w-24 border-y-0 border-l-0 sm:w-32'
                    : 'aspect-[4/3] border-x-0 border-t-0'
            "
        >
            <img
                v-if="item.image_url"
                :src="item.image_url"
                alt=""
                loading="lazy"
                referrerpolicy="no-referrer"
                class="h-full w-full"
                :class="
                    item.image_url.startsWith('/storage/')
                        ? 'object-cover'
                        : 'bg-white object-contain p-2'
                "
            />
            <svg
                v-else
                aria-hidden="true"
                viewBox="0 0 24 24"
                class="h-12 w-12 text-ink opacity-40"
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
            <label
                v-if="editable"
                class="skin-outline absolute right-2 bottom-2 cursor-pointer rounded-control bg-surface px-2.5 py-1.5 text-xs font-semibold text-ink opacity-90 focus-within:outline-2"
            >
                Zmień zdjęcie
                <input
                    type="file"
                    accept="image/jpeg,image/png,image/webp,image/gif"
                    class="sr-only"
                    @change="changePhoto"
                />
            </label>
        </div>

        <div
            class="flex min-w-0 flex-1 gap-3 p-4"
            :class="rows ? 'flex-wrap items-center' : 'flex-col'"
        >
            <div
                class="flex min-w-0 flex-col gap-1.5"
                :class="rows ? 'flex-[1_1_14rem]' : ''"
            >
                <div class="flex items-start gap-2">
                    <div class="min-w-0 flex-1">
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
                                class="skin-display field-sizing-content w-full resize-none bg-transparent text-[21px] leading-tight text-ink"
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
                            class="skin-display line-clamp-3 cursor-text text-left text-[21px] leading-tight text-ink"
                            @click="startEditingTitle"
                        >
                            {{ item.title }}
                        </button>
                        <p
                            v-else
                            :title="item.title"
                            class="skin-display line-clamp-3 text-[21px] leading-tight text-ink"
                        >
                            {{ item.title }}
                        </p>
                    </div>
                    <button
                        v-if="editable"
                        type="button"
                        :aria-pressed="item.priority"
                        :aria-label="
                            item.priority
                                ? 'Bardzo chcę (kliknij, aby zdjąć)'
                                : 'Oznacz: bardzo chcę'
                        "
                        :title="
                            item.priority
                                ? 'Bardzo chcę'
                                : 'Oznacz: bardzo chcę'
                        "
                        class="-mt-1 -mr-2 flex h-11 w-11 flex-none items-center justify-center"
                        :class="item.priority ? 'text-accent' : 'text-muted'"
                        @click="togglePriority"
                    >
                        <svg
                            aria-hidden="true"
                            width="22"
                            height="22"
                            viewBox="0 0 24 24"
                            :fill="item.priority ? 'currentColor' : 'none'"
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
                        class="flex-none rounded-control bg-accent px-2 py-1 text-xs font-semibold text-on-accent"
                        >Bardzo chcę</span
                    >
                </div>

                <a
                    v-if="item.url"
                    :href="item.url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="truncate text-sm text-muted underline underline-offset-4"
                    >{{ item.shop ?? 'otwórz stronę' }}</a
                >

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
                        class="skin-outline field-sizing-content w-full resize-none rounded-control bg-surface px-2.5 py-2 text-sm text-ink placeholder:text-muted"
                        @blur="saveNote"
                        @keydown.esc="editingNote = false"
                    ></textarea>
                </template>
                <button
                    v-else-if="editable"
                    type="button"
                    class="line-clamp-2 text-left text-sm"
                    :class="
                        item.note
                            ? 'text-ink'
                            : 'text-muted underline underline-offset-4'
                    "
                    @click="startEditingNote"
                >
                    {{ item.note ?? 'Dodaj notatkę' }}
                </button>
                <p
                    v-else-if="item.note"
                    class="text-sm whitespace-pre-line text-ink"
                >
                    {{ item.note }}
                </p>
            </div>

            <div
                class="flex items-center gap-3"
                :class="rows ? 'flex-[0_1_13rem]' : 'mt-auto justify-between'"
            >
                <p
                    v-if="!editable"
                    class="min-w-0 flex-1 text-base font-semibold text-ink"
                >
                    {{ item.price ?? '' }}
                </p>
                <template v-else>
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
                        @blur="savePrice"
                        @keydown.enter="blurOnEnter"
                    />
                    <button
                        v-if="draggable"
                        type="button"
                        aria-label="Przeciągnij, aby zmienić kolejność"
                        title="Przeciągnij, aby zmienić kolejność"
                        class="drag-handle skin-outline flex h-11 w-11 flex-none cursor-grab items-center justify-center rounded-control bg-surface text-ink active:cursor-grabbing"
                    >
                        <svg
                            aria-hidden="true"
                            width="18"
                            height="18"
                            viewBox="0 0 24 24"
                            fill="currentColor"
                        >
                            <circle cx="9" cy="6" r="1.6" />
                            <circle cx="15" cy="6" r="1.6" />
                            <circle cx="9" cy="12" r="1.6" />
                            <circle cx="15" cy="12" r="1.6" />
                            <circle cx="9" cy="18" r="1.6" />
                            <circle cx="15" cy="18" r="1.6" />
                        </svg>
                    </button>
                    <button
                        type="button"
                        :aria-label="`Usuń: ${item.title}`"
                        class="skin-outline flex h-11 w-11 flex-none items-center justify-center rounded-control bg-surface text-ink"
                        @click="remove"
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
                </template>
            </div>

            <div
                v-if="!editable"
                class="flex flex-wrap items-center gap-2"
                :class="rows ? 'flex-[0_1_14rem]' : ''"
            >
                <button
                    v-if="item.reservation === null"
                    type="button"
                    class="skin-outline h-11 flex-1 rounded-control bg-accent px-4 text-[15px] font-semibold text-on-accent"
                    @click="emit('reserve', item)"
                >
                    Rezerwuję
                </button>
                <template v-else-if="item.reservation.mine">
                    <p class="flex-1 text-sm font-semibold">
                        Zarezerwowane przez Ciebie
                    </p>
                    <button
                        type="button"
                        class="h-11 px-2 text-sm text-muted underline underline-offset-4"
                        @click="emit('unreserve', item)"
                    >
                        Cofnij
                    </button>
                </template>
                <p v-else class="text-sm font-semibold">
                    Kupuje: {{ item.reservation.name }}
                </p>
            </div>
        </div>
    </li>
</template>
