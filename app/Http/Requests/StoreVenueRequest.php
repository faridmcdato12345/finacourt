<?php

namespace App\Http\Requests;

use App\Models\PsgcLocation;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVenueRequest extends FormRequest
{
    public function authorize(): bool
    {
        $context = app(TenantContext::class);

        return $context->hasOrganization()
            && $this->user()?->can('manageInventory', $context->organization()) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $hasPsgcCatalog = PsgcLocation::query()->exists();

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash:ascii', Rule::unique('venues', 'slug')],
            'description' => ['nullable', 'string', 'max:5000'],
            'address' => ['required', 'string', 'max:500'],
            // city/province remain as a compatibility path for tests and legacy
            // installations before the catalog is seeded. With a catalog present,
            // the controller derives these names from the validated PSGC codes.
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
            'photos' => ['sometimes', 'array', 'max:5'],
            'photos.*' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
                'dimensions:max_width=8000,max_height=8000',
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'photos.*.image' => 'Each upload must be a valid image.',
            'photos.*.mimes' => 'Photos must be JPG, PNG, or WebP files.',
            'photos.*.max' => 'Each photo may be up to 5 MB.',
            'photos.*.dimensions' => 'Photos may be at most 8000 × 8000 pixels.',
        ];
    }
}
