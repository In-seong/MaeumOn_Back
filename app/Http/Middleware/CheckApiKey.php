<?php

namespace App\Http\Middleware;

use App\Models\SiteSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-API-Key');

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'API 키가 필요합니다.',
            ], 401);
        }

        $storedKey = SiteSetting::getValue('corporate_api_key');

        if (!$storedKey || !hash_equals($storedKey, $apiKey)) {
            return response()->json([
                'success' => false,
                'message' => '유효하지 않은 API 키입니다.',
            ], 401);
        }

        return $next($request);
    }
}
