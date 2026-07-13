<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FaxService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FaxCallbackController extends Controller
{
    public function handleBizmoaCallback(Request $request, FaxService $faxService): Response
    {
        $faxService->handleCallback($request->all());

        return response('OK', 200);
    }
}
