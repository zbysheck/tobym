<?php

namespace Tests\Unit;

use App\Support\Urls;
use PHPUnit\Framework\TestCase;

class UrlsTest extends TestCase
{
    public function test_a_product_name_is_read_from_the_address(): void
    {
        $this->assertSame(
            'Telewizor TCL LED 50V6B 4K UHD 60HZ Google TV 2024',
            Urls::titleFromPath('https://www.mediaexpert.pl/telewizory-i-rtv/telewizory/telewizor-tcl-led-50v6b-4k-uhd-60hz-google-tv-2024'),
        );
        $this->assertSame(
            'Kandydat Zulczyk Jakub',
            Urls::titleFromPath('https://www.empik.com/kandydat-zulczyk-jakub,p1603372167,ksiazka-p'),
        );
        $this->assertNull(Urls::titleFromPath('https://sklep.example/p/123456789'));
    }
}
