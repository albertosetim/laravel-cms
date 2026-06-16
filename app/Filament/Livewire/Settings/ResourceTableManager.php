<?php

namespace App\Filament\Livewire\Settings;

use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

/**
 * Gere uma resource (lista + CRUD em sub-modais) embebida no modal de Settings.
 * Reutiliza o `table()` e o `form()` da própria Resource — sem duplicar schema.
 * O acesso é gated ao nível do modal de Settings (manageSettings); aqui só se
 * confirma que é admin/developer.
 */
abstract class ResourceTableManager extends Component implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    /** FQCN da Filament Resource a reutilizar (table + form). */
    abstract protected function resource(): string;

    /** FQCN do model gerido. */
    abstract protected function model(): string;

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['admin', 'developer']) ?? false, 403);
    }

    public function table(Table $table): Table
    {
        $resource = $this->resource();

        return $resource::table($table)
            ->query(fn (): Builder => $this->model()::query())
            ->headerActions([
                CreateAction::make()
                    ->schema(fn (Schema $schema): Schema => $resource::form($schema)),
            ])
            ->recordActions([
                EditAction::make()
                    ->schema(fn (Schema $schema): Schema => $resource::form($schema)),
                DeleteAction::make(),
            ]);
    }

    public function render(): View
    {
        return view('livewire.settings.table-manager');
    }
}
