<?php

namespace App\Filament\Inventory\Pages;

use Filament\Pages\Page;

class TransaksiPenjualan extends Page
{
    public static function getNavigationIcon(): string | \Illuminate\Contracts\Support\Htmlable | null
    {
        return 'heroicon-o-currency-dollar';
    }
    
    public static function getNavigationGroup(): string | \UnitEnum | null
    {
        return 'Transaksi';
    }
    
    public static function getNavigationLabel(): string
    {
        return 'Penjualan';
    }
    
    protected static ?string $title = 'Transaksi Penjualan';
    
    protected ?string $subheading = 'Daftar transaksi penjualan ke pembeli.';
    
    protected string $view = 'filament.pages.transaksi-penjualan';
    
    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }
}
