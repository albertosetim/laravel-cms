<?php

namespace App\Filament\Pages;

use App\Support\Settings as SettingsStore;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;

/**
 * @property-read Schema $form
 */
class Settings extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.settings';

    public static function getNavigationGroup(): ?string
    {
        return __('System');
    }

    public static function getNavigationLabel(): string
    {
        return __('Settings');
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('Settings');
    }

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('manageSettings') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill(SettingsStore::general()->all());
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('General'))->schema([
                TextInput::make('site_name')->label(__('Site name'))->required(),
            ]),
            Section::make(__('Contact'))->columns(2)->schema([
                TextInput::make('contact_email')->label(__('Email'))->email()->required(),
                TextInput::make('contact_phone')->label(__('Phone')),
            ]),
            Section::make(__('System'))->columns(2)->schema([
                Select::make('timezone')
                    ->label(__('Timezone'))
                    ->options(array_combine(timezone_identifiers_list(), timezone_identifiers_list()))
                    ->searchable()
                    ->required(),
                Toggle::make('maintenance_mode')
                    ->label(__('Maintenance mode'))
                    ->helperText(__('When enabled, the public site shows a maintenance page to anonymous visitors.')),
            ]),
            Section::make(__('Site languages'))
                ->description(__('Defined in config/cms.php — routing needs them before any DB query.'))
                ->schema([
                    Placeholder::make('locales')
                        ->label(__('Locales'))
                        ->content(implode(', ', config('cms.locales')).'  ·  default: '.config('cms.default_locale')),
                ]),
        ]);
    }

    public function save(): void
    {
        SettingsStore::general()->fill($this->form->getState());

        Notification::make()->success()->title(__('Settings saved'))->send();
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('Save'))
                ->submit('save')
                ->keyBindings(['mod+s']),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make($this->getFormActions())
                        ->alignment(Alignment::Start)
                        ->key('form-actions'),
                ]),
        ]);
    }
}
