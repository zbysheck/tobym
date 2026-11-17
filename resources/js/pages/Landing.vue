<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import SiteFooter from '@/components/SiteFooter.vue';
import { allThemeFontsUrl } from '@/lib/themes';

defineProps<{
    listUrl: string | null;
}>();

const steps = [
    {
        title: 'Wklej link',
        text: 'Z dowolnego sklepu. Nazwa, zdjęcie i cena pobiorą się same, a Ty tylko potwierdzasz.',
    },
    {
        title: 'Wyślij bliskim',
        text: 'Jeden link albo kod QR na zaproszeniu. Goście nie muszą niczego zakładać ani instalować.',
    },
    {
        title: 'Goście rezerwują',
        text: 'Każdy widzi, co jest już zajęte, więc nikt nie kupi tego samego. Ty nie widzisz, kto co kupił.',
    },
];

const features = [
    {
        title: 'Działa z każdym sklepem',
        text: 'Duże sklepy i małe butiki. Gdy jakiegoś sklepu nie znamy, uczymy się go przy pierwszym linku.',
    },
    {
        title: 'Niespodzianka zostaje niespodzianką',
        text: 'Rezerwacje widzą tylko goście. Właściciel listy nigdy ich nie zobaczy.',
    },
    {
        title: 'Skórki na każdą okazję',
        text: 'Choinka, Mikołaj, zimowa, ślubna, urodzinowa. Do tego Twój własny kolor.',
    },
    {
        title: 'Kilka list naraz',
        text: 'Osobno na święta, urodziny i ślub. Minione wydarzenia same trafiają do archiwum.',
    },
    {
        title: 'Prywatność po Twojemu',
        text: 'Lista dostępna tylko przez link, a jeśli chcesz, dodatkowo zabezpieczona PIN-em.',
    },
    {
        title: 'Też bez linku',
        text: 'Weekend w górach, wspólna kolacja, coś zrobionego ręcznie. Dodasz własne zdjęcie i notatkę.',
    },
];

const themes = [
    { value: 'swiateczna', label: 'Klasyczna' },
    { value: 'choinkowa', label: 'Choinka' },
    { value: 'mikolajowa', label: 'Mikołaj' },
    { value: 'zimowa', label: 'Zima' },
    { value: 'slubna', label: 'Ślub' },
    { value: 'urodzinowa', label: 'Urodziny' },
];

const preview = [
    {
        title: 'Wełniany koc w kratę',
        shop: 'sklep z tekstyliami',
        price: '189 zł',
        taken: null,
    },
    {
        title: 'Ekspres przelewowy',
        shop: 'sklep z kawą',
        price: '349 zł',
        taken: 'Kasia',
    },
    {
        title: 'Weekend w górach',
        shop: 'prezent bez linku',
        price: '',
        taken: null,
    },
];

const faq = [
    {
        q: 'Czy to jest darmowe?',
        a: 'Tak. Tworzenie list, udostępnianie i rezerwowanie prezentów nic nie kosztuje.',
    },
    {
        q: 'Czy muszę zakładać konto?',
        a: 'Nie. Lista działa od razu w przeglądarce. Adres e-mail podajesz tylko wtedy, gdy chcesz ją zapisać i otwierać z innych urządzeń. Logujesz się linkiem z maila, bez hasła.',
    },
    {
        q: 'Czy zobaczę, kto co zarezerwował?',
        a: 'Nie. To celowe: rezerwacje widzą wyłącznie goście, żeby prezent był niespodzianką.',
    },
    {
        q: 'Czy goście muszą się logować?',
        a: 'Nie. Otwierają link, wybierają prezent i podają imię. Mogą też cofnąć własną rezerwację.',
    },
    {
        q: 'Co, jeśli sklep nie da się odczytać?',
        a: 'Niektóre duże sklepy blokują automatyczne pobieranie. Wtedy wpiszesz nazwę i cenę sam, a wkrótce pomoże w tym dodatek do Chrome.',
    },
];
</script>

