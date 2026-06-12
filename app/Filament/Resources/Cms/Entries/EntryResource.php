<?php

namespace App\Filament\Resources\Cms\Entries;

use App\Cms\Admin\BlueprintFormBuilder;
use App\Filament\Resources\Cms\Entries\Pages\CreateEntry;
use App\Filament\Resources\Cms\Entries\Pages\EditEntry;
use App\Filament\Resources\Cms\Entries\Pages\ListEntries;
use App\Models\Cms\ContentType;
use App\Models\Cms\Entry;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * UM resource genérico serve TODOS os tipos de admin: o form é gerado do
 * blueprint do tipo em runtime de leitura (G7) — para o editor é
 * indistinguível de um model feito por dev.
 */
class EntryResource extends Resource
{
    protected static ?string $model = Entry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static ?string $navigationLabel = 'Conteúdos';

    protected static ?string $modelLabel = 'conteúdo';

    protected static ?string $pluralModelLabel = 'conteúdos';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->schema([
                Select::make('type_id')
                    ->label('Tipo')
                    ->options(fn () => ContentType::query()->where('promoted', false)->orderBy('name')->pluck('name', 'id'))
                    ->required()
                    ->live()
                    ->disabledOn('edit')
                    ->dehydrated(),
                TextInput::make('slug')->alphaDash(),
                Select::make('status')
                    ->options([
                        Entry::STATUS_DRAFT => 'Rascunho',
                        Entry::STATUS_PUBLISHED => 'Publicado',
                    ])
                    ->default(Entry::STATUS_DRAFT)
                    ->required(),
            ]),
            Section::make('Conteúdo')->schema([
                Group::make()
                    ->statePath('data')
                    ->schema(function (Get $get): array {
                        $type = ContentType::query()->find($get('type_id'));

                        return $type === null
                            ? []
                            : BlueprintFormBuilder::components($type->fields());
                    })
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type.name')->label('Tipo')->badge()->sortable(),
                TextColumn::make('title')
                    ->label('Conteúdo')
                    ->state(fn (Entry $record) => self::entryLabel($record)),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => $state === Entry::STATUS_PUBLISHED ? 'success' : 'gray'),
                TextColumn::make('updated_at')->label('Atualizado')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('type_id')
                    ->label('Tipo')
                    ->options(fn () => ContentType::query()->orderBy('name')->pluck('name', 'id')),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    /** Primeiro campo "listable" (ou o primeiro de todos) como título do entry. */
    private static function entryLabel(Entry $entry): string
    {
        $fields = $entry->type?->fields() ?? [];

        $candidates = array_values(array_filter($fields, fn (array $f) => ! empty($f['listable'])))
            ?: $fields;

        foreach ($candidates as $field) {
            $value = $entry->field($field['name']);

            if (is_string($value) && $value !== '') {
                return str($value)->stripTags()->limit(60)->toString();
            }
        }

        return $entry->slug ?? '#'.$entry->getKey();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEntries::route('/'),
            'create' => CreateEntry::route('/create'),
            'edit' => EditEntry::route('/{record}/edit'),
        ];
    }
}
