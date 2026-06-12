<x-cms.block name="quote" label="Citação" icon="heroicon-o-chat-bubble-left-right">
    <blockquote class="mx-auto max-w-2xl px-6 py-12 text-center">
        <p class="text-xl font-medium italic text-slate-700">
            “<x-cms.field name="quote" type="textarea" label="Citação" required />”
        </p>
        <footer class="mt-4 text-sm text-slate-500">
            — <x-cms.field name="author" type="text" label="Autor" required />
        </footer>
    </blockquote>
</x-cms.block>
