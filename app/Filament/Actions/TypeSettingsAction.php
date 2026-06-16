<?php

namespace App\Filament\Actions;

use App\Support\Settings;
use App\Support\SettingsBag;
use Filament\Actions\Action;
use Filament\Forms\Components\KeyValue;
use Filament\Notifications\Notification;

/**
 * Ação de cabeçalho da listagem que edita as settings do tipo de conteúdo
 * (um bucket por tipo, ex. todos os Blog). Editor genérico chave/valor.
 *
 *   TypeSettingsAction::make()->settingsModel(static::getResource()::getModel())
 */
class TypeSettingsAction extends Action
{
    /** @var class-string */
    protected string $settingsModel;

    public static function getDefaultName(): ?string
    {
        return 'settings';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('Settings'))
            ->icon('heroicon-o-cog-6-tooth')
            ->modalHeading(__('Type settings'))
            ->modalSubmitActionLabel(__('Save'))
            ->visible(fn (): bool => auth()->user()?->can('manageSettings') ?? false)
            ->fillForm(fn (): array => ['settings' => $this->bag()->all()])
            ->schema([
                KeyValue::make('settings')
                    ->label('')
                    ->keyLabel(__('Key'))
                    ->valueLabel(__('Value'))
                    ->addActionLabel(__('Add setting')),
            ])
            ->action(function (array $data): void {
                $this->bag()->replace((array) ($data['settings'] ?? []));

                Notification::make()->success()->title(__('Settings saved'))->send();
            });
    }

    /**
     * @param  class-string  $modelClass
     */
    public function settingsModel(string $modelClass): static
    {
        $this->settingsModel = $modelClass;

        return $this;
    }

    protected function bag(): SettingsBag
    {
        return Settings::for($this->settingsModel);
    }
}
