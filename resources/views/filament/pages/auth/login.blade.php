<div class="fi-simple-page flex flex-col items-center w-full">
    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIMPLE_PAGE_START, scopes: $this->getRenderHookScopes()) }}

    <div class="w-full">
        {{ $this->content }}
    </div>

    @if ($this->getSubheading())
        <div class="fi-simple-page-subheading mt-4 text-sm text-center text-gray-500 dark:text-gray-400">
            {!! $this->getSubheading() !!}
        </div>
    @endif

    <x-filament-actions::modals />

    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIMPLE_PAGE_END, scopes: $this->getRenderHookScopes()) }}
</div>
