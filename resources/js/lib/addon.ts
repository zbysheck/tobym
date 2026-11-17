/** Czy w tej przeglądarce działa addon Tobym (ustawia znacznik na <html>). */
export function hasAddon(): boolean {
    return document.documentElement.dataset.tobymAddon !== undefined;
}

type PageRead = {
    type: 'tobym:page-read';
    id: string;
    ok?: boolean;
    html?: unknown;
};

/**
 * Prosi addon o odczytanie strony produktu w przeglądarce użytkownika (karta w tle).
 * Zwraca HTML albo null, gdy addonu nie ma, coś poszło nie tak lub minął czas.
 */
export function readPageWithAddon(
    url: string,
    timeoutMs = 35_000,
): Promise<string | null> {
    if (!hasAddon()) {
        return Promise.resolve(null);
    }

    const id = `${Date.now()}-${Math.random().toString(36).slice(2)}`;

    return new Promise((resolve) => {
        const timer = window.setTimeout(() => finish(null), timeoutMs);

        function onMessage(event: MessageEvent<PageRead>): void {
            if (
                event.source !== window ||
                event.origin !== location.origin ||
                event.data?.type !== 'tobym:page-read' ||
                event.data.id !== id
            ) {
                return;
            }

            const { ok, html } = event.data;
            finish(
                ok === true && typeof html === 'string' && html !== ''
                    ? html
                    : null,
            );
        }

        function finish(html: string | null): void {
            window.clearTimeout(timer);
            window.removeEventListener('message', onMessage);
            resolve(html);
        }

        window.addEventListener('message', onMessage);
        window.postMessage(
            { type: 'tobym:read-page', id, url },
            location.origin,
        );
    });
}
