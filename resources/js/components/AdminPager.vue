<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{
    pager: {
        current_page: number;
        last_page: number;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}>();
</script>

<template>
    <div class="flex items-center justify-between text-sm text-muted">
        <span>Razem: {{ pager.total }}</span>
        <div v-if="pager.last_page > 1" class="flex items-center gap-3">
            <Link
                v-if="pager.prev_page_url"
                :href="pager.prev_page_url"
                class="font-semibold text-ink underline underline-offset-4"
                >← Poprzednia</Link
            >
            <span>{{ pager.current_page }} / {{ pager.last_page }}</span>
            <Link
                v-if="pager.next_page_url"
                :href="pager.next_page_url"
                class="font-semibold text-ink underline underline-offset-4"
                >Następna →</Link
            >
        </div>
    </div>
</template>
