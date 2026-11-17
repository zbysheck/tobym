import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { celebrate } from '@/lib/celebrate';
import { toast } from '@/lib/ui';
import { hasAddon, readPageWithAddon } from '@/lib/addon';
import { fetchPreview, fetchPreviewFromHtml } from '@/lib/wishlist';
import type { GiftDetails, GiftPreview } from '@/types';

/**
 * Wspólny przebieg dodawania prezentu: szybki podgląd, dociąganie danych w tle przez AI,
 * okno potwierdzenia z wyborem listy i zapis.
 */
export function useGiftAdder(onAdded?: () => void) {
    const preview = ref<GiftPreview | null>(null);
    const loading = ref(false);
    const enriching = ref(false);
    const saving = ref(false);
    const urlError = ref('');
    const errors = ref<Record<string, string>>({});

    async function addLink(link: string): Promise<void> {
        if (loading.value) {
            return;
        }

        loading.value = true;
        urlError.value = '';
        errors.value = {};

        const quick = await fetchPreview(link, false);
        loading.value = false;

        if (!quick.ok) {
            urlError.value = quick.error;
            toast(quick.error);

            return;
        }

        preview.value = quick.preview;

        if (quick.preview.blocked && hasAddon()) {
            await completeWithAddon(link);

            return;
        }

        if (!quick.preview.pending) {
            return;
        }

        enriching.value = true;
        const deep = await fetchPreview(link, true);
        enriching.value = false;

        if (!deep.ok || preview.value?.url !== link) {
            return;
        }

        const current = preview.value;
        preview.value = {
            ...current,
            title: deep.preview.title,
            image_url: current.image_url ?? deep.preview.image_url,
            price: current.price ?? deep.preview.price,
            shop: current.shop ?? deep.preview.shop,
            pending: false,
        };
    }

    /** Sklep nie wpuszcza serwera: addon czyta produkt w przeglądarce użytkownika. */
    async function completeWithAddon(link: string): Promise<void> {
        enriching.value = true;
        const html = await readPageWithAddon(link);
        const result =
            html === null ? null : await fetchPreviewFromHtml(link, html);
        enriching.value = false;

        const current = preview.value;

        if (current?.url !== link) {
            return;
        }

        if (result === null || !result.ok) {
            preview.value = { ...current, viaAddon: true };

            return;
        }

        preview.value = {
            ...current,
            title: result.preview.title,
            image_url: result.preview.image_url ?? current.image_url,
            price: result.preview.price ?? current.price,
            shop: result.preview.shop ?? current.shop,
            pending: false,
            blocked: false,
        };
    }

    function addManual(): void {
        errors.value = {};
        preview.value = {
            url: null,
            title: '',
            shop: null,
            image_url: null,
            price: null,
            pending: false,
        };
    }

    function confirm(details: GiftDetails): void {
        const current = preview.value;

        if (current === null) {
            return;
        }

        saving.value = true;
        router.post(
            '/items',
            {
                url: current.url,
                title: details.title,
                price: details.price,
                note: details.note,
                priority: details.priority ? 1 : 0,
                shop: current.shop,
                image_url: current.image_url,
                photo: details.photo,
                list: details.list,
                new_list: details.newList,
            },
            {
                preserveScroll: true,
                forceFormData: details.photo !== null,
                onSuccess: () => {
                    preview.value = null;
                    toast('Dodano do listy');
                    celebrate({ x: 0.5, y: 0.35 });
                    onAdded?.();
                },
                onError: (bag) => {
                    errors.value = bag;
                },
                onFinish: () => {
                    saving.value = false;
                },
            },
        );
    }

    function cancel(): void {
        preview.value = null;
    }

    return {
        preview,
        loading,
        enriching,
        saving,
        urlError,
        errors,
        addLink,
        addManual,
        confirm,
        cancel,
    };
}
