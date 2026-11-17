<script setup lang="ts">
import { ref, watch } from 'vue';

const props = defineProps<{
    giftTitle: string | null;
    initialName: string;
}>();

const emit = defineEmits<{
    confirm: [name: string];
    cancel: [];
}>();

const dialog = ref<HTMLDialogElement | null>(null);
const name = ref('');

watch(
    () => props.giftTitle,
    (title) => {
        if (title === null) {
            dialog.value?.close();

            return;
        }

        name.value = props.initialName;

        if (!dialog.value?.open) {
            dialog.value?.showModal();
        }
    },
    { flush: 'post' },
);

function confirm(): void {
    if (name.value.trim() !== '') {
        emit('confirm', name.value.trim());
    }
}

function onClose(): void {
    if (props.giftTitle !== null) {
        emit('cancel');
    }
}
</script>

<template>
    <dialog
        ref="dialog"
        aria-labelledby="reserve-heading"
        class="skin-outline m-auto w-[min(28rem,calc(100vw-2rem))] rounded-card bg-surface p-0 font-body text-ink shadow-card backdrop:bg-black/50"
        @close="onClose"
    >
        <form
            v-if="giftTitle"
            class="flex flex-col gap-4 p-6"
            @submit.prevent="confirm"
        >
            <h2 id="reserve-heading" class="skin-display text-[26px]">
                Kupujesz ten prezent?
            </h2>
            <p class="line-clamp-3 text-[15px] text-muted">{{ giftTitle }}</p>
            <div class="flex flex-col gap-1.5">
                <label for="reserve-name" class="text-sm font-semibold"
                    >Twoje imię</label
                >
                <input
                    id="reserve-name"
                    v-model="name"
                    type="text"
                    maxlength="80"
                    required
                    autocomplete="given-name"
                    class="skin-outline h-11 w-full rounded-control bg-surface px-3 text-base text-ink"
                />
                <p class="text-sm text-muted">
                    Inni goście zobaczą, że ten prezent jest zajęty. Właściciel
                    listy się nie dowie.
                </p>
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
                    class="skin-outline h-11 rounded-control bg-accent px-6 text-[15px] font-semibold text-on-accent"
                >
                    Rezerwuję
                </button>
            </div>
        </form>
    </dialog>
</template>
