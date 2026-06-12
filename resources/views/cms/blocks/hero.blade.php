<x-cms.block name="hero" label="Hero" icon="heroicon-o-photo">
    <section class="relative bg-slate-900 text-white">
        <div class="mx-auto max-w-5xl px-6 py-24 text-center">
            <h1 class="text-4xl font-bold tracking-tight sm:text-6xl">
                <x-cms.field name="title" type="text" label="Título" required />
            </h1>
            <p class="mt-6 text-lg text-slate-300">
                <x-cms.field name="subtitle" type="text" label="Subtítulo" />
            </p>
            <div class="mt-10 [&_a]:inline-block [&_a]:rounded-md [&_a]:bg-indigo-500 [&_a]:px-5 [&_a]:py-3 [&_a]:font-semibold hover:[&_a]:bg-indigo-400">
                <x-cms.field name="cta" type="link" label="Call to action" />
            </div>
        </div>
    </section>
</x-cms.block>
