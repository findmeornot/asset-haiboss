<?php

namespace App\Filament\Inventory\Widgets;

use Filament\Widgets\Widget;

class WelcomeOverview extends Widget
{
    protected static ?int $sort = -2;

    protected int | string | array $columnSpan = 'full';

    protected string $view = 'filament.inventory.widgets.welcome-overview';
}
