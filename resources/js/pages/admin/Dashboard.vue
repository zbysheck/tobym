<script setup lang="ts">
import AdminLayout from '@/components/AdminLayout.vue';

type Item = {
    id: number;
    title: string;
    url: string | null;
    shop: string | null;
    price: string | null;
    image_url: string | null;
    list: { id: number; slug: string; title: string } | null;
    created_at: string | null;
};

defineProps<{
    stats: { label: string; value: number }[];
    items: Item[];
}>();
</script>

<template>
    <AdminLayout title="Przegląd">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div
                v-for="stat in stats"
                :key="stat.label"
                class="rounded-card border border-line bg-surface p-4"
            >
                <p class="text-sm text-muted">{{ stat.label }}</p>
                <p class="mt-1 text-3xl font-semibold tabular-nums">
                    {{ stat.value }}
                </p>
            </div>
        </div>

        <section class="flex flex-col gap-3">
            <h2 class="text-xl font-semibold">Ostatnio dodane prezenty</h2>
            <ul
                class="divide-y divide-line overflow-hidden rounded-card border border-line bg-surface"
            >
                <li
                    v-for="item in items"
                    :key="item.id"
                    class="flex items-center gap-4 px-4 py-3"
                >
                    <div
                        class="flex h-12 w-12 flex-none items-center justify-center overflow-hidden rounded-control bg-tile text-xs text-muted"
                    >
                        <img
                            v-if="item.image_url"
                            :src="item.image_url"
                            alt=""
                            referrerpolicy="no-referrer"
                            class="h-full w-full bg-white object-contain"
                        />
                        <span v-else>brak</span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold">{{ item.title }}</p>
                        <p class="truncate text-sm text-muted">
                            {{ item.shop ?? 'bez sklepu' }} ·
                            {{ item.price ?? 'bez ceny' }} ·
                            {{ item.created_at }}
                        </p>
                    </div>
                    <a
                        v-if="item.list"
                        :href="`/admin/listy/${item.list.slug}`"
                        class="hidden flex-none text-sm underline underline-offset-4 sm:block"
                        >{{ item.list.title }}</a
                    >
                </li>
                <li v-if="items.length === 0" class="px-4 py-6 text-muted">
                    Jeszcze nic nie dodano.
                </li>
            </ul>
        </section>
    </AdminLayout>
</template>
