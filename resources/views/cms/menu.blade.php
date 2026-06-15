{{-- Render recursivo de itens de menu. $items = [{label, type, page_id|url, children}] --}}
@php($level = $level ?? 0)
@if (! empty($items))
    <ul @class([
        'flex gap-6' => $level === 0,
        'mt-2 ml-4 flex-col gap-1' => $level > 0,
        'flex',
    ])>
        @foreach ($items as $item)
            @php($url = \App\Support\Cms\CmsUrl::forItem($item))
            @if ($url !== null)
                <li>
                    <a href="{{ $url }}" class="text-slate-700 hover:text-indigo-600">{{ $item['label'] ?? '' }}</a>
                    @if (! empty($item['children']))
                        @include('cms.menu', ['items' => $item['children'], 'level' => $level + 1])
                    @endif
                </li>
            @endif
        @endforeach
    </ul>
@endif
