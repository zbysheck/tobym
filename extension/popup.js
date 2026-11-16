// Szkielet: pokazuje adres aktywnej karty. Docelowo wyśle go do API wishlisty.
chrome.tabs.query({ active: true, currentWindow: true }).then(([tab]) => {
    document.getElementById('url').textContent = tab?.url ?? 'Brak aktywnej karty';
});
