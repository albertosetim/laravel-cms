<?php

namespace App\Filament\Pages;

use App\Support\Locales;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

/**
 * Perfil self-service: além de nome/email/password, deixa o utilizador escolher
 * o idioma do painel. O valor persiste em users.locale (fillable) e é aplicado
 * pelo middleware SetPanelLocale.
 */
class EditProfile extends BaseEditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getNameFormComponent(),
            $this->getEmailFormComponent(),
            Select::make('locale')
                ->label(__('Panel language'))
                ->options(Locales::options())
                ->placeholder(__('Site default').' ('.Locales::default().')')
                ->native(false),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
            $this->getCurrentPasswordFormComponent(),
        ]);
    }
}
