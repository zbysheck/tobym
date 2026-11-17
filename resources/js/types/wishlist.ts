export type WishlistItem = {
    id: number;
    url: string | null;
    title: string;
    shop: string | null;
    image_url: string | null;
    image_large: string | null;
    price: string | null;
    note: string | null;
    priority: boolean;
    reservation: { name: string; mine: boolean } | null;
};

export type WishlistLayout = 'tiles' | 'rows';

export type WishlistData = {
    title: string;
    theme: string;
    layout: WishlistLayout;
    event_date: string | null;
    accent: string | null;
    has_pin: boolean;
    items: WishlistItem[];
};

export type ListSummary = {
    slug: string;
    title: string;
    past: boolean;
};

export type ThemeOption = {
    value: string;
    label: string;
};

export type GiftPreview = {
    url: string | null;
    title: string;
    shop: string | null;
    image_url: string | null;
    price: string | null;
    pending: boolean;
    blocked?: boolean;
};

export type GiftDetails = {
    title: string;
    price: string;
    note: string;
    priority: boolean;
    photo: File | null;
    /** Slug wybranej listy; null razem z newList = nowa lista o tej nazwie. */
    list: string | null;
    newList: string | null;
};
