<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { WishlistItem, WishlistLayout } from '@/types';

const props = defineProps<{
    item: WishlistItem;
    layout: WishlistLayout;
}>();

const rows = computed(() => props.layout === 'rows');

function fieldValue(event: Event): string {
    return (
        event.target as HTMLInputElement | HTMLTextAreaElement
    ).value.trim();
}

function saveTitle(event: Event): void {
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
        :class="rows ? 'flex items-stretch' : 'flex flex-col'"
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
                <label :for="`title-${item.id}`" class="sr-only"
                    >Nazwa prezentu</label
                >
                <textarea
                    :id="`title-${item.id}`"
                    :key="item.title"
                    :value="item.title"
                    rows="2"
                    maxlength="500"
                    class="skin-display field-sizing-content w-full resize-none bg-transparent text-[21px] leading-tight text-ink"
                    @blur="saveTitle"
                    @keydown.enter.prevent="blurOnEnter"
                ></textarea>
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
                <label :for="`price-${item.id}`" class="sr-only">Cena</label>
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
        </div>
    </li>
</template>
