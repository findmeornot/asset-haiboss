<?php

namespace App\Filament\Inventory\Resources\Traits;

use Livewire\Attributes\Url;

trait HasCleanFilterUrls
{
    // Override Filament's default #[Url] attribute
    public ?array $tableFilters = null;

    #[Url(as: 'kategori', except: '')]
    public ?string $filterKategori = '';

    #[Url(as: 'status', except: '')]
    public ?string $filterStatus = '';

    #[Url(as: 'kondisi', except: '')]
    public ?string $filterKondisi = '';

    #[Url(as: 'gedung', except: '')]
    public ?string $filterGedung = '';

    #[Url(as: 'ruangan', except: '')]
    public ?string $filterRuangan = '';

    public function mountHasCleanFilterUrls(): void
    {
        if ($this->filterKategori || $this->filterStatus || $this->filterKondisi || $this->filterGedung || $this->filterRuangan) {
            if (!is_array($this->tableFilters)) {
                $this->tableFilters = [];
            }
            if ($this->filterKategori) $this->tableFilters['category_id']['value'] = $this->filterKategori;
            if ($this->filterStatus) $this->tableFilters['status']['value'] = $this->filterStatus;
            if ($this->filterKondisi) $this->tableFilters['kondisi']['value'] = $this->filterKondisi;
            if ($this->filterGedung) $this->tableFilters['campus_location']['campus_id'] = $this->filterGedung;
            if ($this->filterRuangan) $this->tableFilters['campus_location']['location_id'] = $this->filterRuangan;
        }
    }

    public function updated($property, $value)
    {
        if (str_starts_with((string) $property, 'tableFilters')) {
            $this->filterKategori = $this->tableFilters['category_id']['value'] ?? '';
            $this->filterStatus = $this->tableFilters['status']['value'] ?? '';
            $this->filterKondisi = $this->tableFilters['kondisi']['value'] ?? '';
            $this->filterGedung = $this->tableFilters['campus_location']['campus_id'] ?? '';
            $this->filterRuangan = $this->tableFilters['campus_location']['location_id'] ?? '';
        }
    }
}
