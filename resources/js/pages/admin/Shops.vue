<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/components/AdminLayout.vue';
import { confirmAction } from '@/lib/ui';

type Shop = {
    domain: string;
    items: number;
    with_image: number;
    with_price: number;
    recipe: Record<string, unknown> | null;
    generated_at: string | null;
};

defineProps<{ shops: Shop[] }>();

function percent(part: number, all: number): string {
    return all === 0 ? '–' : `${Math.round((part / all) * 100)}%`;
}

async function forget(domain: string): Promise<void> {
    const ok = await confirmAction({
        title: `Usunąć przepis dla ${domain}?`,
        text: 'Przy następnym linku z tego sklepu AI nauczy się go od nowa.',
        confirmLabel: 'Usuń przepis',
    });

    if (ok) {
        router.delete(`/admin/sklepy/${domain}`, { preserveScroll: true });
    }
}
</script>

<template>
    <AdminLayout title="Sklepy">
        <p class="text-sm text-muted">
            Jak dobrze czytamy każdy sklep, liczone z dodanych prezentów.
            Zdjęcia i ceny wpisane ręcznie też się liczą.
        </p>
        <div class="overflow-x-auto rounded-card border border-line bg-surface">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-line text-muted">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Sklep</th>
                        <th class="px-4 py-3 text-right font-semibold">
                            Prezenty
                        </th>
                        <th class="px-4 py-3 text-right font-semibold">
                            Ze zdjęciem
                        </th>
                        <th class="px-4 py-3 text-right font-semibold">
                            Z ceną
                        </th>
                        <th class="px-4 py-3 font-semibold">Przepis AI</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    <tr
                        v-for="shop in shops"
                        :key="shop.domain"
                        class="align-top"
                    >
                        <td class="px-4 py-3 font-semibold">
                            {{ shop.domain }}
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            {{ shop.items }}
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            {{ percent(shop.with_image, shop.items) }}
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            {{ percent(shop.with_price, shop.items) }}
                        </td>
                        <td class="max-w-md px-4 py-3">
                            <details v-if="shop.recipe">
                                <summary class="cursor-pointer">
                                    jest
                                    <span class="text-muted"
                                        >({{ shop.generated_at ?? '?' }})</span
                                    >
                                </summary>
                                <pre
                                    class="mt-2 overflow-x-auto rounded-control bg-tile p-2 text-xs whitespace-pre-wrap"
                                    >{{
                                        JSON.stringify(shop.recipe, null, 2)
                                    }}</pre>
                            </details>
                            <span v-else class="text-muted">brak</span>
                        </td>
                        <td
                            class="flex flex-col items-end gap-1 px-4 py-3 whitespace-nowrap"
                        >
                            <a
                                :href="`/admin/symulator?url=${encodeURIComponent(`https://${shop.domain}/`)}`"
                                class="underline underline-offset-4"
                                >Symuluj</a
                            >
                            <button
                                v-if="shop.recipe"
                                type="button"
                                class="text-accent underline underline-offset-4"
                                @click="forget(shop.domain)"
                            >
                                Usuń przepis
                            </button>
                        </td>
                    </tr>
                    <tr v-if="shops.length === 0">
                        <td colspan="6" class="px-4 py-6 text-muted">
                            Brak danych.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>
