<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\PrintJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PrintJobController extends Controller
{
    public function pending(Request $request)
    {
        $station = $request->attributes->get('printer_station');

        $jobs = DB::transaction(function () use ($station) {
            $jobs = PrintJob::where('printer_station', $station->station_key)
                ->where('status', 'pending')
                ->orderBy('id')
                ->limit(2)
                ->lockForUpdate()
                ->get();

            PrintJob::whereIn('id', $jobs->pluck('id'))->update(['status' => 'processing']);

            return $jobs;
        });

        return response()->json([
            'jobs' => $jobs->map(fn (PrintJob $j) => [
                'id' => $j->id,
                'inventory_number' => $j->inventory_number,
                'location_label' => $j->location_label,
            ]),
        ]);
    }

    public function complete(Request $request, PrintJob $printJob)
    {
        $this->assertOwnedByStation($request, $printJob);
        $printJob->update(['status' => 'completed', 'printed_at' => now()]);

        return response()->json(['message' => 'ok']);
    }

    public function fail(Request $request, PrintJob $printJob)
    {
        $this->assertOwnedByStation($request, $printJob);
        $validated = $request->validate(['error_message' => ['required', 'string']]);
        $printJob->update([
            'status' => 'failed',
            'attempts' => $printJob->attempts + 1,
            'error_message' => $validated['error_message'],
        ]);

        return response()->json(['message' => 'ok']);
    }

    private function assertOwnedByStation(Request $request, PrintJob $printJob): void
    {
        $station = $request->attributes->get('printer_station');
        abort_if(
            $printJob->printer_station !== $station->station_key,
            403,
            'Job ini bukan milik station kamu.'
        );
    }
}
