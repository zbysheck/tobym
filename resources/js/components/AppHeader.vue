<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AdminBar from '@/components/AdminBar.vue';
import AppLogo from '@/components/AppLogo.vue';

const page = usePage();
const user = computed(() => page.props.auth.user);
const hasLists = computed(() => page.props.hasLists);
const menuOpen = ref(false);
const menu = ref<HTMLElement | null>(null);
const initial = computed(() =>
    (user.value?.email ?? '?').charAt(0).toUpperCase(),
);

function onDocumentClick(event: MouseEvent): void {
    if (menu.value && !menu.value.contains(event.target as Node)) {
        menuOpen.value = false;
    }
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        menuOpen.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onKeydown);
});

function logout(): void {
    router.post('/logout');
}
</script>

<template>
    <header class="flex flex-wrap items-center justify-between gap-3 py-5">
        <AdminBar />
        <AppLogo />
        <nav aria-label="Konto" class="flex items-center gap-2">
            <a
                v-if="hasLists || user"
                href="/moje-listy"
                class="skin-outline press inline-flex h-11 items-center gap-2 rounded-control bg-surface px-4 text-[15px] font-semibold text-ink"
            >
                <svg
                    aria-hidden="true"
                    width="18"
                    height="18"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                >
                    <path d="M8 6h12M8 12h12M8 18h12" />
                    <circle cx="4" cy="6" r="1" />
                    <circle cx="4" cy="12" r="1" />
                    <circle cx="4" cy="18" r="1" />
                </svg>
                Moje listy
            </a>
            <a
                v-if="!user"
                href="/logowanie"
                class="px-2 text-[15px] font-semibold underline-offset-4 hover:underline"
                >Zaloguj</a
            >
            <div v-if="user" ref="menu" class="relative">
                <button
                    type="button"
                    :aria-expanded="menuOpen"
                    aria-haspopup="menu"
                    :aria-label="`Konto ${user.email}`"
                    class="press flex h-11 w-11 items-center justify-center rounded-full bg-ink text-[17px] font-semibold text-ground"
                    @click.stop="menuOpen = !menuOpen"
                >
                    {{ initial }}
                </button>
                <Transition name="menu">
                    <div
                        v-if="menuOpen"
                        role="menu"
                        class="skin-outline absolute right-0 z-40 mt-2 flex w-64 flex-col overflow-hidden rounded-card bg-surface text-ink shadow-card"
                    >
                        <p
                            class="border-b border-line px-4 py-3 text-sm text-muted"
                        >
                            Zalogowano jako<br />
                            <strong class="break-all text-ink">{{
                                user.email
                            }}</strong>
                        </p>
                        <a
                            role="menuitem"
                            href="/moje-listy"
                            class="px-4 py-3 text-[15px] hover:bg-tile"
                            >Moje listy</a
                        >
                        <a
                            v-if="page.props.auth.isAdmin"
                            role="menuitem"
                            href="/admin"
                            class="px-4 py-3 text-[15px] hover:bg-tile"
                            >Panel admina</a
                        >
                        <a
                            role="menuitem"
                            href="/jak-to-dziala"
                            class="px-4 py-3 text-[15px] hover:bg-tile"
                            >Jak to działa</a
                        >
                        <button
                            role="menuitem"
                            type="button"
                            class="border-t border-line px-4 py-3 text-left text-[15px] hover:bg-tile"
                            @click="logout"
                        >
                            Wyloguj
                        </button>
                    </div>
                </Transition>
            </div>
        </nav>
    </header>
</template>
