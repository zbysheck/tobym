export type WishlistItem = {
    id: number;
    url: string | null;
    title: string;
    shop: string | null;
    image_url: string | null;
    price: string | null;
};

export type WishlistLayout = 'tiles' | 'rows';

export type WishlistData = {
    title: string;
    theme: string;
    layout: WishlistLayout;
    items: WishlistItem[];
};

export type ThemeOption = {
    value: string;
    label: string;
};

export type GiftPreview = {
    url: string;
    title: string;
    shop: string | null;
    image_url: string | null;
    price: string | null;
    pending: boolean;
};
