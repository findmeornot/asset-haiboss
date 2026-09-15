<?php

namespace App\Services\AssetImport;

use App\Models\Campus;
use App\Models\Category;
use App\Models\Classification;
use App\Models\Employee;
use App\Models\Location;

/**
 * Read-only preload of master data used by both RowValidator and Importer.
 *
 * This is deliberately read-only — do NOT add mutation/create-if-missing
 * methods here. Creating missing Category/Location/Employee rows is a
 * persistence concern that only makes sense during Importer::import()
 * (RowValidator must stay a pure dry-run with zero DB writes); see Importer
 * for that logic.
 */
class MasterDataResolver
{
    public static function preload(): array
    {
        return [
            'classifications' => Classification::all()->keyBy(fn($c) => mb_strtolower(ValueNormalizer::cleanString($c->name))),
            'categories'      => Category::all()->keyBy(fn($c) => mb_strtolower(ValueNormalizer::cleanString($c->name))),
            'campuses'        => Campus::all()->keyBy(fn($c) => mb_strtolower(ValueNormalizer::cleanString($c->name))),
            'locations'       => Location::with('campus')->get(),
            'employees'       => Employee::all()->keyBy(fn($e) => mb_strtolower(ValueNormalizer::cleanString($e->name))),
        ];
    }
}
