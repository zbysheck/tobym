import { SERVERS } from './config.js';

// Czyta stronę produktu w karcie w tle: przeglądarka użytkownika otwiera ją normalnie,
// addon zabiera HTML i od razu zamyka kartę. Prośby przyjmuje tylko od stron Tobym.
const MAX_HTML = 3_000_000;
const LOAD_TIMEOUT = 25_000;
const SETTLE = 1_500;
const ALLOWED_ORIGINS = SERVERS.map((server) => new URL(server.url).origin);

// Strony, których addon nigdy nie otwiera w tle (konta, poczta, banki).
const BLOCKED_HOSTS =
    /(^|\.)(google\.com|gmail\.com|live\.com|outlook\.com|microsoft\.com|apple\.com|icloud\.com|facebook\.com|github\.com|paypal\.com|onet\.pl|wp\.pl|interia\.pl|o2\.pl)$|bank/i;

let queue = Promise.resolve();

chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
    if (message?.type !== 'read-page') {
        return false;
    }

    const origin =
        sender.origin ?? (sender.url ? new URL(sender.url).origin : '');
    let target;

    try {
        target = new URL(message.url);
    } catch {
        target = null;
    }

    if (
        !ALLOWED_ORIGINS.includes(origin) ||
        target === null ||
        !['http:', 'https:'].includes(target.protocol) ||
        BLOCKED_HOSTS.test(target.hostname)
    ) {
        sendResponse({ ok: false, error: 'Tego adresu addon nie otwiera.' });

        return false;
    }

    // Jedna karta naraz, żeby wklejenie wielu linków nie otworzyło ich lawiny.
    queue = queue
        .catch(() => {})
        .then(() => readPage(target.href))
        .then(sendResponse, (error) =>
            sendResponse({ ok: false, error: String(error?.message ?? error) }),
        );

    return true;
});

async function readPage(url) {
    const tab = await chrome.tabs.create({ url, active: false });

    try {
        await waitForLoad(tab.id);
        await new Promise((resolve) => setTimeout(resolve, SETTLE));

        const [result] = await chrome.scripting.executeScript({
            target: { tabId: tab.id },
            func: () => document.documentElement.outerHTML,
        });

        return {
            ok: true,
            html: String(result?.result ?? '').slice(0, MAX_HTML),
        };
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
