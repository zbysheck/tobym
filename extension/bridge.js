// Most między stroną Tobym a addonem. Strona prosi o odczytanie produktu, którego sklep nie
// pokazuje serwerowi; addon otwiera go w karcie w tle i odsyła HTML. Działa tylko na stronach Tobym.
document.documentElement.dataset.tobymAddon =
    chrome.runtime.getManifest().version;

window.addEventListener('message', async (event) => {
    const data = event.data;

    if (
        event.source !== window ||
        event.origin !== location.origin ||
        data?.type !== 'tobym:read-page' ||
        typeof data.id !== 'string' ||
        typeof data.url !== 'string'
    ) {
        return;
    }

    let reply;

    try {
        reply = await chrome.runtime.sendMessage({
            type: 'read-page',
            url: data.url,
        });
    } catch (error) {
        reply = { ok: false, error: String(error?.message ?? error) };
    }

    window.postMessage(
        { ...reply, type: 'tobym:page-read', id: data.id },
        location.origin,
    );
});
