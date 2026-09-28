<?php

namespace App\Console\Commands;

use App\Models\PrinterStation;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreatePrinterStationCommand extends Command
{
    protected $signature = 'printer:create-station {name} {station_key}';
    protected $description = 'Buat PrinterStation baru + generate api_token buat agent PowerShell';

    public function handle(): int
    {
        $name = $this->argument('name');
        $stationKey = $this->argument('station_key');

        if (PrinterStation::where('station_key', $stationKey)->exists()) {
            $this->error("station_key \"{$stationKey}\" sudah dipakai. Pilih yang lain.");

            return self::FAILURE;
        }

        $token = Str::random(64);

        $station = PrinterStation::create([
            'name' => $name,
            'station_key' => $stationKey,
            'api_token' => $token,
        ]);

        $this->info("Station \"{$station->name}\" (key: {$station->station_key}) dibuat.");
        $this->warn('API token (CATAT SEKARANG, tidak ditampilkan lagi):');
        $this->line($token);

        return self::SUCCESS;
    }
}
