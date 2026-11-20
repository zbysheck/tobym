<?php

namespace Tests\Feature;

use App\Enums\Theme;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_visitor_sees_the_landing_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Landing')->where('listUrl', null))
            ->assertDontSee('noindex', false);
    }

    public function test_list_pages_are_hidden_from_search_engines(): void
    {
        $this->get(route('start'))->assertOk()->assertSee('noindex', false);
    }

    public function test_missing_pages_use_the_polish_error_page(): void
    {
        $this->get('/l/nieistnieje')
            ->assertNotFound()
            ->assertInertia(fn ($page) => $page->component('Error')->where('status', 404));
    }

    public function test_validation_messages_are_in_polish(): void
    {
        $this->patch(route('wishlist.update-current'), ['title' => str_repeat('a', 200)])
            ->assertSessionHasErrors(['title' => 'Pole nazwa może mieć najwyżej 120 znaków.']);
    }

    public function test_the_privacy_page_is_public(): void
    {
        $this->get(route('privacy'))->assertOk()->assertInertia(fn ($page) => $page->component('Privacy'));
    }

    public function test_my_lists_shows_every_list_of_the_owner(): void
    {
        $user = User::factory()->create();
        Wishlist::query()->create(['title' => 'Święta', 'theme' => Theme::Choinkowa, 'user_id' => $user->id]);
        Wishlist::query()->create(['title' => 'Urodziny', 'theme' => Theme::Urodzinowa, 'user_id' => $user->id]);
        Wishlist::query()->create(['title' => 'Cudza', 'theme' => Theme::Slubna, 'draft_token' => 'x']);

        $this->actingAs($user)->get(route('my-lists'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('MyLists')->has('lists', 2));
    }

    public function test_a_user_deletes_the_account_with_all_lists(): void
    {
        $user = User::factory()->create();
        Wishlist::query()->create(['title' => 'A', 'theme' => Theme::Zimowa, 'user_id' => $user->id]);

        $this->actingAs($user)->delete(route('account.destroy'))->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('wishlists', 0);
    }

    public function test_the_login_page_sends_guests_a_form_and_users_to_their_lists(): void
    {
        $this->withoutVite();
        $this->get(route('login'))->assertOk()->assertInertia(fn ($page) => $page->component('Login'));

        $this->actingAs(User::factory()->create())->get(route('login'))->assertRedirect(route('my-lists'));
    }
}
