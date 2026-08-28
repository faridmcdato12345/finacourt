<?php

namespace Tests\Feature;

use App\Models\CourtResource;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Sport;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenuePhoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VenuePhotoManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_upload_initial_photos_while_creating_a_venue(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $organization = Organization::factory()->create();
        Membership::factory()->owner()->for($owner)->for($organization)->create();
        $sport = Sport::factory()->create();

        $this->actingAs($owner)
            ->get(route('owner.venues.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Owner/Venues/Create'));

        $response = $this->actingAs($owner)->post(route('owner.venues.store'), [
            'name' => 'New Photo Courts',
            'slug' => '',
            'description' => 'A venue created with real photos.',
            'address' => '25 Court Avenue',
            'city' => 'Davao City',
            'province' => 'Davao del Sur',
            'latitude' => null,
            'longitude' => null,
            'phone' => null,
            'email' => null,
            'website' => null,
            'is_published' => false,
            'sports' => [$sport->getKey()],
            'amenities' => [],
            'photos' => [
                $this->image('new-cover.png'),
                $this->image('new-court.png'),
            ],
        ]);

        $venue = Venue::query()->sole();
        $response->assertRedirect(route('owner.venues.show', $venue));
        $this->assertSame($organization->getKey(), $venue->organization_id);

        $photos = $venue->photos()->get();
        $this->assertCount(2, $photos);
        $this->assertTrue($photos->first()->is_primary);
        $this->assertFalse($photos->last()->is_primary);
        $photos->each(fn (VenuePhoto $photo) => Storage::disk('public')->assertExists($photo->storage_path));
    }

    public function test_invalid_initial_photo_prevents_venue_creation(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $organization = Organization::factory()->create();
        Membership::factory()->owner()->for($owner)->for($organization)->create();
        $sport = Sport::factory()->create();

        $this->actingAs($owner)->post(route('owner.venues.store'), [
            'name' => 'Invalid Photo Courts',
            'slug' => '',
            'description' => null,
            'address' => '25 Court Avenue',
            'city' => 'Davao City',
            'province' => 'Davao del Sur',
            'latitude' => null,
            'longitude' => null,
            'phone' => null,
            'email' => null,
            'website' => null,
            'is_published' => false,
            'sports' => [$sport->getKey()],
            'amenities' => [],
            'photos' => [UploadedFile::fake()->create('document.pdf', 20, 'application/pdf')],
        ])->assertSessionHasErrors('photos.0');

        $this->assertDatabaseCount('venues', 0);
        $this->assertDatabaseCount('venue_photos', 0);
    }

    public function test_owner_can_upload_photos_and_the_first_photo_becomes_the_public_cover(): void
    {
        Storage::fake('public');
        [$owner, $venue] = $this->ownerVenue(['is_published' => true]);
        $sport = Sport::factory()->create();
        $venue->sports()->attach($sport);
        CourtResource::factory()->for($venue)->for($sport)->create(['is_active' => true]);

        $this->actingAs($owner)
            ->post(route('owner.venues.photos.store', $venue), [
                'photos' => [
                    $this->image('main-court.png'),
                    $this->image('lobby.png'),
                ],
            ])->assertRedirect();

        $photos = $venue->photos()->get();
        $this->assertCount(2, $photos);
        $this->assertTrue($photos->first()->is_primary);
        $this->assertFalse($photos->last()->is_primary);
        $photos->each(fn (VenuePhoto $photo) => Storage::disk('public')->assertExists($photo->storage_path));

        $this->actingAs($owner)
            ->get(route('owner.venues.edit', $venue))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Owner/Venues/Edit')
                ->has('venue.photos', 2)
                ->where('venue.photos.0.is_primary', true));

        $coverUrl = Storage::disk('public')->url($photos->first()->storage_path);

        $this->get(route('marketplace.venues.show', $venue->slug))
            ->assertOk()
            ->assertSee($coverUrl, false)
            ->assertSee('2 photos');
        $this->get(route('marketplace.home'))
            ->assertOk()
            ->assertSee($coverUrl, false);
        $this->get(route('marketplace.courts.index'))
            ->assertOk()
            ->assertSee($coverUrl, false);
    }

    public function test_owner_can_change_cover_and_deleting_it_promotes_another_photo(): void
    {
        Storage::fake('public');
        [$owner, $venue] = $this->ownerVenue();
        $first = VenuePhoto::factory()->for($venue)->create([
            'storage_path' => 'venues/cover.jpg',
            'sort_order' => 1,
            'is_primary' => true,
        ]);
        $second = VenuePhoto::factory()->for($venue)->create([
            'storage_path' => 'venues/second.jpg',
            'sort_order' => 2,
            'is_primary' => false,
        ]);
        Storage::disk('public')->put($first->storage_path, 'first');
        Storage::disk('public')->put($second->storage_path, 'second');

        $this->actingAs($owner)
            ->patch(route('owner.venues.photos.update', [$venue, $second]), ['is_primary' => true])
            ->assertRedirect();

        $this->assertFalse($first->refresh()->is_primary);
        $this->assertTrue($second->refresh()->is_primary);

        $this->actingAs($owner)
            ->delete(route('owner.venues.photos.destroy', [$venue, $second]))
            ->assertRedirect();

        Storage::disk('public')->assertMissing($second->storage_path);
        $this->assertTrue($first->refresh()->is_primary);
    }

    public function test_photo_upload_is_validated_and_gallery_is_limited_to_ten(): void
    {
        Storage::fake('public');
        [$owner, $venue] = $this->ownerVenue();

        $this->actingAs($owner)
            ->post(route('owner.venues.photos.store', $venue), [
                'photos' => [UploadedFile::fake()->create('not-an-image.pdf', 20, 'application/pdf')],
            ])->assertSessionHasErrors('photos.0');

        VenuePhoto::factory()->count(10)->for($venue)->create();

        $this->actingAs($owner)
            ->post(route('owner.venues.photos.store', $venue), [
                'photos' => [$this->image('eleventh.png')],
            ])->assertSessionHasErrors('photos');

        $this->assertSame(10, $venue->photos()->count());
    }

    public function test_tenant_cannot_manage_another_tenants_photos_or_cross_associate_nested_routes(): void
    {
        Storage::fake('public');
        [$owner] = $this->ownerVenue();
        [, $otherVenue] = $this->ownerVenue();
        $otherPhoto = VenuePhoto::factory()->for($otherVenue)->create();
        [, $ownVenue] = $this->ownerVenueFor($owner);

        $this->actingAs($owner)
            ->post(route('owner.venues.photos.store', $otherVenue), [
                'photos' => [$this->image('intrusion.png')],
            ])->assertForbidden();

        $this->actingAs($owner)
            ->patch(route('owner.venues.photos.update', [$ownVenue, $otherPhoto]), ['is_primary' => true])
            ->assertForbidden();

        $this->actingAs($owner)
            ->delete(route('owner.venues.photos.destroy', [$ownVenue, $otherPhoto]))
            ->assertNotFound();

        $this->assertDatabaseHas('venue_photos', ['id' => $otherPhoto->getKey()]);
    }

    public function test_deleting_a_venue_also_removes_its_photo_files(): void
    {
        Storage::fake('public');
        [$owner, $venue] = $this->ownerVenue();
        $photo = VenuePhoto::factory()->for($venue)->create([
            'storage_path' => "venues/{$venue->getKey()}/cleanup.png",
        ]);
        Storage::disk('public')->put($photo->storage_path, 'image');

        $this->actingAs($owner)
            ->delete(route('owner.venues.destroy', $venue))
            ->assertRedirect(route('owner.venues.index'));

        Storage::disk('public')->assertMissing($photo->storage_path);
        $this->assertDatabaseMissing('venue_photos', ['id' => $photo->getKey()]);
    }

    /** @return array{User, Venue} */
    private function ownerVenue(array $venueAttributes = []): array
    {
        $owner = User::factory()->create();

        return $this->ownerVenueFor($owner, $venueAttributes);
    }

    /** @return array{User, Venue} */
    private function ownerVenueFor(User $owner, array $venueAttributes = []): array
    {
        $organization = Organization::factory()->create();
        Membership::factory()->owner()->for($owner)->for($organization)->create();
        $venue = Venue::factory()->for($organization)->create($venueAttributes);

        return [$owner, $venue];
    }

    private function image(string $name): UploadedFile
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );

        return UploadedFile::fake()->createWithContent($name, $png ?: '');
    }
}
