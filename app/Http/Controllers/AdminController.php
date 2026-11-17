<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\AddonToken;
use App\Models\ExtractionRecipe;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Services\ProductExtractor;
use App\Services\ProductMetadataFetcher;
use App\Support\ActivityLog;
use App\Support\Impersonation;
use App\Support\Urls;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Panel admina: przegląd list, kont i sklepów oraz symulator odczytu produktu.
 */
class AdminController extends Controller
{
    private const PER_PAGE = 40;

    public function dashboard(): Response
    {
        return Inertia::render('admin/Dashboard', [
            'stats' => [
                ['label' => 'Konta', 'value' => User::query()->count()],
                ['label' => 'Listy zapisane', 'value' => Wishlist::query()->whereNotNull('user_id')->count()],
                ['label' => 'Szkice (bez maila)', 'value' => Wishlist::query()->whereNull('user_id')->count()],
                ['label' => 'Prezenty', 'value' => WishlistItem::query()->count()],
                ['label' => 'Rezerwacje', 'value' => Reservation::query()->count()],
                ['label' => 'Przepisy sklepów', 'value' => ExtractionRecipe::query()->count()],
                ['label' => 'Połączone wtyczki', 'value' => AddonToken::query()->count()],
                ['label' => 'Prezenty z ostatnich 7 dni', 'value' => WishlistItem::query()->where('created_at', '>=', now()->subDays(7))->count()],
            ],
            'items' => WishlistItem::query()->with('wishlist')->latest('id')->limit(12)->get()
                ->map(fn (WishlistItem $item): array => $this->item($item))->values(),
        ]);
    }

