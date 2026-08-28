<?php

namespace App\Http\Requests;

use App\Models\PsgcLocation;
use App\Models\Venue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateVenueRequest extends FormRequest
{
    public function authorize(): bool
    {
        $venue = $this->route('venue');

        return $venue instanceof Venue && $this->user()?->can('update', $venue) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Venue $venue */
        $venue = $this->route('venue');
        $hasPsgcCatalog = PsgcLocation::query()->exists();

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash:ascii', Rule::unique('venues', 'slug')->ignore($venue)],
            'description' => ['nullable', 'string', 'max:5000'],
            'address' => ['required', 'string', 'max:500'],
            'city' => [$hasPsgcCatalog ? 'nullable' : 'required', 'string', 'max:160'],
            'province' => [$hasPsgcCatalog ? 'nullable' : 'required', 'string', 'max:160'],
            'psgc_parent_code' => [
                $hasPsgcCatalog ? 'required' : 'nullable',
                'string',
                'regex:/^\d{10}$/',
                Rule::exists('psgc_locations', 'code')->whereIn('level', ['region', 'province', 'area']),
            ],
            'psgc_city_municipality_code' => [
                $hasPsgcCatalog ? 'required' : 'nullable',
                'string',
                'regex:/^\d{10}$/',
                Rule::exists('psgc_locations', 'code')->whereIn('level', ['city', 'municipality']),
            ],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'is_published' => ['required', 'boolean'],
            'sports' => ['required', 'array', 'min:1'],
            'sports.*' => [
                'integer',
                'distinct',
                Rule::exists('sports', 'id')->where('is_active', true),
            ],
            'amenities' => ['present', 'array'],
            'amenities.*' => [
                'integer',
                'distinct',
                Rule::exists('amenities', 'id')->where('is_active', true),
            ],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('sports')) {
                return;
            }

            /** @var Venue $venue */
            $venue = $this->route('venue');
            $sportIds = array_map('intval', $this->input('sports', []));

            if ($venue->resources()->whereNotIn('sport_id', $sportIds)->exists()) {
                $validator->errors()->add(
                    'sports',
                    'A sport used by an existing court cannot be removed from this venue.',
                );
            }
        }];
    }
}
