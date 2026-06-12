{{-- Item de repeater: recebe $item (valores) com o scope já no contexto. --}}
<details class="group py-4" x-data>
    <summary class="cursor-pointer list-none font-medium text-slate-900 group-open:text-indigo-600">
        {{ $item['question'] ?? '' }}
    </summary>
    <p class="mt-2 text-slate-600">{{ $item['answer'] ?? '' }}</p>
</details>
