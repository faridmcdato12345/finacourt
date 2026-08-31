<?php

namespace App\Google\BusinessProfile;

use App\Models\Venue;
use Illuminate\Support\Str;

class GoogleBusinessProfileMatcher
{
    /**
     * @param  array<int, array<string, mixed>>  $candidates
     * @return array{outcome: string, candidates: array<int, array<string, mixed>>}
     */
    public function match(Venue $venue, array $candidates): array
    {
        $scored = collect($candidates)
            ->map(function (array $candidate) use ($venue): array {
                [$score, $signals, $exactPlace] = $this->score($venue, $candidate);

                return [...$candidate, 'score' => $score, 'signals' => $signals, 'exact_place_match' => $exactPlace];
            })
            ->sortByDesc('score')
            ->values();
        $top = $scored->first();
        $second = $scored->get(1);

        $outcome = match (true) {
            $top === null => 'no_match',
            (bool) ($top['exact_place_match'] ?? false) => 'exact',
            ($top['score'] ?? 0) >= 70 && (($top['score'] ?? 0) - ($second['score'] ?? 0)) >= 15 => 'likely',
            ($top['score'] ?? 0) >= 40 => 'ambiguous',
            default => 'no_match',
        };

        $visible = $outcome === 'no_match'
            ? []
            : $scored->filter(fn (array $candidate) => ($candidate['score'] ?? 0) >= 25)->take(8)->values()->all();

        return ['outcome' => $outcome, 'candidates' => $visible];
    }

    /** @return array{int, array<int, string>, bool} */
    private function score(Venue $venue, array $candidate): array
    {
        $score = 0;
        $signals = [];
        $exactPlace = filled($venue->google_place_id)
            && filled($candidate['place_id'] ?? null)
            && hash_equals((string) $venue->google_place_id, (string) $candidate['place_id']);

        if ($exactPlace) {
            return [100, ['Same Google place ID'], true];
        }

        $nameSimilarity = $this->similarity($venue->name, (string) ($candidate['title'] ?? ''));

        if ($nameSimilarity === 100) {
            $score += 45;
            $signals[] = 'Same venue name';
        } elseif ($nameSimilarity >= 85) {
            $score += 30;
            $signals[] = 'Very similar venue name';
        }

        $venuePhone = preg_replace('/\D+/', '', (string) $venue->phone);
        $candidatePhone = preg_replace('/\D+/', '', (string) ($candidate['phone'] ?? ''));

        if ($venuePhone !== '' && $candidatePhone !== '' && (
            str_ends_with($venuePhone, $candidatePhone) || str_ends_with($candidatePhone, $venuePhone)
        )) {
            $score += 25;
            $signals[] = 'Same phone number';
        }

        $addressSimilarity = $this->similarity(
            implode(' ', array_filter([$venue->address, $venue->city, $venue->province])),
            (string) ($candidate['address'] ?? ''),
        );

        if ($addressSimilarity >= 70) {
            $score += 25;
            $signals[] = 'Similar address';
        } elseif ($this->containsNormalized((string) ($candidate['address'] ?? ''), (string) $venue->city)) {
            $score += 10;
            $signals[] = 'Same city';
        }

        $distance = $this->distanceKilometres(
            $venue->latitude !== null ? (float) $venue->latitude : null,
            $venue->longitude !== null ? (float) $venue->longitude : null,
            isset($candidate['latitude']) ? (float) $candidate['latitude'] : null,
            isset($candidate['longitude']) ? (float) $candidate['longitude'] : null,
        );

        if ($distance !== null && $distance <= 0.25) {
            $score += 30;
            $signals[] = 'Map pins are very close';
        } elseif ($distance !== null && $distance <= 2) {
            $score += 15;
            $signals[] = 'Map pins are nearby';
        }

        return [min(99, $score), $signals, false];
    }

    private function similarity(string $first, string $second): int
    {
        $first = $this->normalize($first);
        $second = $this->normalize($second);

        if ($first === '' || $second === '') {
            return 0;
        }

        similar_text($first, $second, $percentage);

        return (int) round($percentage);
    }

    private function containsNormalized(string $haystack, string $needle): bool
    {
        $needle = $this->normalize($needle);

        return $needle !== '' && str_contains($this->normalize($haystack), $needle);
    }

    private function normalize(string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii(trim($value)))) ?? '';
    }

    private function distanceKilometres(?float $firstLatitude, ?float $firstLongitude, ?float $secondLatitude, ?float $secondLongitude): ?float
    {
        if ($firstLatitude === null || $firstLongitude === null || $secondLatitude === null || $secondLongitude === null) {
            return null;
        }

        $latitudeDelta = deg2rad($secondLatitude - $firstLatitude);
        $longitudeDelta = deg2rad($secondLongitude - $firstLongitude);
        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($firstLatitude)) * cos(deg2rad($secondLatitude)) * sin($longitudeDelta / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
