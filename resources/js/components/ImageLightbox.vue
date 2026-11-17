<script setup lang="ts">
import { ref, watch } from 'vue';

const props = defineProps<{
    src: string | null;
    alt: string;
}>();

const emit = defineEmits<{ close: [] }>();
const dialog = ref<HTMLDialogElement | null>(null);

watch(
    () => props.src,
    (src) => {
        if (src === null) {
            dialog.value?.close();
        } else if (!dialog.value?.open) {
            dialog.value?.showModal();
        }
    },
    { flush: 'post' },
);
</script>

<template>
    <dialog
        ref="dialog"
        :aria-label="`Zdjęcie: ${alt}`"
        class="lightbox m-auto h-full max-h-none w-full max-w-none bg-transparent p-0 backdrop:bg-black/85"
        @close="emit('close')"
        @click.self="emit('close')"
    >
        <div
            v-if="src"
            class="flex h-full w-full items-center justify-center p-4 sm:p-10"
            @click.self="emit('close')"
        >
            <img
                :src="src"
                :alt="alt"
                referrerpolicy="no-referrer"
                class="max-h-full max-w-full rounded-card bg-white object-contain shadow-2xl"
            />
            <button
                type="button"
                aria-label="Zamknij zdjęcie"
                class="press absolute top-4 right-4 flex h-12 w-12 items-center justify-center rounded-full bg-white/90 text-2xl leading-none text-black"
                @click="emit('close')"
            >
                ×
            </button>
        </div>
    </dialog>
</template>
