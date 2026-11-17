# Addon Chrome Tobym

Dodaje prezent ze strony otwartej w przeglądarce. HTML strony idzie do serwera, który
wyciąga z niego nazwę, zdjęcie i cenę (tymi samymi przepisami co strona, łącznie z AI).
Dzięki temu działa też w sklepach, które blokują pobieranie przez serwer.

Gdy addon jest zainstalowany, strona Tobym sama z niego korzysta: jeśli sklep nie wpuszcza
serwera, addon otwiera produkt w karcie w tle, odczytuje stronę, zamyka kartę i odsyła HTML.
Prośby przyjmuje tylko od stron Tobym (adresy z `config.js`), po jednej karcie naraz, i nie
otwiera stron kont, poczty ani banków. Dlatego addon prosi przy instalacji o dostęp do stron.

## Instalacja (tryb dewelopera)

1. `chrome://extensions` > włącz "Tryb dewelopera".
2. "Załaduj rozpakowane" > wskaż ten folder (`extension`).
3. Kliknij ikonę Tobym > "Połącz z Tobym" > na otwartej stronie "Połącz addon".

## Pliki

- `popup.html`, `popup.js` - okienko po kliknięciu ikony.
- `connect.js` - odbiera token ze strony `/addon/connect`.
- `bridge.js` - most między stroną Tobym a addonem (prośby o odczytanie produktu).
- `background.js` - otwiera produkt w karcie w tle i odczytuje jego HTML.
- `config.js` - adresy serwerów.
