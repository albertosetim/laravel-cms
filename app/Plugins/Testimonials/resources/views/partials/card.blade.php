<figure class="rounded-lg border border-slate-200 p-6">
    <blockquote class="text-slate-700">“{{ $item['quote'] ?? '' }}”</blockquote>
    <figcaption class="mt-3 text-sm text-slate-500">
        {{ $item['author'] ?? '' }}@if (! empty($item['company'])) · {{ $item['company'] }}@endif
    </figcaption>
</figure>
