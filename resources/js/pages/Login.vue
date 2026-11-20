<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminBar from '@/components/AdminBar.vue';
import AppLogo from '@/components/AppLogo.vue';
import SiteFooter from '@/components/SiteFooter.vue';
import { themeFontsUrl } from '@/lib/themes';

const page = usePage();
const status = computed(() => page.props.flash.status);
const errors = computed(() => page.props.errors);

const email = ref('');
const sending = ref(false);

function send(): void {
    if (email.value.trim() === '' || sending.value) {
        return;
    }

    sending.value = true;
    router.post(
        '/login-link',
        { email: email.value.trim() },
        {
            preserveScroll: true,
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
        <link rel="stylesheet" :href="themeFontsUrl('swiateczna')" />
    </Head>

    <AdminBar />

    <div
        data-theme="swiateczna"
        class="skin-backdrop flex min-h-screen flex-col bg-ground font-body text-ink antialiased"
    >
        <header class="mx-auto w-full max-w-6xl px-5 py-5 sm:px-8">
            <AppLogo />
        </header>

        <main class="flex flex-1 items-start justify-center px-5 pt-10 pb-20">
            <form
                class="skin-outline flex w-full max-w-md flex-col gap-4 rounded-card bg-surface p-7 shadow-card"
                @submit.prevent="send"
            >
                <h1 class="font-brand text-4xl leading-tight">Zaloguj się</h1>
                <p class="text-muted">
                    Bez hasła. Wyślemy Ci na maila link, który zaloguje Cię
                    jednym kliknięciem.
                </p>
                <label class="flex flex-col gap-1.5">
                    <span class="text-sm font-semibold">Adres e-mail</span>
                    <input
                        v-model="email"
                        v-focus
                        type="email"
                        required
                        autocomplete="email"
                        placeholder="twoj@email.pl"
                        class="skin-outline h-12 rounded-control bg-surface px-3 text-base"
                    />
                </label>
                <p
                    v-if="errors.email"
                    role="alert"
                    class="text-sm font-semibold text-accent"
                >
                    {{ errors.email }}
                </p>
                <p
                    v-if="status"
                    role="status"
                    class="rounded-control bg-tile px-3 py-2.5 text-sm"
                >
                    {{ status }}
                </p>
                <button
                    type="submit"
                    :disabled="sending"
                    class="h-12 rounded-control bg-accent text-[16px] font-semibold text-on-accent disabled:opacity-60"
                >
                    {{ sending ? 'Wysyłam…' : 'Wyślij link logowania' }}
                </button>
                <p class="text-sm text-muted">
                    Nie masz jeszcze konta? Ten sam link je założy.
                </p>
            </form>
        </main>

        <SiteFooter />
    </div>
</template>
