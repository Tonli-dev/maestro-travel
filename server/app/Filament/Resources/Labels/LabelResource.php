<?php

namespace App\Filament\Resources\Labels;

use App\Filament\Resources\Labels\Pages\ManageLabels;
use App\Models\Label;
use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LabelResource extends Resource
{
    protected static ?string $model = Label::class;

    protected static ?string $modelLabel = 'oznaka';

    protected static ?string $pluralModelLabel = 'Oznake';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('kind')->label('Skupina')->options(Label::GROUPS)->default('category')
                ->required()->live()->disabledOn('edit'),
            TextInput::make('name')->label('Naziv')->required()->maxLength(120)
                ->rules([fn (?Label $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                    if ($record && Label::where('kind', $record->kind)->whereKeyNot($record->id)
                        ->where('name_key', Label::nameKey($value))->exists()) {
                        $fail('Oznaka s ovim nazivom već postoji u ovoj skupini.');
                    }
                }]),
            Toggle::make('featured')->label('Izdvojena kategorija')->visible(fn (Get $get): bool => $get('kind') === 'category'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('name')->columns([
            TextColumn::make('name')->label('Naziv')->searchable(),
            TextColumn::make('kind')->label('Skupina')->formatStateUsing(fn (string $state): string => Label::GROUPS[$state]),
            IconColumn::make('featured')->label('Izdvojena')->boolean()
                ->tooltip(fn (Label $record): string => match (true) {
                    $record->kind !== 'category' => 'Samo kategorije mogu biti izdvojene.',
                    $record->featured => 'Kliknite da kategorija više ne bude izdvojena.',
                    default => 'Kliknite da izdvojite kategoriju kao prečac na naslovnici.',
                })
                ->action(function (Label $record): void {
                    if ($record->kind === 'category') {
                        $record->update(['featured' => ! $record->featured]);
                    }
                })
                ->disabledClick(fn (Label $record): bool => $record->kind !== 'category')
                ->extraAttributes(fn (Label $record): array => $record->kind === 'category' ? [] : ['style' => 'opacity:.4', 'aria-disabled' => 'true'])
                ->alignCenter(),
        ])->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageLabels::route('/')];
    }
}
