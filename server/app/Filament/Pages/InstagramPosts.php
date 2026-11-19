<?php

namespace App\Filament\Pages;

use App\Models\InstagramPost;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

/**
 * One list of the posts shown in the homepage "S našeg Instagrama" section: add, remove, reorder,
 * with a live preview of the real Instagram post next to each entry.
 *
 * @property-read Schema $form
 */
class InstagramPosts extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCamera;

    protected static ?string $navigationLabel = 'Instagram';

    protected static ?string $title = 'Instagram objave';

    protected static ?string $slug = 'instagram';

    protected static ?int $navigationSort = 50;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->fillFromDatabase();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Repeater::make('posts')->hiddenLabel()
                ->helperText('Objave se na naslovnici prikazuju ovim redoslijedom. Promjene su javne odmah nakon spremanja.')
                ->schema([
                    Hidden::make('id'),
                    Grid::make(5)->schema([
                        Group::make([
                            TextInput::make('url')->label('Poveznica na objavu')->required()->maxLength(255)
                                ->placeholder('https://www.instagram.com/p/…')->prefixIcon(Heroicon::OutlinedLink)
                                ->live(onBlur: true)
                                ->rules([fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                    if (InstagramPost::normalizeUrl(is_string($value) ? $value : null) === null) {
                                        $fail('Upišite poveznicu na Instagram objavu ili reel, npr. https://www.instagram.com/p/AbC123/.');
                                    }
                                }])
                                ->dehydrateStateUsing(fn (?string $state): ?string => InstagramPost::normalizeUrl($state) ?? $state),
                            Section::make('Vlastita kartica (neobavezno)')
                                ->description('Bez vlastite fotografije naslovnica prikazuje originalnu objavu s Instagrama: fotografiju, profil i opis. Učitajte fotografiju samo ako želite karticu u dizajnu stranice.')
                                ->icon(Heroicon::OutlinedPhoto)
                                ->schema([
                                    FileUpload::make('image')->label('Vlastita fotografija')
                                        ->disk('public')->directory('instagram')->visibility('public')
                                        ->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(4096)
                                        ->imagePreviewHeight('140')
                                        /*
                                         * This page edits a list, not one record, so Filament cannot trace a saved path to a record.
                                         * Accept a stored path only when it is the photo this same post already has.
                                         */
                                        ->preventFilePathTampering(allowFilePathUsing: fn (string $file, Get $get): bool => filled($get('id'))
                                            && InstagramPost::query()->whereKey($get('id'))->where('image', $file)->exists())
                                        ->helperText('JPG, PNG ili WebP, do 4 MB.'),
                                    TextInput::make('title')->label('Naslov kartice')->maxLength(120)
                                        ->helperText('Kratko, npr. „Opatija i Festival čokolade“. Koristi se samo uz vlastitu fotografiju.'),
                                    Textarea::make('description')->label('Kratki opis')->rows(2)->maxLength(300),
                                    DatePicker::make('posted_on')->label('Datum objave'),
                                ])->collapsible()->collapsed()->compact(),
                        ])->columnSpan(3),
                        Html::make(fn (Get $get): HtmlString => new HtmlString(view('filament.instagram-preview', [
                            'embed' => InstagramPost::embedUrl($get('url')),
                            'url' => InstagramPost::normalizeUrl($get('url')),
                        ])->render()))->columnSpan(2),
                    ]),
                ])
                ->extraAttributes(['class' => 'maestro-panels'])
                ->itemLabel(fn (?int $index, array $state): string => self::itemLabel($index, $state))
                ->defaultItems(0)->maxItems(InstagramPost::MAX_POSTS)
                ->addActionLabel('Dodaj objavu')
                ->reorderableWithButtons()->collapsible()
                ->deleteAction(fn (Action $action): Action => $action->requiresConfirmation()
                    ->modalHeading('Ukloniti objavu?')->modalDescription('Objava nestaje s naslovnice kad spremite promjene.'))
                ->rules([fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                    $urls = collect(is_array($value) ? $value : [])
                        ->map(fn (mixed $post): ?string => is_array($post) ? InstagramPost::normalizeUrl($post['url'] ?? null) : null)
                        ->filter();
                    if ($urls->count() !== $urls->unique()->count()) {
                        $fail('Ista objava je dodana više puta.');
                    }
                }]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('Spremi objave')->submit('save')->keyBindings(['mod+s']),
                        Action::make('openPublic')->label('Pogledaj na stranici')->color('gray')
                            ->url(route('home').'#instagram')->openUrlInNewTab(),
                    ])->sticky()->key('form-actions'),
                ]),
        ]);
    }

    public function save(): void
    {
        $posts = array_values($this->form->getState()['posts'] ?? []);
        $imagesBefore = InstagramPost::query()->whereNotNull('image')->pluck('image')->all();

        DB::transaction(function () use ($posts): void {
            $kept = [];
            foreach ($posts as $position => $data) {
                $post = filled($data['id'] ?? null) ? InstagramPost::find($data['id']) : null;
                $post ??= new InstagramPost;
                $post->fill([
                    'url' => $data['url'], 'image' => $data['image'] ?? null,
                    'title' => $data['title'] ?? null, 'description' => $data['description'] ?? null,
                    'posted_on' => $data['posted_on'] ?? null, 'sort' => $position,
                ])->save();
                $kept[] = $post->id;
            }
            InstagramPost::query()->whereNotIn('id', $kept)->delete();
        });

        $imagesAfter = InstagramPost::query()->whereNotNull('image')->pluck('image')->all();
        foreach (array_diff($imagesBefore, $imagesAfter) as $unused) {
            if (str_starts_with($unused, 'instagram/')) {
                Storage::disk('public')->delete($unused);
            }
        }

        $this->fillFromDatabase();
        Notification::make()->success()->title('Instagram objave su spremljene')
            ->body('Naslovnica odmah prikazuje novi popis.')->send();
    }

    /** @param array<string, mixed> $state */
    private static function itemLabel(?int $index, array $state): string
    {
        $name = is_string($state['title'] ?? null) && filled($state['title'])
            ? $state['title']
            : (InstagramPost::shortcode(is_string($state['url'] ?? null) ? $state['url'] : null) ?? 'nova objava');
        $hasPhoto = is_array($state['image'] ?? null) ? array_filter($state['image']) !== [] : filled($state['image'] ?? null);

        return 'Objava '.(($index ?? 0) + 1).' · '.$name.' · '.($hasPhoto ? 'vlastita fotografija' : 'originalna objava s Instagrama');
    }

    private function fillFromDatabase(): void
    {
        $this->form->fill(['posts' => InstagramPost::query()->ordered()->get()->map(fn (InstagramPost $post): array => [
            'id' => $post->id, 'url' => $post->url, 'image' => $post->image,
            'title' => $post->title, 'description' => $post->description,
            'posted_on' => $post->posted_on?->toDateString(),
        ])->all()]);
    }
}