<template>
    <Head title="Listy prezentów, z których da się kupić">
        <meta
            name="description"
            content="Wklej link z dowolnego sklepu, wyślij listę bliskim, a oni zarezerwują prezenty. Bez rejestracji, za darmo, z niespodzianką."
        />
        <link rel="preconnect" href="https://fonts.bunny.net" />
        <link rel="stylesheet" :href="allThemeFontsUrl()" />
    </Head>

    <div
        data-theme="swiateczna"
        class="skin-backdrop min-h-screen bg-ground font-body text-ink antialiased"
    >
        <header
            class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-5 py-5 sm:px-8"
        >
            <AppLogo />
            <nav
                aria-label="Główna"
                class="flex flex-wrap items-center gap-x-5 gap-y-2"
            >
                <a href="#jak-to-dziala" class="hidden text-[15px] sm:inline"
                    >Jak to działa</a
                >
                <a href="#pytania" class="hidden text-[15px] sm:inline"
                    >Pytania</a
                >
                <a
                    :href="listUrl ?? '/start'"
                    class="skin-outline inline-flex h-11 items-center rounded-control bg-surface px-4 text-[15px] font-semibold"
                    >{{ listUrl ? 'Moja lista' : 'Zacznij listę' }}</a
                >
            </nav>
        </header>

        <main>
            <section
                class="mx-auto grid max-w-6xl items-center gap-12 px-5 pt-10 pb-20 sm:px-8 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)] lg:pt-16"
            >
                <div class="flex flex-col items-start gap-6">
                    <p
                        class="rounded-control bg-surface px-3 py-1.5 text-sm font-semibold text-accent"
                    >
                        Bez rejestracji · Za darmo
                    </p>
                    <h1
                        class="font-brand text-[clamp(2.6rem,6.5vw,4.6rem)] leading-[1.02] tracking-tight"
                    >
                        Lista prezentów, z&nbsp;której naprawdę da się kupić.
                    </h1>
                    <p class="max-w-xl text-lg leading-relaxed text-muted">
                        Wklej link z dowolnego sklepu, a nazwa, zdjęcie i cena
                        pobiorą się same. Bliscy rezerwują prezenty, nikt nie
                        kupuje dwa razy tego samego, a&nbsp;Ty do końca nie
                        wiesz, co dostaniesz.
                    </p>
                    <div class="flex flex-wrap items-center gap-3">
                        <a
                            :href="listUrl ?? '/start'"
                            class="skin-outline inline-flex h-13 items-center rounded-control bg-accent px-7 text-base font-semibold text-on-accent shadow-card"
                            >{{
                                listUrl
                                    ? 'Wróć do swojej listy'
                                    : 'Zacznij swoją listę'
                            }}</a
                        >
                        <a
                            href="#jak-to-dziala"
                            class="inline-flex h-13 items-center px-3 text-base underline underline-offset-4"
                            >Jak to działa</a
                        >
                    </div>
                </div>

                <figure
                    class="skin-outline relative rounded-card bg-surface p-5 shadow-card sm:p-7"
                    aria-label="Przykładowa lista prezentów"
                >
                    <div
                        class="mb-1 text-xs font-semibold tracking-[0.16em] text-accent uppercase"
                    >
                        Przykładowa lista
                    </div>
                    <div class="font-brand text-[34px] leading-tight">
                        Święta 2026
                    </div>
                    <div class="mb-5 text-sm text-muted">
                        24 grudnia · 3 prezenty
                    </div>
                    <ul class="flex flex-col gap-3">
                        <li
                            v-for="gift in preview"
                            :key="gift.title"
                            class="skin-outline flex items-center gap-4 rounded-control bg-ground/60 p-3"
                            :class="gift.taken ? 'opacity-60' : ''"
                        >
                            <div
                                class="flex h-14 w-14 flex-none items-center justify-center rounded-control bg-tile text-ink"
                                aria-hidden="true"
                            >
                                <svg
                                    width="26"
                                    height="26"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.6"
                                    stroke-linejoin="round"
                                >
                                    <rect
                                        x="3.5"
                                        y="9"
                                        width="17"
                                        height="11.5"
                                        rx="2"
                                    />
                                    <rect
                                        x="2.5"
                                        y="6"
                                        width="19"
                                        height="4"
                                        rx="1.5"
                                    />
                                    <path
                                        d="M12 6v14.5M12 6C10 2.5 6.5 2.5 7 5s5 1 5 1Zm0 0c2-3.5 5.5-3.5 5-1s-5 1-5 1Z"
                                    />
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="truncate font-semibold">
                                    {{ gift.title }}
                                </div>
                                <div class="truncate text-sm text-muted">
                                    {{ gift.shop }}
                                </div>
                            </div>
                            <div class="text-right">
                                <div
                                    v-if="gift.price"
                                    class="text-sm font-semibold"
                                >
                                    {{ gift.price }}
                                </div>
                                <div
                                    v-if="gift.taken"
                                    class="text-xs font-semibold"
                                >
                                    Kupuje: {{ gift.taken }}
                                </div>
                                <div
                                    v-else
                                    class="mt-1 rounded-control bg-accent px-2.5 py-1 text-xs font-semibold text-on-accent"
                                >
                                    Rezerwuję
                                </div>
                            </div>
                        </li>
                    </ul>
                </figure>
            </section>

            <section
                id="jak-to-dziala"
                class="skin-outline scroll-mt-6 border-x-0 bg-surface/80 py-20"
            >
                <div class="mx-auto max-w-6xl px-5 sm:px-8">
                    <h2
                        class="mb-12 font-brand text-[clamp(2rem,4.5vw,3rem)] leading-tight"
                    >
                        Trzy kroki i gotowe
                    </h2>
                    <ol class="grid gap-8 md:grid-cols-3">
                        <li
                            v-for="(step, index) in steps"
                            :key="step.title"
                            class="flex flex-col gap-3"
                        >
                            <span
                                class="flex h-11 w-11 items-center justify-center rounded-full bg-ink text-lg font-semibold text-ground"
                                aria-hidden="true"
                                >{{ index + 1 }}</span
                            >
                            <h3 class="text-xl font-semibold">
                                {{ step.title }}
                            </h3>
                            <p class="leading-relaxed text-muted">
                                {{ step.text }}
                            </p>
                        </li>
                    </ol>
                </div>
            </section>

            <section class="mx-auto max-w-6xl px-5 py-20 sm:px-8">
                <h2
                    class="mb-12 font-brand text-[clamp(2rem,4.5vw,3rem)] leading-tight"
                >
                    Wszystko, czego potrzebuje dobra lista
                </h2>
                <div
                    class="grid gap-x-10 gap-y-10 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <div
                        v-for="feature in features"
                        :key="feature.title"
                        class="flex flex-col gap-2 border-t-2 border-accent pt-4"
                    >
                        <h3 class="text-lg font-semibold">
                            {{ feature.title }}
                        </h3>
                        <p class="leading-relaxed text-muted">
                            {{ feature.text }}
                        </p>
                    </div>
                </div>
            </section>

            <section class="mx-auto max-w-6xl px-5 pb-20 sm:px-8">
                <h2
                    class="mb-3 font-brand text-[clamp(2rem,4.5vw,3rem)] leading-tight"
                >
                    Skórka pod okazję
                </h2>
                <p class="mb-10 max-w-xl text-lg text-muted">
                    Zmienisz ją jednym kliknięciem, także gdy lista jest już
                    wysłana.
                </p>
                <ul
                    class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6"
                >
                    <li
                        v-for="theme in themes"
                        :key="theme.value"
                        :data-theme="theme.value"
                        class="skin-outline skin-card skin-backdrop flex aspect-[3/4] flex-col justify-end overflow-hidden rounded-card bg-ground p-4 shadow-card"
                    >
                        <div
                            class="skin-ornament mb-auto max-w-full scale-75"
                            aria-hidden="true"
                        ></div>
                        <div
                            class="skin-display text-[26px] leading-tight text-ink"
                        >
                            {{ theme.label }}
                        </div>
                        <div
                            class="mt-3 h-8 rounded-control bg-accent"
                            aria-hidden="true"
                        ></div>
                    </li>
                </ul>
            </section>

            <section
                id="pytania"
                class="skin-outline scroll-mt-6 border-x-0 bg-surface/80 py-20"
            >
                <div class="mx-auto max-w-3xl px-5 sm:px-8">
                    <h2
                        class="mb-10 font-brand text-[clamp(2rem,4.5vw,3rem)] leading-tight"
                    >
                        Częste pytania
                    </h2>
                    <div class="flex flex-col gap-3">
                        <details
                            v-for="item in faq"
                            :key="item.q"
                            class="skin-outline group rounded-control bg-ground/60 px-5 py-4"
                        >
                            <summary
                                class="flex cursor-pointer list-none items-center justify-between gap-4 text-lg font-semibold"
                            >
                                {{ item.q }}
                                <span
                                    class="text-2xl leading-none text-accent transition-transform group-open:rotate-45"
                                    aria-hidden="true"
                                    >+</span
                                >
                            </summary>
                            <p class="mt-3 leading-relaxed text-muted">
                                {{ item.a }}
                            </p>
                        </details>
                    </div>
                </div>
            </section>

            <section class="mx-auto max-w-6xl px-5 py-20 text-center sm:px-8">
                <h2
                    class="mb-4 font-brand text-[clamp(2rem,4.5vw,3.2rem)] leading-tight"
                >
                    Zacznij teraz, zajmie Ci to minutę
                </h2>
                <p class="mb-8 text-lg text-muted">
                    Bez zakładania konta. Zapiszesz listę mailem, kiedy
                    zechcesz.
                </p>
                <a
                    :href="listUrl ?? '/start'"
                    class="skin-outline inline-flex h-13 items-center rounded-control bg-accent px-8 text-base font-semibold text-on-accent shadow-card"
                    >{{
                        listUrl ? 'Wróć do swojej listy' : 'Zacznij swoją listę'
                    }}</a
                >
            </section>
        </main>

        <SiteFooter />
    </div>
</template>
