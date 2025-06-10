<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use App\Models\Setting;

class RestrictBySettingIP
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {

        $ip = $request->ip();
        $allowedIPs = explode(',', Setting::getValue('allowed_ips', ''));
        $allowedIPs = array_map('trim', $allowedIPs);

        if (!in_array($ip, $allowedIPs)) {
            return response()->view('pages.errors.ip_blocked', ['ip' => $ip], 403);
        }

        return $next($request);
    }
}
