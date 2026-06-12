<x-cms.block name="wall" label="Mural de testemunhos" icon="heroicon-o-squares-2x2">
    <section class="mx-auto grid max-w-5xl gap-6 px-6 py-12 sm:grid-cols-2">
        <x-cms.repeater
            name="testimonials"
            label="Testemunhos"
            :fields="[
                ['name' => 'quote', 'type' => 'textarea', 'label' => 'Citação', 'required' => true],
                ['name' => 'author', 'type' => 'text', 'label' => 'Autor', 'required' => true],
                ['name' => 'company', 'type' => 'text', 'label' => 'Empresa'],
            ]"
            item-view="testimonials::partials.card"
        />
    </section>
</x-cms.block>
