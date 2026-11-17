/** Kroje pisma każdej skórki (Bunny Fonts), ładowane tylko dla wybranej skórki. */
const FONTS: Record<string, string> = {
    swiateczna: 'dm-serif-display:400|dm-sans:400,600',
    choinkowa: 'fraunces:600,700|dm-sans:400,600',
    mikolajowa: 'lobster:400|nunito:400,600,700',
    zimowa: 'josefin-sans:300,600|nunito:400,600,700',
    slubna: 'cormorant-garamond:500,500i|jost:400,500',
    urodzinowa: 'bricolage-grotesque:800|inter:400,600,700',
};

export function themeFontsUrl(theme: string): string {
    const families = FONTS[theme] ?? FONTS.swiateczna;

    return `https://fonts.bunny.net/css?family=${families}&display=swap`;
}
