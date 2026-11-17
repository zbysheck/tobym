<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '@/components/AdminLayout.vue';
import AdminPager from '@/components/AdminPager.vue';

type Row = {
    id: number;
    slug: string;
    title: string;
    theme: string;
    owner: string | null;
    items: number;
    reserved: number;
    pin: boolean;
    event_date: string | null;
    updated_at: string | null;
};

const props = defineProps<{
    lists: {
        data: Row[];
        current_page: number;
        last_page: number;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    q: string;
}>();

const query = ref(props.q);

function search(): void {
    router.get('/admin/listy', query.value === '' ? {} : { q: query.value }, {
        preserveState: true,
    });
}
</script>

<template>
    <AdminLayout title="Listy">
        <form class="flex gap-2" @submit.prevent="search">
            <input
                v-model="query"
                type="search"
                aria-label="Szukaj list"
                placeholder="Tytuł, mail właściciela albo adres listy"
                class="h-11 flex-1 rounded-control border border-line bg-surface px-3"
            />
            <button
                type="submit"
                class="h-11 rounded-control bg-ink px-5 font-semibold text-ground"
            >
                Szukaj
            </button>
        </form>

        <div class="overflow-x-auto rounded-card border border-line bg-surface">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-line text-muted">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Lista</th>
                        <th class="px-4 py-3 font-semibold">Właściciel</th>
                        <th class="px-4 py-3 font-semibold">Skórka</th>
                        <th class="px-4 py-3 text-right font-semibold">
                            Prezenty
                        </th>
                        <th class="px-4 py-3 text-right font-semibold">
                            Zarezerw.
                        </th>
                        <th class="px-4 py-3 font-semibold">Zmiana</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    <tr v-for="list in lists.data" :key="list.id">
                        <td class="px-4 py-3">
                            <a
                                :href="`/admin/listy/${list.slug}`"
                                class="font-semibold underline underline-offset-4"
                                >{{ list.title }}</a
                            >
                            <span
                                v-if="list.pin"
                                class="ml-2 rounded-full bg-tile px-2 py-0.5 text-xs"
                                >PIN</span
                            >
                            <p class="text-xs text-muted">
                                /l/{{ list.slug }}
                                <template v-if="list.event_date">
                                    · {{ list.event_date }}</template
                                >
                            </p>
                        </td>
                        <td class="px-4 py-3">
                            <span v-if="list.owner">{{ list.owner }}</span>
                            <span v-else class="text-muted">szkic</span>
                        </td>
                        <td class="px-4 py-3">{{ list.theme }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            {{ list.items }}
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            {{ list.reserved }}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-muted">
                            {{ list.updated_at }}
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a
                                :href="`/admin/zdarzenia?list=${list.id}`"
                                class="mr-3 underline underline-offset-4"
                                >Zdarzenia</a
                            >
                            <button
                                type="button"
                                class="underline underline-offset-4"
                                @click="
                                    router.post(
                                        `/admin/jako/lista/${list.slug}`,
                                    )
                                "
                            >
                                Jako właściciel
                            </button>
                        </td>
                    </tr>
                    <tr v-if="lists.data.length === 0">
                        <td colspan="7" class="px-4 py-6 text-muted">
                            Nic nie znaleziono.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <AdminPager :pager="lists" />
        <p class="text-sm text-muted">
            Kliknięcie listy pokazuje ją oczami gościa (bez PIN-u). Rezerwacje
            widać tak jak gościom, więc rezerwując coś w podglądzie, robisz
            prawdziwą rezerwację.
        </p>
    </AdminLayout>
</template>