    public function lists(Request $request): Response
    {
        $search = trim($request->string('q')->toString());

        $lists = Wishlist::query()
            ->with('user')
            ->withCount(['items', 'items as reserved_count' => fn (Builder $query) => $query->has('reservation')])
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->where('title', 'like', "%{$search}%")
                ->orWhere('slug', $search)
                ->orWhereHas('user', fn (Builder $user) => $user->where('email', 'like', "%{$search}%"))))
            ->latest('updated_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Wishlist $list): array => [
                'id' => $list->id,
                'slug' => $list->slug,
                'title' => $list->title,
                'theme' => $list->theme->label(),
                'owner' => $list->user?->email,
                'items' => $list->items_count,
                'reserved' => $list->reserved_count ?? 0,
                'pin' => $list->pin_hash !== null,
                'event_date' => $list->event_date?->toDateString(),
                'updated_at' => $list->updated_at?->toDateTimeString(),
            ]);

        return Inertia::render('admin/Lists', ['lists' => $lists, 'q' => $search]);
    }

    public function users(): Response
    {
        $users = User::query()
            ->withCount('wishlists')
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'email' => $user->email,
                'lists' => $user->wishlists_count ?? 0,
                'admin' => $user->isAdmin(),
                'created_at' => $user->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('admin/Users', ['users' => $users]);
    }

    /**
     * Sklepy: jak dobrze czytamy każdą domenę (na podstawie dodanych prezentów) i zapisane przepisy.
     */
    public function shops(): Response
    {
        $recipes = ExtractionRecipe::query()->get()->keyBy('domain');
        $shops = [];

        foreach (WishlistItem::query()->whereNotNull('url')->get(['url', 'image_url', 'image_path', 'price']) as $item) {
            $domain = Urls::host((string) $item->url) ?? '?';
            $shops[$domain] ??= ['domain' => $domain, 'items' => 0, 'with_image' => 0, 'with_price' => 0];
            $shops[$domain]['items']++;
            $shops[$domain]['with_image'] += $item->image_url !== null || $item->image_path !== null ? 1 : 0;
            $shops[$domain]['with_price'] += $item->price !== null ? 1 : 0;
        }

        foreach ($recipes as $domain => $recipe) {
            $shops[$domain] ??= ['domain' => $domain, 'items' => 0, 'with_image' => 0, 'with_price' => 0];
        }

        $rows = collect($shops)
            ->map(function (array $shop) use ($recipes): array {
                $recipe = $recipes->get($shop['domain']);

                return [
                    ...$shop,
                    'recipe' => $recipe?->recipe,
                    'generated_at' => $recipe?->generated_at?->toDateTimeString(),
                ];
            })
            ->sortByDesc('items')
            ->values();

        return Inertia::render('admin/Shops', ['shops' => $rows]);
    }

    public function forgetRecipe(string $domain): RedirectResponse
    {
        ExtractionRecipe::query()->where('domain', $domain)->delete();
        ActivityLog::record('admin.recipe', "Admin usunął przepis sklepu {$domain}");

        return back()->with('status', "Usunięto przepis dla {$domain}. Następny link z tego sklepu nauczy się od nowa.");
    }

    /**
     * Symulator: co tobym odczyta z linku (jak serwer) albo z wklejonego HTML (jak wtyczka).
     */
    public function probe(Request $request, ProductMetadataFetcher $fetcher, ProductExtractor $extractor): Response
    {
        $result = null;

        if ($request->isMethod('post')) {
            $request->validate([
                'url' => ['required', 'string', 'url:http,https', 'max:2000'],
                'html' => ['nullable', 'string'],
                'mode' => ['required', 'in:quick,ai,fresh'],
            ]);

            $result = $this->runProbe($request, $fetcher, $extractor);
        }

        return Inertia::render('admin/Probe', [
            'result' => $result,
            'input' => [
                'url' => $request->string('url')->toString(),
                'mode' => $request->string('mode', 'ai')->toString(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function runProbe(Request $request, ProductMetadataFetcher $fetcher, ProductExtractor $extractor): array
    {
        set_time_limit(150);
        $started = microtime(true);
        $url = $request->string('url')->trim()->toString();
        $html = $request->string('html')->toString();
        $mode = $request->string('mode')->toString();
        $domain = Urls::host($url);
        $steps = [];

        if ($html !== '') {
            $steps[] = 'HTML wklejony ręcznie ('.strlen($html).' bajtów), tak jak przysłałaby go wtyczka.';
        } else {
            try {
                $trace = $fetcher->trace($url);
                $steps = $trace['steps'];
                $html = $trace['html'] ?? '';
            } catch (Throwable $exception) {
                $steps[] = 'Pobieranie przerwane: '.$exception->getMessage();
            }
        }

        $fields = null;

        if ($html !== '') {
            if ($mode === 'fresh' && $domain !== null) {
                ExtractionRecipe::query()->where('domain', $domain)->delete();
                $steps[] = 'Usunięto zapisany przepis, AI uczy się od nowa.';
            }

            $fields = $extractor->extract($html, $url, $mode !== 'quick');

            if ($extractor->generationSkipped) {
                $steps[] = 'Tryb szybki: AI mogłoby jeszcze coś uzupełnić.';
            }
        } else {
            $steps[] = 'Brak strony do odczytania. Użytkownik dostanie nazwę z adresu: '.(Urls::titleFromPath($url) ?? $domain ?? '?');
        }

        return [
            'steps' => $steps,
            'fields' => $fields,
            'ai' => $extractor->lastGenerated,
            'recipe' => $domain === null ? null : ExtractionRecipe::query()->where('domain', $domain)->first()?->recipe,
            'seconds' => round(microtime(true) - $started, 1),
        ];
    }

    /**
     * Log zdarzeń z filtrami: rodzaj, tekst, lista, konto.
     */
    public function events(Request $request): Response
    {
        $type = $request->string('type')->toString();
        $search = trim($request->string('q')->toString());
        $listId = $request->integer('list');
        $slug = $request->string('lista')->toString();

        if ($slug !== '') {
            $listId = (int) Wishlist::query()->where('slug', $slug)->value('id');
        }
        $userId = $request->integer('user');

        $events = Activity::query()
            ->with(['user', 'actor', 'wishlist'])
            ->when($type !== '', fn (Builder $query) => $query->where('type', 'like', $type.'%'))
            ->when($search !== '', fn (Builder $query) => $query->where('description', 'like', "%{$search}%"))
            ->when($listId > 0, fn (Builder $query) => $query->where('wishlist_id', $listId))
            ->when($userId > 0, fn (Builder $query) => $query->where('user_id', $userId))
            ->latest('id')
            ->paginate(60)
            ->withQueryString()
            ->through(fn (Activity $event): array => [
                'id' => $event->id,
                'type' => $event->type,
                'description' => $event->description,
                'user' => $event->user === null ? null : ['id' => $event->user->id, 'email' => $event->user->email],
                'actor' => $event->actor?->email,
                'list' => $event->wishlist === null ? null : ['id' => $event->wishlist->id, 'slug' => $event->wishlist->slug, 'title' => $event->wishlist->title],
                'meta' => $event->meta,
                'created_at' => $event->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('admin/Events', [
            'events' => $events,
            'filters' => ['type' => $type, 'q' => $search, 'list' => $listId ?: null, 'user' => $userId ?: null],
            'types' => [
                ['value' => '', 'label' => 'Wszystko'],
                ['value' => 'list.', 'label' => 'Listy'],
                ['value' => 'item.', 'label' => 'Prezenty'],
                ['value' => 'reservation.', 'label' => 'Rezerwacje'],
                ['value' => 'preview', 'label' => 'Odczyt linków'],
                ['value' => 'auth.', 'label' => 'Logowanie'],
                ['value' => 'account.', 'label' => 'Konta'],
                ['value' => 'admin.', 'label' => 'Działania admina'],
            ],
        ]);
    }

    /**
     * "Wyświetl jako właściciel": konto albo szkic, do którego należy lista.
     */
    public function viewAsOwner(Request $request, Wishlist $wishlist): RedirectResponse
    {
        $label = Impersonation::start($request, $wishlist);
        ActivityLog::record('admin.view_as', "Admin ogląda jako: {$label}", $wishlist);

        return redirect()->route('wishlist.show', $wishlist);
    }

    public function viewAsUser(Request $request, User $user): RedirectResponse
    {
        Impersonation::startForUser($request, $user);
        ActivityLog::record('admin.view_as', "Admin ogląda jako: {$user->email}");

        return redirect()->route('my-lists');
    }

    public function stopViewing(Request $request): RedirectResponse
    {
        Impersonation::stop($request);

        return redirect()->back(fallback: route('admin.dashboard'));
    }

    /**
     * @return array<string, mixed>
     */
    private function item(WishlistItem $item): array
    {
        return [
            'id' => $item->id,
            'title' => $item->title,
            'url' => $item->url,
            'shop' => $item->shop,
            'price' => $item->price,
            'image_url' => $item->image_url,
            'list' => $item->wishlist === null ? null : ['id' => $item->wishlist->id, 'slug' => $item->wishlist->slug, 'title' => $item->wishlist->title],
            'created_at' => $item->created_at?->toDateTimeString(),
        ];
    }
}
