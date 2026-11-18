# Wtyczka Chrome Tobym

Bez łączenia z kontem: działa na tej samej sesji Tobym co przeglądarka (konto albo szkic).

- **Kliknięcie ikony w sklepie**: wtyczka zabiera stronę produktu i otwiera Tobym z oknem
  dodawania prezentu (wybór listy, nazwa, cena, zdjęcie).
- **Wklejenie linku na Tobym**: jeśli sklep nie wpuszcza serwera, wtyczka otwiera produkt
  w karcie w tle, odczytuje go i od razu zamyka kartę.

Działa tylko z adresem z `config.js` (oraz `matches` w `manifest.json`). Prośby przyjmuje
wyłącznie od stron Tobym, po jednej karcie naraz, i nie otwiera stron kont, poczty ani banków.
Dostęp do wszystkich stron jest potrzebny do odczytu w tle; dodawanie ikoną korzysta tylko
z bieżącej karty (activeTab).

## Instalacja (tryb dewelopera)

1. `chrome://extensions` > włącz "Tryb dewelopera".
2. "Załaduj rozpakowane" > wskaż ten folder (`extension`).

## Pliki

- `background.js` - kliknięcie ikony i odczyt w tle.
- `bridge.js` - most między stroną Tobym a wtyczką.
- `config.js` - adres Tobym.
