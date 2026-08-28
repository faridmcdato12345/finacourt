<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVenuePhotoRequest;
use App\Http\Requests\UpdateVenuePhotoRequest;
use App\Models\Venue;
use App\Models\VenuePhoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class VenuePhotoController extends Controller
{
    public function store(StoreVenuePhotoRequest $request, Venue $venue): RedirectResponse
    {
        /** @var array<int, UploadedFile> $photos */
        $photos = $request->file('photos', []);
        $storedPaths = [];

        try {
            DB::transaction(function () use ($venue, $photos, &$storedPaths): void {
                $existing = $venue->photos()->lockForUpdate()->get();

                if ($existing->count() + count($photos) > 10) {
                    throw ValidationException::withMessages([
                        'photos' => 'A venue can have up to 10 photos.',
                    ]);
                }

                $sortOrder = ((int) $existing->max('sort_order')) + 1;
                $hasPrimary = $existing->contains('is_primary', true);

                foreach ($photos as $photo) {
                    $path = $photo->store("venues/{$venue->organization_id}/{$venue->getKey()}", 'public');

                    if (! is_string($path)) {
                        throw new RuntimeException('The venue photo could not be stored.');
                    }

                    $storedPaths[] = $path;
                    $venue->photos()->create([
                        'storage_path' => $path,
                        'alt_text' => $venue->name.' venue photo',
                        'sort_order' => $sortOrder++,
                        'is_primary' => ! $hasPrimary,
                    ]);
                    $hasPrimary = true;
                }
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($storedPaths);

            throw $exception;
        }

        return back()->with('status', count($photos).' '.str('photo')->plural(count($photos)).' uploaded.');
    }

    public function update(
        UpdateVenuePhotoRequest $request,
        Venue $venue,
        VenuePhoto $photo,
    ): RedirectResponse {
        $data = $request->validated();

        DB::transaction(function () use ($venue, $photo, $data): void {
            $venue->photos()->lockForUpdate()->get();

            if (array_key_exists('alt_text', $data)) {
                $photo->alt_text = $data['alt_text'];
            }

            if ($data['is_primary'] ?? false) {
                $venue->photos()->where('id', '!=', $photo->getKey())->update(['is_primary' => false]);
                $photo->is_primary = true;
            }

            $photo->save();
        });

        return back()->with('status', 'Venue photo updated.');
    }

    public function destroy(Venue $venue, VenuePhoto $photo): RedirectResponse
    {
        abort_unless($photo->venue_id === $venue->getKey(), 404);
        Gate::authorize('update', $venue);

        $path = $photo->storage_path;

        DB::transaction(function () use ($venue, $photo): void {
            $venue->photos()->lockForUpdate()->get();
            $wasPrimary = $photo->is_primary;
            $photo->delete();

            if ($wasPrimary) {
                $venue->photos()->orderBy('sort_order')->first()?->update(['is_primary' => true]);
            }
        });

        Storage::disk('public')->delete($path);

        return back()->with('status', 'Venue photo deleted.');
    }
}
