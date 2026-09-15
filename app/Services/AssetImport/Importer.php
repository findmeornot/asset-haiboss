<?php

namespace App\Services\AssetImport;

use App\Models\Asset;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Employee;
use App\Models\InventoryBalance;
use App\Models\InventoryBalanceUnit;
use App\Models\Location;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Services\BarcodeNumberGenerator;
use App\Services\InventoryNumberGenerator;
use App\Services\SupplyBarcodeGenerator;
use Illuminate\Support\Str;

/**
 * Writes validated import rows to the database. Assumes RowValidator::validate()
 * already ran with zero errors — this class does not re-validate. The caller is
 * still responsible for wrapping import() in DB::transaction().
 */
class Importer
{
    /**
     * Import data ke database. Gunakan dalam DB::transaction.
     * Pastikan RowValidator::validate() sudah dipanggil dan tidak ada error sebelum memanggil ini.
     *
     * Setiap baris CSV dengan Jumlah=N akan menghasilkan:
     *   - Kategori 'supply' : 1 InventoryBalance saldo += N (tidak membuat Asset)
     *   - Kategori lainnya  : 1 Purchase + 1 PurchaseItem + N Asset records individual
     *
     * @return int Jumlah unit berhasil diimport (jumlah Asset yang dibuat + jumlah supply unit)
     */
    public function import(array $rows): int
    {
        $master = MasterDataResolver::preload();
        $classifications = $master['classifications'];
        $categories      = $master['categories'];
        $campuses        = $master['campuses'];
        $locations       = $master['locations'];
        $employees       = $master['employees'];

        $count = 0;

        foreach ($rows as $row) {
            $classificationName = ValueNormalizer::cleanString($row['Kategori Akuntansi'] ?? '');
            $classification = ValueNormalizer::resolveClassification($classifications, $classificationName);
            $campus         = $campuses->get(mb_strtolower(ValueNormalizer::cleanString($row['Gedung'] ?? '')));

            // Cari kategori; jika belum ada → buat otomatis dan attach ke classification
            $categoryName = ValueNormalizer::cleanString($row['Kategori'] ?? '');
            $category     = $categories->get(mb_strtolower($categoryName));
            if (!$category && $categoryName !== '') {
                $category = Category::create([
                    'name'        => $categoryName,
                    'code'        => strtoupper(Str::slug($categoryName, '-')),
                    'description' => 'Kategori otomatis dari import untuk ' . $categoryName,
                    'active'      => true,
                ]);
                // Attach ke classification yang sesuai
                if ($classification) {
                    $category->classifications()->syncWithoutDetaching([$classification->id]);
                }
                // Tambahkan ke koleksi in-memory
                $categories->put(mb_strtolower($categoryName), $category);
            } elseif ($category && $classification) {
                // Pastikan linkage ada meski kategori sudah ada tapi belum terhubung
                $category->classifications()->syncWithoutDetaching([$classification->id]);
            }

            $ruanganName = trim($row['Ruangan'] ?? '');

            // Cari ruangan; jika belum ada dan gedung valid → buat otomatis
            $location = null;
            if ($campus && $ruanganName !== '') {
                $location = $locations
                    ->where('campus_id', $campus->id)
                    ->first(fn($l) => mb_strtolower($l->name) === mb_strtolower($ruanganName));

                if (!$location) {
                    $location = Location::create([
                        'campus_id' => $campus->id,
                        'name'      => $ruanganName,
                    ]);
                    // Tambahkan ke koleksi in-memory agar baris berikutnya bisa menemukannya
                    $locations->push($location->load('campus'));
                }
            }

            // Cari PIC; jika belum ada → buat otomatis
            $picName = trim($row['PIC'] ?? '');
            $pic     = null;
            if ($picName !== '') {
                $pic = $employees->get(mb_strtolower($picName));
                if (!$pic) {
                    $pic = Employee::create(['name' => $picName]);
                    // Tambahkan ke koleksi in-memory agar baris berikutnya tidak membuat duplikat
                    $employees->put(mb_strtolower($picName), $pic);
                }
            }

            $ownershipVal = ValueNormalizer::OWNERSHIP_MAP[mb_strtolower(trim($row['Sumber Dana'] ?? ''))] ?? 'company';
            $statusVal    = ValueNormalizer::STATUS_MAP[mb_strtolower(trim($row['Status'] ?? ''))] ?? 'stock';
            $kondisiVal   = ValueNormalizer::KONDISI_MAP[mb_strtolower(trim($row['Kondisi'] ?? ''))] ?? 'good';

            // Tahun perolehan → purchase_date: null jika kosong/strip (tidak diketahui)
            $tahunRaw     = trim((string) ($row['Tahun Perolehan'] ?? ''));
            $tahunUnknown = $tahunRaw === '' || preg_match('/^-+$/', $tahunRaw);
            $purchaseDate = !$tahunUnknown ? ValueNormalizer::parseImportDate($tahunRaw) : null;
            $tahun        = $purchaseDate ? (int) date('Y', strtotime($purchaseDate)) : null;

            // Harga: normalisasi (kosong/strip = tidak diketahui)
            $hargaRaw     = trim((string) ($row['Harga Perolehan'] ?? ''));
            $hargaVal     = null;
            $hargaUnknown = $hargaRaw === '' || preg_match('/^-+$/', $hargaRaw);
            if (!$hargaUnknown) {
                $hargaVal = ValueNormalizer::parseCurrency($hargaRaw);
            }

            $jumlah     = max(1, (int) trim($row['Jumlah'] ?? 1));
            $unitPrice  = $hargaVal ?? null;
            $totalPrice = $unitPrice !== null ? $unitPrice * $jumlah : null;

            // Notes (Keterangan) + unknown info suffix
            $notesRaw    = trim($row['Keterangan'] ?? '');
            $unknownInfo = array_filter([
                $tahunUnknown ? 'tahun perolehan tidak diketahui' : null,
                $hargaUnknown ? 'harga perolehan tidak diketahui' : null,
            ]);
            if ($unknownInfo) {
                $suffix   = '(' . implode(', ', $unknownInfo) . ')';
                $notesRaw = $notesRaw !== '' ? $notesRaw . ' ' . $suffix : $suffix;
            }

            // Shared base data for Asset records
            $baseAssetData = [
                'classification_id' => $classification?->id,
                'category_id'       => $category?->id,
                'name'              => trim($row['Nama Barang'] ?? ''),
                'brand'             => trim($row['Merk/Tipe'] ?? '') ?: null,
                'serial_number'     => trim($row['Nomor Seri'] ?? '') ?: null,
                'unit'              => trim($row['Satuan'] ?? '') ?: null,
                'ownership'         => $ownershipVal,
                'campus_id'         => $campus?->id,
                'location_id'       => $location?->id,
                'pic_id'            => $pic?->id,
                'status'            => $statusVal,
                'kondisi'           => $kondisiVal,
                'notes'             => $notesRaw ?: null,
            ];

            // ──────────────────────────────────────────────────────────────
            // SUPPLY PATH: update InventoryBalance saldo — no Asset records
            // ──────────────────────────────────────────────────────────────
            if ($classification && strtolower($classification->slug) === 'barang-habis-pakai') {
                // Create Purchase header for traceability
                $purchase = Purchase::create([
                    'purchase_date' => $purchaseDate,
                    'ownership'     => $ownershipVal,
                    'total_amount'  => $totalPrice,
                ]);

                // firstOrCreate balance grouped by category + name + location
                $balanceName = trim($row['Nama Barang'] ?? '');
                $balance = InventoryBalance::where([
                    'category_id' => $category->id,
                    'name'        => $balanceName,
                    'brand'       => trim($row['Merk/Tipe'] ?? '') ?: null,
                    'location_id' => $location?->id,
                ])->first();

                $isNewBalance = false;

                if (!$balance) {
                    $balance = InventoryBalance::create([
                        'category_id' => $category->id,
                        'name'        => $balanceName,
                        'brand'       => trim($row['Merk/Tipe'] ?? ''),
                        'location_id' => $location?->id,
                        'campus_id' => $campus?->id,
                        'quantity'  => 0,
                        'master_barcode' => SupplyBarcodeGenerator::generateMaster(),
                        'latest_sequence' => 0,
                        'has_pure_master_unit' => false,
                        'pic_id'      => $pic?->id,
                        'status'      => $statusVal,
                        'kondisi'     => $kondisiVal,
                        'notes'       => $notesRaw ?: null,
                    ]);
                    $isNewBalance = true;
                } else {
                    $balance->update(array_filter([
                        'pic_id'  => $pic?->id,
                        'status'  => $statusVal,
                        'kondisi' => $kondisiVal,
                        'notes'   => $notesRaw ?: null,
                    ]));
                }

                // Add purchased quantity to the running balance
                $balance->increment('quantity', $jumlah);

                // Record purchase item linked to the balance
                $purchaseItem = PurchaseItem::create([
                    'purchase_id'        => $purchase->id,
                    'inventory_balance_id' => $balance->id,
                    'category_id'        => $category->id,
                    'classification_id'  => $classification?->id,
                    'name'               => $balanceName,
                    'quantity'           => $jumlah,
                    'unit'               => trim($row['Satuan'] ?? '') ?: null,
                    'unit_price'         => $unitPrice,
                    'total_price'        => $totalPrice,
                    'is_capitalized'     => false, // Supply is never capitalized
                ]);

                // BARCODE LOGIC
                if ($isNewBalance && $jumlah === 1) {
                    $balance->update(['has_pure_master_unit' => true]);
                    $balance->units()->create([
                        'purchase_item_id' => $purchaseItem->id,
                        'sub_barcode' => $balance->master_barcode,
                        'status' => 'available'
                    ]);
                } else {
                    $subBarcodes = SupplyBarcodeGenerator::generateSub($balance, $jumlah);
                    $unitRecords = [];
                    foreach ($subBarcodes as $sb) {
                        $unitRecords[] = [
                            'inventory_balance_id' => $balance->id,
                            'purchase_item_id' => $purchaseItem->id,
                            'sub_barcode' => $sb,
                            'status' => 'available',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                    InventoryBalanceUnit::insert($unitRecords);
                }

                $count += $jumlah; // Count as stock units added
                continue;
            }

            // ──────────────────────────────────────────────────────────────
            // ASSET / INVENTORY PATH: UPSERT (Update existing or Create new)
            // ──────────────────────────────────────────────────────────────
            $kode = ValueNormalizer::cleanString((string) ($row['Kode'] ?? ''));
            $existingAsset = null;

            if ($kode !== '') {
                // We know it's not soft-deleted because RowValidator already checked it
                $existingAsset = Asset::where('inventory_number', $kode)->lockForUpdate()->first();
            }

            if ($existingAsset) {
                // UPDATE
                // We DO NOT create Purchase/PurchaseItem, and we DO NOT overwrite financial
                // data that is already known. We only update the allowed physical/status
                // fields, plus fill in financial fields that are still empty (see below).
                $existingAsset->update([
                    'classification_id' => $classification?->id,
                    'category_id'       => $category?->id,
                    'name'              => trim($row['Nama Barang'] ?? ''),
                    'brand'             => trim($row['Merk/Tipe'] ?? '') ?: null,
                    'serial_number'     => trim($row['Nomor Seri'] ?? '') ?: null,
                    'unit'              => trim($row['Satuan'] ?? '') ?: null,
                    'campus_id'         => $campus?->id,
                    'location_id'       => $location?->id,
                    'pic_id'            => $pic?->id,
                    'status'            => $statusVal,
                    'kondisi'           => $kondisiVal,
                    'notes'             => $notesRaw ?: null,
                ]);

                // Lengkapi data finansial yang masih kosong (unit_price/total_price/
                // purchase_date null = "tidak diketahui"). Tidak pernah menimpa nilai yang
                // sudah ada — hanya mengisi yang kosong.
                $purchaseItem = $existingAsset->purchaseItem;
                if ($purchaseItem) {
                    if ($purchaseItem->unit_price === null && $unitPrice !== null) {
                        $purchaseItem->update([
                            'unit_price'     => $unitPrice,
                            'total_price'    => $unitPrice * $purchaseItem->quantity,
                            'is_capitalized' => PurchaseItem::isCapitalizable($unitPrice, $classification),
                        ]);
                    }

                    $purchase = $purchaseItem->purchase;
                    if ($purchase && $purchase->purchase_date === null && $purchaseDate !== null) {
                        $purchase->update(['purchase_date' => $purchaseDate]);
                    }
                }

                $count++;
            } else {
                // INSERT — hanya terjadi saat Kode kosong. Kode terisi tapi tidak ditemukan
                // sudah ditolak di RowValidator; Kode Barang untuk aset baru SELALU
                // di-generate otomatis oleh sistem, tidak pernah dari nilai Excel.

                // Create Purchase header
                $purchase = Purchase::create([
                    'purchase_date' => $purchaseDate,
                    'ownership'     => $ownershipVal,
                    'total_amount'  => $totalPrice,
                ]);

                // Create PurchaseItem
                $purchaseItem = PurchaseItem::create([
                    'purchase_id'       => $purchase->id,
                    'category_id'       => $category?->id,
                    'classification_id' => $classification?->id,
                    'name'              => trim($row['Nama Barang'] ?? ''),
                    'quantity'          => $jumlah,
                    'unit'              => trim($row['Satuan'] ?? '') ?: null,
                    'unit_price'        => $unitPrice,
                    'total_price'       => $totalPrice,
                    'is_capitalized'    => PurchaseItem::isCapitalizable($unitPrice, $classification),
                ]);

                // Create N individual Asset records
                // Kode Barang & Barcode aset baru selalu di-generate otomatis oleh sistem —
                // ambil semua sekaligus dalam 1 transaksi (bukan generate() N kali) supaya
                // import baris dengan Jumlah besar tidak lambat.
                $inventoryNumbers = InventoryNumberGenerator::generateBulk($jumlah);
                $barcodes = BarcodeNumberGenerator::generateBulk($jumlah);

                for ($i = 0; $i < $jumlah; $i++) {
                    $assetData = $baseAssetData;
                    $assetData['inventory_number'] = $inventoryNumbers[$i];
                    $assetData['barcode'] = $barcodes[$i];
                    $assetData['purchase_item_id'] = $purchaseItem->id;

                    if ($i > 0) {
                        $assetData['serial_number'] = null;
                    }

                    // Barcode sudah diisi di atas, AssetObserver::creating() akan skip
                    // generate ulang (guard: if (empty($asset->barcode))).
                    Asset::create($assetData);
                    $count++;
                }
            }
        }

        return $count;
    }
}
