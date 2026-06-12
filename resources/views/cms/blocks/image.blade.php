<x-cms.block name="image" label="Imagem" icon="heroicon-o-camera">
    <figure class="mx-auto max-w-4xl px-6 py-8 [&_img]:w-full [&_img]:rounded-lg">
        <x-cms.field name="image" type="media" label="Imagem" required />
        <figcaption class="mt-2 text-center text-sm text-slate-500">
            <x-cms.field name="caption" type="text" label="Legenda" />
        </figcaption>
    </figure>
</x-cms.block>
