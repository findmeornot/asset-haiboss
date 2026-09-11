<div class="text-center p-4 border rounded-lg bg-gray-50 dark:bg-gray-800 flex justify-center flex-col items-center">
    <div class="mb-4">
        <h3 class="font-bold text-lg">Label Preview</h3>
        <p class="text-sm text-gray-500">Ukuran fisik: 50 &times; 25 mm</p>
    </div>
    <div class="label-card bg-white" style="width: 50mm; height: 25mm; border: 1px solid #000; box-sizing: border-box; position: relative; overflow: hidden; padding: 1mm; display: flex; flex-direction: column; color: #000; font-family: Arial, sans-serif; text-align: left;">
        <div class="watermark-left" style="position: absolute; left: 1mm; top: 0; bottom: 0; font-size: 4.5pt; font-weight: bold; color: #777; opacity: 0.35; display: flex; align-items: center; justify-content: center; z-index: 1; white-space: nowrap; writing-mode: vertical-lr; transform: rotate(180deg);">UNIVERSITAS STEKOM</div>
        <div class="watermark-right" style="position: absolute; right: 1mm; top: 0; bottom: 0; font-size: 4.5pt; font-weight: bold; color: #777; opacity: 0.35; display: flex; align-items: center; justify-content: center; z-index: 1; white-space: nowrap; writing-mode: vertical-rl;">UNIVERSITAS STEKOM</div>
        <div class="label-content" style="position: relative; z-index: 2; display: flex; flex-direction: column; height: 100%; width: 100%; justify-content: space-between; padding: 0 4mm; box-sizing: border-box;">
            <div class="label-location" style="font-size: 5.5pt; font-weight: bold; text-align: center; height: 4mm; line-height: 4mm; text-transform: uppercase; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                {{ $record->campus->code ?? $record->campus->name ?? '' }} - {{ $record->location->name ?? '' }}
            </div>
            <div class="label-barcode-img" style="flex-grow: 1; display: flex; align-items: center; justify-content: center; padding: 0.5mm 1mm; background: transparent; overflow: hidden;">
                @php
                    $barcodeGenerator = new \Picqer\Barcode\BarcodeGeneratorSVG();
                @endphp
                <div style="height: 100%; max-height: 10mm; display: flex; justify-content: center; align-items: center;">
                    {!! $barcodeGenerator->getBarcode($record->inventory_number, $barcodeGenerator::TYPE_CODE_128, 1, 25) !!}
                </div>
            </div>
            <div class="label-sku" style="height: 4.5mm; line-height: 4.5mm; background: #000; color: #fff; font-weight: bold; text-align: center; font-size: 6.5pt; letter-spacing: 0.5px; box-sizing: border-box; border-radius: 1px; overflow: hidden;">
                {{ $record->inventory_number }}
            </div>
        </div>
    </div>
</div>
<style>
    .label-barcode-img svg { max-height: 10mm; width: auto; max-width: 100%; }
</style>
