<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import SiteFooter from '@/components/SiteFooter.vue';
import { themeFontsUrl } from '@/lib/themes';

defineProps<{
    controller: string;
    contact: string;
    updated: string;
}>();
</script>

<template>
    <Head title="Prywatność i ciasteczka">
        <link rel="preconnect" href="https://fonts.bunny.net" />
        <link rel="stylesheet" :href="themeFontsUrl('swiateczna')" />
    </Head>

    <div
        data-theme="swiateczna"
        class="min-h-screen bg-ground font-body text-ink antialiased"
    >
        <header class="mx-auto max-w-3xl px-5 py-5 sm:px-8">
            <AppLogo />
        </header>

        <main
            class="mx-auto flex max-w-3xl flex-col gap-8 px-5 pb-20 leading-relaxed sm:px-8"
        >
            <div>
                <h1
                    class="font-brand text-[clamp(2.2rem,5vw,3.2rem)] leading-tight"
                >
                    Prywatność i ciasteczka
                </h1>
                <p class="text-sm text-muted">Ostatnia zmiana: {{ updated }}</p>
            </div>

            <section class="flex flex-col gap-3">
                <h2 class="text-xl font-semibold">Kto przetwarza dane</h2>
                <p>
                    Administratorem danych jest {{ controller }}. W sprawach
                    dotyczących danych napisz na {{ contact }}.
                </p>
            </section>

            <section class="flex flex-col gap-3">
                <h2 class="text-xl font-semibold">
                    Jakie dane zbieramy i po co
                </h2>
                <ul class="flex list-disc flex-col gap-2 pl-5">
                    <li>
                        <strong>Adres e-mail</strong>, jeśli zapiszesz listę.
                        Służy tylko do wysyłania linków logowania. Nie wysyłamy
                        reklam.
                    </li>
                    <li>
                        <strong>Treść list</strong>: nazwy prezentów, linki,
                        ceny, notatki i wgrane zdjęcia. Są potrzebne, żeby lista
                        działała.
                    </li>
                    <li>
                        <strong>Imiona gości</strong>, którzy rezerwują
                        prezenty. Widzą je tylko inni goście tej listy,
                        właściciel nie.
                    </li>
                    <li>
                        <strong>Dzienniki serwera</strong> (m.in. adres IP)
                        prowadzone przez hosting w celach bezpieczeństwa.
                    </li>
                </ul>
            </section>

            <section class="flex flex-col gap-3">
                <h2 class="text-xl font-semibold">Ciasteczka</h2>
                <p>
                    Używamy wyłącznie ciasteczek niezbędnych do działania
                    serwisu, dlatego nie pytamy o zgodę. Nie ma ciasteczek
                    reklamowych ani analitycznych.
                </p>
                <ul class="flex list-disc flex-col gap-2 pl-5">
                    <li>
                        <strong>Sesja i ochrona formularzy</strong>: logowanie i
                        zabezpieczenie przed podszywaniem się.
                    </li>
                    <li>
                        <strong>Szkic listy</strong>: rozpoznaje listy zrobione
                        w tej przeglądarce przed zapisaniem mailem (do roku).
                    </li>
                    <li>
                        <strong>Gość</strong>: pozwala cofnąć własną rezerwację
                        bez zakładania konta (do roku).
                    </li>
                </ul>
                <p>
                    Imię podane przy rezerwacji jest zapamiętywane w Twojej
                    przeglądarce, żeby nie wpisywać go za każdym razem.
                </p>
            </section>

            <section class="flex flex-col gap-3">
                <h2 class="text-xl font-semibold">Komu przekazujemy dane</h2>
                <ul class="flex list-disc flex-col gap-2 pl-5">
                    <li>
                        <strong>Hosting</strong> (MyDevil, Polska): przechowuje
                        serwis, bazę danych i pocztę.
                    </li>
                    <li>
                        <strong>Google (Gemini)</strong>: gdy nie umiemy
                        odczytać strony sklepu, wysyłamy jej publiczną treść,
                        żeby nauczyć się tego sklepu. Nie wysyłamy przy tym
                        Twoich danych.
                    </li>
                    <li>
                        <strong>Bunny Fonts</strong>: dostarcza kroje pisma, bez
                        śledzenia.
                    </li>
                    <li>
                        Zdjęcia produktów mogą być wczytywane bezpośrednio ze
                        stron sklepów.
                    </li>
                </ul>
            </section>

            <section class="flex flex-col gap-3">
                <h2 class="text-xl font-semibold">
                    Jak długo i jakie masz prawa
                </h2>
                <p>
                    Listy bez zapisanego maila usuwamy po 30 dniach od ostatniej
                    zmiany. Listy na koncie przechowujemy, dopóki konto
                    istnieje. Konto razem ze wszystkimi listami usuniesz sam w
                    ustawieniach listy.
                </p>
                <p>
                    Masz prawo dostępu do swoich danych, ich poprawienia,
                    usunięcia, ograniczenia przetwarzania i przeniesienia, a
                    także prawo skargi do Prezesa Urzędu Ochrony Danych
                    Osobowych.
                </p>
            </section>
        </main>

        <SiteFooter />
    </div>
</template>
