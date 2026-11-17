<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted } from 'vue';

const page = usePage();
const isAdmin = computed(() => page.props.auth.isAdmin === true);
const viewingAs = computed(() => page.props.auth.viewingAs ?? null);
const path = computed(() => page.url.split('?')[0]);

// Adres listy na stronie listy (także w podglądzie gościa z panelu).
const slug = computed<string | null>(() => {
    const match = /^\/(?:l|admin\/listy)\/([a-z0-9]+)/.exec(path.value);

    return match ? match[1] : null;
});
const guestPreview = computed(() => path.value.startsWith('/admin/listy/'));

function viewAsOwner(): void {
    if (slug.value !== null) {
        router.post(`/admin/jako/lista/${slug.value}`);
    }
}

function stop(): void {
    router.post('/admin/jako/koniec');
}

onMounted(() => {
    if (isAdmin.value) {
        document.body.style.paddingTop = '1.5rem';
    }
});

onBeforeUnmount(() => {
    document.body.style.paddingTop = '';
});
</script>

<template>
    <Teleport v-if="isAdmin" to="body">
        <div
            class="fixed inset-x-0 top-0 z-50 flex h-6 items-center gap-2.5 overflow-x-auto px-3 font-mono text-[11px] leading-none whitespace-nowrap"
            :class="
                viewingAs
                    ? 'bg-amber-400 text-black'
                    : 'bg-neutral-900/90 text-neutral-300'
            "
        >
            <span
                class="rounded-sm px-1 py-px text-[10px] font-bold uppercase"
                :class="
                    viewingAs
                        ? 'bg-black text-amber-300'
                        : 'bg-white text-black'
                "
                >Admin</span
            >

            <template v-if="viewingAs">
                <span
                    >Oglądasz jako <strong>{{ viewingAs }}</strong
                    >. Zmiany są prawdziwe.</span
                >
                <button
                    type="button"
                    class="rounded-sm bg-black px-1.5 py-0.5 font-semibold text-white"
                    @click="stop"
                >
                    Wróć do siebie
                </button>
            </template>

            <template v-else>
                <a href="/admin" class="hover:underline">Panel</a>
                <a href="/admin/zdarzenia" class="hover:underline">Zdarzenia</a>
                <template v-if="slug">
                    <span class="opacity-50">|</span>
                    <span class="opacity-70">Wyświetl jako:</span>
                    <a
                        v-if="guestPreview"
                        :href="`/l/${slug}`"
                        class="hover:underline"
                        >siebie</a
                    >
                    <a
                        v-else
                        :href="`/admin/listy/${slug}`"
                        class="hover:underline"
                        >gość</a
                    >
                    <button
                        type="button"
                        class="hover:underline"
                        @click="viewAsOwner"
                    >
                        właściciel
                    </button>
                    <span class="opacity-50">|</span>
                    <a
                        :href="`/admin/zdarzenia?lista=${slug}`"
                        class="hover:underline"
                        >Zdarzenia tej listy</a
                    >
                    <span v-if="guestPreview" class="opacity-70"
                        >podgląd gościa, bez PIN-u</span
                    >
                </template>
            </template>
            <span class="ml-auto pl-4 opacity-50">{{ page.component }}</span>
        </div>
    </Teleport>
</template>
