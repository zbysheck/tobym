/** Czy w tej przeglądarce działa wtyczka Tobym (ustawia znacznik na <html>). */
export function hasAddon(): boolean {
    return document.documentElement.dataset.tobymAddon !== undefined;
}

type Reply = {
    type: 'tobym:reply';
    id: string;
    ok?: boolean;
    html?: unknown;
    url?: unknown;
};

function ask(
    type: string,
    payload: Record<string, string>,
    timeoutMs: number,
): Promise<Reply | null> {
    if (!hasAddon()) {
        return Promise.resolve(null);
    }

    const id = `${Date.now()}-${Math.random().toString(36).slice(2)}`;

    return new Promise((resolve) => {
        const timer = window.setTimeout(() => finish(null), timeoutMs);

        function onMessage(event: MessageEvent<Reply>): void {
            if (
                event.source !== window ||
                event.origin !== location.origin ||
                event.data?.type !== 'tobym:reply' ||
                event.data.id !== id
            ) {
                return;
            }

            finish(event.data.ok === true ? event.data : null);
        }

        function finish(reply: Reply | null): void {
            window.clearTimeout(timer);
            window.removeEventListener('message', onMessage);
            resolve(reply);
        }

        window.addEventListener('message', onMessage);
        window.postMessage({ type, id, ...payload }, location.origin);
    });
}

/**
 * Prosi wtyczkę o odczytanie strony produktu w przeglądarce użytkownika (karta w tle).
 * Zwraca HTML albo null, gdy wtyczki nie ma, coś poszło nie tak lub minął czas.
 */
export async function readPageWithAddon(
    url: string,
    timeoutMs = 35_000,
): Promise<string | null> {
    const reply = await ask('tobym:read-page', { url }, timeoutMs);

    return typeof reply?.html === 'string' && reply.html !== ''
        ? reply.html
        : null;
}

/** Strona zabrana kliknięciem ikony wtyczki w sklepie (adres ?dodaj=...). */
export async function takePageFromAddon(
    key: string,
): Promise<{ url: string; html: string } | null> {
    const reply = await ask('tobym:take-page', { key }, 5_000);

    if (typeof reply?.url !== 'string') {
        return null;
    }

    return {
        url: reply.url,
        html: typeof reply.html === 'string' ? reply.html : '',
    };
}
