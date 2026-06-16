<?php

namespace App\Filament\Livewire;

use App\Support\Settings as SettingsStore;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Botão sticky no fundo da sidebar + modal full-width das general settings.
 * O menu lateral do modal troca de categoria (sem fechar) e o formulário é
 * reconstruído por categoria; persiste via App\Support\Settings::general().
 *
 * @property-read Schema $form
 */
class SettingsModal extends Component implements HasForms
{
    use InteractsWithForms;

    /** @var array<string, mixed>|null Estado completo de TODAS as categorias. */
    public ?array $data = [];

    public string $activeCategory = 'general';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('manageSettings') ?? false, 403);

        // Enche o $data inteiro: ao gravar preservamos categorias não visíveis.
        $this->form->fill(SettingsStore::general()->all());
    }

    /** Categorias geridas por uma Resource embebida (CRUD), não por form de settings. */
    private const RESOURCE_CATEGORIES = ['permissions', 'groups'];

    /** @return array<string, string> chave => label do menu lateral */
    public function categories(): array
    {
        return [
            'general' => __('General'),
            'contact' => __('Contact'),
            'system' => __('System'),
            'languages' => __('Site languages'),
            'permissions' => __('Permissions'),
            'groups' => __('Groups'),
        ];
    }

    public function isManagedResource(): bool
    {
        return in_array($this->activeCategory, self::RESOURCE_CATEGORIES, true);
    }

    public function setCategory(string $key): void
    {
        if (array_key_exists($key, $this->categories())) {
            $this->activeCategory = $key;
        }
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components($this->isManagedResource() ? [] : $this->sectionsFor($this->activeCategory));
    }

    /**
     * Secções de form da categoria ativa (migradas da antiga página Settings).
     *
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    protected function sectionsFor(string $key): array
    {
        return match ($key) {
            'contact' => [
                Section::make(__('Contact'))->columns(2)->schema([
                    TextInput::make('contact_email')->label(__('Email'))->email()->required(),
                    TextInput::make('contact_phone')->label(__('Phone')),
                ]),
            ],
            'system' => [
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
            ],
            'languages' => [
                Section::make(__('Site languages'))
                    ->description(__('Defined in config/cms.php — routing needs them before any DB query.'))
                    ->schema([
                        Placeholder::make('locales')
                            ->label(__('Locales'))
                            ->content(implode(', ', config('cms.locales')).'  ·  default: '.config('cms.default_locale')),
                    ]),
            ],
            default => [
                Section::make(__('General'))->schema([
                    TextInput::make('site_name')->label(__('Site name'))->required(),
                ]),
            ],
        };
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->can('manageSettings') ?? false, 403);

        $this->form->validate();

        // $this->data tem o estado de todas as categorias (preenchido no mount).
        SettingsStore::general()->fill($this->data ?? []);

        Notification::make()->success()->title(__('Settings saved'))->send();
    }

    public function render(): View
    {
        return view('livewire.settings-modal');
    }
}
