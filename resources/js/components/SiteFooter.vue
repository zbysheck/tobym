<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
</script>

<template>
    <footer class="skin-outline border-x-0 border-b-0 bg-surface/70">
        <div
            class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-x-8 gap-y-4 px-5 py-8 sm:px-8"
        >
            <div class="flex flex-col gap-1">
                <AppLogo size="sm" />
                <p class="text-sm text-muted">
                    Listy prezentów, z których naprawdę da się kupić.
                </p>
            </div>
            <nav aria-label="Stopka" class="flex flex-wrap gap-x-6 gap-y-2">
                <a
                    href="/jak-to-dziala"
                    class="text-sm text-ink underline-offset-4 hover:underline"
                    >Jak to działa</a
                >
                <a
                    href="/prywatnosc"
                    class="text-sm text-ink underline-offset-4 hover:underline"
                    >Prywatność i ciasteczka</a
                >
            </nav>
        </div>
    </footer>
</template>
