<x-filament-panels::page>
    <!-- Header with Search & Filter Placeholders -->
    <div class="flex flex-col md:flex-row gap-4 mb-4 items-center justify-between">
        <div class="w-full md:w-1/3">
            <x-filament::input.wrapper>
                <x-slot name="prefix">
                    <x-filament::icon
                        icon="heroicon-m-magnifying-glass"
                        class="w-5 h-5 text-gray-400"
                    />
                </x-slot>
                
                <x-filament::input
                    type="text"
                    disabled
                    placeholder="Cari nomor transaksi atau pembeli (Segera Hadir)"
                />
            </x-filament::input.wrapper>
        </div>
        
        <div class="flex gap-2 w-full md:w-auto">
            <x-filament::button color="gray" icon="heroicon-m-funnel" disabled>
                Filter
            </x-filament::button>
            <x-filament::button icon="heroicon-m-plus" disabled>
                Buat Transaksi
            </x-filament::button>
        </div>
    </div>

    <!-- Data Table Skeleton -->
    <x-filament::section>
        <div class="overflow-x-auto -mx-6 -mt-6 -mb-6">
            <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-800/50 dark:text-gray-400">
                    <tr>
                        <th scope="col" class="px-6 py-4 font-medium">Nomor Transaksi</th>
                        <th scope="col" class="px-6 py-4 font-medium">Tanggal</th>
                        <th scope="col" class="px-6 py-4 font-medium">Pembeli</th>
                        <th scope="col" class="px-6 py-4 font-medium text-right">Total</th>
                        <th scope="col" class="px-6 py-4 font-medium text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="bg-white border-b dark:bg-gray-900/50 dark:border-white/5">
                        <td colspan="5" class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center justify-center">
                                <x-filament::icon
                                    icon="heroicon-o-banknotes"
                                    class="w-12 h-12 text-gray-300 dark:text-gray-600 mb-4"
                                />
                                
                                <h2 class="text-lg font-semibold text-gray-700 dark:text-gray-200">
                                    Belum Ada Data Transaksi
                                </h2>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 max-w-md mx-auto">
                                    Modul transaksi penjualan sedang dalam tahap pengembangan (API eksternal). 
                                    Data transaksi penjualan akan ditampilkan di sini setelah integrasi selesai.
                                </p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
