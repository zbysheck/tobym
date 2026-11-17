<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import type { GiftDetails, GiftPreview } from '@/types';

const props = defineProps<{
    preview: GiftPreview | null;
    saving: boolean;
    enriching: boolean;
    errors: Record<string, string>;
}>();

const emit = defineEmits<{
    confirm: [details: GiftDetails];
    cancel: [];
}>();

const dialog = ref<HTMLDialogElement | null>(null);
const title = ref('');
const price = ref('');
const note = ref('');
const priority = ref(false);
const photo = ref<File | null>(null);
const photoPreview = ref<string | null>(null);
const titleTouched = ref(false);
const priceTouched = ref(false);

const manual = computed(
    () => props.preview !== null && props.preview.url === null,
);
const image = computed(
    () => photoPreview.value ?? props.preview?.image_url ?? null,
);

function resetPhoto(): void {
    if (photoPreview.value !== null) {
        URL.revokeObjectURL(photoPreview.value);
    }

    photo.value = null;
    photoPreview.value = null;
}

watch(
    () => props.preview,
    (preview, previous) => {
        if (preview === null) {
            dialog.value?.close();

            return;
        }

        // Nowe okno (inny link albo nowy prezent bez linku): wyczyść formularz.
        if (
            previous === null ||
            previous === undefined ||
            previous.url !== preview.url
        ) {
            title.value = preview.title;
            price.value = preview.price ?? '';
            note.value = '';
            priority.value = false;
            titleTouched.value = false;
            priceTouched.value = false;
            resetPhoto();
        }

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

onBeforeUnmount(resetPhoto);

function choosePhoto(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    resetPhoto();

    if (file !== null) {
        photo.value = file;
        photoPreview.value = URL.createObjectURL(file);
    }
}

function confirm(): void {
    if (title.value.trim() === '' || props.saving) {
        return;
    }

    emit('confirm', {
        title: title.value.trim(),
        price: price.value.trim(),
        note: note.value.trim(),
        priority: priority.value,
        photo: photo.value,
    });
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
        class="skin-outline m-auto w-[min(36rem,calc(100vw-2rem))] rounded-card bg-surface p-0 font-body text-ink shadow-card backdrop:bg-black/50"
        @close="onClose"
    >
        <form
            v-if="preview"
            class="flex flex-col gap-5 p-6"
            @submit.prevent="confirm"
        >
            <h2 id="add-gift-heading" class="skin-display text-[28px]">
                {{ manual ? 'Nowy prezent' : 'Dodać ten prezent?' }}
            </h2>
            <div class="flex flex-wrap gap-5">
                <div class="flex w-32 flex-none flex-col gap-2">
                    <div
                        class="skin-outline flex h-32 w-32 items-center justify-center overflow-hidden rounded-control bg-tile"
                    >
                        <img
                            v-if="image"
                            :src="image"
                            alt=""
                            referrerpolicy="no-referrer"
                            class="h-full w-full object-cover"
                        />
                        <span v-else class="px-2 text-center text-sm">{{
                            enriching ? 'Szukam zdjęcia…' : 'bez zdjęcia'
                        }}</span>
                    </div>
                    <label
                        class="cursor-pointer text-center text-sm text-muted underline underline-offset-4 focus-within:outline-2"
                    >
                        {{ photo ? 'Zmień zdjęcie' : 'Własne zdjęcie' }}
                        <input
                            type="file"
                            accept="image/jpeg,image/png,image/webp,image/gif"
                            class="sr-only"
                            @change="choosePhoto"
                        />
                    </label>
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
                            rows="2"
                            maxlength="500"
                            required
                            :placeholder="
                                manual ? 'np. Wspólny weekend w górach' : ''
                            "
                            class="skin-outline field-sizing-content w-full resize-none rounded-control bg-surface px-3 py-2.5 text-base text-ink placeholder:text-muted"
                            @input="titleTouched = true"
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
                            class="skin-outline h-11 w-full rounded-control bg-surface px-3 text-base text-ink placeholder:text-muted"
                            @input="priceTouched = true"
                        />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label for="add-gift-note" class="text-sm font-semibold"
                            >Notatka
                            <span class="font-normal text-muted"
                                >(rozmiar, kolor, wariant)</span
                            ></label
                        >
                        <textarea
                            id="add-gift-note"
                            v-model="note"
                            rows="2"
                            maxlength="2000"
                            class="skin-outline field-sizing-content w-full resize-none rounded-control bg-surface px-3 py-2.5 text-base text-ink"
                        ></textarea>
                    </div>
                    <label class="flex items-center gap-2.5 text-[15px]">
                        <input
                            v-model="priority"
                            type="checkbox"
                            class="h-5 w-5 accent-accent"
                        />
                        Bardzo chcę
                    </label>
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
                    <p
                        v-for="(message, field) in errors"
                        :key="field"
                        role="alert"
                        class="text-sm font-semibold text-accent"
                    >
                        {{ message }}
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
