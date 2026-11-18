<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Enums\MembershipRole;
use App\Models\Booking;
use App\Models\Promotion;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class BuildCurrentDemoVideoManifest extends Command
{
    protected $signature = 'finacourt:demo-video-current-manifest {--json : Print the manifest as JSON}';

    protected $description = 'Build a read-only tutorial-video manifest from approved local development demo records';

    public function handle(): int
    {
        if (! in_array(app()->environment(), config('demo-video.allowed_environments', []), true)) {
            throw new RuntimeException('Current development demo-video manifests may only be built in local, testing, or staging.');
        }

        $settings = config('demo-video.current_development');
        $ownerEmail = (string) $settings['owner_email'];
        $playerEmail = (string) $settings['player_email'];

        $this->assertApprovedEmail($ownerEmail, 'owner');
        $this->assertApprovedEmail($playerEmail, 'player');

        $venue = Venue::query()
            ->with(['organization', 'resources' => fn ($query) => $query->where('is_active', true)->orderBy('name')])
            ->where('slug', (string) $settings['venue_slug'])
            ->firstOrFail();

        if (! str_starts_with(strtolower($venue->name), 'demo ')) {
            throw new RuntimeException('Current development capture is restricted to an explicitly labelled Demo venue.');
        }

        $owner = User::query()->where('email', $ownerEmail)->firstOrFail();
        $player = User::query()->where('email', $playerEmail)->firstOrFail();
        $password = (string) $settings['account_password'];

        if (! Hash::check($password, $owner->password) || ! Hash::check($password, $player->password)) {
            throw new RuntimeException('Approved demo accounts do not match DEMO_VIDEO_CURRENT_ACCOUNT_PASSWORD.');
        }

        if (! $owner->memberships()
            ->where('organization_id', $venue->organization_id)
            ->where('role', MembershipRole::Owner->value)
            ->whereNull('suspended_at')
            ->whereNull('removed_at')
            ->exists()) {
            throw new RuntimeException('Approved demo owner does not own the selected demo venue.');
        }

        $resource = $venue->resources->first()
            ?? throw new RuntimeException('The selected demo venue has no active court resource.');
        $booking = Booking::query()
            ->where('venue_id', $venue->getKey())
            ->where('player_user_id', $player->getKey())
            ->where('status', BookingStatus::Confirmed->value)
            ->latest('start_at')
            ->first()
            ?? throw new RuntimeException('The approved demo player has no confirmed booking at the selected demo venue.');
        $promotion = Promotion::query()
            ->where('venue_id', $venue->getKey())
            ->where('is_active', true)
            ->latest('id')
            ->first();

        $date = now($venue->organization->timezone)->toDateString();
        $manifest = [
            'profile' => 'current-development-data',
            'generated_at' => now()->toIso8601String(),
            'venue_id' => $venue->getKey(),
            'venue_name' => $venue->name,
            'venue_slug' => $venue->slug,
            'resource_id' => $resource->getKey(),
            'resource_name' => $resource->name,
            'booking_date' => $date,
            'booking_start' => '09:00',
            'booking_duration' => $resource->booking_increment_minutes,
            'booking_reference' => $booking->reference,
            'promotion_id' => $promotion?->getKey(),
            'promotion_title' => $promotion?->title,
            'owner_email' => $ownerEmail,
            'player_email' => $playerEmail,
        ];
        $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
        $directory = base_path('output/demo-video');

        File::ensureDirectoryExists($directory);
        File::put($directory.'/tutorial-manifest.json', $json);

        if ($this->option('json')) {
            $this->line($json);
        } else {
            $this->info('Read-only tutorial manifest ready at output/demo-video/tutorial-manifest.json.');
        }

        return self::SUCCESS;
    }

    private function assertApprovedEmail(string $email, string $role): void
    {
        if (! str_ends_with(strtolower($email), '@example.com')) {
            throw new RuntimeException("Current development {$role} must use the reserved @example.com domain.");
        }
    }
}
