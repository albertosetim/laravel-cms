<?php

namespace App\Http\Controllers\Cms;

use App\Cms\Render\CmsRenderContext;
use App\Http\Controllers\Controller;
use App\Models\Cms\Page;
use App\Services\Cms\PageTree;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PageController extends Controller
{
    public function __construct(private PageTree $tree)
    {
    }

    public function show(Request $request, string $locale, string $path = ''): View
    {
        abort_unless(in_array($locale, config('cms.locales'), true), 404);

        $path = trim($path, '/');
        $preview = $request->hasValidSignature() && $request->boolean('preview');

        $lookup = $preview
            ? $this->tree->pathLookupWithDrafts($locale)
            : $this->tree->pathLookup($locale);

        $pageId = $lookup[$path] ?? abort(404);

        $page = Page::with(['publishedRevision', 'parent'])->findOrFail($pageId);

        $revision = $preview
            ? ($page->draftRevision()->first() ?? $page->publishedRevision)
            : $page->publishedRevision;

        abort_if($revision === null, 404);

        app()->setLocale($locale);

        $context = app(CmsRenderContext::class);
        $context->setMode(CmsRenderContext::MODE_VIEW);
        $context->setPage($page);

        return view('templates.'.$page->template, [
            'page' => $page,
            'revision' => $revision,
            'isPreview' => $preview,
        ]);
    }
}
