<?php

namespace App\Filament\Inventory\Resources\Traits;

use Livewire\Attributes\Url;

trait HasCleanBarangHabisPakaiFilterUrls
{
    public ?array $tableFilters = null;

    #[Url(as: 'kategori', except: '')]
    public ?string $filterKategori = '';

    #[Url(as: 'gedung', except: '')]
    public ?string $filterGedung = '';

    #[Url(as: 'ruangan', except: '')]
    public ?string $filterRuangan = '';

    public function mountHasCleanBarangHabisPakaiFilterUrls(): void
    {
        if ($this->filterKategori || $this->filterGedung || $this->filterRuangan) {
            if (!is_array($this->tableFilters)) {
                $this->tableFilters = [];
            }
            if ($this->filterKategori) $this->tableFilters['category_id']['value'] = $this->filterKategori;
            if ($this->filterGedung) $this->tableFilters['campus_location']['campus_id'] = $this->filterGedung;
            if ($this->filterRuangan) $this->tableFilters['campus_location']['location_id'] = $this->filterRuangan;
        }
    }

    public function updated($property, $value)
    {
        if (str_starts_with((string) $property, 'tableFilters')) {
            $this->filterKategori = $this->tableFilters['category_id']['value'] ?? '';
            $this->filterGedung = $this->tableFilters['campus_location']['campus_id'] ?? '';
            $this->filterRuangan = $this->tableFilters['campus_location']['location_id'] ?? '';
        }
    }
}
