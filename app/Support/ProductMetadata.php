<?php

namespace App\Support;

final readonly class ProductMetadata
{
    public function __construct(
        public string $title,
        public ?string $shop,
        public ?string $imageUrl,
        public ?string $price,
        public bool $improvable = false,
        public bool $blocked = false,
        public bool $gone = false,
    ) {}
}
