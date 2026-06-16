{{-- Botão no rodapé da sidebar; dispara o overlay (renderizado no <body> end).
     Inline styles porque o painel usa o CSS pré-compilado do Filament. --}}
<div x-data="{}" style="border-top: 1px solid #e5e7eb; padding: 0.5rem;">
    <button
        type="button"
        x-on:click="$dispatch('open-modal', { id: 'settings-modal' })"
        style="display: flex; align-items: center; gap: 0.75rem; width: 100%; padding: 0.5rem 0.75rem; border: 0; background: transparent; cursor: pointer; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 500; color: #374151;"
    >
        <x-filament::icon icon="heroicon-o-cog-6-tooth" style="width: 1.5rem; height: 1.5rem; color: #9ca3af;" />
        <span>{{ __('Settings') }}</span>
    </button>
</div>
