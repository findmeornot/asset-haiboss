<div class="text-center p-4 border rounded-lg bg-gray-50 dark:bg-gray-800">
    <div class="mb-4">
        <h3 class="font-bold text-lg">PT. Haiboss Asset Management</h3>
        <p class="text-sm text-gray-500">Label Preview</p>
    </div>
    
    <div class="inline-block p-4 bg-white border border-gray-300 rounded shadow-sm">
        <div class="font-bold text-black mb-2">{{ $record->name }}</div>
        <div class="mx-auto flex justify-center py-2">
            {!! app(\App\Services\BarcodeService::class)->generateSvg($record->barcode) !!}
        </div>
        <div class="mt-2 text-sm text-black font-mono">{{ $record->barcode }}</div>
    </div>
</div>
