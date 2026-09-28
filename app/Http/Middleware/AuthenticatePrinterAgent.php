<?php

namespace App\Http\Middleware;

use App\Models\PrinterStation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatePrinterAgent
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        if (! $token) {
            return response()->json(['message' => 'Missing agent token'], 401);
        }

        $station = PrinterStation::where('api_token', $token)->first();
        if (! $station) {
            return response()->json(['message' => 'Invalid agent token'], 401);
        }

        $request->attributes->set('printer_station', $station);

        return $next($request);
    }
}
