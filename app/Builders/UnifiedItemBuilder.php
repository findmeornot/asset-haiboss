<?php

namespace App\Builders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class UnifiedItemBuilder extends Builder
{
    public function get($columns = ['*'])
    {
        $version = Cache::get('unified_items_version', 1);
        $sql = $this->toSql();
        $bindings = $this->getBindings();
        
        $key = 'unified_item_get_' . $version . '_' . md5($sql . serialize($bindings) . serialize($columns));

        $data = Cache::remember($key, 60, function () use ($columns) {
            return parent::get($columns)->map(fn($item) => $item->getAttributes())->toArray();
        });

        return $this->model->hydrate($data);
    }

    public function count($columns = '*')
    {
        $version = Cache::get('unified_items_version', 1);
        $sql = $this->toSql();
        $bindings = $this->getBindings();
        
        $key = 'unified_item_count_' . $version . '_' . md5($sql . serialize($bindings) . serialize($columns));

        return Cache::remember($key, 60, function () use ($columns) {
            return parent::count($columns);
        });
    }
}
