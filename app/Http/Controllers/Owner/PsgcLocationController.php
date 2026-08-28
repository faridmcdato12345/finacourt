<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\PsgcLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PsgcLocationController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'parent_code' => [
                'required',
                'string',
                'regex:/^\d{10}$/',
                Rule::exists('psgc_locations', 'code')->whereIn('level', ['region', 'province', 'area']),
            ],
        ]);

        return response()->json([
            'data' => PsgcLocation::query()
                ->where('parent_code', $validated['parent_code'])
                ->whereIn('level', ['city', 'municipality'])
                ->orderBy('name')
                ->get(['code', 'name', 'level', 'type']),
        ]);
    }
}
