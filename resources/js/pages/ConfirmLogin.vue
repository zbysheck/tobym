<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    action: string;
}>();

const sending = ref(false);

function confirm(): void {
    sending.value = true;
    router.post(
        props.action,
        {},
        {
            onFinish: () => {
                sending.value = false;
            },
        },
    );
}
</script>

<template>
    <Head title="Logowanie">
        <link rel="preconnect" href="https://fonts.bunny.net" />
        <link
            rel="stylesheet"
            href="https://fonts.bunny.net/css?family=dm-serif-display:400|dm-sans:400,600&display=swap"
        />
    </Head>

    <div
        data-theme="swiateczna"
        class="flex min-h-screen items-center justify-center bg-ground p-5 font-body text-ink antialiased"
    >
        <main
            class="skin-outline flex w-full max-w-md flex-col gap-5 rounded-card bg-surface p-8 text-center shadow-card"
        >
            <div class="skin-display text-[26px]">tobym</div>
            <h1 class="skin-display text-[34px] leading-tight">
                Otwieramy Twoją listę
            </h1>
            <p class="text-[16px] text-muted">
                Kliknij, aby się zalogować na tym urządzeniu.
            </p>
            <button
                type="button"
                :disabled="sending"
                class="skin-outline h-12 rounded-control bg-accent px-6 text-base font-semibold text-on-accent disabled:opacity-60"
                @click="confirm"
            >
                {{ sending ? 'Loguję…' : 'Otwórz moją listę' }}
            </button>
        </main>
    </div>
</template>
