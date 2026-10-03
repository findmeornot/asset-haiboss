@php
    $statePath = $getStatePath();
    $selectedCount = count($getState() ?? []);
    $totalCount = $assets->count();
@endphp

<div style="width: 100%; min-width: 0;">
    @if ($assets->isEmpty())
        <div class="text-sm text-danger-600">Tidak ada barang yang memiliki barcode pada ruangan ini.</div>
    @else
        <div class="mb-3 flex items-center justify-end gap-3">
            <button
                type="button"
                class="text-sm font-medium text-primary-600 hover:text-primary-500 focus:outline-none dark:text-primary-500 dark:hover:text-primary-400"
                wire:click="$set('{{ $statePath }}', {{ json_encode($assets->pluck('id')->map(fn($id) => (string) $id)->all()) }})"
            >
                Pilih Semua
            </button>
            <span class="text-gray-300 dark:text-gray-600">|</span>
            <button
                type="button"
                class="text-sm font-medium text-danger-600 hover:text-danger-500 focus:outline-none dark:text-danger-500 dark:hover:text-danger-400"
                wire:click="$set('{{ $statePath }}', [])"
            >
                Hapus Pilihan
            </button>
        </div>

        <div
            class="rounded-xl ring-1 ring-gray-950/10 dark:ring-white/10"
            style="max-height: 55vh; overflow-y: auto; overflow-x: hidden;"
        >
            <table class="text-sm" style="width: 100%; table-layout: fixed; border-collapse: collapse;">
                <colgroup>
                    <col style="width: 3rem;">
                    <col style="width: 28%;">
                    <col>
                    <col style="width: 24%;">
                    <col style="width: 4.5rem;">
                </colgroup>
                <thead>
                    <tr class="bg-gray-50 dark:bg-gray-800" style="position: sticky; top: 0; z-index: 1;">
                        <th class="px-3 py-2.5 text-start font-semibold text-gray-950 dark:text-white">No</th>
                        <th class="px-3 py-2.5 text-start font-semibold text-gray-950 dark:text-white">Kode Barang</th>
                        <th class="px-3 py-2.5 text-start font-semibold text-gray-950 dark:text-white">Nama Barang</th>
                        <th class="px-3 py-2.5 text-start font-semibold text-gray-950 dark:text-white">Merk / Type</th>
                        <th class="px-3 py-2.5 text-center font-semibold text-gray-950 dark:text-white">Cetak?</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($assets as $asset)
                        <tr
                            wire:key="print-asset-{{ $asset->id }}"
                            class="border-t border-gray-200 dark:border-white/10"
                        >
                            <td class="px-3 py-2 text-gray-500 dark:text-gray-400">{{ $loop->iteration }}</td>
                            <td class="px-3 py-2 font-mono text-xs text-gray-950 dark:text-white" style="word-break: break-all;">{{ $asset->inventory_number }}</td>
                            <td class="px-3 py-2 text-gray-950 dark:text-white" style="overflow-wrap: anywhere;">{{ $asset->name }}</td>
                            <td class="px-3 py-2 text-gray-600 dark:text-gray-300" style="overflow-wrap: anywhere;">{{ $asset->brand ?: '-' }}</td>
                            <td class="px-3 py-2 text-center">
                                <x-filament::input.checkbox
                                    value="{{ $asset->id }}"
                                    wire:model.live="{{ $statePath }}"
                                />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-3 flex items-center justify-between rounded-lg px-4 py-2.5 text-sm {{ $selectedCount > 0 ? 'bg-primary-50 text-primary-700 dark:bg-primary-400/10 dark:text-primary-300' : 'bg-danger-50 text-danger-700 dark:bg-danger-400/10 dark:text-danger-300' }}">
            <span>Total yang akan dicetak</span>
            <span class="font-bold">{{ $selectedCount }} dari {{ $totalCount }} barang</span>
        </div>
    @endif
</div>
