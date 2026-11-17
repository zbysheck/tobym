<?php

return [
    // Dane do polityki prywatności (strona /prywatnosc). Uzupełnij w .env przed publicznym startem.
    'privacy_controller' => env('PRIVACY_CONTROLLER', '[uzupełnij: imię i nazwisko lub nazwa firmy, adres]'),
    'privacy_contact' => env('PRIVACY_CONTACT', '[uzupełnij: adres e-mail do spraw danych]'),

    // Limit miejsca na kopie zdjęć produktów (w bajtach).
    'image_cache_limit' => (int) env('IMAGE_CACHE_LIMIT', 5 * 1024 * 1024 * 1024),

    // Po ilu dniach bez zmian kasujemy listy niezapisane mailem.
    'draft_days' => (int) env('DRAFT_DAYS', 30),
];
