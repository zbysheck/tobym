<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import UiLayer from '@/components/UiLayer.vue';
import { themeFontsUrl } from '@/lib/themes';

defineProps<{ title: string }>();

const page = usePage();
const status = computed(() => page.props.flash.status);

const nav = [
    { href: '/admin', label: 'Przegląd' },
    { href: '/admin/listy', label: 'Listy' },
    { href: '/admin/konta', label: 'Konta' },
    { href: '/admin/sklepy', label: 'Sklepy' },
    { href: '/admin/zdarzenia', label: 'Zdarzenia' },
    { href: '/admin/symulator', label: 'Symulator' },
];

function active(href: string): boolean {
    const path = page.url.split('?')[0];

    return href === '/admin' ? path === '/admin' : path.startsWith(href);
}
</script>

<template>
    <Head :title="`Admin: ${title}`">
        <link rel="preconnect" href="https://fonts.bunny.net" />
        <link rel="stylesheet" :href="themeFontsUrl('swiateczna')" />
    </Head>

    <div
        data-theme="swiateczna"
        class="min-h-screen bg-ground font-body text-ink antialiased"
    >
        <header class="border-b border-line bg-surface">
            <div
                class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-6 gap-y-3 px-5 py-4"
            >
                <div class="flex items-center gap-3">
                    <AppLogo />
                    <span
                        class="rounded-full bg-ink px-2.5 py-0.5 text-xs font-semibold text-ground"
                        >admin</span
                    >
                </div>
                <nav class="flex flex-wrap gap-1 text-[15px]">
                    <Link
                        v-for="link in nav"
                        :key="link.href"
                        :href="link.href"
                        class="rounded-control px-3 py-1.5 font-semibold"
                        :class="
                            active(link.href)
                                ? 'bg-ink text-ground'
                                : 'text-muted hover:bg-tile hover:text-ink'
                        "
                        >{{ link.label }}</Link
                    >
                </nav>
                <a
                    href="/moje-listy"
                    class="ml-auto text-sm text-muted underline underline-offset-4"
                    >Wróć do strony</a
                >
            </div>
        </header>

        <main class="mx-auto flex max-w-6xl flex-col gap-6 px-5 py-8">
            <h1 class="font-brand text-4xl">{{ title }}</h1>
            <p
                v-if="status"
                role="status"
                class="rounded-control bg-tile px-4 py-2.5 text-sm"
            >
                {{ status }}
            </p>
            <slot />
        </main>
        <UiLayer />
    </div>
</template>
