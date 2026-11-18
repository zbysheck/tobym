// Most między stroną Tobym a wtyczką (działa tylko na stronach Tobym).
// Strona może poprosić o: stronę zabraną kliknięciem ikony ("take-page")
// albo odczyt zablokowanego sklepu w karcie w tle ("read-page").
document.documentElement.dataset.tobymAddon =
    chrome.runtime.getManifest().version;

const REQUESTS = {
    'tobym:take-page': (data) =>
        typeof data.key === 'string'
            ? { type: 'take-page', id: data.key }
            : null,
    'tobym:read-page': (data) =>
        typeof data.url === 'string'
            ? { type: 'read-page', url: data.url }
            : null,
};

window.addEventListener('message', async (event) => {
    const data = event.data;
    const build = REQUESTS[data?.type];

    if (
        event.source !== window ||
        event.origin !== location.origin ||
        !build ||
        typeof data.id !== 'string'
    ) {
        return;
    }

    const request = build(data);

    if (request === null) {
        return;
    }

    let reply;

    try {
        reply = await chrome.runtime.sendMessage(request);
    } catch (error) {
        reply = { ok: false, error: String(error?.message ?? error) };
    }

    window.postMessage(
        { ...reply, type: 'tobym:reply', id: data.id },
        location.origin,
    );
});
