<div
    x-data="{ open: false }"
    x-on:open-modal.window="if ($event.detail?.id === 'settings-modal') open = true"
    x-on:close-modal.window="if ($event.detail?.id === 'settings-modal') open = false"
    x-on:keydown.escape.window="open = false"
>
    {{-- O painel admin usa o CSS pré-compilado do Filament (sem viteTheme), por
         isso utilities Tailwind arbitrárias não existem aqui → layout via inline
         styles. wire:ignore.self impede o morph do Livewire de repor display:none. --}}
    <div
        x-show="open"
        wire:ignore.self
        x-transition.opacity
        style="display: none; position: fixed; inset: 0; z-index: 50;"
    >
        {{-- Backdrop (página toda) --}}
        <div style="position: absolute; inset: 0; background-color: rgba(17, 24, 39, 0.5);"></div>

        {{-- Painel: 40px de margem em todos os lados --}}
        <div style="position: absolute; inset: 40px; display: flex; overflow: hidden; border-radius: 0.75rem; background-color: #ffffff; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);">
            {{-- Aside: secções --}}
            <aside style="display: flex; flex-direction: column; width: 16rem; flex: none; overflow-y: auto; border-right: 1px solid #e5e7eb; background-color: #f9fafb; padding: 1rem;">
                <h2 style="padding: 0 0.75rem 0.75rem; font-size: 1rem; font-weight: 600; color: #030712;">
                    {{ __('Settings') }}
                </h2>

                @foreach ($this->categories() as $key => $label)
                    <button
                        type="button"
                        wire:click="setCategory('{{ $key }}')"
                        style="display: block; width: 100%; text-align: left; padding: 0.5rem 0.75rem; margin-bottom: 0.125rem; border: 0; cursor: pointer; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 500; {{ $activeCategory === $key ? 'background-color: #eff6ff; color: #2563eb;' : 'background: transparent; color: #374151;' }}"
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </aside>

            {{-- Conteúdo --}}
            <div style="display: flex; flex-direction: column; flex: 1 1 0%; min-width: 0;">
                <header style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #e5e7eb; padding: 1rem 1.5rem;">
                    <h3 style="font-size: 1.125rem; font-weight: 600; color: #030712;">
                        {{ $this->categories()[$activeCategory] ?? __('Settings') }}
                    </h3>

                    <button
                        type="button"
                        x-on:click="open = false"
                        aria-label="{{ __('Close') }}"
                        style="border: 0; background: transparent; cursor: pointer; padding: 0.25rem 0.5rem; border-radius: 9999px; font-size: 1.5rem; line-height: 1; color: #6b7280;"
                    >&times;</button>
                </header>

                <div style="flex: 1 1 0%; min-width: 0; overflow-y: auto; padding: 1.5rem;">
                    @if ($this->isManagedResource())
                        @php($manager = $activeCategory === 'permissions'
                            ? \App\Filament\Livewire\Settings\RolesManager::class
                            : \App\Filament\Livewire\Settings\GroupsManager::class)

                        @livewire($manager, [], key('settings-mgr-'.$activeCategory))
                    @else
                        <form wire:submit="save" wire:key="settings-cat-{{ $activeCategory }}">
                            {{ $this->form }}

                            <div style="margin-top: 1.5rem; display: flex; justify-content: flex-end;">
                                <x-filament::button type="submit">
                                    {{ __('Save') }}
                                </x-filament::button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
