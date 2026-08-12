@php
    $livewire ??= null;
    $renderHookScopes = $livewire?->getRenderHookScopes();
@endphp

<x-filament-panels::layout.base :livewire="$livewire">
    <style>
        /* Tighten vertical spacing inside simple login card */
        .fi-simple-main {
            padding: 1.5rem 1.75rem !important;
        }
        .fi-simple-main form {
            display: flex !important;
            flex-direction: column !important;
            gap: 1rem !important;
        }
        .fi-simple-main .fi-form-actions {
            margin-top: 0.25rem !important;
        }
        .fi-simple-main .fi-fo-component-ctn {
            gap: 1rem !important;
        }
    </style>

    <div class="fi-simple-layout flex min-h-dvh flex-col items-center justify-center bg-gray-50 dark:bg-gray-950 py-6 px-4">
        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIMPLE_LAYOUT_START, scopes: $renderHookScopes) }}

        @if (($hasTopbar ?? true) && filament()->auth()->check())
            <div class="fi-simple-layout-header">
                @if (filament()->hasDatabaseNotifications())
                    @livewire(Filament\Livewire\DatabaseNotifications::class, [
                        'lazy' => filament()->hasLazyLoadedDatabaseNotifications(),
                        'position' => \Filament\Enums\DatabaseNotificationsPosition::Topbar,
                    ])
                @endif

                @if (filament()->hasUserMenu())
                    @livewire(Filament\Livewire\SimpleUserMenu::class)
                @endif
            </div>
        @endif

        {{-- CONTAINER UTAMA HEADER + CARD --}}
        <div class="w-full max-w-md flex flex-col items-center">

            {{-- HEADER / BRANDING DI LUAR CARD --}}
            <div class="mb-4 text-center w-full flex flex-col items-center">
                <a href="/" class="inline-block transition hover:opacity-90">
                    <img src="{{ asset('logo.png') }}" alt="{{ config('app.name') }}" class="h-9 w-auto" />
                </a>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mt-1">
                    Sistem Manajemen Aset
                </p>
            </div>

            {{-- WHITE CARD CONTAINER --}}
            <main class="fi-simple-main w-full !my-0 !bg-white !shadow-lg !ring-1 !ring-gray-950/5 sm:!rounded-2xl dark:!bg-gray-900 dark:!ring-white/10">
                {{ $slot }}
            </main>

        </div>

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::FOOTER, scopes: $renderHookScopes) }}
        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIMPLE_LAYOUT_END, scopes: $renderHookScopes) }}
    </div>
</x-filament-panels::layout.base>
