<?php

use App\Enums\AnalyticsEventType;
use App\Enums\DemandSearchOutcome;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analytics_events', function (Blueprint $table) {
            $table->string('demand_city_slug', 120)->nullable()->after('event_type');
            $table->string('demand_sport_slug', 120)->nullable()->after('demand_city_slug');
            $table->string('demand_setting', 32)->nullable()->after('demand_sport_slug');
            $table->date('requested_date')->nullable()->after('demand_setting');
            $table->time('requested_start_time')->nullable()->after('requested_date');
            $table->time('requested_end_time')->nullable()->after('requested_start_time');
            $table->unsignedSmallInteger('duration_minutes')->nullable()->after('requested_end_time');
            $table->decimal('maximum_hourly_rate', 10, 2)->nullable()->after('duration_minutes');
            $table->unsignedSmallInteger('matching_venue_count')->nullable()->after('maximum_hourly_rate');
            $table->unsignedSmallInteger('available_result_count')->nullable()->after('matching_venue_count');
            $table->string('search_outcome', 40)->nullable()->after('available_result_count');
            $table->string('entry_context', 40)->nullable()->after('search_outcome');
            $table->boolean('is_demo')->default(false)->after('entry_context');

            $table->index(
                ['event_type', 'is_demo', 'occurred_at'],
                'analytics_demand_type_demo_date_idx',
            );
            $table->index(
                ['event_type', 'is_demo', 'demand_city_slug', 'demand_sport_slug', 'occurred_at'],
                'analytics_demand_city_sport_date_idx',
            );
            $table->index(
                ['event_type', 'is_demo', 'demand_sport_slug', 'occurred_at'],
                'analytics_demand_sport_date_idx',
            );
            $table->index(
                ['event_type', 'is_demo', 'search_outcome', 'occurred_at'],
                'analytics_demand_outcome_date_idx',
            );
            $table->index(
                ['event_type', 'is_demo', 'requested_start_time', 'occurred_at'],
                'analytics_demand_time_date_idx',
            );
        });

        $this->backfillExistingEvents();
    }

    public function down(): void
    {
        Schema::table('analytics_events', function (Blueprint $table) {
            $table->dropIndex('analytics_demand_type_demo_date_idx');
            $table->dropIndex('analytics_demand_city_sport_date_idx');
            $table->dropIndex('analytics_demand_sport_date_idx');
            $table->dropIndex('analytics_demand_outcome_date_idx');
            $table->dropIndex('analytics_demand_time_date_idx');
            $table->dropColumn([
                'demand_city_slug',
                'demand_sport_slug',
                'demand_setting',
                'requested_date',
                'requested_start_time',
                'requested_end_time',
                'duration_minutes',
                'maximum_hourly_rate',
                'matching_venue_count',
                'available_result_count',
                'search_outcome',
                'entry_context',
                'is_demo',
            ]);
        });
    }

    private function backfillExistingEvents(): void
    {
        DB::table('analytics_events')
            ->whereNotNull('metadata')
            ->orderBy('id')
            ->chunkById(500, function ($events): void {
                foreach ($events as $event) {
                    $metadata = is_string($event->metadata)
                        ? json_decode($event->metadata, true)
                        : (array) $event->metadata;

                    if (! is_array($metadata)) {
                        continue;
                    }

                    $attributes = ['is_demo' => (bool) ($metadata['local_demo'] ?? false)];

                    if ($event->event_type === AnalyticsEventType::MarketplaceSearch->value) {
                        $resultCount = isset($metadata['result_count']) && is_numeric($metadata['result_count'])
                            ? max(0, (int) $metadata['result_count'])
                            : null;
                        $startTime = $this->validTime($metadata['start_time'] ?? null);
                        $duration = isset($metadata['duration_minutes']) && is_numeric($metadata['duration_minutes'])
                            ? max(0, (int) $metadata['duration_minutes'])
                            : null;

                        $attributes = [
                            ...$attributes,
                            'demand_city_slug' => $this->stringOrNull($metadata['city'] ?? null),
                            'demand_sport_slug' => $this->stringOrNull($metadata['sport'] ?? null),
                            'demand_setting' => $this->stringOrNull($metadata['setting'] ?? null),
                            'requested_date' => $this->validDate($metadata['date'] ?? null),
                            'requested_start_time' => $startTime,
                            'requested_end_time' => $this->endTime($startTime, $duration),
                            'duration_minutes' => $duration,
                            'maximum_hourly_rate' => isset($metadata['max_price']) && is_numeric($metadata['max_price'])
                                ? number_format((float) $metadata['max_price'], 2, '.', '')
                                : null,
                            'matching_venue_count' => $resultCount,
                            'available_result_count' => $resultCount,
                            'search_outcome' => $resultCount === null
                                ? null
                                : ($resultCount > 0
                                    ? DemandSearchOutcome::ResultsAvailable->value
                                    : DemandSearchOutcome::NoResults->value),
                            'entry_context' => 'legacy',
                        ];
                    }

                    DB::table('analytics_events')->where('id', $event->id)->update($attributes);
                }
            });
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function validDate(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));

        return $date !== false && $date->format('Y-m-d') === $value ? $value : null;
    }

    private function validTime(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $time = DateTimeImmutable::createFromFormat('!H:i', $value, new DateTimeZone('UTC'));

        return $time !== false && $time->format('H:i') === $value ? $value : null;
    }

    private function endTime(?string $startTime, ?int $duration): ?string
    {
        if ($startTime === null || $duration === null) {
            return null;
        }

        $time = DateTimeImmutable::createFromFormat('!H:i', $startTime, new DateTimeZone('UTC'));

        return $time === false ? null : $time->modify("+{$duration} minutes")->format('H:i');
    }
};
