<?php

namespace App\Support\Cms;

use App\Models\Cms\Page;

/**
 * Resolução de URLs do CMS num único sítio: páginas internas (por id, para
 * sobreviver a renames de slug) e links de itens de menu / campos link.
 */
class CmsUrl
{
    public static function forPage(Page $page): string
    {
        $path = $page->path();

        return url('/'.$page->locale.($path !== '' ? '/'.$path : ''));
    }

    /**
     * URL de um item (menu ou campo link): {type|page_id} → URL.
     * Devolve null se o destino não resolve (ex.: página apagada).
     */
    public static function forItem(array $item): ?string
    {
        if (! empty($item['page_id'])) {
            $page = Page::find($item['page_id']);

            return $page ? self::forPage($page) : null;
        }

        $url = $item['url'] ?? null;

        return is_string($url) && $url !== '' ? $url : null;
    }
}
