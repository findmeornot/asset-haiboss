<?php

namespace App\Filament\Inventory\Pages;

use Filament\Pages\Page;

class TransaksiPembelian extends Page
{
    public static function getNavigationIcon(): string | \Illuminate\Contracts\Support\Htmlable | null
    {
        return 'heroicon-o-shopping-cart';
    }
    
    public static function getNavigationGroup(): string | \UnitEnum | null
    {
        return 'Transaksi';
    }
    
    public static function getNavigationLabel(): string
    {
        return 'Pembelian';
    }
    
    protected static ?string $title = 'Transaksi Pembelian';
    
    protected ?string $subheading = 'Daftar transaksi pembelian dari supplier.';
    
    protected string $view = 'filament.pages.transaksi-pembelian';
    
    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }
}
