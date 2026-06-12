<?php

namespace App\Filament\Resources\Cms\Pages\Schemas;

use App\Cms\Admin\BlueprintFormBuilder;
use App\Cms\Blocks\BlockRegistry;
use App\Models\Cms\Page;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make()->columnSpanFull()->tabs([
                Tab::make('Conteúdo')->schema([
                    // O documento de blocos da DRAFT revision — hidratado e
                    // persistido pelas páginas Create/Edit, nunca coluna da Page.
                    Builder::make('blocks')
                        ->label('Blocos')
                        ->blocks(self::builderBlocks())
                        ->reorderable()
                        ->collapsible()
                        ->blockNumbers(false)
                        ->dehydrated()
                        ->columnSpanFull(),
                ]),
                Tab::make('Página')->schema([
                    Section::make()->columns(2)->schema([
                        TextInput::make('name')->label('Nome interno')->required(),
                        TextInput::make('slug')
                            ->required()
                            ->alphaDash()
                            ->rules([
                                fn () => Rule::notIn(config('cms.reserved_slugs')),
                            ])
                            ->unique(
                                ignoreRecord: true,
                                modifyRuleUsing: fn ($rule, Get $get) => $rule
                                    ->where('parent_id', $get('parent_id'))
                                    ->where('locale', $get('locale')),
                            )
                            ->helperText('Único entre irmãos do mesmo parent e locale.'),
                        Select::make('template')
                            ->options(self::templates())
                            ->default('default')
                            ->required(),
                        Select::make('locale')
                            ->options(array_combine(config('cms.locales'), config('cms.locales')))
                            ->default(config('cms.default_locale'))
                            ->required(),
                        Select::make('parent_id')
                            ->label('Página mãe')
                            ->options(fn (?Page $record) => Page::query()
                                ->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))
                                ->orderBy('name')
                                ->pluck('name', 'id'))
                            ->searchable()
                            ->placeholder('— raiz —'),
                        Toggle::make('show_in_menu')->label('Mostrar no menu')->default(true)->inline(false),
                    ]),
                ]),
                Tab::make('SEO')->schema([
                    TextInput::make('seo_title')->label('Title'),
                    Textarea::make('seo_description')->label('Description')->rows(3),
                ]),
            ]),
        ]);
    }

    /** Paleta a partir do manifesto committed (G5) — nunca scan por request. */
    private static function builderBlocks(): array
    {
        return collect(app(BlockRegistry::class)->blocks())
            ->map(fn (array $definition, string $key) => Builder\Block::make($key)
                ->label($definition['label'] ?? $key)
                ->schema(BlueprintFormBuilder::components($definition['fields'] ?? [])))
            ->values()
            ->all();
    }

    private static function templates(): array
    {
        return collect(File::files(resource_path('views/templates')))
            ->map(fn ($f) => str_replace('.blade.php', '', $f->getFilename()))
            ->mapWithKeys(fn (string $name) => [$name => str($name)->headline()->toString()])
            ->all();
    }
}
