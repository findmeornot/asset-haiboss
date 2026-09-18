<div class="hidden lg:flex absolute top-8 -right-[12px] -translate-y-1/2" style="z-index: 999;">
    <button 
        x-on:click="$store.sidebar.isOpen ? $store.sidebar.close() : $store.sidebar.open()"
        class="flex items-center justify-center w-6 h-6 bg-white border border-gray-200 rounded-full cursor-pointer hover:bg-gray-50 text-gray-400 hover:text-gray-600 dark:bg-gray-900 dark:border-white/10 dark:hover:bg-gray-800 dark:text-gray-500 dark:hover:text-gray-300 focus:outline-none transition-colors"
    >
        <x-filament::icon
            icon="heroicon-m-chevron-left"
            class="w-3 h-3 transition-transform duration-300"
            x-bind:class="{ 'rotate-180': !$store.sidebar.isOpen }"
        />
    </button>
</div>
