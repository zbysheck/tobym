<?php

namespace Tests\Feature;

use App\Enums\Theme;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Support\CurrentWishlist;
use App\Support\GuestToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationTest extends TestCase
{
    use RefreshDatabase;

    private Wishlist $wishlist;

    private WishlistItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wishlist = Wishlist::query()->create([
            'title' => 'Urodziny Ani',
            'theme' => Theme::Urodzinowa,
            'draft_token' => hash('sha256', 'wlasciciel'),
        ]);
        $this->item = $this->wishlist->items()->create(['title' => 'Rolki']);
    }

    public function test_a_guest_reserves_a_gift_and_sees_it_as_theirs(): void
    {
        $this->withCookie(GuestToken::COOKIE, 'gosc-1')
            ->post($this->reserveUrl(), ['name' => 'Kasia'])
            ->assertRedirect();

        $this->withCookie(GuestToken::COOKIE, 'gosc-1')
            ->get(route('wishlist.show', $this->wishlist))
            ->assertInertia(fn ($page) => $page
                ->where('wishlist.items.0.reservation.name', 'Kasia')
                ->where('wishlist.items.0.reservation.mine', true));
    }

    public function test_the_owner_never_sees_reservations(): void
    {
        $this->item->reservation()->create(['name' => 'Kasia', 'guest_token' => hash('sha256', 'gosc-1')]);

        $this->withCookie(CurrentWishlist::COOKIE, 'wlasciciel')
            ->get(route('wishlist.show', $this->wishlist))
            ->assertInertia(fn ($page) => $page
                ->where('editable', true)
                ->where('wishlist.items.0.reservation', null));
    }

    public function test_a_reserved_gift_cannot_be_reserved_again(): void
    {
        $this->item->reservation()->create(['name' => 'Kasia', 'guest_token' => hash('sha256', 'gosc-1')]);

        $this->withCookie(GuestToken::COOKIE, 'gosc-2')
            ->post($this->reserveUrl(), ['name' => 'Tomek'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('reservations', ['name' => 'Kasia']);
        $this->assertDatabaseMissing('reservations', ['name' => 'Tomek']);
    }

    public function test_only_the_guest_who_reserved_can_cancel(): void
    {
        $this->item->reservation()->create(['name' => 'Kasia', 'guest_token' => hash('sha256', 'gosc-1')]);

        $this->withCookie(GuestToken::COOKIE, 'gosc-2')->delete($this->reserveUrl());
        $this->assertDatabaseCount('reservations', 1);

        $this->withCookie(GuestToken::COOKIE, 'gosc-1')->delete($this->reserveUrl());
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_the_owner_cannot_reserve_their_own_gift(): void
    {
        $this->withCookie(CurrentWishlist::COOKIE, 'wlasciciel')
            ->post($this->reserveUrl(), ['name' => 'Ja'])
            ->assertForbidden();
    }

    private function reserveUrl(): string
    {
        return route('reservations.store', ['wishlist' => $this->wishlist, 'item' => $this->item]);
    }
}
