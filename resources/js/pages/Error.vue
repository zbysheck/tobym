<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import { themeFontsUrl } from '@/lib/themes';

const props = defineProps<{ status: number }>();

const messages: Record<number, { title: string; text: string }> = {
    403: {
        title: 'Brak dostępu',
        text: 'Nie możesz tego zrobić na tej liście.',
    },
    404: {
        title: 'Nie ma takiej strony',
        text: 'Ta lista mogła zostać usunięta albo link jest niepełny. Poproś osobę, która go wysłała, o nowy.',
    },
    419: {
        title: 'Strona wygasła',
        text: 'Odśwież stronę i spróbuj jeszcze raz.',
    },
    429: {
        title: 'Za szybko',
        text: 'Zbyt wiele prób w krótkim czasie. Odczekaj minutę i spróbuj ponownie.',
    },
    500: {
        title: 'Coś poszło nie tak',
        text: 'To błąd po naszej stronie. Spróbuj za chwilę.',
    },
    503: { title: 'Przerwa techniczna', text: 'Za chwilę wracamy.' },
};

const message = computed(() => messages[props.status] ?? messages[500]);
</script>

<template>
    <Head :title="message.title">
        <link rel="preconnect" href="https://fonts.bunny.net" />
        <link rel="stylesheet" :href="themeFontsUrl('swiateczna')" />
    </Head>

    <div
        data-theme="swiateczna"
        class="skin-backdrop flex min-h-screen flex-col items-center justify-center gap-8 bg-ground p-5 text-center font-body text-ink antialiased"
    >
        <AppLogo size="lg" />
        <main class="flex max-w-md flex-col items-center gap-4">
            <p
                class="text-sm font-semibold tracking-[0.16em] text-accent uppercase"
            >
                Błąd {{ status }}
            </p>
            <h1
                class="font-brand text-[clamp(2.2rem,6vw,3.2rem)] leading-tight"
            >
                {{ message.title }}
            </h1>
            <p class="text-lg text-muted">{{ message.text }}</p>
            <a
                href="/"
                class="skin-outline mt-2 inline-flex h-12 items-center rounded-control bg-accent px-6 font-semibold text-on-accent"
                >Strona główna</a
            >
        </main>
    </div>
</template>
