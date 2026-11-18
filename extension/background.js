import { SERVER } from './config.js';

// Wtyczka nie wymaga łączenia z kontem: działa na sesji Tobym w tej samej przeglądarce.
// 1) Kliknięcie ikony w sklepie: zabiera stronę i otwiera Tobym z oknem dodawania.
// 2) Prośba strony Tobym: odczytuje zablokowany sklep w karcie w tle.
const MAX_HTML = 3_000_000;
const LOAD_TIMEOUT = 25_000;
const SETTLE = 1_500;
const SERVER_ORIGIN = new URL(SERVER).origin;

// Strony, których wtyczka nigdy nie otwiera w tle (konta, poczta, banki).
const BLOCKED_HOSTS =
    /(^|\.)(google\.com|gmail\.com|live\.com|outlook\.com|microsoft\.com|apple\.com|icloud\.com|facebook\.com|github\.com|paypal\.com|onet\.pl|wp\.pl|interia\.pl|o2\.pl)$|bank/i;

let queue = Promise.resolve();

chrome.action.onClicked.addListener(async (tab) => {
    if (!tab.id || !tab.url || !/^https?:/.test(tab.url)) {
        return;
    }

    if (new URL(tab.url).origin === SERVER_ORIGIN) {
        return;
    }

    let html = '';

    try {
        html = await pageHtml(tab.id);
    } catch {
        // Strona nie dała się odczytać (np. sklep z PDF-em) - Tobym weźmie nazwę z adresu.
    }

    const id = crypto.randomUUID();
    await chrome.storage.session.set({ [id]: { url: tab.url, html } });
    await chrome.tabs.create({
        url: `${SERVER}/?dodaj=${id}`,
        index: tab.index + 1,
    });
});

chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
    const origin =
        sender.origin ?? (sender.url ? new URL(sender.url).origin : '');

    if (origin !== SERVER_ORIGIN) {
        return false;
    }

    if (message?.type === 'take-page' && typeof message.id === 'string') {
        chrome.storage.session.get(message.id).then(async (stored) => {
            await chrome.storage.session.remove(message.id);
            const page = stored[message.id];
            sendResponse(page ? { ok: true, ...page } : { ok: false });
        });

        return true;
    }

    if (message?.type === 'read-page') {
        let target;

        try {
            target = new URL(message.url);
        } catch {
            target = null;
        }

        if (
            target === null ||
            !['http:', 'https:'].includes(target.protocol) ||
            BLOCKED_HOSTS.test(target.hostname)
        ) {
            sendResponse({
                ok: false,
                error: 'Tego adresu wtyczka nie otwiera.',
            });

            return false;
        }

        // Jedna karta naraz, żeby wklejenie wielu linków nie otworzyło ich lawiny.
        queue = queue
            .catch(() => {})
            .then(() => readInBackground(target.href))
            .then(sendResponse, (error) =>
                sendResponse({
                    ok: false,
                    error: String(error?.message ?? error),
                }),
            );

        return true;
    }

    return false;
});

async function pageHtml(tabId) {
    const [result] = await chrome.scripting.executeScript({
        target: { tabId },
        func: () => document.documentElement.outerHTML,
    });

    return String(result?.result ?? '').slice(0, MAX_HTML);
}

async function readInBackground(url) {
    const tab = await chrome.tabs.create({ url, active: false });

    try {
        await waitForLoad(tab.id);
        await new Promise((resolve) => setTimeout(resolve, SETTLE));

        return { ok: true, html: await pageHtml(tab.id) };
    } finally {
        chrome.tabs.remove(tab.id).catch(() => {});
    }
}

function waitForLoad(tabId) {
    return new Promise((resolve, reject) => {
        const timer = setTimeout(() => {
            cleanup();
            reject(new Error('Strona sklepu ładowała się za długo.'));
        }, LOAD_TIMEOUT);

        function listener(id, info) {
            if (id === tabId && info.status === 'complete') {
                cleanup();
                resolve();
            }
        }

        function cleanup() {
            clearTimeout(timer);
            chrome.tabs.onUpdated.removeListener(listener);
        }

        chrome.tabs.onUpdated.addListener(listener);
    });
}
