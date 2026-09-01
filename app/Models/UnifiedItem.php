<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class UnifiedItem extends Model
{
    protected $table = 'unified_items';

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    public function classification() { return $this->belongsTo(\App\Models\Classification::class, 'classification_id'); }
    public function category() { return $this->belongsTo(\App\Models\Category::class, 'category_id'); }
    public function campus() { return $this->belongsTo(\App\Models\Campus::class, 'campus_id'); }
    public function location() { return $this->belongsTo(\App\Models\Location::class, 'location_id'); }
    public function pic() { return $this->belongsTo(\App\Models\Employee::class, 'pic_id'); }

    protected static function booted()
    {
        static::addGlobalScope('unified', function (Builder $builder) {
            $assets = DB::table('assets')
                ->leftJoin('categories', 'assets.category_id', '=', 'categories.id')
                ->leftJoin('category_classification', 'categories.id', '=', 'category_classification.category_id')
                ->leftJoin('classifications', 'category_classification.classification_id', '=', 'classifications.id')
                ->leftJoin('locations', 'assets.location_id', '=', 'locations.id')
                ->leftJoin('purchase_items', 'assets.purchase_item_id', '=', 'purchase_items.id')
                ->whereNull('assets.deleted_at')
                ->select(
                    DB::raw("CONCAT('asset_', assets.id) as id"),
                    'assets.id as raw_id',
                    'assets.ulid as route_key',
                    DB::raw("'asset' as row_type"),
                    'assets.barcode as barcode',
                    'assets.inventory_number as sku',
                    'categories.name as category_name',
                    'assets.name as item_name',
                    'assets.brand as brand',
                    'classifications.name as classification_name',
                    DB::raw('1 as quantity'),
                    'locations.name as location_name',
                    'purchase_items.unit_price as price',
                    'assets.status as status',
                    'assets.kondisi as kondisi',
                    'assets.created_at as created_at'
                );

            $supplies = DB::table('inventory_balances')
                ->leftJoin('categories', 'inventory_balances.category_id', '=', 'categories.id')
                ->leftJoin('category_classification', 'categories.id', '=', 'category_classification.category_id')
                ->leftJoin('classifications', 'category_classification.classification_id', '=', 'classifications.id')
                ->leftJoin('locations', 'inventory_balances.location_id', '=', 'locations.id')
                ->select(
                    DB::raw("CONCAT('supply_', inventory_balances.id) as id"),
                    'inventory_balances.id as raw_id',
                    'inventory_balances.id as route_key',
                    DB::raw("'supply' as row_type"),
                    'inventory_balances.master_barcode as barcode',
                    DB::raw("NULL as sku"),
                    'categories.name as category_name',
                    'inventory_balances.name as item_name',
                    'inventory_balances.brand as brand',
                    DB::raw("COALESCE(classifications.name, 'Persediaan Barang') as classification_name"),
                    'inventory_balances.quantity as quantity',
                    'locations.name as location_name',
                    DB::raw("NULL as price"),
                    DB::raw("'available' as status"),
                    'inventory_balances.kondisi as kondisi',
                    'inventory_balances.created_at as created_at'
                );

            $builder->fromSub($assets->unionAll($supplies), 'unified_items');
        });
    }
}
