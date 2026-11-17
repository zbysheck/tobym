import confetti from 'canvas-confetti';

/** Małe konfetti w kolorze akcentu skórki (pomijane przy ograniczonym ruchu). */
export function celebrate(
    origin: { x: number; y: number } = { x: 0.5, y: 0.7 },
): void {
    const accent = getComputedStyle(
        document.querySelector('[data-theme]') ?? document.body,
    )
        .getPropertyValue('--skin-accent')
        .trim();

    void confetti({
        particleCount: 70,
        spread: 70,
        startVelocity: 32,
        ticks: 140,
        scalar: 0.9,
        origin,
        colors: [accent || '#b5302a', '#ffffff', '#e9c46a'],
        disableForReducedMotion: true,
    });
}
