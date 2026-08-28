<?php

namespace App\Http\Controllers\Owner;

use App\Bookings\AvailabilityService;
use App\Http\Controllers\Controller;
use App\Http\Requests\BookingAvailabilityRequest;
use App\Models\CourtResource;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;

class BookingAvailabilityController extends Controller
{
    public function __invoke(
        BookingAvailabilityRequest $request,
        TenantContext $context,
        AvailabilityService $availability,
    ): JsonResponse {
        $data = $request->validated();
        $resource = CourtResource::query()
            ->whereKey($data['resource_id'])
            ->whereHas('venue', fn ($query) => $query->where(
                'organization_id',
                $context->organization()->getKey(),
            ))
            ->with('venue.organization')
            ->firstOrFail();

        return response()->json($availability->slots(
            $resource,
            $data['date'],
            (int) $data['duration_minutes'],
        ));
    }
}
