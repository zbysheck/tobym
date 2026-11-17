<script setup lang="ts">
defineProps<{ visible: boolean }>();
</script>

<template>
    <Transition name="menu">
        <div
            v-if="visible"
            class="pointer-events-none fixed inset-0 z-50 flex items-center justify-center bg-ink/40 p-6 backdrop-blur-[2px]"
            aria-hidden="true"
        >
            <div
                class="flex max-w-sm flex-col items-center gap-3 rounded-card border-4 border-dashed border-surface bg-surface/95 px-10 py-10 text-center text-ink shadow-card"
            >
                <svg
                    width="48"
                    height="48"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.5"
                    stroke-linejoin="round"
                >
                    <rect x="3.5" y="9" width="17" height="11.5" rx="2" />
                    <rect x="2.5" y="6" width="19" height="4" rx="1.5" />
                    <path
                        d="M12 6v14.5M12 6C10 2.5 6.5 2.5 7 5s5 1 5 1Zm0 0c2-3.5 5.5-3.5 5-1s-5 1-5 1Z"
                    />
                </svg>
                <p class="skin-display text-[26px] leading-tight">
                    Upuść link tutaj
                </p>
                <p class="text-[15px] text-muted">
                    Dodamy prezent, a Ty wybierzesz listę.
                </p>
            </div>
        </div>
    </Transition>
</template>
