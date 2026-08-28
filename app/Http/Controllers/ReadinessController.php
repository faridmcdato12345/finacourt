<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class ReadinessController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::select('SELECT 1');
            $ready = is_writable(storage_path('framework'))
                && is_writable(storage_path('logs'))
                && is_writable(base_path('bootstrap/cache'));
        } catch (Throwable) {
            $ready = false;
        }

        return response()
            ->json(['status' => $ready ? 'ready' : 'unavailable'], $ready ? 200 : 503)
            ->header('Cache-Control', 'no-store, max-age=0');
    }
}
