# Addon Chrome Tobym

Dodaje prezent ze strony otwartej w przeglądarce. HTML strony idzie do serwera, który
wyciąga z niego nazwę, zdjęcie i cenę (tymi samymi przepisami co strona, łącznie z AI).
Dzięki temu działa też w sklepach, które blokują pobieranie przez serwer.

## Instalacja (tryb dewelopera)

1. `chrome://extensions` > włącz "Tryb dewelopera".
2. "Załaduj rozpakowane" > wskaż ten folder (`extension`).
3. Kliknij ikonę Tobym > "Połącz z Tobym" > na otwartej stronie "Połącz addon".

## Pliki

- `popup.html`, `popup.js` - okienko po kliknięciu ikony.
- `connect.js` - odbiera token ze strony `/addon/connect`.
- `config.js` - adresy serwerów.
