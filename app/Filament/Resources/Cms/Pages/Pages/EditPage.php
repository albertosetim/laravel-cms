<?php

namespace App\Filament\Resources\Cms\Pages\Pages;

use App\Filament\Resources\Cms\Pages\PageResource;
use App\Filament\Resources\Cms\Pages\Pages\Concerns\HandlesPageBlocks;
use App\Models\Cms\Page;
use App\Services\Cms\PagePublisher;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;

class EditPage extends EditRecord
{
    use HandlesPageBlocks;

    protected static string $resource = PageResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Page $page */
        $page = $this->getRecord();
        $draft = $page->draftRevision()->first() ?? $page->publishedRevision;

        $data['blocks'] = $this->blocksToBuilderState($draft?->blockInstances() ?? []);

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $blocks = $this->builderStateToBlocks($data['blocks'] ?? []);
        unset($data['blocks']);

        $record->update($data);

        app(PagePublisher::class)->saveDraft($record, ['blocks' => $blocks], auth()->id());

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label(__('Preview'))
                ->icon('heroicon-o-eye')
                ->url(fn (Page $record) => URL::temporarySignedRoute('cms.page', now()->addMinutes(30), [
                    'locale' => $record->locale,
                    'path' => $record->path(),
                    'preview' => 1,
                ]))
                ->openUrlInNewTab(),

            Action::make('publish')
                ->label(__('Publish'))
                ->icon('heroicon-o-rocket-launch')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (Page $record) => auth()->user()->can('publish', $record))
                ->action(function (Page $record) {
                    // Garante que o que está no form vai junto na publicação.
                    $this->save(shouldRedirect: false);

                    app(PagePublisher::class)->publish($record->refresh(), auth()->id());

                    Notification::make()->title(__('Page published.'))->success()->send();
                }),

            Action::make('unpublish')
                ->label(__('Unpublish'))
                ->icon('heroicon-o-eye-slash')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn (Page $record) => $record->isPublished() && auth()->user()->can('publish', $record))
                ->action(function (Page $record) {
                    app(PagePublisher::class)->unpublish($record);

                    Notification::make()->title(__('Page unpublished.'))->warning()->send();
                }),

            DeleteAction::make(),
        ];
    }
}
