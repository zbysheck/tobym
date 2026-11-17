<?php

namespace Tests\Feature;

use App\Enums\Theme;
use App\Models\ExtractionRecipe;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /**
     * Logowanie jak w prawdziwej sesji (id w sesji), bo podgląd "jako" czyta admina z sesji.
     */
    private function signIn(User $user): void
    {
        $this->actingAs($user)->withSession([Auth::guard('web')->getName() => $user->id]);
    }

    private function admin(): User
    {
        config(['tobym.admin_emails' => ['szef@example.com']]);

        return User::factory()->create(['email' => 'Szef@example.com']);
    }

    public function test_the_panel_is_hidden_from_guests_and_other_users(): void
    {
        $this->get(route('admin.dashboard'))->assertNotFound();

        $this->actingAs(User::factory()->create())->get(route('admin.dashboard'))->assertNotFound();
    }

    public function test_the_admin_sees_every_page(): void
    {
        $admin = $this->admin();
        Wishlist::query()->create(['title' => 'Urodziny Ani', 'theme' => Theme::Kotki, 'user_id' => $admin->id]);

        $this->actingAs($admin);

        foreach (['admin.dashboard', 'admin.lists', 'admin.users', 'admin.shops', 'admin.probe'] as $route) {
            $this->get(route($route))->assertOk();
        }

        $this->get(route('admin.lists', ['q' => 'Ani']))->assertOk()->assertSee('Urodziny Ani');
    }

    public function test_the_admin_previews_a_pin_protected_list(): void
    {
        $admin = $this->admin();
        $list = Wishlist::query()->create(['title' => 'Tajna', 'theme' => Theme::Slubna, 'pin_hash' => Hash::make('1234'), 'draft_token' => hash('sha256', 'x')]);

        $this->actingAs($admin)->get(route('admin.lists.preview', $list))->assertOk()->assertSee('Tajna');
    }

    public function test_the_simulator_reads_pasted_html_and_a_recipe_can_be_forgotten(): void
    {
        Http::preventStrayRequests();
        $admin = $this->admin();
        ExtractionRecipe::query()->create(['domain' => 'sklep.example', 'recipe' => ['title' => '//h1']]);

        $html = '<html><head><meta property="og:title" content="Koc"></head><body></body></html>';

        $this->actingAs($admin)
            ->post(route('admin.probe'), ['url' => 'https://sklep.example/koc', 'html' => $html, 'mode' => 'quick'])
            ->assertOk()
            ->assertSee('Koc');

        $this->delete(route('admin.shops.forget', 'sklep.example'))->assertRedirect();
        $this->assertDatabaseCount('extraction_recipes', 0);
    }

    public function test_the_admin_can_view_a_draft_as_its_owner_and_come_back(): void
    {
        $admin = $this->admin();
        $list = Wishlist::query()->create(['title' => 'Szkic Ani', 'theme' => Theme::Kotki, 'draft_token' => hash('sha256', 'ania')]);

        $this->signIn($admin);
        $this->post(route('admin.view-as.list', $list))->assertRedirect(route('wishlist.show', $list));

        $this->get(route('wishlist.show', $list))->assertInertia(fn (Assert $page) => $page
            ->where('editable', true)
            ->where('auth.user', null)
            ->where('auth.isAdmin', true)
            ->where('auth.viewingAs', 'właściciel szkicu „Szkic Ani”'));

        $this->patch(route('wishlist.update', $list), ['title' => 'Szkic Ani 2'])->assertRedirect();
        $this->assertDatabaseHas('activity_log', ['type' => 'list.updated', 'actor_id' => $admin->id, 'wishlist_id' => $list->id]);

        $this->post(route('admin.view-as.stop'))->assertRedirect();
        $this->get(route('wishlist.show', $list))->assertInertia(fn (Assert $page) => $page->where('editable', false));
    }

    public function test_logging_out_while_viewing_as_someone_only_ends_the_view(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        Wishlist::query()->create(['title' => 'Lista Basi', 'theme' => Theme::Slubna, 'user_id' => $user->id]);

        $this->signIn($admin);
        $this->post(route('admin.view-as.user', $user));
        $this->get(route('my-lists'))->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.id', $user->id)
            ->has('lists', 1));

        $this->delete(route('account.destroy'))->assertForbidden();
        $this->post(route('logout'))->assertRedirect('/admin');

        $this->assertAuthenticatedAs($admin);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_events_are_logged_and_listed(): void
    {
        Http::preventStrayRequests();
        $admin = $this->admin();

        $this->post(route('items.store-current'), ['url' => 'https://sklep.invalid/koc', 'title' => 'Koc'])->assertRedirect();

        $this->assertDatabaseHas('activity_log', ['type' => 'list.created']);
        $this->assertDatabaseHas('activity_log', ['type' => 'item.added']);

        $this->actingAs($admin)->get(route('admin.events', ['type' => 'item.']))
            ->assertInertia(fn (Assert $page) => $page->has('events.data', 1)->where('events.data.0.type', 'item.added'));
    }
}
