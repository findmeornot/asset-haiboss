<x-filament::widget>
    <x-filament::card>
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 20px;">
            <div style="flex: 1;">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                    <h2 style="font-size: 1.5rem; font-weight: 700; margin: 0; color: inherit;">
                        Selamat Datang, {{ auth()->user()->name }}! 👋
                    </h2>
                    <span
                        style="background-color: #dcfce7; color: #15803d; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase;">
                        {{ auth()->user()->roles()->first()?->name ?? 'User' }}
                    </span>
                </div>

                <p style="color: #6b7280; font-size: 1rem; line-height: 1.5; margin-bottom: 0; max-width: 600px;">
                    Panel Inventaris siap digunakan. Pantau mutasi aset, jalankan stock opname, dan kelola lokasi
                    penyimpanan barang.
                </p>
            </div>

            <div style="flex-shrink: 0; display: none; @media (min-width: 768px) { display: block; }">
                <img src="/logo.png" style="width: 100px; height: auto;" alt="Logo">
            </div>
        </div>
    </x-filament::card>
</x-filament::widget>
