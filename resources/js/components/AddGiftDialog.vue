<script setup lang="ts">
import { ref, watch } from 'vue';
import type { GiftPreview } from '@/types';

const props = defineProps<{
    preview: GiftPreview | null;
    saving: boolean;
    enriching: boolean;
}>();

const emit = defineEmits<{
    confirm: [details: { title: string; price: string }];
    cancel: [];
}>();

const dialog = ref<HTMLDialogElement | null>(null);
const title = ref('');
const price = ref('');
const titleTouched = ref(false);
const priceTouched = ref(false);

watch(
    () => props.preview?.url ?? null,
    (url) => {
        if (url === null || props.preview === null) {
            dialog.value?.close();

            return;
        }

        title.value = props.preview.title;
        price.value = props.preview.price ?? '';
        titleTouched.value = false;
        priceTouched.value = false;

        if (!dialog.value?.open) {
            dialog.value?.showModal();
        }
    },
    { flush: 'post' },
);

// Dane dociągnięte w tle nadpisują pola tylko, jeśli użytkownik ich nie ruszał.
watch(
    () => [props.preview?.title, props.preview?.price] as const,
    ([nextTitle, nextPrice]) => {
        if (!titleTouched.value && nextTitle) {
            title.value = nextTitle;
        }

        if (!priceTouched.value && nextPrice) {
            price.value = nextPrice;
        }
    },
);

function confirm(): void {
    if (title.value.trim() === '' || props.saving) {
        return;
    }

    emit('confirm', { title: title.value.trim(), price: price.value.trim() });
}

function onClose(): void {
    if (props.preview !== null) {
        emit('cancel');
    }
}
</script>

<template>
    <dialog
        ref="dialog"
        aria-labelledby="add-gift-heading"
        class="skin-outline m-auto w-[min(34rem,calc(100vw-2rem))] rounded-card bg-surface p-0 font-body text-ink shadow-card backdrop:bg-black/50"
        @close="onClose"
    >
        <form
            v-if="preview"
            class="flex flex-col gap-5 p-6"
            @submit.prevent="confirm"
        >
            <h2 id="add-gift-heading" class="skin-display text-[28px]">
                Dodać ten prezent?
            </h2>
            <div class="flex flex-wrap gap-5">
                <div
                    class="skin-outline flex h-32 w-32 flex-none items-center justify-center overflow-hidden rounded-control bg-tile"
                >
                    <img
                        v-if="preview.image_url"
                        :src="preview.image_url"
                        alt=""
                        referrerpolicy="no-referrer"
                        class="h-full w-full object-cover"
                    />
                    <span v-else class="px-2 text-center text-sm">{{
                        enriching ? 'Szukam zdjęcia…' : 'bez zdjęcia'
                    }}</span>
                </div>
                <div class="flex min-w-0 flex-[1_1_14rem] flex-col gap-4">
                    <div class="flex flex-col gap-1.5">
                        <label
                            for="add-gift-title"
                            class="text-sm font-semibold"
                            >Nazwa</label
                        >
                        <textarea
                            id="add-gift-title"
                            v-model="title"
                            rows="3"
                            maxlength="500"
                            required
                            @input="titleTouched = true"
                            class="skin-outline field-sizing-content w-full resize-none rounded-control bg-surface px-3 py-2.5 text-base text-ink"
                        ></textarea>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label
                            for="add-gift-price"
                            class="text-sm font-semibold"
                            >Cena</label
                        >
                        <input
                            id="add-gift-price"
                            v-model="price"
                            type="text"
                            maxlength="64"
                            :placeholder="
                                enriching ? 'Szukam ceny…' : 'np. 349 zł'
                            "
                            @input="priceTouched = true"
                            class="skin-outline h-11 w-full rounded-control bg-surface px-3 text-base text-ink placeholder:text-muted"
                        />
                    </div>
                    <p v-if="preview.shop" class="truncate text-sm text-muted">
                        {{ preview.shop }}
                    </p>
                    <p
                        v-if="enriching"
                        role="status"
                        class="text-sm text-muted"
                    >
                        Uzupełniam brakujące dane, możesz już dodać…
                    </p>
                </div>
            </div>
            <div class="flex flex-wrap justify-end gap-2.5">
                <button
                    type="button"
                    class="skin-outline h-11 rounded-control bg-surface px-5 text-[15px] font-semibold text-ink"
                    @click="emit('cancel')"
                >
                    Anuluj
                </button>
                <button
                    type="submit"
                    :disabled="saving"
                    class="skin-outline h-11 rounded-control bg-accent px-6 text-[15px] font-semibold text-on-accent disabled:opacity-60"
                >
                    {{ saving ? 'Dodaję…' : 'Dodaj do listy' }}
                </button>
            </div>
        </form>
    </dialog>
</template>
