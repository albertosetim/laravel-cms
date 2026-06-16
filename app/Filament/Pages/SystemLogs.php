<?php

namespace App\Filament\Pages;

use App\Support\LogReader;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\Activitylog\Models\Activity;

class SystemLogs extends Page implements HasTable
{
    use InteractsWithTable;

    /** Máximo de registos de atividade carregados para a vista unificada. */
    private const ACTIVITY_CAP = 1000;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bug-ant';

    protected static ?int $navigationSort = 99;

    protected string $view = 'filament.pages.system-logs';

    public static function getNavigationGroup(): ?string
    {
        return __('System');
    }

    public static function getNavigationLabel(): string
    {
        return __('Logs');
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('Logs');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewSystemLogs') ?? false;
    }

    public function table(Table $table): Table
    {
        $reader = LogReader::make();

        return $table
            ->records(function (?array $filters, ?string $search, array $sort, int $page, int $recordsPerPage) use ($reader): Paginator {
                $source = $filters['source']['value'] ?? null;

                $rows = collect();

                if ($source !== 'system') {
                    foreach (Activity::query()->latest()->limit(self::ACTIVITY_CAP)->get() as $activity) {
                        $rows->push([
                            '__key' => 'a-'.$activity->getKey(),
                            'source' => 'activity',
                            'sort_ts' => $activity->created_at?->getTimestamp() ?? 0,
                            'datetime' => $activity->created_at?->format('Y-m-d H:i:s') ?? '—',
                            'level' => null,
                            'tag' => $activity->event ?: ($activity->log_name ?: 'log'),
                            'message' => (string) $activity->description,
                            'author' => $activity->causer?->name ?? '—',
                            'activity_id' => $activity->getKey(),
                        ]);
                    }
                }

                if ($source !== 'activity') {
                    $file = $filters['file']['value'] ?? $reader->defaultFile();

                    foreach ($reader->entries($file) as $entry) {
                        $datetime = str_replace('T', ' ', substr($entry['datetime'], 0, 19));

                        $rows->push([
                            '__key' => 's-'.$entry['__key'],
                            'source' => 'system',
                            'sort_ts' => strtotime($datetime) ?: 0,
                            'datetime' => $datetime,
                            'level' => $entry['level'],
                            'tag' => $entry['level'],
                            'message' => $entry['message'],
                            'author' => '—',
                            'detail' => $entry['full'],
                        ]);
                    }
                }

                if ($level = $filters['level']['value'] ?? null) {
                    $rows = $rows->where('level', $level);
                }

                if (filled($search)) {
                    $needle = mb_strtolower($search);
                    $rows = $rows->filter(fn (array $row): bool => str_contains(
                        mb_strtolower($row['message'].' '.$row['tag'].' '.$row['author']),
                        $needle
                    ));
                }

                $rows = (($sort[1] ?? 'desc') === 'asc')
                    ? $rows->sortBy('sort_ts')
                    : $rows->sortByDesc('sort_ts');

                $rows = $rows->values();

                return new LengthAwarePaginator(
                    $rows->forPage($page, $recordsPerPage)->all(),
                    $rows->count(),
                    $recordsPerPage,
                    $page,
                );
            })
            ->columns([
                TextColumn::make('datetime')
                    ->label(__('When'))
                    ->sortable(),
                TextColumn::make('source')
                    ->label(__('Source'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'activity' ? __('Activity') : __('System'))
                    ->color(fn (string $state): string => $state === 'activity' ? 'info' : 'gray'),
                TextColumn::make('tag')
                    ->label(__('Type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => strtoupper($state))
                    ->color(fn (string $state, array $record): string => $record['source'] === 'system'
                        ? self::levelColor($state)
                        : 'primary'),
                TextColumn::make('message')
                    ->label(__('Description'))
                    ->wrap()
                    ->limit(180),
                TextColumn::make('author')
                    ->label(__('Author'))
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('source')
                    ->label(__('Source'))
                    ->options([
                        'activity' => __('Activity'),
                        'system' => __('System'),
                    ]),
                SelectFilter::make('level')
                    ->label(__('Level (system)'))
                    ->options([
                        'emergency' => 'Emergency',
                        'alert' => 'Alert',
                        'critical' => 'Critical',
                        'error' => 'Error',
                        'warning' => 'Warning',
                        'notice' => 'Notice',
                        'info' => 'Info',
                        'debug' => 'Debug',
                    ]),
                SelectFilter::make('file')
                    ->label(__('File (system)'))
                    ->options(fn (): array => $reader->files()->pluck('name', 'name')->all())
                    ->default($reader->defaultFile())
                    ->selectablePlaceholder(false),
            ])
            ->defaultSort('datetime', 'desc')
            ->recordActions([
                Action::make('view')
                    ->label(__('View'))
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (array $record): string => ($record['source'] === 'activity' ? __('Activity') : __('System')).' — '.$record['datetime'])
                    ->modalContent(fn (array $record): View => $this->detailFor($record))
                    ->modalWidth(Width::FiveExtraLarge)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('Close')),
            ])
            ->emptyStateHeading(__('No entries'))
            ->emptyStateDescription(__('There are no records for the selected filters.'))
            ->emptyStateIcon('heroicon-o-document-magnifying-glass');
    }

    private function detailFor(array $record): View
    {
        if ($record['source'] === 'system') {
            return $this->code($record['detail'] ?? '', strtoupper($record['tag']).' · '.$record['datetime']);
        }

        $activity = Activity::query()->find($record['activity_id'] ?? null);

        if (! $activity) {
            return $this->code($record['message'], __('Activity').' · '.$record['datetime']);
        }

        $subject = $activity->subject_type
            ? class_basename($activity->subject_type).($activity->subject_id ? " #{$activity->subject_id}" : '')
            : '—';

        $properties = json_encode(
            $activity->properties,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        $lines = [
            __('Channel').':     '.($activity->log_name ?: '—'),
            __('Event').':    '.($activity->event ?: '—'),
            __('Object').':    '.$subject,
            __('Author').':     '.($activity->causer?->name ?? '—'),
            __('Description').': '.$activity->description,
            '',
            __('Changes').':',
            $properties,
        ];

        return $this->code(implode("\n", $lines), __('Activity').' · '.$record['datetime']);
    }

    private function code(string $content, string $title): View
    {
        return view('filament.pages.partials.log-code', [
            'content' => $content,
            'title' => $title,
        ]);
    }

    private static function levelColor(string $level): string
    {
        return match ($level) {
            'emergency', 'alert', 'critical', 'error' => 'danger',
            'warning' => 'warning',
            'notice', 'info' => 'info',
            default => 'gray',
        };
    }
}
