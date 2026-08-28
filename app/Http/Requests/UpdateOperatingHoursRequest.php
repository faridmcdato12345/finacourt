<?php

namespace App\Http\Requests;

use App\Models\Venue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateOperatingHoursRequest extends FormRequest
{
    public function authorize(): bool
    {
        $venue = $this->route('venue');

        return $venue instanceof Venue && $this->user()?->can('update', $venue) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'hours' => ['required', 'array', 'size:7'],
            'hours.*.day_of_week' => ['required', 'integer', 'between:0,6', 'distinct'],
            'hours.*.is_closed' => ['required', 'boolean'],
            'hours.*.opens_at' => ['nullable', 'date_format:H:i'],
            'hours.*.closes_at' => ['nullable', 'date_format:H:i'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('hours')) {
                return;
            }

            $hours = collect($this->input('hours', []));
            $days = $hours->pluck('day_of_week')->map(fn ($day) => (int) $day)->sort()->values()->all();

            if ($days !== range(0, 6)) {
                $validator->errors()->add('hours', 'Operating hours must include each day of the week exactly once.');
            }

            foreach ($hours as $index => $hour) {
                if ((bool) ($hour['is_closed'] ?? false)) {
                    continue;
                }

                $opensAt = $hour['opens_at'] ?? null;
                $closesAt = $hour['closes_at'] ?? null;

                if (! $opensAt) {
                    $validator->errors()->add("hours.$index.opens_at", 'An opening time is required.');
                }

                if (! $closesAt) {
                    $validator->errors()->add("hours.$index.closes_at", 'A closing time is required.');
                }

                if ($opensAt && $closesAt && $closesAt <= $opensAt) {
                    $validator->errors()->add(
                        "hours.$index.closes_at",
                        'Closing time must be later than opening time.',
                    );
                }
            }
        }];
    }
}
