<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineProps<{
    token: string | null;
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);
const sending = ref(false);

function connect(): void {
    sending.value = true;
    router.post(
        '/addon/connect',
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
    <Head title="Połącz addon">
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

            <template v-if="token">
                <!-- Addon odczytuje token z tego atrybutu i zapamiętuje go u siebie. -->
                <div :data-addon-token="token" hidden></div>
                <h1 class="skin-display text-[34px] leading-tight">Gotowe!</h1>
                <p id="addon-status" class="text-[16px] text-muted">
                    Addon jest połączony z Twoimi listami. Możesz zamknąć tę
                    kartę i dodawać prezenty ikoną Tobym na stronach sklepów.
                </p>
            </template>

            <template v-else>
                <h1 class="skin-display text-[34px] leading-tight">
                    Połącz addon Chrome
                </h1>
                <p class="text-[16px] text-muted">
                    <template v-if="user"
                        >Prezenty z addona trafią na listy konta
                        {{ user.email }}.</template
                    >
                    <template v-else
                        >Prezenty z addona trafią na listy zapisane w tej
                        przeglądarce. Po zapisaniu list mailem addon przejdzie
                        na konto razem z nimi.</template
                    >
                </p>
                <button
                    type="button"
                    :disabled="sending"
                    class="skin-outline h-12 rounded-control bg-accent px-6 text-base font-semibold text-on-accent disabled:opacity-60"
                    @click="connect"
                >
                    {{ sending ? 'Łączę…' : 'Połącz addon' }}
                </button>
            </template>
        </main>
    </div>
</template>
