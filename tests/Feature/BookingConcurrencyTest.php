<?php

namespace Tests\Feature;

use App\Bookings\CreateBooking;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\CourtResource;
use App\Models\Membership;
use App\Models\OperatingHour;
use App\Models\Organization;
use App\Models\Sport;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BookingConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_simultaneous_overlapping_attempts_only_create_one_booking(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create(['timezone' => 'Asia/Manila']);
        Membership::factory()->owner()->for($owner)->for($organization)->create();
        $venue = Venue::factory()->for($organization)->create();
        $sport = Sport::factory()->create();
        $venue->sports()->attach($sport);
        $resource = CourtResource::factory()->for($venue)->for($sport)->create([
            'booking_increment_minutes' => 60,
            'base_hourly_rate' => '650.00',
            'is_active' => true,
        ]);

        foreach (range(0, 6) as $day) {
            OperatingHour::factory()->for($venue)->create([
                'day_of_week' => $day,
                'is_closed' => false,
                'opens_at' => '08:00',
                'closes_at' => '22:00',
            ]);
        }

        $directory = sys_get_temp_dir().'/court-booking-race-'.Str::uuid();
        mkdir($directory, 0700);
        DB::disconnect();
        $processes = [];

        foreach ([1, 2] as $attempt) {
            $pid = pcntl_fork();
            $this->assertNotSame(-1, $pid, 'Unable to fork the concurrency test process.');

            if ($pid === 0) {
                DB::purge();

                while (! file_exists("$directory/start")) {
                    usleep(1000);
                }

                try {
                    app(CreateBooking::class)->handle($organization->getKey(), $owner, [
                        'resource_id' => $resource->getKey(),
                        'booking_date' => now('Asia/Manila')->addDays(7)->toDateString(),
                        'start_time' => '09:00',
                        'end_time' => '10:00',
                        'status' => BookingStatus::Confirmed->value,
                        'source' => 'manual',
                        'hold_minutes' => null,
                        'customer_name' => "Concurrent customer $attempt",
                        'customer_email' => null,
                        'customer_phone' => null,
                        'notes' => null,
                    ]);
                    $result = 'created';
                } catch (ValidationException) {
                    $result = 'conflict';
                } catch (\Throwable $exception) {
                    $result = 'error:'.$exception::class.':'.$exception->getMessage();
                }

                file_put_contents("$directory/result-$attempt", $result);
                exit(str_starts_with($result, 'error:') ? 1 : 0);
            }

            $processes[] = $pid;
        }

        touch("$directory/start");

        foreach ($processes as $pid) {
            pcntl_waitpid($pid, $status);
            $this->assertTrue(pcntl_wifexited($status));
            $this->assertSame(0, pcntl_wexitstatus($status));
        }

        $results = [
            file_get_contents("$directory/result-1"),
            file_get_contents("$directory/result-2"),
        ];

        DB::purge();
        sort($results);
        $this->assertSame(['conflict', 'created'], $results);
        $this->assertSame(1, Booking::query()->count());

        unlink("$directory/start");
        unlink("$directory/result-1");
        unlink("$directory/result-2");
        rmdir($directory);
    }
}
