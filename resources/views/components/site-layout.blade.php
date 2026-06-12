@props(['page', 'isPreview' => false])

<!DOCTYPE html>
<html lang="{{ $page->locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $page->seo_title ?? $page->name }}</title>
    @if ($page->seo_description)
        <meta name="description" content="{{ $page->seo_description }}">
    @endif
    @foreach ($page->translations()->where('status', 'published')->get() as $translation)
        <link rel="alternate" hreflang="{{ $translation->locale }}"
              href="{{ url('/'.$translation->locale.'/'.$translation->path()) }}">
    @endforeach
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-white text-slate-900 antialiased">
    @if ($isPreview)
        <div class="bg-amber-400 px-4 py-2 text-center text-sm font-semibold text-amber-950">
            Preview — estás a ver o rascunho. Esta versão não está publicada.
        </div>
    @endif

    <header class="border-b border-slate-200">
        <nav class="mx-auto flex max-w-5xl items-center gap-6 px-6 py-4">
            <a href="{{ url('/'.$page->locale) }}" class="font-bold">{{ config('app.name') }}</a>
            @foreach (app(\App\Services\Cms\PageTree::class)->tree($page->locale) as $node)
                @if ($node['page']->show_in_menu)
                    <a href="{{ url('/'.$page->locale.'/'.$node['page']->path()) }}"
                       class="text-sm text-slate-600 hover:text-slate-900">{{ $node['page']->name }}</a>
                @endif
            @endforeach
        </nav>
    </header>

    <main>
        {{ $slot }}
    </main>

    <footer class="mt-24 border-t border-slate-200 py-8 text-center text-sm text-slate-500">
        © {{ now()->year }} {{ config('app.name') }}
    </footer>
</body>
</html>
