<?php

namespace App\Http\Controllers;

use App\Models\PrintJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PrintJobController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'printer_station' => ['required', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.inventory_number' => ['required', 'string'],
            'items.*.location_label' => ['required', 'string'],
        ]);

        $jobs = collect($validated['items'])->map(function (array $item) use ($validated) {
            return PrintJob::create([
                'printer_station' => $validated['printer_station'],
                'inventory_number' => $item['inventory_number'],
                'location_label' => $item['location_label'],
                'status' => 'pending',
                'requested_by' => Auth::id(),
            ]);
        });

        return response()->json([
            'message' => 'Print job dibuat, menunggu agent di PC printer.',
            'job_ids' => $jobs->pluck('id'),
        ], 201);
    }

    public function status(Request $request)
    {
        $ids = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:print_jobs,id'],
        ])['ids'];

        $jobs = PrintJob::whereIn('id', $ids)->get(['id', 'status', 'error_message', 'printed_at']);

        return response()->json(['jobs' => $jobs]);
    }
}
