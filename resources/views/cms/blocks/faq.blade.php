<x-cms.block name="faq" label="FAQ" icon="heroicon-o-question-mark-circle">
    <section class="mx-auto max-w-3xl px-6 py-12">
        <h2 class="text-2xl font-bold">
            <x-cms.field name="heading" type="text" label="Título da secção" default="FAQ" />
        </h2>
        <div class="mt-6 divide-y divide-slate-200">
            <x-cms.repeater
                name="items"
                label="Perguntas"
                :fields="[
                    ['name' => 'question', 'type' => 'text', 'label' => 'Pergunta', 'required' => true],
                    ['name' => 'answer', 'type' => 'textarea', 'label' => 'Resposta', 'required' => true],
                ]"
                item-view="cms.blocks.partials.faq-item"
            />
        </div>
    </section>
</x-cms.block>
