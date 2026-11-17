<script setup lang="ts">
import QRCode from 'qrcode';
import { ref, watch } from 'vue';

const props = defineProps<{
    url: string | null;
    title: string;
}>();

const emit = defineEmits<{ close: [] }>();

const dialog = ref<HTMLDialogElement | null>(null);
const image = ref('');

watch(
    () => props.url,
    async (url) => {
        if (url === null) {
            dialog.value?.close();

            return;
        }

        image.value = await QRCode.toDataURL(url, {
            width: 720,
            margin: 2,
            color: { dark: '#111111', light: '#ffffff' },
        });

        if (!dialog.value?.open) {
            dialog.value?.showModal();
        }
    },
    { flush: 'post' },
);
</script>

<template>
    <dialog
        ref="dialog"
        aria-labelledby="qr-heading"
        class="skin-outline m-auto w-[min(26rem,calc(100vw-2rem))] rounded-card bg-surface p-0 font-body text-ink shadow-card backdrop:bg-black/50"
        @close="emit('close')"
    >
        <div
            v-if="url"
            class="flex flex-col items-center gap-4 p-6 text-center"
        >
            <h2 id="qr-heading" class="skin-display text-[26px]">
                Kod QR do listy
            </h2>
            <img
                :src="image"
                :alt="`Kod QR prowadzący do listy ${title}`"
                class="w-64 rounded-control"
            />
            <p class="text-sm text-muted">
                Wydrukuj go na zaproszeniu albo kartce. Aparat w telefonie
                otworzy listę.
            </p>
            <div class="flex flex-wrap justify-center gap-2.5">
                <a
                    :href="image"
                    :download="`lista-${title}.png`"
                    class="skin-outline flex h-11 items-center rounded-control bg-accent px-5 text-[15px] font-semibold text-on-accent"
                    >Pobierz obrazek</a
                >
                <button
                    type="button"
                    class="skin-outline h-11 rounded-control bg-surface px-5 text-[15px] font-semibold text-ink"
                    @click="emit('close')"
                >
                    Zamknij
                </button>
            </div>
        </div>
    </dialog>
</template>
