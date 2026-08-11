<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Form;
use Filament\Forms\Components;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Auth;

use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;

class ReportDashboard extends Page implements HasForms
{
    use InteractsWithForms;
    public static function getNavigationIcon(): string | \Illuminate\Contracts\Support\Htmlable | null
    {
        return 'heroicon-o-document-chart-bar';
    }

    protected string $view = 'filament.pages.report-dashboard';
    
    public static function getNavigationGroup(): string | \UnitEnum | null
    {
        return 'Reports';
    }

    public static function getNavigationLabel(): string
    {
        return 'Laporan Aset';
    }
    
    public static function canAccess(): bool
    {
        return Auth::user()->hasPermissionTo('reports.view');
    }

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function submit()
    {
        $data = $this->form->getState();
        request()->merge($data);
        return app(\App\Http\Controllers\ReportController::class)->exportExcel(request());
    }

    public function form(\Filament\Schemas\Schema $form): \Filament\Schemas\Schema
    {
        return $form
            ->schema([
                \Filament\Schemas\Components\Section::make('Konfigurasi Laporan')
                    ->schema([
                        Components\Select::make('report_type')
                            ->label('Jenis Laporan')
                            ->options([
                                'all_assets' => 'Daftar Seluruh Barang',
                                'by_location' => 'Barang per Lokasi',
                                'by_campus' => 'Barang per Kampus',
                                'by_category' => 'Barang per Kategori',
                                'by_status' => 'Barang berdasarkan Status',
                                'by_year' => 'Barang berdasarkan Tahun Pembelian',
                                'damaged' => 'Barang Rusak',
                                'lost' => 'Barang Hilang',
                                'borrowed' => 'Barang Dipinjam',
                                'movements' => 'Mutasi Barang',
                                'stock_opname' => 'Stock Opname',
                            ])
                            ->required()
                            ->reactive(),
                            
                        \Filament\Schemas\Components\Fieldset::make('Filter Spesifik')
                            ->schema([
                                Components\Select::make('campus_id')
                                    ->label('Kampus')
                                    ->options(\App\Models\Campus::pluck('name', 'id')),
                                Components\Select::make('location_id')
                                    ->label('Lokasi')
                                    ->options(\App\Models\Location::pluck('name', 'id')),
                                Components\Select::make('category_id')
                                    ->label('Kategori')
                                    ->options(\App\Models\Category::pluck('name', 'id')),
                                Components\Select::make('ownership')
                                    ->label('Kepemilikan')
                                    ->options([
                                        'company' => 'Perusahaan',
                                        'grant' => 'Hibah',
                                        'loan' => 'Pinjaman',
                                    ]),
                                Components\TextInput::make('year')
                                    ->label('Tahun Pembelian')
                                    ->numeric(),
                            ])
                            ->columns(3),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('export_excel')
                ->label('Export ke Excel')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->submit('submit')
                ->disabled(fn () => empty($this->data['report_type'])),
        ];
    }
}
