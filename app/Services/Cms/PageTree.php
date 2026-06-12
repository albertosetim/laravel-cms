<?php

namespace App\Services\Cms;

use App\Models\Cms\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Árvore de páginas em memória + lookup de paths, ambas cacheadas por locale.
 * A resolução de um request é um array access, não uma travessia (03-arvore).
 */
class PageTree
{
    /** @return array<string, int> path => page_id (páginas publicadas) */
    public function pathLookup(string $locale): array
    {
        return Cache::rememberForever("cms.paths.{$locale}", function () use ($locale) {
            return $this->buildLookup($locale, publishedOnly: true);
        });
    }

    /** Lookup incluindo rascunhos — usada pelo preview assinado. */
    public function pathLookupWithDrafts(string $locale): array
    {
        return $this->buildLookup($locale, publishedOnly: false);
    }

    /**
     * Árvore aninhada do locale para navegação/menus:
     * [['page' => Page, 'children' => [...]], ...]
     */
    public function tree(string $locale): array
    {
        return Cache::rememberForever("cms.tree.{$locale}", function () use ($locale) {
            $pages = Page::query()
                ->where('locale', $locale)
                ->where('status', Page::STATUS_PUBLISHED)
                ->orderBy('parent_id')
                ->orderBy('position')
                ->get();

            return $this->nest($pages, null);
        });
    }

    public function flush(): void
    {
        foreach (config('cms.locales') as $locale) {
            Cache::forget("cms.tree.{$locale}");
            Cache::forget("cms.paths.{$locale}");
        }
    }

    private function buildLookup(string $locale, bool $publishedOnly): array
    {
        $query = Page::query()->where('locale', $locale);

        if ($publishedOnly) {
            $query->where('status', Page::STATUS_PUBLISHED);
        }

        $pages = $query->get(['id', 'slug', 'parent_id']);
        $byParent = $pages->groupBy('parent_id');

        $lookup = [];
        $walk = function (?int $parentId, string $prefix) use (&$walk, $byParent, &$lookup) {
            foreach ($byParent->get($parentId ?? '', collect()) as $page) {
                $path = ltrim($prefix.'/'.$page->slug, '/');

                if ($parentId === null && $page->slug === config('cms.home_slug')) {
                    $lookup[''] = $page->id;
                } else {
                    $lookup[$path] = $page->id;
                }

                $walk($page->id, $path);
            }
        };
        $walk(null, '');

        return $lookup;
    }

    private function nest(Collection $pages, ?int $parentId): array
    {
        return $pages
            ->where('parent_id', $parentId)
            ->map(fn (Page $page) => [
                'page' => $page,
                'children' => $this->nest($pages, $page->id),
            ])
            ->values()
            ->all();
    }
}
