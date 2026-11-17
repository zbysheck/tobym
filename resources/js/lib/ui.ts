import { reactive } from 'vue';

/** Krótkie powiadomienia na dole ekranu ("Zapisano", "Skopiowano link"). */
export const toasts = reactive<{ items: { id: number; text: string }[] }>({
    items: [],
});

let nextId = 1;

export function toast(text: string): void {
    const id = nextId++;
    toasts.items.push({ id, text });
    window.setTimeout(() => {
        const index = toasts.items.findIndex((item) => item.id === id);

        if (index !== -1) {
            toasts.items.splice(index, 1);
        }
    }, 2600);
}

type ConfirmRequest = {
    title: string;
    text: string;
    confirmLabel: string;
    resolve: (confirmed: boolean) => void;
};

/** Stan okna potwierdzenia, zamiast systemowego window.confirm. */
export const confirmState = reactive<{ request: ConfirmRequest | null }>({
    request: null,
});

export function confirmAction(options: {
    title: string;
    text: string;
    confirmLabel?: string;
}): Promise<boolean> {
    confirmState.request?.resolve(false);

    return new Promise((resolve) => {
        confirmState.request = {
            title: options.title,
            text: options.text,
            confirmLabel: options.confirmLabel ?? 'Usuń',
            resolve,
        };
    });
}

export function settleConfirm(confirmed: boolean): void {
    const request = confirmState.request;
    confirmState.request = null;
    request?.resolve(confirmed);
}
