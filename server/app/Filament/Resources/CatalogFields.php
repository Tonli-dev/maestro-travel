<?php

namespace App\Filament\Resources;

use App\Models\Label;
use App\Support\CatalogPresentation;
use App\Support\Destinations;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Js;
use Illuminate\Validation\Rule;
use Throwable;

class CatalogFields
{
    public const STATUSES = ['draft' => 'Nacrt', 'published' => 'Objavljeno', 'archived' => 'Arhivirano'];

    public const AVAILABILITY = ['on_request' => 'Na upit', 'available' => 'Raspoloživo', 'sold_out' => 'Popunjeno'];

    public static function status(): Select
    {
        return Select::make('status')->label('Status')->options(self::STATUSES)->default('draft')->required()
            ->helperText('Spremanje sa statusom Objavljeno odmah mijenja javnu ponudu. Pregled prikazuje samo posljednje spremljene podatke.');
    }

    /**
     * Destination with suggestions from existing trips/hotels. Case/accent/spacing variants are saved with the
     * established spelling; a probable typo shows a warning with a one-click fix.
     */
    public static function destination(): TextInput
    {
        return TextInput::make('destination')->label('Destinacija')->required()->maxLength(255)
            ->datalist(fn (): array => app(Destinations::class)->all())
            ->autocomplete(false)
            ->live(onBlur: true)
            ->afterStateUpdated(function (?string $state, Set $set): void {
                $canonical = app(Destinations::class)->canonical($state);
                if ($canonical !== $state) {
                    $set('destination', $canonical);
                }
            })
            ->dehydrateStateUsing(fn (?string $state): ?string => app(Destinations::class)->canonical($state))
            ->helperText(fn (?string $state): string => ($similar = app(Destinations::class)->similar($state))
                ? 'Postoji slična destinacija „'.$similar.'“. Ako je to ista destinacija, odaberite postojeći naziv.'
                : 'Odaberite postojeću destinaciju s popisa ili upišite novu.')
            ->hintAction(Action::make('useSimilarDestination')->icon(Heroicon::OutlinedCheck)
                ->label(fn (Get $get): string => 'Koristi „'.app(Destinations::class)->similar($get('destination')).'“')
                ->visible(fn (Get $get): bool => app(Destinations::class)->similar($get('destination')) !== null)
                /*
                 * Write the exact suggestion shown in the label straight into the field in the browser, so the
                 * input changes immediately even if it still holds focus or an unsent edit; $wire.set syncs it.
                 * Both values are JSON-encoded (HTML-safe) rather than interpolated.
                 */
                ->alpineClickHandler(fn (Get $get, TextInput $component): string => '$wire.set('
                    .Js::from($component->getStatePath()).', '.Js::from(app(Destinations::class)->similar($get('destination'))).')')
                ->action(fn (Get $get, Set $set) => $set('destination', app(Destinations::class)->similar($get('destination')))));
    }

    public static function labels(string $relationship, string $kind): Select
    {
        return Select::make($relationship)->label(Label::GROUPS[$kind])
            ->multiple()->relationship($relationship, 'name')->searchable()->preload()
            ->afterStateUpdated(fn (Select $component, ?array $state) => $component->state(array_values(array_unique($state ?? []))))
            ->nestedRecursiveRules([Rule::exists('labels', 'id')->where('kind', $kind)])
            ->createOptionForm([TextInput::make('name')->label('Naziv')->required()->maxLength(120)])
            ->createOptionAction(fn (Action $action): Action => $action->label('Dodaj oznaku')->tooltip('Dodaj oznaku')->modalHeading('Dodaj oznaku')->modalSubmitActionLabel('Dodaj'))
            ->createOptionUsing(fn (array $data): int => Label::resolveName($kind, $data['name'])->id);
    }

    public static function images(string $directory): FileUpload
    {
        return FileUpload::make('images')->label('Fotografije')
            ->disk('public')->directory($directory)->visibility('public')
            ->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->multiple()->reorderable()->maxFiles(12)->maxSize(4096)
            ->preventFilePathTampering()->columnSpanFull()
            ->helperText('Prva fotografija je naslovna. JPG, PNG ili WebP; do 4 MB po slici.');
    }

    /**
     * One-line summary for a collapsed departure/rate item, e.g. "18.01.2027. – 20.01.2027. · 3 dana · 499,00 KM · Raspoloživo".
     *
     * @param  array<string, mixed>  $state
     */
    public static function rateLabel(array $state): string
    {
        $parts = [];
        try {
            $start = is_string($state['starts_at'] ?? null) && filled($state['starts_at']) ? Carbon::parse($state['starts_at']) : null;
            $end = is_string($state['ends_at'] ?? null) && filled($state['ends_at']) ? Carbon::parse($state['ends_at']) : null;
        } catch (Throwable) {
            $start = $end = null;
        }
        $parts[] = $start ? $start->format('d.m.Y.').($end ? ' – '.$end->format('d.m.Y.') : '') : 'Novi termin';
        if ($start && $end && $end->greaterThanOrEqualTo($start)) {
            $days = (int) $start->diffInDays($end) + 1;
            $parts[] = $days.' '.($days % 10 === 1 && $days % 100 !== 11 ? 'dan' : 'dana');
        }
        $price = $state['price'] ?? null;
        $parts[] = is_numeric($price) ? CatalogPresentation::money(number_format((float) $price, 2, '.', '')) : 'Cijena na upit';
        $parts[] = self::AVAILABILITY[$state['availability'] ?? ''] ?? '';

        return implode(' · ', array_filter($parts));
    }

    /** Collapse saved repeater items to their summary line; keep newly added items open for typing. */
    public static function collapseSavedItems(): Closure
    {
        return fn (?Schema $item): bool => $item?->getRecord() instanceof Model && $item->getRecord()->exists;
    }

    /** @return array<DatePicker|TextInput|Select> */
    public static function rateFields(): array
    {
        return [
            DatePicker::make('starts_at')->label('Od')->required(),
            DatePicker::make('ends_at')->label('Do')->required()->afterOrEqual('starts_at'),
            TextInput::make('price')->label('Cijena')->numeric()->minValue(0.01)->maxValue(99999999.99)
                ->rules(['decimal:0,2'])->helperText('Ostavite prazno za cijenu na upit.'),
            Select::make('currency')->label('Valuta')->options(['BAM' => 'KM (BAM)'])->default('BAM')->required(),
            Select::make('availability')->label('Dostupnost')->options(self::AVAILABILITY)->default('on_request')->required(),
        ];
    }
}
