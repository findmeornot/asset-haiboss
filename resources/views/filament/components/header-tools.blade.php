<div class="flex items-center gap-x-2">
    @if (\Illuminate\Support\Facades\Route::has('filament.inventory.pages.asset-scanner') && auth()->user()?->hasPermissionTo('asset_scanner.use'))
        {{-- Shortcut ke Scanner Aset --}}
        <a
            href="{{ route('filament.inventory.pages.asset-scanner') }}"
            class="flex items-center gap-1.5 px-2.5 py-1.5 text-sm font-medium text-gray-700 hover:text-gray-900 hover:bg-gray-100 dark:text-gray-300 dark:hover:text-white dark:hover:bg-gray-800 rounded-lg transition-colors focus:outline-none"
            title="Scanner Aset"
        >
            <svg class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4h4v4H4V4zm12 0h4v4h-4V4zM4 16h4v4H4v-4zm12 0h4v4h-4v-4zM4 10h16"/>
            </svg>
            <span>Scanner Aset</span>
        </a>
    @endif

    {{-- Fullscreen Toggle Button --}}
    <button
        type="button"
        x-data="{ isFullscreen: false }"
        x-on:click="
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen();
                isFullscreen = true;
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                    isFullscreen = false;
                }
            }
        "
        class="p-2 text-gray-500 rounded-lg hover:text-gray-900 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-white dark:hover:bg-gray-700 transition-colors focus:outline-none"
        title="Toggle Fullscreen"
    >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-5h-4m4 0v4m0-4l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/>
        </svg>
    </button>
</div>
