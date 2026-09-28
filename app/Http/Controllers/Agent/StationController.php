<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class StationController extends Controller
{
    public function heartbeat(Request $request)
    {
        $station = $request->attributes->get('printer_station');
        $station->update(['last_seen_at' => now()]);

        return response()->json(['message' => 'ok']);
    }
}
