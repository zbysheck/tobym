<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AddGiftDialog from '@/components/AddGiftDialog.vue';
import DropOverlay from '@/components/DropOverlay.vue';
import { useGiftAdder } from '@/lib/useGiftAdder';
import { useLinkCapture } from '@/lib/useLinkCapture';

/**
 * Dodawanie prezentu z dowolnej strony: wklejenie linku (Ctrl+V), upuszczenie linku
 * albo wywołanie addLink() z zewnątrz. Pyta, na którą listę dodać, albo tworzy nową.
 */
const props = withDefaults(defineProps<{ defaultList?: string | null }>(), {
    defaultList: null,
});

const page = usePage();
const lists = computed(() => page.props.myLists ?? []);

const adder = useGiftAdder();
const { preview, enriching, saving, errors, loading } = adder;
const { dragging } = useLinkCapture(
    (url) => void adder.addLink(url),
    () => true,
);

defineExpose({ addLink: adder.addLink, addManual: adder.addManual, loading });
</script>

<template>
    <DropOverlay :visible="dragging" />
    <AddGiftDialog
        :preview="preview"
        :enriching="enriching"
        :saving="saving"
        :errors="errors"
        :lists="lists"
        :default-list="props.defaultList ?? lists[0]?.slug ?? null"
        @confirm="adder.confirm"
        @cancel="adder.cancel"
    />
</template>
