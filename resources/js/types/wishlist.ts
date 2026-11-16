export type WishlistItem = {
    id: number;
    url: string | null;
    title: string;
    shop: string | null;
    image_url: string | null;
    price: string | null;
};

export type WishlistData = {
    title: string;
    theme: string;
    items: WishlistItem[];
};

export type ThemeOption = {
    value: string;
    label: string;
};
