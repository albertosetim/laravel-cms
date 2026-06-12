<?php

namespace App\Services\Cms;

use App\Models\Cms\Page;
use App\Models\Cms\PageRevision;
use Illuminate\Support\Facades\DB;

class PagePublisher
{
    public function __construct(private PageTree $tree)
    {
    }

    /**
     * Publica a draft corrente: snapshot imutável + ponteiro (03-arvore).
     */
    public function publish(Page $page, ?int $userId = null): PageRevision
    {
        return DB::transaction(function () use ($page, $userId) {
            $draft = $page->draftRevision()->first();

            $snapshot = PageRevision::create([
                'page_id' => $page->id,
                'data' => $draft?->data ?? ['blocks' => []],
                'is_draft' => false,
                'created_by' => $userId,
            ]);

            $page->forceFill([
                'published_revision_id' => $snapshot->id,
                'status' => Page::STATUS_PUBLISHED,
            ])->save();

            $this->prune($page);
            $this->tree->flush();

            return $snapshot;
        });
    }

    public function unpublish(Page $page): void
    {
        $page->forceFill(['status' => Page::STATUS_DRAFT])->save();
        $this->tree->flush();
    }

    /** Rollback = apontar o ponteiro para uma revision anterior. */
    public function rollback(Page $page, PageRevision $revision): void
    {
        abort_unless($revision->page_id === $page->id, 404);

        $page->forceFill([
            'published_revision_id' => $revision->id,
            'status' => Page::STATUS_PUBLISHED,
        ])->save();

        $this->tree->flush();
    }

    /** Grava conteúdo na draft corrente (cria-a se não existir). */
    public function saveDraft(Page $page, array $data, ?int $userId = null): PageRevision
    {
        $draft = $page->draftRevision()->first();

        if ($draft === null) {
            return PageRevision::create([
                'page_id' => $page->id,
                'data' => $data,
                'is_draft' => true,
                'created_by' => $userId,
            ]);
        }

        $draft->update(['data' => $data, 'created_by' => $userId]);

        return $draft;
    }

    private function prune(Page $page): void
    {
        $keep = (int) config('cms.revisions.keep', 20);

        $page->revisions()
            ->where('is_draft', false)
            ->whereKeyNot($page->published_revision_id)
            ->orderByDesc('created_at')
            ->skip($keep)
            ->take(PHP_INT_MAX)
            ->get()
            ->each
            ->delete();
    }
}
