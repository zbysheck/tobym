<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/components/AdminLayout.vue';
import AdminPager from '@/components/AdminPager.vue';

defineProps<{
    users: {
        data: {
            id: number;
            email: string;
            lists: number;
            admin: boolean;
            created_at: string | null;
        }[];
        current_page: number;
        last_page: number;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}>();
</script>

<template>
    <AdminLayout title="Konta">
        <div class="overflow-x-auto rounded-card border border-line bg-surface">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-line text-muted">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Mail</th>
                        <th class="px-4 py-3 text-right font-semibold">
                            Listy
                        </th>
                        <th class="px-4 py-3 font-semibold">Założone</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    <tr v-for="user in users.data" :key="user.id">
                        <td class="px-4 py-3">
                            <a
                                :href="`/admin/listy?q=${encodeURIComponent(user.email)}`"
                                class="underline underline-offset-4"
                                >{{ user.email }}</a
                            >
                            <span
                                v-if="user.admin"
                                class="ml-2 rounded-full bg-ink px-2 py-0.5 text-xs text-ground"
                                >admin</span
                            >
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            {{ user.lists }}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-muted">
                            {{ user.created_at }}
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a
                                :href="`/admin/zdarzenia?user=${user.id}`"
                                class="mr-3 underline underline-offset-4"
                                >Zdarzenia</a
                            >
                            <button
                                v-if="!user.admin"
                                type="button"
                                class="underline underline-offset-4"
                                @click="
                                    router.post(`/admin/jako/konto/${user.id}`)
                                "
                            >
                                Wyświetl jako
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <AdminPager :pager="users" />
    </AdminLayout>
</template>
