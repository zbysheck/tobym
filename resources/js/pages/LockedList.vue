<script setup lang="ts">
import AdminBar from '@/components/AdminBar.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { themeFontsUrl } from '@/lib/themes';
import { textOn } from '@/lib/wishlist';

const props = defineProps<{
    slug: string;
    title: string;
    theme: string;
    accent: string | null;
}>();

const page = usePage();
const error = computed(() => page.props.errors.pin);
const pin = ref('');
const sending = ref(false);

const accentStyle = computed(() =>
    props.accent
        ? {
              '--skin-accent': props.accent,
              '--skin-on-accent': textOn(props.accent),
          }
        : {},
);

function unlock(): void {
    sending.value = true;
    router.post(
        `/l/${props.slug}/unlock`,
        { pin: pin.value },
        {
            onFinish: () => {
                sending.value = false;
            },
        },
    );
}
</script>

<template>
    <Head :title="title">
        <link rel="preconnect" href="https://fonts.bunny.net" />
        <link rel="stylesheet" :href="themeFontsUrl(theme)" />
    </Head>

    <AdminBar />

    <div
        :data-theme="theme"
        :style="accentStyle"
        class="skin-backdrop flex min-h-screen items-center justify-center bg-ground p-5 font-body text-ink antialiased"
    >
        <main
            class="skin-outline flex w-full max-w-md flex-col gap-5 rounded-card bg-surface p-8 text-center shadow-card"
        >
            <div class="skin-ornament self-center" aria-hidden="true"></div>
            <h1 class="skin-display text-[34px] leading-tight">{{ title }}</h1>
            <p class="text-[16px] text-muted">
                Ta lista jest chroniona PIN-em. Poproś o niego osobę, która Ci
                ją wysłała.
            </p>
            <form class="flex flex-col gap-3" @submit.prevent="unlock">
                <label for="list-pin" class="sr-only">PIN</label>
                <input
                    id="list-pin"
                    v-model="pin"
                    v-focus
                    type="text"
                    inputmode="numeric"
                    autocomplete="off"
                    maxlength="8"
                    placeholder="PIN"
                    class="skin-outline h-12 rounded-control bg-surface px-4 text-center text-xl tracking-[0.3em] text-ink"
                />
                <p v-if="error" role="alert" class="text-sm font-semibold">
                    {{ error }}
                </p>
                <button
                    type="submit"
                    :disabled="sending"
                    class="skin-outline h-12 rounded-control bg-accent px-6 text-base font-semibold text-on-accent disabled:opacity-60"
                >
                    Otwórz listę
                </button>
            </form>
        </main>
    </div>
</template>
