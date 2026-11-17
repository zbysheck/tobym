<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import AdminLayout from '@/components/AdminLayout.vue';
import AdminPager from '@/components/AdminPager.vue';

type Event = {
    id: number;
    type: string;
    description: string;
    user: { id: number; email: string } | null;
    actor: string | null;
    list: { id: number; slug: string; title: string } | null;
    meta: Record<string, unknown> | null;
    created_at: string | null;
};

const props = defineProps<{
    events: {
        data: Event[];
        current_page: number;
        last_page: number;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: {
        type: string;
        q: string;
        list: number | null;
        user: number | null;
    };
    types: { value: string; label: string }[];
}>();

const form = reactive({ type: props.filters.type, q: props.filters.q });

function apply(extra: Record<string, number | null> = {}): void {
    const query: Record<string, string | number> = {};

    if (form.type !== '') {
        query.type = form.type;
    }

    if (form.q !== '') {
        query.q = form.q;
    }

    const list = 'list' in extra ? extra.list : props.filters.list;
    const user = 'user' in extra ? extra.user : props.filters.user;

    if (list) {
        query.list = list;
    }

    if (user) {
        query.user = user;
    }

    router.get('/admin/zdarzenia', query, { preserveState: true });
}

const badge: Record<string, string> = {
    list: 'bg-sky-100 text-sky-900',
    item: 'bg-emerald-100 text-emerald-900',
    reservation: 'bg-violet-100 text-violet-900',
    preview: 'bg-amber-100 text-amber-900',
    auth: 'bg-neutral-200 text-neutral-900',
    account: 'bg-rose-100 text-rose-900',
    admin: 'bg-neutral-900 text-white',
};

function badgeClass(type: string): string {
    return badge[type.split('.')[0]] ?? 'bg-tile';
}
</script>

<template>
    <AdminLayout title="Zdarzenia">
        <form class="flex flex-wrap gap-2" @submit.prevent="apply()">
            <select
                v-model="form.type"
                aria-label="Rodzaj zdarzeń"
                class="h-11 rounded-control border border-line bg-surface px-3"
                @change="apply()"
            >
                <option
                    v-for="type in types"
                    :key="type.value"
                    :value="type.value"
                >
                    {{ type.label }}
                </option>
            </select>
            <input
                v-model="form.q"
                type="search"
                aria-label="Szukaj w opisie"
                placeholder="Szukaj w opisie, np. mediaexpert"
                class="h-11 min-w-48 flex-1 rounded-control border border-line bg-surface px-3"
            />
            <button
                type="submit"
                class="h-11 rounded-control bg-ink px-5 font-semibold text-ground"
            >
                Filtruj
            </button>
        </form>

        <div
            v-if="filters.list || filters.user"
            class="flex flex-wrap gap-2 text-sm"
        >
            <button
                v-if="filters.list"
                type="button"
                class="rounded-full bg-tile px-3 py-1"
                @click="apply({ list: null })"
            >
                Tylko jedna lista ✕
            </button>
            <button
                v-if="filters.user"
                type="button"
                class="rounded-full bg-tile px-3 py-1"
                @click="apply({ user: null })"
            >
                Tylko jedno konto ✕
            </button>
        </div>

        <ul
            class="divide-y divide-line overflow-hidden rounded-card border border-line bg-surface text-sm"
        >
            <li
                v-for="event in events.data"
                :key="event.id"
                class="flex flex-wrap items-baseline gap-x-3 gap-y-1 px-4 py-2.5"
            >
                <span class="w-36 flex-none text-muted tabular-nums">{{
                    event.created_at
                }}</span>
                <span
                    class="flex-none rounded-full px-2 py-0.5 text-xs font-semibold"
                    :class="badgeClass(event.type)"
                    >{{ event.type }}</span
                >
                <span class="min-w-0 flex-1">
                    {{ event.description }}
                    <span
                        v-if="event.actor"
                        class="ml-1 rounded bg-amber-300 px-1.5 py-0.5 text-xs text-black"
                        >admin {{ event.actor }}</span
                    >
                </span>
                <span class="flex flex-none gap-3 text-xs">
                    <button
                        v-if="event.list"
                        type="button"
                        class="underline underline-offset-2"
                        @click="apply({ list: event.list.id })"
                    >
                        {{ event.list.title }}
                    </button>
                    <button
                        v-if="event.user"
                        type="button"
                        class="underline underline-offset-2"
                        @click="apply({ user: event.user.id })"
                    >
                        {{ event.user.email }}
                    </button>
                    <span v-else-if="!event.actor" class="text-muted"
                        >gość / szkic</span
                    >
                </span>
            </li>
            <li v-if="events.data.length === 0" class="px-4 py-6 text-muted">
                Brak zdarzeń.
            </li>
        </ul>
        <AdminPager :pager="events" />
        <p class="text-sm text-muted">
            Zdarzenia starsze niż 90 dni są usuwane automatycznie.
        </p>
    </AdminLayout>
</template>
