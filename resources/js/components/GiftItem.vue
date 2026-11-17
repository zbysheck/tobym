<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';
import type { WishlistItem, WishlistLayout } from '@/types';

const props = defineProps<{
    item: WishlistItem;
    layout: WishlistLayout;
    editable: boolean;
}>();

const emit = defineEmits<{
    reserve: [item: WishlistItem];
    unreserve: [item: WishlistItem];
}>();

const rows = computed(() => props.layout === 'rows');
const editingTitle = ref(false);
const titleField = ref<HTMLTextAreaElement | null>(null);

async function startEditingTitle(): Promise<void> {
    editingTitle.value = true;
    await nextTick();
    titleField.value?.focus();
}

function fieldValue(event: Event): string {
    return (
        event.target as HTMLInputElement | HTMLTextAreaElement
    ).value.trim();
}

function saveTitle(event: Event): void {
    editingTitle.value = false;
    const title = fieldValue(event);

    if (title !== '' && title !== props.item.title) {
        router.patch(
            `/items/${props.item.id}`,
            { title },
            { preserveScroll: true },
        );
    }
}

function savePrice(event: Event): void {
    const price = fieldValue(event);

    if (price !== (props.item.price ?? '')) {
        router.patch(
            `/items/${props.item.id}`,
            { price: price === '' ? null : price },
            { preserveScroll: true },
        );
    }
}

function remove(): void {
    router.delete(`/items/${props.item.id}`, { preserveScroll: true });
}

function blurOnEnter(event: KeyboardEvent): void {
    (event.target as HTMLElement).blur();
}
</script>

<template>
    <li
        class="skin-outline overflow-hidden rounded-card bg-surface shadow-card"
        :class="[
            rows ? 'flex items-stretch' : 'flex flex-col',
            !editable && item.reservation && !item.reservation.mine
                ? 'opacity-60'
                : '',
        ]"
    >
        <div
            class="skin-outline flex flex-none items-center justify-center bg-tile"
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
                class="h-full w-full object-cover"
            />
            <span v-else class="px-2 text-center text-sm text-ink"
                >bez zdjęcia</span
            >
        </div>
        <div
            class="flex min-w-0 flex-1 gap-3 p-4"
            :class="rows ? 'flex-wrap items-center' : 'flex-col'"
        >
            <div
                class="flex min-w-0 flex-col gap-1"
                :class="rows ? 'flex-[1_1_14rem]' : ''"
            >
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
                class="flex items-center gap-3"
                :class="rows ? 'flex-[0_1_13rem]' : 'mt-auto justify-between'"
            >
                <p
                    v-if="!editable"
                    class="min-w-0 flex-1 text-base font-semibold text-ink"
                >
                    {{ item.price ?? '' }}
                </p>
                <label v-if="editable" :for="`price-${item.id}`" class="sr-only"
                    >Cena</label
                >
                <input
                    v-if="editable"
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
                    v-if="editable"
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
            </div>
            <div
                v-if="!editable"
                class="flex flex-wrap items-center gap-2"
                :class="rows ? 'flex-[0_1_14rem]' : ''"
            >
                <template v-if="item.reservation === null">
                    <button
                        type="button"
                        class="skin-outline h-11 flex-1 rounded-control bg-accent px-4 text-[15px] font-semibold text-on-accent"
                        @click="emit('reserve', item)"
                    >
                        Rezerwuję
                    </button>
                </template>
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
