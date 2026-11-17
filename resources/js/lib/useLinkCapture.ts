import { onBeforeUnmount, onMounted, ref } from 'vue';

function extractUrl(text: string | undefined | null): string | null {
    const candidate = (text ?? '').trim().split(/\s+/)[0] ?? '';

    return /^https?:\/\/\S+$/i.test(candidate) ? candidate : null;
}

function isEditable(target: EventTarget | null): boolean {
    if (!(target instanceof HTMLElement)) {
        return false;
    }

    return (
        target.isContentEditable ||
        ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName)
    );
}

/**
 * Łapie link wklejony gdziekolwiek na stronie (poza polami tekstowymi) albo upuszczony
 * na stronę. "dragging" pozwala pokazać nakładkę "upuść tutaj".
 */
export function useLinkCapture(
    onLink: (url: string) => void,
    enabled: () => boolean,
) {
    const dragging = ref(false);
    let depth = 0;

    function hasLink(event: DragEvent): boolean {
        const types = event.dataTransfer?.types ?? [];

        return types.includes('text/uri-list') || types.includes('text/plain');
    }

    function onPaste(event: ClipboardEvent): void {
        if (
            !enabled() ||
            isEditable(event.target) ||
            document.querySelector('dialog[open]')
        ) {
            return;
        }

        const url = extractUrl(event.clipboardData?.getData('text'));

        if (url !== null) {
            event.preventDefault();
            onLink(url);
        }
    }

    function onDragEnter(event: DragEvent): void {
        if (!enabled() || !hasLink(event)) {
            return;
        }

        depth++;
        dragging.value = true;
    }

    function onDragOver(event: DragEvent): void {
        if (enabled() && hasLink(event)) {
            event.preventDefault();
        }
    }

    function onDragLeave(): void {
        depth = Math.max(0, depth - 1);

        if (depth === 0) {
            dragging.value = false;
        }
    }

    function onDrop(event: DragEvent): void {
        depth = 0;
        dragging.value = false;

        if (!enabled()) {
            return;
        }

        const url =
            extractUrl(event.dataTransfer?.getData('text/uri-list')) ??
            extractUrl(event.dataTransfer?.getData('text/plain'));

        if (url !== null) {
            event.preventDefault();
            onLink(url);
        }
    }

    onMounted(() => {
        document.addEventListener('paste', onPaste);
        window.addEventListener('dragenter', onDragEnter);
        window.addEventListener('dragover', onDragOver);
        window.addEventListener('dragleave', onDragLeave);
        window.addEventListener('drop', onDrop);
    });

    onBeforeUnmount(() => {
        document.removeEventListener('paste', onPaste);
        window.removeEventListener('dragenter', onDragEnter);
        window.removeEventListener('dragover', onDragOver);
        window.removeEventListener('dragleave', onDragLeave);
        window.removeEventListener('drop', onDrop);
    });

    return { dragging };
}
