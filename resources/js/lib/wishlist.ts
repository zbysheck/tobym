import type { GiftPreview, WishlistItem } from '@/types';

/** Adres zmian listy: konkretnej (po slugu) albo bieżącej, gdy lista jeszcze nie ma adresu. */
export function listUrl(slug: string | null): string {
    return slug === null ? '/list' : `/l/${slug}`;
}

export function itemsUrl(slug: string | null): string {
    return slug === null ? '/items' : `/l/${slug}/items`;
}

export function xsrfToken(): string {
    const match = /(?:^|; )XSRF-TOKEN=([^;]+)/.exec(document.cookie);

    return match ? decodeURIComponent(match[1]) : '';
}

export type PreviewResult =
    | { ok: true; preview: GiftPreview }
    | { ok: false; error: string };

export function fetchPreview(
    link: string,
    deep: boolean,
): Promise<PreviewResult> {
    return postPreview('/items/preview', { url: link, deep });
}

/** Podgląd z HTML odczytanego przez addon w przeglądarce użytkownika. */
export function fetchPreviewFromHtml(
    link: string,
    html: string,
): Promise<PreviewResult> {
    return postPreview('/items/preview-html', { url: link, html });
}

async function postPreview(
    path: string,
    body: Record<string, unknown>,
): Promise<PreviewResult> {
    try {
        const response = await fetch(path, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            body: JSON.stringify(body),
        });

        if (response.status === 422) {
            const data = (await response.json()) as {
                errors?: Record<string, string[]>;
            };

            return {
                ok: false,
                error: data.errors?.url?.[0] ?? 'Nie udało się odczytać linku.',
            };
        }

        if (!response.ok) {
            return {
                ok: false,
                error: 'Nie udało się pobrać danych. Odśwież stronę i spróbuj ponownie.',
            };
        }

        return { ok: true, preview: (await response.json()) as GiftPreview };
    } catch {
        return { ok: false, error: 'Brak połączenia. Spróbuj ponownie.' };
    }
}

export function priceValue(item: WishlistItem): number | null {
    const match = /\d+(?:\.\d+)?/.exec(
        (item.price ?? '').replace(/[\s ]/g, '').replace(',', '.'),
    );

    return match ? Number(match[0]) : null;
}

/** Kolor tekstu na tle akcentu: biały albo prawie czarny, zależnie od jasności. */
export function textOn(hex: string): string {
    const value = hex.replace('#', '');
    const [r, g, b] = [0, 2, 4].map((offset) => {
        const channel = parseInt(value.slice(offset, offset + 2), 16) / 255;

        return channel <= 0.03928
            ? channel / 12.92
            : ((channel + 0.055) / 1.055) ** 2.4;
    });
    const luminance = 0.2126 * r + 0.7152 * g + 0.0722 * b;

    return luminance > 0.4 ? '#111111' : '#ffffff';
}

/** "Za 12 dni", "Jutro", "Dziś!" albo "Minęło", względem dzisiejszej daty. */
export function countdown(date: string): string {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const target = new Date(`${date}T00:00:00`);
    const days = Math.round((target.getTime() - today.getTime()) / 86_400_000);

    if (days < 0) {
        return 'Wydarzenie już minęło';
    }

    if (days === 0) {
        return 'To już dziś!';
    }

    if (days === 1) {
        return 'Już jutro';
    }

    return `Za ${days} dni`;
}

export function formatDate(date: string): string {
    return new Date(`${date}T00:00:00`).toLocaleDateString('pl-PL', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}
