<?php

namespace App\Http\Requests;

use App\Enums\DirectorySourceType;
use App\Models\PsgcLocation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveVenueDirectoryListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_platform_admin === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $usesPsgcCatalog = PsgcLocation::query()->exists()
            && Str::lower(trim((string) $this->input('country', 'Philippines'))) === 'philippines';

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
            'address' => ['required', 'string', 'max:255'],
            // Philippine names are resolved from the validated PSGC hierarchy.
            // Manual names remain available for international directory records
            // and installations whose bundled catalog has not been seeded yet.
            'city' => [$usesPsgcCatalog ? 'nullable' : 'required', 'string', 'max:160'],
            'province' => [$usesPsgcCatalog ? 'nullable' : 'required', 'string', 'max:160'],
            'country' => ['required', 'string', 'max:80'],
            'psgc_parent_code' => [
                $usesPsgcCatalog ? 'required' : 'nullable',
                'string',
                'regex:/^\d{10}$/',
                Rule::exists('psgc_locations', 'code')->whereIn('level', ['region', 'province', 'area']),
            ],
            'psgc_city_municipality_code' => [
                $usesPsgcCatalog ? 'required' : 'nullable',
                'string',
                'regex:/^\d{10}$/',
                Rule::exists('psgc_locations', 'code')->whereIn('level', ['city', 'municipality']),
            ],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'coordinates_verified' => ['required', 'boolean'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url:http,https', 'max:2048'],
            'source_type' => ['required', Rule::enum(DirectorySourceType::class)],
            'source_url' => ['nullable', 'required_without:source_reference', 'url:http,https', 'max:2048'],
            'source_reference' => ['nullable', 'required_without:source_url', 'string', 'max:500'],
            'rights_confirmed' => ['accepted'],
            'sports' => ['required', 'array', 'min:1'],
            'sports.*' => ['integer', 'distinct', Rule::exists('sports', 'id')->where('is_active', true)],
            'hours' => ['sometimes', 'array', 'max:7'],
            'hours.*.day_of_week' => ['required', 'integer', 'between:0,6', 'distinct'],
            'hours.*.is_closed' => ['required', 'boolean'],
            'hours.*.opens_at' => ['nullable', 'required_if:hours.*.is_closed,false', 'date_format:H:i'],
            'hours.*.closes_at' => [
                'nullable',
                'required_if:hours.*.is_closed,false',
                'date_format:H:i',
                'after:hours.*.opens_at',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'rights_confirmed.accepted' => 'Confirm that the listing facts and text are lawful to publish and are not copied from a competitor dataset.',
        ];
    }
}
