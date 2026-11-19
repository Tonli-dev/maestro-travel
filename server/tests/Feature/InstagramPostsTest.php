<?php

namespace Tests\Feature;

use App\Filament\Pages\InstagramPosts;
use App\Models\InstagramPost;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class InstagramPostsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Repeater::fake();
    }

    public function test_migration_carries_over_the_three_existing_homepage_posts(): void
    {
        $this->assertSame(
            ['Shopping u Italiji', 'Opatija i Festival čokolade', 'Interliber u Zagrebu'],
            InstagramPost::query()->ordered()->pluck('title')->all(),
        );
        $this->assertSame('https://www.instagram.com/p/De2C00fFw_8/', InstagramPost::query()->ordered()->first()->url);

        $this->get('/')->assertOk()->assertSeeInOrder(['id="instagram"', 'Shopping u Italiji', 'Opatija i Festival čokolade', 'Interliber u Zagrebu', 'Objava 1 od 3'], false)
            ->assertSee('https://www.instagram.com/p/De2C00fFw_8/', false)->assertSee('23.10.2026.');
    }

    public function test_page_requires_an_active_employee(): void
    {
        $this->get('/admin/instagram')->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create())->get('/admin/instagram')->assertForbidden();
        $this->actingAs(User::factory()->active()->create())->get('/admin/instagram')->assertOk()
            ->assertSee('Instagram objave')->assertSee('Dodaj objavu');
    }

    public function test_posts_can_be_added_reordered_and_removed_in_one_save(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->active()->create());
        DB::table('instagram_posts')->delete();
        $keep = InstagramPost::factory()->create(['title' => 'Ostaje', 'sort' => 0]);
        $remove = InstagramPost::factory()->create(['title' => 'Uklanja se', 'sort' => 1, 'image' => 'instagram/stara.webp']);
        Storage::disk('public')->put('instagram/stara.webp', 'x');

        Livewire::test(InstagramPosts::class)
            ->assertSeeText('Objava 1 · Ostaje')->assertSeeText('Objava 2 · Uklanja se')
            ->set('data.posts', [
                'a' => ['id' => null, 'url' => 'https://instagram.com/reel/NovaObjava1/?igsh=abc', 'title' => 'Nova', 'description' => 'Opis', 'posted_on' => '2026-11-01', 'image' => null],
                'b' => ['id' => $keep->id, 'url' => $keep->url, 'title' => 'Ostaje', 'description' => null, 'posted_on' => null, 'image' => null],
            ])
            ->set('data.posts.a.image', [UploadedFile::fake()->image('nova.jpg')])
            ->call('save')->assertHasNoFormErrors()->assertNotified('Instagram objave su spremljene');

        $posts = InstagramPost::query()->ordered()->get();
        $this->assertSame(['Nova', 'Ostaje'], $posts->pluck('title')->all());
        $this->assertSame('https://www.instagram.com/reel/NovaObjava1/', $posts->first()->url, 'Tracking parameters are removed.');
        $this->assertSame($keep->id, $posts->last()->id, 'Existing posts are updated, not recreated.');
        $this->assertModelMissing($remove);
        Storage::disk('public')->assertMissing('instagram/stara.webp');
        Storage::disk('public')->assertExists($posts->first()->image);
    }

    public function test_a_post_can_be_removed_while_other_posts_keep_their_photos(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('instagram/a.webp', 'a');
        Storage::disk('public')->put('instagram/b.webp', 'b');
        $this->actingAs(User::factory()->active()->create());
        DB::table('instagram_posts')->delete();
        $first = InstagramPost::factory()->create(['title' => 'Prva', 'image' => 'instagram/a.webp', 'sort' => 0]);
        $second = InstagramPost::factory()->create(['title' => 'Druga', 'image' => 'instagram/b.webp', 'sort' => 1]);

        $page = Livewire::test(InstagramPosts::class);
        $keys = array_keys($page->get('data.posts'));
        $page->set('data.posts', [$keys[0] => $page->get('data.posts.'.$keys[0])])
            ->call('save')->assertHasNoFormErrors()->assertNotified('Instagram objave su spremljene');

        $this->assertSame('instagram/a.webp', $first->fresh()->image);
        $this->assertModelMissing($second);
        Storage::disk('public')->assertExists('instagram/a.webp');
        Storage::disk('public')->assertMissing('instagram/b.webp');

        Livewire::test(InstagramPosts::class)->call('save')->assertHasNoFormErrors();
        $this->assertSame('instagram/a.webp', $first->fresh()->image, 'Saving again keeps existing photos.');
    }

    public function test_a_post_cannot_claim_another_posts_or_an_arbitrary_file_path(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('instagram/a.webp', 'a');
        Storage::disk('public')->put('instagram/b.webp', 'b');
        Storage::disk('public')->put('trips/private.webp', 'x');
        $this->actingAs(User::factory()->active()->create());
        DB::table('instagram_posts')->delete();
        $first = InstagramPost::factory()->create(['image' => 'instagram/a.webp', 'sort' => 0]);
        InstagramPost::factory()->create(['image' => 'instagram/b.webp', 'sort' => 1]);

        foreach (['instagram/b.webp', 'trips/private.webp'] as $path) {
            $page = Livewire::test(InstagramPosts::class);
            $key = array_keys($page->get('data.posts'))[0];
            $page->set('data.posts.'.$key.'.image', [(string) Str::uuid() => $path])
                ->call('save')->assertHasFormErrors(['posts.'.$key.'.image']);
            $this->assertSame('instagram/a.webp', $first->fresh()->image);
        }
    }

    #[TestWith(['https://www.facebook.com/maestro/posts/1'])]
    #[TestWith(['https://www.instagram.com/maestro.travel.orasje/'])]
    #[TestWith(['javascript:alert(1)'])]
    #[TestWith(['nije poveznica'])]
    public function test_only_instagram_post_or_reel_links_are_accepted(string $url): void
    {
        $this->actingAs(User::factory()->active()->create());
        $before = InstagramPost::count();

        Livewire::test(InstagramPosts::class)
            ->set('data.posts', ['a' => ['id' => null, 'url' => $url, 'title' => 'X']])
            ->call('save')->assertHasFormErrors(['posts.a.url']);

        $this->assertSame($before, InstagramPost::count(), 'An invalid save changes nothing.');
    }

    public function test_the_same_post_cannot_be_added_twice(): void
    {
        $this->actingAs(User::factory()->active()->create());

        Livewire::test(InstagramPosts::class)
            ->set('data.posts', [
                'a' => ['id' => null, 'url' => 'https://www.instagram.com/p/Isti12345/'],
                'b' => ['id' => null, 'url' => 'https://instagram.com/p/Isti12345?utm_source=ig'],
            ])
            ->call('save')->assertHasFormErrors(['posts']);
    }

    public function test_admin_preview_uses_the_instagram_embed_of_the_typed_link(): void
    {
        $this->actingAs(User::factory()->active()->create());

        Livewire::test(InstagramPosts::class)
            ->set('data.posts', ['a' => ['id' => null, 'url' => 'https://www.instagram.com/p/Pregled123/']])
            ->assertSee('https://www.instagram.com/p/Pregled123/embed/', false)
            ->set('data.posts', ['a' => ['id' => null, 'url' => 'krivo']])
            ->assertSee('Upišite poveznicu na objavu i pregled će se prikazati ovdje.');
    }

    public function test_post_without_own_photo_shows_the_official_instagram_embed(): void
    {
        DB::table('instagram_posts')->delete();
        InstagramPost::factory()->create(['url' => 'https://www.instagram.com/p/Original1/', 'title' => '<b>Naslov</b>', 'image' => null]);

        $this->get('/')->assertOk()->assertSee('Objava 1 od 1')
            ->assertSee('<iframe class="instagram-embed" src="https://www.instagram.com/p/Original1/embed/captioned/"', false)
            ->assertSee('loading="lazy"', false)->assertSee('data-instagram-embed', false)
            ->assertSee('&lt;b&gt;Naslov&lt;/b&gt; — objava na Instagramu', false)->assertDontSee('<b>Naslov</b>', false)
            ->assertSee('id="instagram-next" aria-label="Sljedeća Instagram objava" disabled', false);
    }

    public function test_post_with_own_photo_keeps_the_site_card_and_loads_nothing_from_instagram(): void
    {
        DB::table('instagram_posts')->delete();
        InstagramPost::factory()->create(['title' => 'Vlastita', 'description' => '<script>x</script>', 'image' => 'instagram/vlastita.webp']);

        $this->get('/')->assertOk()->assertSee('/storage/instagram/vlastita.webp', false)->assertSeeText('Vlastita')
            ->assertDontSee('<script>x</script>', false)->assertDontSee('instagram-embed', false)->assertDontSee('/embed/', false);
    }

    public function test_section_is_hidden_without_posts(): void
    {
        DB::table('instagram_posts')->delete();

        $this->get('/')->assertOk()->assertDontSee('id="instagram"', false);
    }

    public function test_admin_preview_lets_instagram_links_open_and_posts_are_clearly_labelled(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('instagram/x.webp', 'x');
        $this->actingAs(User::factory()->active()->create());
        DB::table('instagram_posts')->delete();
        InstagramPost::factory()->create(['url' => 'https://www.instagram.com/p/BezSlike1/', 'title' => null, 'image' => null, 'sort' => 0]);
        InstagramPost::factory()->create(['title' => 'S fotografijom', 'image' => 'instagram/x.webp', 'sort' => 1]);

        Livewire::test(InstagramPosts::class)
            ->assertSee('allow-popups allow-popups-to-escape-sandbox', false)
            ->assertSeeText('Objava 1 · BezSlike1 · originalna objava s Instagrama')
            ->assertSeeText('Objava 2 · S fotografijom · vlastita fotografija')
            ->assertSeeText('Vlastita kartica (neobavezno)')->assertSee('maestro-panels', false);
    }

    #[TestWith(['https://www.instagram.com/p/AbC_-123/', 'https://www.instagram.com/p/AbC_-123/'])]
    #[TestWith(['http://instagram.com/p/AbC123?img_index=1', 'https://www.instagram.com/p/AbC123/'])]
    #[TestWith(['https://www.instagram.com/reels/AbC123/', 'https://www.instagram.com/reel/AbC123/'])]
    #[TestWith(['https://www.instagram.com/maestro.travel.orasje/p/AbC123/', 'https://www.instagram.com/p/AbC123/'])]
    public function test_links_are_normalized(string $input, string $expected): void
    {
        $this->assertSame($expected, InstagramPost::normalizeUrl($input));
    }
}
