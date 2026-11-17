<script setup lang="ts">
withDefaults(defineProps<{ size?: 'sm' | 'md' | 'lg' }>(), { size: 'md' });
</script>

<template>
    <a
        href="/"
        class="inline-flex items-center gap-2 text-ink no-underline"
        :class="{
            'text-[22px]': size === 'sm',
            'text-[27px]': size === 'md',
            'text-[34px]': size === 'lg',
        }"
        aria-label="tobym, strona główna"
    >
        <svg
            aria-hidden="true"
            viewBox="0 0 64 64"
            class="h-[1.15em] w-[1.15em] flex-none"
        >
            <path d="M32 19C27 9 17 6 13.5 10.5S16 19 32 19Z" fill="#b5302a" />
            <path d="M32 19C37 9 47 6 50.5 10.5S48 19 32 19Z" fill="#b5302a" />
            <rect x="9" y="28" width="46" height="30" rx="6" fill="#17392c" />
            <rect x="5" y="18" width="54" height="13" rx="5" fill="#245641" />
            <rect x="28" y="18" width="8" height="40" fill="#b5302a" />
            <circle cx="32" cy="18.5" r="4.5" fill="#8f2420" />
        </svg>
        <span class="font-brand leading-none tracking-tight">tobym</span>
    </a>
</template>
