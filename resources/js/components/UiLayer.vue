<script setup lang="ts">
import { ref, watch } from 'vue';
import { confirmState, settleConfirm, toasts } from '@/lib/ui';

const dialog = ref<HTMLDialogElement | null>(null);

watch(
    () => confirmState.request,
    (request) => {
        if (request === null) {
            dialog.value?.close();
        } else if (!dialog.value?.open) {
            dialog.value?.showModal();
        }
    },
    { flush: 'post' },
);

function onClose(): void {
    if (confirmState.request !== null) {
        settleConfirm(false);
    }
}
</script>

<template>
    <dialog
        ref="dialog"
        aria-labelledby="confirm-heading"
        class="skin-outline m-auto w-[min(26rem,calc(100vw-2rem))] rounded-card bg-surface p-0 font-body text-ink shadow-card backdrop:bg-black/50"
        @close="onClose"
    >
        <div v-if="confirmState.request" class="flex flex-col gap-4 p-6">
            <h2
                id="confirm-heading"
                class="skin-display text-[26px] leading-tight"
            >
                {{ confirmState.request.title }}
            </h2>
            <p class="text-[15px] text-muted">
                {{ confirmState.request.text }}
            </p>
            <div class="flex flex-wrap justify-end gap-2.5">
                <button
                    type="button"
                    class="skin-outline h-11 rounded-control bg-surface px-5 text-[15px] font-semibold text-ink"
                    @click="settleConfirm(false)"
                >
                    Anuluj
                </button>
                <button
                    type="button"
                    class="skin-outline h-11 rounded-control bg-accent px-6 text-[15px] font-semibold text-on-accent"
                    @click="settleConfirm(true)"
                >
                    {{ confirmState.request.confirmLabel }}
                </button>
            </div>
        </div>
    </dialog>

    <div
        class="pointer-events-none fixed inset-x-0 bottom-5 z-50 flex flex-col items-center gap-2 px-4"
        role="status"
        aria-live="polite"
    >
        <TransitionGroup name="item">
            <p
                v-for="item in toasts.items"
                :key="item.id"
                class="rounded-control bg-ink px-4 py-2.5 text-[15px] font-semibold text-ground shadow-card"
            >
                {{ item.text }}
            </p>
        </TransitionGroup>
    </div>
</template>
