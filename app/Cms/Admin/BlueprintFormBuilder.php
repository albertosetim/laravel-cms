<?php

namespace App\Cms\Admin;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;

/**
 * Traduz campos de blueprint (blocos x-cms e tipos de admin) em inputs
 * Filament. Construir um form dinamicamente é runtime de leitura, não
 * codegen (G1 intacto). Mapa único, usado pelo builder de páginas e pelos
 * forms de entries (07-backend).
 */
class BlueprintFormBuilder
{
    /** @param array<int, array> $fields @return array<int, mixed> */
    public static function components(array $fields): array
    {
        return array_map(fn (array $field) => self::component($field), $fields);
    }

    public static function component(array $field): mixed
    {
        $name = $field['name'];
        $label = $field['label'] ?? str($name)->headline()->toString();
        $required = (bool) ($field['required'] ?? false);

        $component = match ($field['type'] ?? 'text') {
            'textarea' => Textarea::make($name)->rows(3),
            'richtext' => RichEditor::make($name),
            'number' => TextInput::make($name)->numeric(),
            'boolean' => Toggle::make($name),
            'date' => DatePicker::make($name),
            'select' => Select::make($name)->options(self::selectOptions($field)),
            'media' => FileUpload::make($name)
                ->disk('public')
                ->directory('cms')
                ->image()
                ->imageEditor(),
            'link' => Fieldset::make($label)->schema([
                TextInput::make($name.'.label')->label('Texto'),
                TextInput::make($name.'.url')->label('URL externo')->url(),
                Select::make($name.'.page_id')
                    ->label('Página interna')
                    ->options(fn () => \App\Models\Cms\Page::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable(),
            ])->columns(3),
            'repeater' => Repeater::make($name)
                ->schema(self::components($field['fields'] ?? []))
                ->reorderable()
                ->collapsible()
                ->defaultItems(0),
            default => TextInput::make($name),
        };

        if (method_exists($component, 'label') && ($field['type'] ?? '') !== 'link') {
            $component->label($label);
        }

        if ($required && method_exists($component, 'required')) {
            $component->required();
        }

        if (isset($field['default']) && method_exists($component, 'default')) {
            $component->default($field['default']);
        }

        return $component;
    }

    private static function selectOptions(array $field): array
    {
        $options = $field['options'] ?? [];

        // Lista simples ['a','b'] → ['a' => 'a', 'b' => 'b'].
        return array_is_list($options) ? array_combine($options, $options) : $options;
    }
}
