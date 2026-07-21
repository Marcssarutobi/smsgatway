<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Device;

class DeviceAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {

        $token = $request->bearerToken();
        $device = Device::where('device_token', $token)->first();

        if (!$device) {
            return response()->json(['message' => 'Device non authentifié'], 401);
        }

        $device->update(['status' => 'online', 'last_seen_at' => now()]);
        $request->merge(['authenticated_device' => $device]);

        return $next($request);
    }
}
