<x-filament-panels::page>
    <div class="mb-4 text-gray-600 dark:text-gray-400">
        Pilih jenis laporan dan filter yang diinginkan, kemudian klik tombol Export untuk mengunduh laporan dalam format Excel. Laporan akan menyesuaikan dengan hak akses yang Anda miliki.
    </div>

    <form wire:submit="submit">
        {{ $this->form }}

        <div class="mt-4 flex gap-4">
            @foreach($this->getFormActions() as $action)
                {{ $action }}
            @endforeach
        </div>
    </form>
</x-filament-panels::page>
