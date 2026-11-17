// Strona /addon/connect: odczytuje jednorazowy token i zapisuje go w addonie.
function save(element) {
    const token = element.getAttribute('data-addon-token');

    if (!token) {
        return;
    }

    chrome.storage.local.set({ token, server: location.origin }).then(() => {
        const status = document.getElementById('addon-status');

        if (status) {
            status.textContent =
                'Addon zapisał połączenie. Możesz zamknąć tę kartę i dodawać prezenty ikoną Tobym.';
        }
    });
}

const existing = document.querySelector('[data-addon-token]');

if (existing) {
    save(existing);
} else {
    const observer = new MutationObserver(() => {
        const element = document.querySelector('[data-addon-token]');

        if (element) {
            observer.disconnect();
            save(element);
        }
    });

    observer.observe(document.documentElement, {
        childList: true,
        subtree: true,
    });
}
