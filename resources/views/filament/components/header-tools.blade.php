<div class="flex items-center gap-x-2">
    @if (\Illuminate\Support\Facades\Route::has('filament.inventory.pages.asset-scanner') && auth()->user()?->hasPermissionTo('asset_scanner.use'))
        {{-- Shortcut ke Scanner Aset --}}
        <a
            href="{{ route('filament.inventory.pages.asset-scanner') }}"
            class="flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-primary-600 bg-primary-50 border border-primary-200/80 rounded-lg shadow-sm hover:bg-primary-100 hover:border-primary-300 dark:bg-gray-800 dark:text-primary-400 dark:border-gray-700 dark:hover:bg-gray-700 transition-all focus:outline-none"
            title="Scanner Aset"
        >
            <svg class="w-5 h-5 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4h4v4H4V4zm12 0h4v4h-4V4zM4 16h4v4H4v-4zm12 0h4v4h-4v-4zM4 10h16"/>
            </svg>
            <span>Scanner Aset</span>
        </a>
    @endif
</div>
