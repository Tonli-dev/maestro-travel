<?php

namespace App\Filament\Resources;

use App\Support\CatalogAdminActions;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Compact, shared pieces of the trips and hotels tables.
 */
class CatalogTable
{
    public const STATUS_COLORS = ['published' => 'success', 'draft' => 'warning', 'archived' => 'gray'];

    /** Constrains a departures/rates query to current dates: today or later in Sarajevo and not sold out. */
    public static function currentDates(Builder $query): Builder
    {
        return $query->whereDate('starts_at', '>=', now('Europe/Sarajevo')->toDateString())
            ->whereIn('availability', ['available', 'on_request']);
    }

    /** Adds the current-date count used by {@see self::nextDate()}. */
    public static function withCurrentDateCount(Builder $query, string $relationship): Builder
    {
        return $query->withCount([$relationship.' as current_dates_count' => fn (Builder $dates): Builder => self::currentDates($dates)]);
    }

    /** One column for "when is the next date and how many are left". */
    public static function nextDate(string $relationship, string $label): TextColumn
    {
        return TextColumn::make($relationship.'_min_starts_at')->label($label)
            ->min([$relationship => fn (Builder $dates): Builder => self::currentDates($dates)], 'starts_at')
            ->date('d.m.Y.')->placeholder('Nema aktualnih termina')
            ->description(function (Model $record): ?string {
                $count = (int) ($record->current_dates_count ?? 0);

                return $count === 0 ? null : $count.' '.($count % 10 === 1 && $count % 100 !== 11 ? 'aktualan termin' : 'aktualna termina');
            })
            ->sortable();
    }

    public static function status(): TextColumn
    {
        return TextColumn::make('status')->label('Status')->badge()
            ->formatStateUsing(fn (string $state): string => CatalogFields::STATUSES[$state] ?? $state)
            ->color(fn (string $state): string => self::STATUS_COLORS[$state] ?? 'gray');
    }

    public static function updatedAt(): TextColumn
    {
        return TextColumn::make('updated_at')->label('Ažurirano')->dateTime('d.m.Y. H:i')->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    /** Edit stays one click away; preview, public link, delete and restore sit behind the ⋮ menu. */
    public static function recordActions(): array
    {
        return [
            EditAction::make()->iconButton()->tooltip('Uredi'),
            ActionGroup::make([
                ...CatalogAdminActions::links(),
                DeleteAction::make(),
                RestoreAction::make(),
            ])->icon(Heroicon::EllipsisVertical)->tooltip('Više opcija'),
        ];
    }

    /**
     * Status tabs above the table; "Aktivna" (published + drafts) is the default.
     *
     * @return array<string, Tab>
     */
    public static function statusTabs(string $modelClass): array
    {
        $count = fn (array $statuses): int => $modelClass::query()->whereIn('status', $statuses)->count();
        $tab = fn (string $label, array $statuses): Tab => Tab::make($label)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', $statuses))
            ->badge(fn (): int => $count($statuses));

        return [
            'aktivna' => $tab('Aktivna', ['published', 'draft']),
            'objavljena' => $tab('Objavljena', ['published']),
            'nacrti' => $tab('Nacrti', ['draft']),
            'arhivirana' => $tab('Arhivirana', ['archived']),
            'sve' => Tab::make('Sve'),
        ];
    }
}
