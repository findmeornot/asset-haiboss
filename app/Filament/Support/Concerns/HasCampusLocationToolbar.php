<?php

namespace App\Filament\Support\Concerns;

use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

/**
 * Dropdown Gedung + Ruangan di toolbar tabel (lihat
 * resources/views/filament/tables/campus-location-toolbar.blade.php).
 *
 * Schema harus terdaftar di halaman (bukan dibuat di dalam view), karena
 * Select Filament ngambil opsinya lewat Livewire pakai key komponen. State
 * nempel ke filter `campus_location`, jadi query & indikator filter tetap jalan.
 */
trait HasCampusLocationToolbar
{
    public function campusLocationToolbar(Schema $schema): Schema
    {
        return $schema
            ->statePath('tableFilters.campus_location')
            ->columns(2)
            ->components([
                Select::make('campus_id')
                    ->hiddenLabel()
                    ->placeholder('Semua Gedung')
                    ->options(fn () => \App\Models\Campus::orderBy('name')->pluck('name', 'id'))
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(fn (callable $set) => $set('location_id', null)),
                Select::make('location_id')
                    ->hiddenLabel()
                    ->placeholder(fn (callable $get) => blank($get('campus_id')) ? 'Pilih gedung dulu' : 'Semua Ruangan')
                    ->options(fn (callable $get) => \App\Models\Location::where('campus_id', $get('campus_id'))->orderBy('name')->pluck('name', 'id'))
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->live()
                    ->disabled(fn (callable $get) => blank($get('campus_id'))),
            ]);
    }
}
