<?php

namespace App\Services\Cms;

use App\Models\Cms\Page;
use Illuminate\Support\Facades\Cache;

/**
 * Árvore de páginas + lookup de paths, cacheadas por locale. No cache vivem
 * SÓ arrays puros (nunca models serializados) — a resolução de um request é
 * um array access, não uma travessia (03-arvore).
 */
class PageTree
{
    /** @return array<string, int> path => page_id (páginas publicadas) */
    public function pathLookup(string $locale): array
    {
        return Cache::rememberForever(
            "cms.paths.{$locale}",
            fn () => $this->buildLookup($locale, publishedOnly: true),
        );
    }

    /** Lookup incluindo rascunhos — usada pelo preview assinado. */
    public function pathLookupWithDrafts(string $locale): array
    {
        return $this->buildLookup($locale, publishedOnly: false);
    }

    /**
     * Árvore aninhada (publicadas) para navegação/menus. Nós são arrays:
     * ['id', 'name', 'slug', 'path', 'show_in_menu', 'children' => [...]]
     */
    public function tree(string $locale): array
    {
        return Cache::rememberForever("cms.tree.{$locale}", function () use ($locale) {
            $pages = Page::query()
                ->where('locale', $locale)
                ->where('status', Page::STATUS_PUBLISHED)
                ->orderBy('position')
                ->get(['id', 'name', 'slug', 'parent_id', 'show_in_menu'])
                ->groupBy(fn (Page $page) => $page->parent_id ?? 0);

            return $this->nest($pages, 0, '');
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

        $byParent = $query
            ->get(['id', 'slug', 'parent_id'])
            ->groupBy(fn (Page $page) => $page->parent_id ?? 0);

        $lookup = [];
        $walk = function (int $parentId, string $prefix) use (&$walk, $byParent, &$lookup) {
            foreach ($byParent->get($parentId, collect()) as $page) {
                $path = ltrim($prefix.'/'.$page->slug, '/');

                if ($parentId === 0 && $page->slug === config('cms.home_slug')) {
                    $lookup[''] = $page->id;
                } else {
                    $lookup[$path] = $page->id;
                }

                $walk($page->id, $path);
            }
        };
        $walk(0, '');

        return $lookup;
    }

    private function nest($byParent, int $parentId, string $prefix): array
    {
        $nodes = [];

        foreach ($byParent->get($parentId, collect()) as $page) {
            $isHome = $parentId === 0 && $page->slug === config('cms.home_slug');
            $path = $isHome ? '' : ltrim($prefix.'/'.$page->slug, '/');

            $nodes[] = [
                'id' => $page->id,
                'name' => $page->name,
                'slug' => $page->slug,
                'path' => $path,
                'show_in_menu' => (bool) $page->show_in_menu,
                'children' => $this->nest($byParent, $page->id, $path),
            ];
        }

        return $nodes;
    }
}
