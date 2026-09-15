<div class="flex items-center h-full" x-data>
    <!-- Mobile: Always show full logo -->
    <img src="{{ asset('logo.png') }}" alt="Logo" class="h-full lg:hidden" />

    <!-- Desktop: Dynamic logo -->
    <img 
        src="{{ asset('logo.png') }}"
        x-bind:src="$store.sidebar.isOpen ? '{{ asset('logo.png') }}' : '{{ asset('logo-app.png') }}'" 
        alt="Logo" 
        class="h-full hidden lg:block" 
    />
</div>
