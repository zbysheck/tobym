import { SERVERS } from './config.js';

const $ = (id) => document.getElementById(id);
const MAX_HTML = 3_000_000;

let settings = await chrome.storage.local.get(['token', 'server', 'list']);

function show(section) {
    $('disconnected').hidden = section !== 'disconnected';
    $('connected').hidden = section !== 'connected';
}

function setStatus(text) {
    $('status').textContent = text;
}

async function api(path, options = {}) {
    const response = await fetch(`${settings.server}/api/addon${path}`, {
        ...options,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            Authorization: `Bearer ${settings.token}`,
            ...options.headers,
        },
    });

    if (response.status === 401) {
        await chrome.storage.local.remove('token');
        settings.token = undefined;
        show('disconnected');
        throw new Error('Połączenie wygasło. Połącz addon jeszcze raz.');
    }

    if (!response.ok) {
        throw new Error(`Serwer odpowiedział błędem (${response.status}).`);
    }

    return response.json();
}

async function loadLists() {
    const { lists } = await api('/lists');
    const select = $('list');
    select.replaceChildren();

    if (lists.length === 0) {
        select.append(new Option('Nowa lista (powstanie sama)', ''));
    }

    for (const list of lists) {
        select.append(new Option(list.title, list.slug));
    }

    if (settings.list && lists.some((list) => list.slug === settings.list)) {
        select.value = settings.list;
    }
}

async function pageHtml(tabId) {
    const [result] = await chrome.scripting.executeScript({
        target: { tabId },
        func: () => document.documentElement.outerHTML,
    });

    return String(result?.result ?? '').slice(0, MAX_HTML);
}

async function addCurrentPage() {
    const button = $('add');
    button.disabled = true;
    $('result').hidden = true;
    setStatus('Czytam stronę…');

    try {
        const [tab] = await chrome.tabs.query({
            active: true,
            currentWindow: true,
        });

        if (!tab?.url || !/^https?:/.test(tab.url)) {
            throw new Error('Na tej karcie nie ma strony sklepu.');
        }

        const html = await pageHtml(tab.id);
        setStatus('Dodaję… przy nowym sklepie może to chwilę potrwać.');

        const list = $('list').value;
        await chrome.storage.local.set({ list });
        settings.list = list;

        const item = await api('/items', {
            method: 'POST',
            body: JSON.stringify({ url: tab.url, html, list: list || null }),
        });

        setStatus(`Dodano do listy „${item.list.title}”.`);
        $('result-title').textContent = item.title;
        $('result-price').textContent = item.price ?? '';
        $('result-image').src = item.image_url ?? '';
        $('result-image').hidden = !item.image_url;
        $('result').hidden = false;
        $('open-list').href = item.list.url;
        $('open-list').hidden = false;
        await loadLists();
        $('list').value = item.list.slug;
    } catch (error) {
        setStatus(error.message);
    } finally {
        button.disabled = false;
    }
}

for (const server of SERVERS) {
    $('server').append(new Option(server.label, server.url));
}

$('connect').addEventListener('click', () => {
    chrome.tabs.create({ url: `${$('server').value}/addon/connect` });
    window.close();
});

$('add').addEventListener('click', addCurrentPage);

$('disconnect').addEventListener('click', async (event) => {
    event.preventDefault();
    await chrome.storage.local.remove(['token', 'list']);
    settings = { server: settings.server };
    show('disconnected');
});

if (settings.token && settings.server) {
    show('connected');

    try {
        await loadLists();
    } catch (error) {
        setStatus(error.message);
    }
} else {
    show('disconnected');
}
