<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && !$user->is_active) {
            $user->currentAccessToken()->delete();

            return response()->json([
                'success' => false,
                'message' => '비활성화된 계정입니다. 관리자에게 문의해주세요.',
            ], 401);
        }

        return $next($request);
    }
}
